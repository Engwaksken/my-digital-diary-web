<?php
namespace App\Http\Controllers;
use App\Models\PrivacyReportRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
class PrivacyController extends Controller {
    private array $allowedModules=['plans','incomes','budgets','expenses','debts','savings_goals','savings_contributions','diet_logs','exercise_logs','sleep_logs','health_checkups','projects','project_tasks','reminders','spiritual_practices','daily_plans','notes','ai_plans'];
    public function show(Request $request){ return view('privacy.show',['user'=>$request->user(),'reportRequests'=>PrivacyReportRequest::where('user_id',$request->user()->id)->latest()->limit(20)->get()]); }
    public function requestExport(Request $request){
        $data=$request->validate(['reason'=>'required|string|max:2000','modules'=>'required|array|min:1','modules.*'=>'string','date_from'=>'nullable|date','date_to'=>'nullable|date|after_or_equal:date_from']);
        $modules=array_values(array_intersect($data['modules'],$this->allowedModules)); abort_if(!$modules,422,'Select at least one valid module.');
        $rr=PrivacyReportRequest::create(['user_id'=>$request->user()->id,'reason'=>$data['reason'],'modules'=>$modules,'date_from'=>$data['date_from']??null,'date_to'=>$data['date_to']??null,'status'=>'processing']);
        try {
            $modelMap=['plans'=>\App\Models\Plan::class,'incomes'=>\App\Models\Income::class,'budgets'=>\App\Models\Budget::class,'expenses'=>\App\Models\Expense::class,'debts'=>\App\Models\Debt::class,'savings_goals'=>\App\Models\SavingsGoal::class,'savings_contributions'=>\App\Models\SavingsContribution::class,'diet_logs'=>\App\Models\DietLog::class,'exercise_logs'=>\App\Models\ExerciseLog::class,'sleep_logs'=>\App\Models\SleepLog::class,'health_checkups'=>\App\Models\HealthCheckup::class,'projects'=>\App\Models\Project::class,'project_tasks'=>\App\Models\ProjectTask::class,'reminders'=>\App\Models\Reminder::class,'spiritual_practices'=>\App\Models\SpiritualPractice::class,'daily_plans'=>\App\Models\DailyPlan::class,'notes'=>\App\Models\Note::class,'ai_plans'=>\App\Models\AiPlan::class]; $payload=[]; foreach($modules as $module){ $class=$modelMap[$module]??null; if(!$class) continue; $q=$class::where('user_id',$request->user()->id); if($rr->date_from) $q->whereDate('created_at','>=',$rr->date_from); if($rr->date_to) $q->whereDate('created_at','<=',$rr->date_to); $payload[$module]=$q->get(); }
            $pdf=Pdf::loadView('privacy.report',['user'=>$request->user(),'requestRow'=>$rr,'payload'=>$payload])->setPaper('a4');
            $path="privacy-reports/{$request->user()->id}/report-{$rr->id}.pdf"; Storage::disk('local')->put($path,$pdf->output());
            $rr->update(['status'=>'completed','file_path'=>$path,'completed_at'=>now()]);
            try { Mail::raw('Your requested My Digital Diary personal data report is attached.',function($m)use($request,$path){$m->to($request->user()->email)->subject('Your Personal Data Report')->attachData(Storage::disk('local')->get($path),'my-digital-diary-data-report.pdf',['mime'=>'application/pdf']);}); } catch(\Throwable $e) { report($e); }
        } catch(\Throwable $e){ $rr->update(['status'=>'failed','error_message'=>$e->getMessage()]); report($e); return back()->withErrors(['report'=>'Could not generate the report. Please try again.']); }
        return back()->with('success','Your customized PDF report was generated and sent to your registered email.');
    }
    public function downloadReport(Request $request, PrivacyReportRequest $privacyReport){ abort_unless($privacyReport->user_id===$request->user()->id,403); abort_unless($privacyReport->file_path&&Storage::disk('local')->exists($privacyReport->file_path),404); return Storage::disk('local')->download($privacyReport->file_path,'my-digital-diary-data-report.pdf'); }
    public function destroyAccount(Request $request){ $data=$request->validate(['password'=>['required','current_password'],'reason'=>'required|string|max:2000','backup'=>'nullable|boolean','confirm_delete'=>'accepted']); $user=$request->user(); if((bool)($data['backup']??false)) $this->emailFullBackup($user); $user->update(['deletion_reason'=>$data['reason'],'deletion_requested_at'=>now(),'scheduled_deletion_at'=>now()->addDays(30)]); return back()->with('success','Your account is scheduled for permanent deletion in 30 days. You may cancel deletion during this grace period.'); }

    private function emailFullBackup($user): void {
        $modelMap=['plans'=>\App\Models\Plan::class,'incomes'=>\App\Models\Income::class,'budgets'=>\App\Models\Budget::class,'expenses'=>\App\Models\Expense::class,'debts'=>\App\Models\Debt::class,'savings_goals'=>\App\Models\SavingsGoal::class,'savings_contributions'=>\App\Models\SavingsContribution::class,'diet_logs'=>\App\Models\DietLog::class,'exercise_logs'=>\App\Models\ExerciseLog::class,'sleep_logs'=>\App\Models\SleepLog::class,'health_checkups'=>\App\Models\HealthCheckup::class,'projects'=>\App\Models\Project::class,'project_tasks'=>\App\Models\ProjectTask::class,'reminders'=>\App\Models\Reminder::class,'spiritual_practices'=>\App\Models\SpiritualPractice::class,'daily_plans'=>\App\Models\DailyPlan::class,'notes'=>\App\Models\Note::class,'ai_plans'=>\App\Models\AiPlan::class];
        $backup=['exported_at'=>now()->toIso8601String(),'profile'=>$user->only(['id','name','email','phone','created_at'])];
        foreach($modelMap as $key=>$class){ $backup[$key]=$class::where('user_id',$user->id)->get()->toArray(); }
        $json=json_encode($backup, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        try { Mail::raw('Your complete My Digital Diary backup is attached. Keep it in a safe place.', function($mail) use($user,$json){ $mail->to($user->email)->subject('Your My Digital Diary Backup')->attachData($json,'my-digital-diary-backup.json',['mime'=>'application/json']); }); } catch(\Throwable $e){ report($e); }
    }

    public function cancelDeletion(Request $request){ $request->user()->update(['deletion_reason'=>null,'deletion_requested_at'=>null,'scheduled_deletion_at'=>null]); return back()->with('success','Account deletion has been cancelled.'); }
}

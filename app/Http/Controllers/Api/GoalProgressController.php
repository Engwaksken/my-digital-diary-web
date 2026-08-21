<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoalMilestone;
use App\Models\GoalReflection;
use App\Models\PersonalGoal;
use App\Services\GoalProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalProgressController extends Controller
{
    private function owned(Request $request, PersonalGoal $goal): PersonalGoal
    {
        abort_unless((int)$goal->user_id === (int)$request->user()->id,403);
        return $goal;
    }

    public function show(Request $request, PersonalGoal $goal, GoalProgressService $service): JsonResponse
    {
        $goal = $this->owned($request,$goal);
        $goal = $service->recalculate($goal) ?? $goal;
        $goal->load(['milestones','annualPlans:id,personal_goal_id,title,status,progress_percent,target_date,updated_at','dailyTasks:id,personal_goal_id,title,is_completed,completed_at,start_time,updated_at','projectTasks:id,personal_goal_id,title,status,due_date,updated_at','reflections.milestone']);
        return response()->json(['data'=>[
            'goal'=>$goal->only(['id','module','title','description','progress_percent','status','priority','target_date','current_value','target_value']),
            'snapshot'=>$service->snapshot($goal),
            'week'=>$service->weeklyMovement($goal,$request->user()),
            'accountability'=>$service->accountability($goal,$request->user()),
            'learning'=>$service->learning($goal),
            'reflections'=>$goal->reflections->map(fn($r)=>[
                'id'=>$r->id,'reflection_type'=>$r->reflection_type,'goal_milestone_id'=>$r->goal_milestone_id,
                'milestone_title'=>$r->milestone?->title,'what_worked'=>$r->what_worked,'challenges'=>$r->challenges,
                'lessons_learned'=>$r->lessons_learned,'repeat_next_time'=>$r->repeat_next_time,
                'change_next_time'=>$r->change_next_time,'confidence_after'=>$r->confidence_after,
                'reflected_on'=>$r->reflected_on?->toDateString(),
            ])->values(),
            'milestones'=>$goal->milestones,
            'plans'=>$goal->annualPlans,
            'daily_tasks'=>$goal->dailyTasks,
            'project_tasks'=>$goal->projectTasks,
        ]]);
    }

    public function storeMilestone(Request $request, PersonalGoal $goal, GoalProgressService $service): JsonResponse
    {
        $goal = $this->owned($request,$goal);
        $data=$request->validate(['title'=>'required|string|max:255','description'=>'nullable|string','target_date'=>'nullable|date','weight'=>'nullable|integer|min:1|max:100','status'=>'nullable|in:pending,in_progress,completed']);
        $status=$data['status']??'pending';
        $milestone=$goal->milestones()->create($data+['user_id'=>$request->user()->id,'completed_at'=>$status==='completed'?now():null]);
        $service->recalculate($goal);
        return response()->json(['data'=>$milestone],201);
    }

    public function toggleMilestone(Request $request, PersonalGoal $goal, GoalMilestone $milestone, GoalProgressService $service): JsonResponse
    {
        $goal=$this->owned($request,$goal);
        abort_unless((int)$milestone->personal_goal_id===(int)$goal->id && (int)$milestone->user_id===(int)$request->user()->id,403);
        $complete=$milestone->status!=='completed';
        $milestone->update(['status'=>$complete?'completed':'in_progress','completed_at'=>$complete?now():null]);
        $service->recalculate($goal);
        return response()->json(['data'=>$milestone->fresh()]);
    }

    public function destroyMilestone(Request $request, PersonalGoal $goal, GoalMilestone $milestone, GoalProgressService $service): JsonResponse
    {
        $goal=$this->owned($request,$goal);
        abort_unless((int)$milestone->personal_goal_id===(int)$goal->id && (int)$milestone->user_id===(int)$request->user()->id,403);
        $milestone->delete();
        $service->recalculate($goal);
        return response()->json(['message'=>'Milestone removed.']);
    }


    public function storeCheckin(Request $request, PersonalGoal $goal, GoalProgressService $service): JsonResponse
    {
        $goal=$this->owned($request,$goal);
        $data=$request->validate(['confidence'=>'required|integer|min:1|max:5','planned_action'=>'required|string|max:255','note'=>'nullable|string|max:2000']);
        $tz=$request->user()->timezone ?: 'Africa/Kampala';
        $weekStart=now($tz)->startOfWeek()->toDateString();
        $checkin=$goal->checkins()->updateOrCreate(['week_start'=>$weekStart],$data+['user_id'=>$request->user()->id,'status'=>'active']);
        return response()->json(['data'=>$checkin]);
    }

    public function rescheduleMilestone(Request $request, PersonalGoal $goal, GoalMilestone $milestone, GoalProgressService $service): JsonResponse
    {
        $goal=$this->owned($request,$goal);
        abort_unless((int)$milestone->personal_goal_id===(int)$goal->id && (int)$milestone->user_id===(int)$request->user()->id,403);
        $data=$request->validate(['target_date'=>'required|date|after_or_equal:today']);
        $milestone->update(['target_date'=>$data['target_date'],'status'=>$milestone->status==='pending'?'in_progress':$milestone->status]);
        $service->recalculate($goal);
        return response()->json(['data'=>$milestone->fresh()]);
    }

    public function storeReflection(Request $request, PersonalGoal $goal, GoalProgressService $service): JsonResponse
    {
        $goal = $this->owned($request,$goal);
        $data = $request->validate([
            'goal_milestone_id'=>'nullable|integer',
            'reflection_type'=>'nullable|in:goal,milestone',
            'what_worked'=>'nullable|string|max:4000',
            'challenges'=>'nullable|string|max:4000',
            'lessons_learned'=>'required|string|max:4000',
            'repeat_next_time'=>'nullable|string|max:4000',
            'change_next_time'=>'nullable|string|max:4000',
            'confidence_after'=>'nullable|integer|min:1|max:5',
        ]);
        $milestoneId=$data['goal_milestone_id']??null;
        if($milestoneId){
            $milestone=$goal->milestones()->whereKey($milestoneId)->where('user_id',$request->user()->id)->firstOrFail();
            $data['reflection_type']='milestone';
            $data['goal_milestone_id']=$milestone->id;
        } else {
            $data['reflection_type']='goal';
            $data['goal_milestone_id']=null;
        }
        $reflection=$goal->reflections()->create($data+[
            'user_id'=>$request->user()->id,
            'reflected_on'=>now($request->user()->timezone ?: 'Africa/Kampala')->toDateString(),
        ]);
        return response()->json(['data'=>$reflection],201);
    }

    public function destroyReflection(Request $request, PersonalGoal $goal, GoalReflection $reflection): JsonResponse
    {
        $goal=$this->owned($request,$goal);
        abort_unless((int)$reflection->personal_goal_id===(int)$goal->id && (int)$reflection->user_id===(int)$request->user()->id,403);
        $reflection->delete();
        return response()->json(['message'=>'Reflection removed.']);
    }

}

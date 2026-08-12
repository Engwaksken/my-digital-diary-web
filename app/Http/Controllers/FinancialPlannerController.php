<?php
namespace App\Http\Controllers;
use App\Models\{Budget,Debt,Expense,FinancialPlannerProfile,Income,SavingsContribution,SavingsGoal};
use Carbon\Carbon;
use Illuminate\Http\Request;
class FinancialPlannerController extends Controller
{
 public function index(Request $request){
  $user=$request->user();
  $end=$request->filled('end_date')?Carbon::parse($request->end_date)->endOfDay():now()->endOfDay();
  $start=$request->filled('start_date')?Carbon::parse($request->start_date)->startOfDay():$end->copy()->subMonths(5)->startOfMonth();
  if($start->gt($end)){ [$start,$end]=[$end->copy()->startOfDay(),$start->copy()->endOfDay()]; }
  $months=max(1,$start->copy()->startOfMonth()->diffInMonths($end->copy()->startOfMonth())+1);
  $uid=$user->id;
  $income=(float)Income::where('user_id',$uid)->whereBetween('received_at',[$start->toDateString(),$end->toDateString()])->sum('amount');
  $expenses=(float)Expense::where('user_id',$uid)->whereBetween('spent_at',[$start->toDateString(),$end->toDateString()])->sum('amount');
  $saved=(float)SavingsContribution::where('user_id',$uid)->whereBetween('contributed_at',[$start->toDateString(),$end->toDateString()])->sum('amount');
  $allSavings=(float)SavingsContribution::where('user_id',$uid)->sum('amount');
  $outstandingDebt=(float)Debt::where('user_id',$uid)->where('type','borrowed')->where('status','outstanding')->sum('amount');
  $monthlyBudget=(float)Budget::where('user_id',$uid)->get()->sum(function($b){ return match($b->period){'weekly'=>(float)$b->amount*52/12,'annually'=>(float)$b->amount/12,default=>(float)$b->amount}; });
  $goalTarget=(float)SavingsGoal::where('user_id',$uid)->whereIn('status',['in_progress','paused'])->sum('target_amount');
  $monthlyIncome=$income/$months; $monthlyExpense=$expenses/$months; $monthlySaved=$saved/$months; $monthlySurplus=$monthlyIncome-$monthlyExpense;
  $profile=FinancialPlannerProfile::firstOrCreate(['user_id'=>$uid],[
   'current_age'=>30,'retirement_age'=>60,'current_retirement_savings'=>0,'expected_annual_return'=>5,'inflation_rate'=>3,'retirement_years'=>20,
  ]);
  $years=max(0,(int)$profile->retirement_age-(int)$profile->current_age); $n=$years*12; $r=((float)$profile->expected_annual_return/100)/12;
  $contribution=$profile->monthly_retirement_contribution!==null?(float)$profile->monthly_retirement_contribution:max(0,$monthlySaved,$monthlySurplus*.5);
  $starting=(float)$profile->current_retirement_savings+$allSavings;
  if($r>0 && $n>0){ $future=$starting*pow(1+$r,$n)+$contribution*((pow(1+$r,$n)-1)/$r); } else { $future=$starting+$contribution*$n; }
  $desired=$profile->desired_monthly_retirement_income!==null?(float)$profile->desired_monthly_retirement_income:max(0,$monthlyExpense);
  $inflated=$desired*pow(1+((float)$profile->inflation_rate/100),$years); $target=$inflated*12*(int)$profile->retirement_years;
  $funding=$target>0?min(999,($future/$target)*100):0;
  $metrics=compact('income','expenses','saved','allSavings','outstandingDebt','monthlyBudget','goalTarget','monthlyIncome','monthlyExpense','monthlySaved','monthlySurplus','contribution','starting','future','desired','inflated','target','funding','years','months');
  return view('financial-planner.index',compact('profile','metrics','start','end'));
 }
 public function update(Request $request){
  $data=$request->validate(['current_age'=>'required|integer|min:18|max:100','retirement_age'=>'required|integer|min:18|max:110|gt:current_age','current_retirement_savings'=>'required|numeric|min:0','monthly_retirement_contribution'=>'nullable|numeric|min:0','expected_annual_return'=>'required|numeric|min:0|max:30','inflation_rate'=>'required|numeric|min:0|max:30','desired_monthly_retirement_income'=>'nullable|numeric|min:0','retirement_years'=>'required|integer|min:1|max:60']);
  FinancialPlannerProfile::updateOrCreate(['user_id'=>$request->user()->id],$data);
  return back()->with('success','Retirement assumptions updated.');
 }
}

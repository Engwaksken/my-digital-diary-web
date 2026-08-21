<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\PersonalGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class GoalExecutionController extends Controller {
 public function show(Request $request, PersonalGoal $goal): JsonResponse {
   abort_unless((int)$goal->user_id === (int)$request->user()->id,403);
   $goal->load(['annualPlans:id,personal_goal_id,title,status,progress_percent,target_date','dailyTasks:id,personal_goal_id,title,is_completed,start_time','projectTasks:id,personal_goal_id,title,status,due_date']);
   $tasks=$goal->dailyTasks->count()+$goal->projectTasks->count(); $done=$goal->dailyTasks->where('is_completed',true)->count()+$goal->projectTasks->where('status','done')->count();
   return response()->json(['goal'=>$goal->only(['id','module','title','description','progress_percent','status','priority','target_date']),'plans'=>$goal->annualPlans,'daily_tasks'=>$goal->dailyTasks,'project_tasks'=>$goal->projectTasks,'execution'=>['task_total'=>$tasks,'task_completed'=>$done,'task_progress'=>$tasks?(int)round(($done/$tasks)*100):0]]);
 }
}

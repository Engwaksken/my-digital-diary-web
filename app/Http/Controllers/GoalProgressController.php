<?php

namespace App\Http\Controllers;

use App\Models\GoalMilestone;
use App\Models\GoalReflection;
use App\Models\PersonalGoal;
use App\Services\GoalProgressService;
use Illuminate\Http\Request;

class GoalProgressController extends Controller
{
    private function owned(Request $request, PersonalGoal $goal): PersonalGoal
    {
        abort_unless((int)$goal->user_id === (int)$request->user()->id, 403);
        return $goal;
    }

    public function show(Request $request, PersonalGoal $goal, GoalProgressService $service)
    {
        $goal = $this->owned($request, $goal);
        $goal = $service->recalculate($goal) ?? $goal;
        $goal->load(['milestones','annualPlans','dailyTasks','projectTasks','reflections.milestone']);
        return view('personal-goals.progress', [
            'goal'=>$goal,
            'snapshot'=>$service->snapshot($goal),
            'week'=>$service->weeklyMovement($goal, $request->user()),
            'accountability'=>$service->accountability($goal, $request->user()),
            'learning'=>$service->learning($goal),
        ]);
    }

    public function storeMilestone(Request $request, PersonalGoal $goal, GoalProgressService $service)
    {
        $goal = $this->owned($request, $goal);
        $data = $request->validate([
            'title'=>'required|string|max:255','description'=>'nullable|string','target_date'=>'nullable|date',
            'weight'=>'nullable|integer|min:1|max:100','status'=>'nullable|in:pending,in_progress,completed',
        ]);
        $status = $data['status'] ?? 'pending';
        $goal->milestones()->create($data + ['user_id'=>$request->user()->id,'completed_at'=>$status === 'completed' ? now() : null]);
        $service->recalculate($goal);
        return back()->with('success','Milestone added.');
    }

    public function toggleMilestone(Request $request, PersonalGoal $goal, GoalMilestone $milestone, GoalProgressService $service)
    {
        $goal = $this->owned($request, $goal);
        abort_unless((int)$milestone->personal_goal_id === (int)$goal->id && (int)$milestone->user_id === (int)$request->user()->id,403);
        $complete = $milestone->status !== 'completed';
        $milestone->update(['status'=>$complete ? 'completed' : 'in_progress','completed_at'=>$complete ? now() : null]);
        $service->recalculate($goal);
        return back()->with('success',$complete ? 'Milestone completed.' : 'Milestone reopened.');
    }

    public function destroyMilestone(Request $request, PersonalGoal $goal, GoalMilestone $milestone, GoalProgressService $service)
    {
        $goal = $this->owned($request, $goal);
        abort_unless((int)$milestone->personal_goal_id === (int)$goal->id && (int)$milestone->user_id === (int)$request->user()->id,403);
        $milestone->delete();
        $service->recalculate($goal);
        return back()->with('success','Milestone removed.');
    }


    public function storeCheckin(Request $request, PersonalGoal $goal, GoalProgressService $service)
    {
        $goal = $this->owned($request, $goal);
        $data = $request->validate([
            'confidence'=>'required|integer|min:1|max:5',
            'planned_action'=>'required|string|max:255',
            'note'=>'nullable|string|max:2000',
        ]);
        $tz = $request->user()->timezone ?: 'Africa/Kampala';
        $weekStart = now($tz)->startOfWeek()->toDateString();
        $goal->checkins()->updateOrCreate(
            ['week_start'=>$weekStart],
            $data + ['user_id'=>$request->user()->id,'status'=>'active']
        );
        return back()->with('success','Weekly goal check-in saved.');
    }

    public function rescheduleMilestone(Request $request, PersonalGoal $goal, GoalMilestone $milestone, GoalProgressService $service)
    {
        $goal = $this->owned($request, $goal);
        abort_unless((int)$milestone->personal_goal_id === (int)$goal->id && (int)$milestone->user_id === (int)$request->user()->id,403);
        $data = $request->validate(['target_date'=>'required|date|after_or_equal:today']);
        $milestone->update(['target_date'=>$data['target_date'],'status'=>$milestone->status === 'pending' ? 'in_progress' : $milestone->status]);
        $service->recalculate($goal);
        return back()->with('success','Milestone rescheduled.');
    }

    public function storeReflection(Request $request, PersonalGoal $goal, GoalProgressService $service)
    {
        $goal = $this->owned($request, $goal);
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

        $milestoneId = $data['goal_milestone_id'] ?? null;
        if ($milestoneId) {
            $milestone = $goal->milestones()->whereKey($milestoneId)->where('user_id',$request->user()->id)->firstOrFail();
            $data['reflection_type'] = 'milestone';
            $data['goal_milestone_id'] = $milestone->id;
        } else {
            $data['reflection_type'] = 'goal';
            $data['goal_milestone_id'] = null;
        }

        $goal->reflections()->create($data + [
            'user_id'=>$request->user()->id,
            'reflected_on'=>now($request->user()->timezone ?: 'Africa/Kampala')->toDateString(),
        ]);

        return back()->with('success','Reflection saved. These lessons can now guide future planning.');
    }

    public function destroyReflection(Request $request, PersonalGoal $goal, GoalReflection $reflection)
    {
        $goal = $this->owned($request, $goal);
        abort_unless((int)$reflection->personal_goal_id === (int)$goal->id && (int)$reflection->user_id === (int)$request->user()->id, 403);
        $reflection->delete();
        return back()->with('success','Reflection removed.');
    }

}

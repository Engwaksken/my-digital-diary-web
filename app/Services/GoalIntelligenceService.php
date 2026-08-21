<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Project;
use App\Models\PersonalGoal;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Support\Carbon;
use App\Services\GoalProgressService;

class GoalIntelligenceService
{
    public function build(User $user): array
    {
        $today = Carbon::now($user->timezone ?: 'Africa/Kampala')->startOfDay();
        $goals = collect();

        Plan::where('user_id', $user->id)->where('is_archived', false)->where('status', '!=', 'completed')
            ->orderBy('target_date')->limit(8)->get()->each(function ($plan) use ($goals, $today) {
                $progress = (int) ($plan->progress_percent ?? 0);
                $due = $plan->target_date;
                $days = $due ? $today->diffInDays($due, false) : null;
                $state = $this->state($progress, $days);
                $goals->push([
                    'type' => 'Annual Plan', 'title' => $plan->title, 'progress' => $progress,
                    'due_date' => $due?->toDateString(), 'days_remaining' => $days, 'state' => $state,
                    'next_action' => $this->nextAction($progress, $days, 'plan'), 'route' => 'annual-plans.index',
                ]);
            });

        SavingsGoal::where('user_id', $user->id)->where('is_archived', false)->where('status', '!=', 'completed')
            ->withSum('contributions', 'amount')->orderBy('target_date')->limit(8)->get()->each(function ($goal) use ($goals, $today) {
                $saved = (float) ($goal->contributions_sum_amount ?? 0);
                $target = max(0.01, (float) $goal->target_amount);
                $progress = min(100, (int) round(($saved / $target) * 100));
                $due = $goal->target_date;
                $days = $due ? $today->diffInDays($due, false) : null;
                $goals->push([
                    'type' => 'Savings Goal', 'title' => $goal->name, 'progress' => $progress,
                    'due_date' => $due?->toDateString(), 'days_remaining' => $days, 'state' => $this->state($progress, $days),
                    'next_action' => $progress < 100 ? 'Add a realistic contribution and review your monthly spending.' : 'Goal achieved.',
                    'route' => 'savings-goals.index',
                ]);
            });

        Project::where('user_id', $user->id)->where('is_archived', false)->whereIn('status', ['planned', 'in_progress'])
            ->withCount(['tasks as total_tasks_count', 'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'done')])
            ->orderBy('deadline')->limit(8)->get()->each(function ($project) use ($goals, $today) {
                $total = max(1, (int) $project->total_tasks_count);
                $progress = min(100, (int) round(((int) $project->completed_tasks_count / $total) * 100));
                $due = $project->deadline;
                $days = $due ? $today->diffInDays($due, false) : null;
                $goals->push([
                    'type' => 'Project', 'title' => $project->name, 'progress' => $progress,
                    'due_date' => $due?->toDateString(), 'days_remaining' => $days, 'state' => $this->state($progress, $days),
                    'next_action' => $progress < 100 ? 'Complete the next open project task.' : 'Project complete.',
                    'route' => 'projects.index',
                ]);
            });

        $progressService = app(GoalProgressService::class);
        PersonalGoal::where('user_id', $user->id)->where('is_archived', false)
            ->whereIn('status', ['not_started', 'in_progress'])
            ->with(['annualPlans','dailyTasks','projectTasks','milestones'])
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderBy('target_date')->limit(12)->get()->each(function ($goal) use ($goals, $today, $progressService, $user) {
                $snapshot = $progressService->snapshot($goal);
                $progress = (int) $snapshot['progress'];
                $due = $goal->target_date;
                $days = $due ? $today->diffInDays($due, false) : null;
                $moduleLabel = str($goal->module)->replace('_', ' ')->title()->toString();
                $week = $progressService->weeklyMovement($goal, $user);
                $accountability = $progressService->accountability($goal, $user);
                $goals->push([
                    'id' => $goal->id, 'type' => $moduleLabel . ' Goal', 'title' => $goal->title, 'progress' => $progress,
                    'due_date' => $due?->toDateString(), 'days_remaining' => $days, 'state' => match($accountability['status']) { 'at_risk'=>'needs_attention','overdue'=>'overdue', default=>'on_track' },
                    'next_action' => $this->nextAction($progress, $days, 'goal'),
                    'route' => 'personal-goals.index', 'detail_route' => 'personal-goals.progress', 'module' => $goal->module,
                    'execution' => [
                        'plans' => (int)$snapshot['plans_total'], 'tasks' => (int)$snapshot['tasks_total'],
                        'completed_tasks' => (int)$snapshot['tasks_completed'],
                        'task_progress' => $snapshot['tasks_total'] > 0 ? (int)round(($snapshot['tasks_completed']/$snapshot['tasks_total'])*100) : null,
                        'milestones' => (int)$snapshot['milestones_total'], 'completed_milestones' => (int)$snapshot['milestones_completed'],
                    ],
                    'week_movement' => ['count'=>$week['movement_count'], 'message'=>$week['message']],
                    'accountability' => ['status'=>$accountability['status'],'label'=>$accountability['status_label'],'overdue_milestones'=>$accountability['overdue_count'],'expected_progress'=>$accountability['expected_progress']],
                ]);
            });

        $sorted = $goals->sortBy(fn($g) => match($g['state']) { 'overdue' => 0, 'needs_attention' => 1, 'on_track' => 2, default => 3 })->values();
        $attention = $sorted->whereIn('state', ['overdue', 'needs_attention']);

        return [
            'summary' => [
                'total_active' => $sorted->count(),
                'on_track' => $sorted->where('state', 'on_track')->count(),
                'needs_attention' => $attention->count(),
                'average_progress' => $sorted->count() ? (int) round($sorted->avg('progress')) : 0,
            ],
            'goals' => $sorted->take(12)->all(),
            'next_actions' => $attention->take(3)->map(fn($g) => [
                'id' => $g['id'] ?? null, 'title' => $g['title'], 'type' => $g['type'], 'message' => $g['next_action'], 'route' => $g['route'], 'detail_route' => $g['detail_route'] ?? null, 'state' => $g['state'],
            ])->values()->all(),
        ];
    }

    private function state(int $progress, ?int $days): string
    {
        if ($progress >= 100) return 'completed';
        if ($days !== null && $days < 0) return 'overdue';
        if ($days !== null && $days <= 14 && $progress < 75) return 'needs_attention';
        if ($progress < 25 && $days !== null && $days <= 45) return 'needs_attention';
        return 'on_track';
    }

    private function nextAction(int $progress, ?int $days, string $kind): string
    {
        if ($days !== null && $days < 0) return 'Review the deadline and choose the smallest action you can complete today.';
        if ($progress < 25) return 'Break this '.$kind.' into one small action and schedule it today.';
        if ($progress < 75) return 'Continue with the next milestone and protect time for it this week.';
        return 'Finish the remaining steps and close this goal.';
    }
}

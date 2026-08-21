<?php

namespace App\Services;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\Expense;
use App\Models\ExerciseLog;
use App\Models\Income;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PeriodReviewMetricsService
{
    public function review(User $user, string $period): array
    {
        [$from, $to] = $this->range($period, $user);

        $taskQuery = DailyPlanItem::query()
            ->whereHas('plan', function ($query) use ($user, $from, $to) {
                $query
                    ->where('user_id', $user->id)
                    ->whereBetween('plan_date', [
                        $from->toDateString(),
                        $to->toDateString(),
                    ]);
            });

        $tasksTotal = (clone $taskQuery)->count();
        $tasksCompleted = (clone $taskQuery)
            ->where('is_completed', true)
            ->count();

        $income = (float) Income::query()
            ->where('user_id', $user->id)
            ->whereBetween('received_at', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->sum('amount');

        $expenses = (float) Expense::query()
            ->where('user_id', $user->id)
            ->whereBetween('spent_at', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->sum('amount');

        $exerciseSessions = ExerciseLog::query()
            ->where('user_id', $user->id)
            ->whereBetween('performed_at', [
                $from->copy()->startOfDay(),
                $to->copy()->endOfDay(),
            ])
            ->count();

        return [
            'period' => $period,
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),

            'tasks_total' => $tasksTotal,
            'tasks_completed' => $tasksCompleted,
            'tasks_pending' => max(0, $tasksTotal - $tasksCompleted),
            'completion_percent' => $tasksTotal > 0
                ? (int) round(($tasksCompleted / $tasksTotal) * 100)
                : 0,

            'income' => $income,
            'expenses' => $expenses,
            'exercise_sessions' => $exerciseSessions,
        ];
    }

    /**
     * Pending Daily Planner tasks for the user's current LOCAL day.
     */
    public function todayFocus(User $user, int $limit = 6): Collection
    {
        $today = Carbon::now($this->timezone($user))->toDateString();

        $plan = DailyPlan::query()
            ->where('user_id', $user->id)
            ->whereDate('plan_date', $today)
            ->first();

        if (! $plan) {
            return collect();
        }

        return $plan->items()
            ->where('is_completed', false)
            ->orderByRaw("
                CASE priority
                    WHEN 'high' THEN 1
                    WHEN 'medium' THEN 2
                    ELSE 3
                END
            ")
            ->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_time')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(function (DailyPlanItem $item) {
                $item->setAttribute('source', 'Daily Planner');
                return $item;
            });
    }

    public function todayTaskProgress(User $user): array
    {
        $today = Carbon::now($this->timezone($user))->toDateString();

        $plan = DailyPlan::query()
            ->where('user_id', $user->id)
            ->whereDate('plan_date', $today)
            ->first();

        if (! $plan) {
            return [
                'tasks_total' => 0,
                'tasks_completed' => 0,
                'tasks_pending' => 0,
                'completion_percent' => 0,
            ];
        }

        $total = $plan->items()->count();
        $completed = $plan->items()
            ->where('is_completed', true)
            ->count();

        return [
            'tasks_total' => $total,
            'tasks_completed' => $completed,
            'tasks_pending' => max(0, $total - $completed),
            'completion_percent' => $total > 0
                ? (int) round(($completed / $total) * 100)
                : 0,
        ];
    }

    private function range(string $period, User $user): array
    {
        $now = Carbon::now($this->timezone($user));

        if ($period === 'month') {
            return [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ];
        }

        return [
            $now->copy()->startOfWeek(Carbon::MONDAY),
            $now->copy()->endOfWeek(Carbon::SUNDAY),
        ];
    }

    private function timezone(User $user): string
    {
        $timezone = trim((string) ($user->timezone ?? ''));

        return $timezone !== ''
            ? $timezone
            : 'Africa/Kampala';
    }
}

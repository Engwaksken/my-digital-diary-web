<?php

namespace App\Services;

use App\Models\DailyPlan;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Meeting;
use App\Models\PersonalRelationship;
use App\Models\ProjectTask;
use App\Models\Reminder;
use App\Models\SavingsGoal;
use App\Models\User;
use Carbon\Carbon;

class DailyRoutineService
{
    public function start(User $user): array
    {
        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        $today = Carbon::now($timezone);
        $date = $today->toDateString();

        $plan = DailyPlan::where('user_id', $user->id)->whereDate('plan_date', $date)->first();

        try {
            if (! class_exists(DailyPlannerRecurrenceService::class)) {
                throw new \RuntimeException('DailyPlannerRecurrenceService is not available.');
            }

            $todayItems = app(DailyPlannerRecurrenceService::class)
                ->itemsForDate($user->id, $today);

            $tasks = $todayItems
                ->filter(fn ($item) => ! (bool) data_get($item, 'is_completed', false))
                ->sortBy(fn ($item) => sprintf(
                    '%d-%s-%010d',
                    in_array(strtolower((string) data_get($item, 'priority')), ['urgent', 'high'], true)
                        ? 0
                        : (strtolower((string) data_get($item, 'priority')) === 'medium' ? 1 : 2),
                    (string) data_get($item, 'start_time', '') ?: '99:99:99',
                    (int) data_get($item, 'id', 0)
                ))
                ->take(8)
                ->values();
        } catch (\Throwable $exception) {
            report($exception);
            $tasks = $plan
                ? $plan->items()->where('is_completed', false)->orderByRaw(
                    "CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END"
                )->orderBy('start_time')->limit(8)->get()
                : collect();
        }

        return [
            'date' => $date,
            'priorities' => $tasks->values(),
            'meetings' => Meeting::where('user_id', $user->id)
                ->whereDate('start_at', $date)->orderBy('start_at')->limit(8)->get(),
            'tasks_due' => ProjectTask::where('user_id', $user->id)
                ->whereIn('status', ['todo','in_progress'])->whereDate('due_date', $date)
                ->orderBy('due_date')->limit(8)->get(),
            'reminders' => Reminder::where('user_id', $user->id)->where('is_active', true)
                ->whereDate('next_run_at', $date)->orderBy('next_run_at')->limit(8)->get(),
            'financial_commitments' => [
                'debts_due' => Debt::where('user_id', $user->id)->where('status', 'outstanding')
                    ->whereDate('due_date', '<=', $date)->orderBy('due_date')->limit(6)->get(),
                'savings_goals' => SavingsGoal::where('user_id', $user->id)
                    ->where('status', 'in_progress')->orderBy('target_date')->limit(6)->get(),
            ],
            'relationships' => PersonalRelationship::where('user_id', $user->id)
                ->whereNotNull('next_planned_interaction')
                ->whereDate('next_planned_interaction', '<=', $today->copy()->addDays(3)->toDateString())
                ->orderBy('next_planned_interaction')->limit(6)->get(),
            'intention_prompt' => 'What matters most today, and what would make today feel well used?',
        ];
    }

    public function end(User $user): array
    {
        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        $today = Carbon::now($timezone);
        $date = $today->toDateString();

        $plan = DailyPlan::where('user_id', $user->id)->whereDate('plan_date', $date)->first();

        try {
            if (! class_exists(DailyPlannerRecurrenceService::class)) {
                throw new \RuntimeException('DailyPlannerRecurrenceService is not available.');
            }

            $items = app(DailyPlannerRecurrenceService::class)
                ->itemsForDate($user->id, $today)
                ->sortBy(fn ($item) => (string) data_get($item, 'start_time', '') ?: '99:99:99')
                ->values();
        } catch (\Throwable $exception) {
            report($exception);
            $items = $plan ? $plan->items()->orderBy('start_time')->get() : collect();
        }

        return [
            'date' => $date,
            'completed_tasks' => $items->where('is_completed', true)->values(),
            'incomplete_tasks' => $items->where('is_completed', false)->values(),
            'meetings_completed' => Meeting::where('user_id', $user->id)
                ->whereDate('start_at', $date)->where('status', 'completed')->orderBy('start_at')->get(),
            'income_today' => (float) Income::where('user_id', $user->id)->whereDate('received_at', $date)->sum('amount'),
            'expenses_today' => (float) Expense::where('user_id', $user->id)->whereDate('spent_at', $date)->sum('amount'),
            'plan' => $plan,
            'carry_forward_suggestion' => $items->where('is_completed', false)->pluck('title')->values(),
            'reflection_prompt' => 'What went well, what was difficult, and what should carry forward to tomorrow?',
        ];
    }
}

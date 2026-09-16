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

        $meetings = Meeting::where('user_id', $user->id)
            ->whereDate('start_at', $date)->orderBy('start_at')->limit(8)->get();
        $tasksDue = ProjectTask::where('user_id', $user->id)
            ->whereIn('status', ['todo','in_progress'])->whereDate('due_date', $date)
            ->orderBy('due_date')->limit(8)->get();
        $reminders = Reminder::where('user_id', $user->id)->where('is_active', true)
            ->whereDate('next_run_at', $date)->orderBy('next_run_at')->limit(8)->get();
        $relationships = PersonalRelationship::where('user_id', $user->id)
            ->whereNotNull('next_planned_interaction')
            ->whereDate('next_planned_interaction', '<=', $today->copy()->addDays(3)->toDateString())
            ->orderBy('next_planned_interaction')->limit(6)->get();

        return [
            'date' => $date,
            'priorities' => $tasks->values(),
            'meetings' => $meetings,
            'tasks_due' => $tasksDue,
            'reminders' => $reminders,
            'financial_commitments' => [
                'debts_due' => Debt::where('user_id', $user->id)->where('status', 'outstanding')
                    ->whereDate('due_date', '<=', $date)->orderBy('due_date')->limit(6)->get(),
                'savings_goals' => SavingsGoal::where('user_id', $user->id)
                    ->where('status', 'in_progress')->orderBy('target_date')->limit(6)->get(),
            ],
            'relationships' => $relationships,
            'intention_prompt' => 'What matters most today, and what would make today feel well used?',
            'reflection_suggestions' => $this->startSuggestions([
                'priorities' => $tasks->pluck('title')->all(),
                'meetings' => $meetings->pluck('title')->all(),
                'tasks_due' => $tasksDue->pluck('title')->all(),
                'reminders' => $reminders->pluck('title')->all(),
                'people' => $relationships->pluck('name')->all(),
            ]),
            'gratitude_suggestions' => $this->gratitudeSuggestions(),
        ];
    }

    private function startSuggestions(array $context): array
    {
        $priorities = $context['priorities'] ?? [];
        $meetings = $context['meetings'] ?? [];
        $tasksDue = $context['tasks_due'] ?? [];
        $people = $context['people'] ?? [];

        $suggestions = [];

        if ($priorities) {
            $suggestions[] = 'Give my best attention to “'.$priorities[0].'”.';

            if (count($priorities) > 1) {
                $suggestions[] = 'Also give time to “'.$priorities[1].'” so it does not crowd out the rest of the day.';
            }
        } else {
            $suggestions[] = 'Keep today simple: choose one meaningful outcome and give it my full attention.';
        }

        foreach (array_slice($people, 0, 1) as $name) {
            $suggestions[] = 'Reach out to '.$name.' today.';
        }

        if ($meetings) {
            $suggestions[] = 'Prepare for “'.$meetings[0].'” so the meeting stays productive.';
        }

        if ($tasksDue) {
            $suggestions[] = 'Catch up on “'.$tasksDue[0].'”, which is due today.';
        }

        $suggestions[] = 'Move my body and take a proper break before the day ends.';
        $suggestions[] = 'Notice one small win by the end of today.';

        return array_values(array_unique(array_filter($suggestions)));
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

        $incompleteTasks = $items->where('is_completed', false)->values();

        return [
            'date' => $date,
            'completed_tasks' => $items->where('is_completed', true)->values(),
            'incomplete_tasks' => $incompleteTasks,
            'meetings_completed' => Meeting::where('user_id', $user->id)
                ->whereDate('start_at', $date)->where('status', 'completed')->orderBy('start_at')->get(),
            'income_today' => (float) Income::where('user_id', $user->id)->whereDate('received_at', $date)->sum('amount'),
            'expenses_today' => (float) Expense::where('user_id', $user->id)->whereDate('spent_at', $date)->sum('amount'),
            'plan' => $plan,
            'carry_forward_suggestion' => $incompleteTasks->pluck('title')->values(),
            'reflection_prompt' => 'What went well, what was difficult, and what should carry forward to tomorrow?',
            'reflection_suggestions' => $this->endSuggestions([
                'completed' => $items->where('is_completed', true)->count(),
                'incomplete' => $incompleteTasks->pluck('title')->all(),
                'carry_forward' => $incompleteTasks->pluck('title')->all(),
            ]),
            'gratitude_suggestions' => $this->gratitudeSuggestions(),
        ];
    }

    private function endSuggestions(array $context): array
    {
        $completed = (int) ($context['completed'] ?? 0);
        $incomplete = $context['incomplete'] ?? [];
        $carryForward = $context['carry_forward'] ?? [];

        $suggestions = [];

        if ($completed > 0) {
            $suggestions[] = 'Reflect on the '.$completed.' completed task'.($completed === 1 ? '' : 's').' and what made them work.';
        } else {
            $suggestions[] = 'Name one thing I got done today, however small.';
        }

        foreach (array_slice($carryForward, 0, 1) as $title) {
            $suggestions[] = 'Carry forward “'.$title.'” to tomorrow.';
        }

        if ($incomplete) {
            $suggestions[] = 'Check what blocked “'.$incomplete[0].'” and plan how to unblock it tomorrow.';
        }

        $suggestions[] = 'Name one thing I handled well today.';
        $suggestions[] = 'Name one thing I would do differently tomorrow.';

        return array_values(array_unique(array_filter($suggestions)));
    }

    private function gratitudeSuggestions(): array
    {
        return [
            'A person who helped me today.',
            'A comfortable moment I almost missed.',
            'Something about my home or environment I value.',
            'My health, my people, or a simple meal.',
        ];
    }
}

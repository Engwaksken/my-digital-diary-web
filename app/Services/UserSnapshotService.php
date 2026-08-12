<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Debt;
use App\Models\DietLog;
use App\Models\EducationPlan;
use App\Models\ExerciseLog;
use App\Models\Expense;
use App\Models\HealthCheckup;
use App\Models\Income;
use App\Models\Meeting;
use App\Models\NetworkContact;
use App\Models\PersonalRelationship;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\SavingsGoal;
use App\Models\SleepLog;
use App\Models\SpiritualPractice;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Builds a single cross-module snapshot of a user's own tracked data.
 * Shared by AiPlannerService (turned into an AI prompt) and
 * ReportController (turned into a PDF report), so both stay in sync with
 * exactly one place that knows how to summarize "everything about this
 * user right now".
 *
 * NOTE ON HISTORY: this only covered plans/finances/health/projects/
 * education/network/relationships for a long stretch of this app's
 * development — Debts, Savings, Meetings, Reminders, Spiritual Growth,
 * and Exercise were all added as modules later but never added HERE,
 * meaning both the downloadable report AND every AI-generated plan since
 * then silently had no awareness those six modules existed at all. Fixed
 * below — if you add another module in the future, add it here too, or
 * it'll have the same blind spot.
 */
class UserSnapshotService
{
    /**
     * $now defaults to server time (Carbon::now()) — unchanged behavior
     * for the web app. Mobile passes the phone's own local time instead,
     * since a server and a user's phone can genuinely disagree on what
     * day/time "now" is (different timezone, or a misconfigured server
     * clock), which matters here specifically because the AI plan's
     * "next 7 days" / "overdue" reasoning depends on knowing the
     * correct current moment.
     */
    public function build(User $user, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $userId = $user->id;
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        return [
            'generated_at' => $now,
            'plans' => [
                'daily_pending_or_active' => Plan::where('user_id', $userId)
                    ->where('period', 'daily')
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->latest()->limit(10)->pluck('title'),
                'overdue_or_upcoming' => Plan::where('user_id', $userId)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->whereNotNull('target_date')
                    ->orderBy('target_date')->limit(10)
                    ->get(['title', 'period', 'target_date', 'status']),
            ],
            'finances' => [
                'income_this_month' => (float) Income::where('user_id', $userId)
                    ->whereBetween('received_at', [$startOfMonth, $endOfMonth])->sum('amount'),
                'expenses_this_month' => (float) Expense::where('user_id', $userId)
                    ->whereBetween('spent_at', [$startOfMonth, $endOfMonth])->sum('amount'),
                'monthly_budget_total' => (float) Budget::where('user_id', $userId)
                    ->where('period', 'monthly')->sum('amount'),
                'top_expense_categories' => Expense::where('user_id', $userId)
                    ->whereBetween('spent_at', [$startOfMonth, $endOfMonth])
                    ->selectRaw('category, SUM(amount) as total')
                    ->groupBy('category')->orderByDesc('total')->limit(5)->get(),
            ],
            'health' => [
                'avg_sleep_minutes_last_7_days' => SleepLog::where('user_id', $userId)
                    ->where('sleep_date', '>=', $now->copy()->subDays(7))->avg('duration_minutes'),
                'recent_meals' => DietLog::where('user_id', $userId)
                    ->latest('logged_at')->limit(5)->pluck('food_items'),
                'upcoming_checkups' => HealthCheckup::where('user_id', $userId)
                    ->whereNotNull('next_due_date')
                    ->where('next_due_date', '>=', $now->copy()->startOfDay())
                    ->orderBy('next_due_date')->limit(5)
                    ->get(['checkup_type', 'next_due_date']),
            ],
            'projects' => Project::where('user_id', $userId)
                ->whereIn('status', ['planned', 'in_progress'])
                ->withCount('tasks')->limit(10)
                ->get(['id', 'name', 'status', 'deadline']),
            'education' => EducationPlan::where('user_id', $userId)
                ->whereIn('status', ['planned', 'in_progress'])
                ->limit(10)->get(['title', 'level', 'status', 'target_completion_date']),
            'network' => NetworkContact::where('user_id', $userId)
                ->whereNotNull('next_follow_up_date')
                ->orderBy('next_follow_up_date')->limit(10)
                ->get(['name', 'relationship_type', 'next_follow_up_date']),
            'relationships' => PersonalRelationship::where('user_id', $userId)
                ->orderBy('next_planned_interaction')
                ->limit(10)
                ->get(['name', 'category', 'priority', 'next_planned_interaction', 'strengthening_goal']),

            'debts' => Debt::where('user_id', $userId)
                ->where('status', 'outstanding')
                ->limit(10)
                ->get(['type', 'person_name', 'amount', 'due_date']),

            'savings_goals' => SavingsGoal::where('user_id', $userId)
                ->withSum('contributions', 'amount')
                ->limit(10)
                ->get(['id', 'name', 'target_amount', 'target_date', 'status']),

            'upcoming_meetings' => Meeting::where('user_id', $userId)
                ->where('status', 'scheduled')
                ->where('start_at', '>=', now())
                ->orderBy('start_at')->limit(10)
                ->get(['title', 'start_at', 'location']),

            'active_reminders' => Reminder::where('user_id', $userId)
                ->where('is_active', true)
                ->orderBy('next_run_at')->limit(10)
                ->get(['title', 'frequency', 'next_run_at']),

            'spiritual_practices_this_week' => SpiritualPractice::where('user_id', $userId)
                ->where('practiced_at', '>=', now()->startOfWeek())
                ->count(),

            'recent_exercise' => ExerciseLog::where('user_id', $userId)
                ->orderByDesc('performed_at')->limit(10)
                ->get(['activity', 'duration_minutes', 'intensity', 'performed_at']),
        ];
    }

    public function toJson(array $snapshot): string
    {
        return json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

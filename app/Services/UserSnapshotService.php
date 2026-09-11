<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\DailyPlan;
use App\Models\Debt;
use App\Models\DietLog;
use App\Models\EducationPlan;
use App\Models\ExerciseLog;
use App\Models\Expense;
use App\Models\FinancialPlannerProfile;
use App\Models\HealthCheckup;
use App\Models\Income;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Models\NetworkContact;
use App\Models\Payment;
use App\Models\PersonalRelationship;
use App\Models\PersonalGoal;
use App\Models\GoalReflection;
use App\Models\DailyWellbeingLog;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Note;
use App\Models\Reminder;
use App\Models\SavingsGoal;
use App\Models\SleepLog;
use App\Models\SpiritualPractice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-module snapshot shared by the Personal Report and AI Planner.
 * Keep new user-facing modules represented here so the PDF never drifts
 * behind the application feature set.
 */
class UserSnapshotService
{
    public function build(User $user, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $userId = $user->id;
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $allows = fn (string $module): bool => $user->aiAllows($module);

        $dailyPlan = null;
        if (class_exists(DailyPlan::class) && Schema::hasTable('daily_plans')) {
            $dailyPlan = DailyPlan::where('user_id', $userId)
                ->with('items')
                ->whereDate('plan_date', $now->toDateString())
                ->first();

            if (! $dailyPlan) {
                $dailyPlan = DailyPlan::where('user_id', $userId)
                    ->with('items')
                    ->latest('plan_date')
                    ->first();
            }
        }

        $financialPlanner = null;
        if (class_exists(FinancialPlannerProfile::class) && Schema::hasTable('financial_planner_profiles')) {
            $financialPlanner = FinancialPlannerProfile::where('user_id', $userId)->first();
        }

        $recentRecordings = collect();
        if (class_exists(MeetingRecording::class) && Schema::hasTable('meeting_recordings')) {
            $recentRecordings = MeetingRecording::query()
                ->whereHas('meeting', fn ($query) => $query->where('user_id', $userId))
                ->with('meeting:id,title,user_id')
                ->latest('id')
                ->limit(10)
                ->get();
        }

        $recentPayments = collect();
        if (class_exists(Payment::class) && Schema::hasTable('payments')) {
            $recentPayments = Payment::where('user_id', $userId)
                ->with(['plan:id,name', 'invoice'])
                ->latest('id')
                ->limit(10)
                ->get();
        }

        return [
            'generated_at' => $now,

            'plans' => $allows('planning') || $allows('goals') ? [
                'annual' => Plan::where('user_id', $userId)
                    ->where('period', 'annual')
                    ->latest('id')->limit(12)->get(),
                'overdue_or_upcoming' => Plan::where('user_id', $userId)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->whereNotNull('target_date')
                    ->orderBy('target_date')->limit(10)->get(),
            ] : null,

            'daily_planner' => $allows('planning') ? $dailyPlan : null,
            'financial_planner' => $allows('finance') ? $financialPlanner : null,

            'finances' => $allows('finance') ? [
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
                'recent_itemized_expenses' => Expense::where('user_id', $userId)
                    ->with('items')
                    ->latest('spent_at')->limit(10)->get(),
            ] : null,

            'health' => ($allows('health') || $allows('wellbeing')) ? [
                'avg_sleep_minutes_last_7_days' => SleepLog::where('user_id', $userId)
                    ->where('sleep_date', '>=', $now->copy()->subDays(7))->avg('duration_minutes'),
                'recent_meals' => DietLog::where('user_id', $userId)
                    ->latest('logged_at')->limit(5)->pluck('food_items'),
                'upcoming_checkups' => HealthCheckup::where('user_id', $userId)
                    ->whereNotNull('next_due_date')
                    ->where('next_due_date', '>=', $now->copy()->startOfDay())
                    ->orderBy('next_due_date')->limit(5)->get(),
            ] : null,

            'projects' => $allows('planning') ? Project::where('user_id', $userId)
                ->whereIn('status', ['planned', 'in_progress'])
                ->withCount('tasks')->limit(10)->get() : collect(),

            'tasks' => $allows('planning') ? ProjectTask::where('user_id', $userId)
                ->whereIn('status', ['todo', 'in_progress'])
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date')->limit(12)->get() : collect(),

            'notes' => $allows('notes') ? Note::where('user_id', $userId)
                ->orderByDesc('is_pinned')->latest('updated_at')->limit(8)->get() : collect(),

            'education' => $allows('education') ? EducationPlan::where('user_id', $userId)
                ->where('is_archived', false)
                ->whereIn('status', ['planned', 'in_progress'])
                ->limit(10)->get() : collect(),

            'network' => $allows('network') ? NetworkContact::where('user_id', $userId)
                ->whereNotNull('next_follow_up_date')
                ->orderBy('next_follow_up_date')->limit(10)->get() : collect(),

            'relationships' => $allows('relationships') ? PersonalRelationship::where('user_id', $userId)
                ->orderBy('next_planned_interaction')->limit(10)->get() : collect(),

            'debts' => $allows('finance') ? Debt::where('user_id', $userId)
                ->where('status', 'outstanding')->limit(10)->get() : collect(),

            'personal_goals' => $allows('goals') ? PersonalGoal::where('user_id', $userId)
                ->where('is_archived', false)->whereIn('status', ['not_started','in_progress'])
                ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
                ->orderBy('target_date')->limit(15)->get() : collect(),

            'goal_learnings' => ($allows('goals') && Schema::hasTable('goal_reflections')) ? GoalReflection::where('user_id', $userId)
                ->with(['goal:id,title,module','milestone:id,title'])
                ->latest('reflected_on')->latest('id')->limit(12)->get()
                ->map(fn($r) => [
                    'goal'=>$r->goal?->title, 'module'=>$r->goal?->module, 'milestone'=>$r->milestone?->title,
                    'what_worked'=>$r->what_worked, 'challenges'=>$r->challenges, 'lessons_learned'=>$r->lessons_learned,
                    'repeat_next_time'=>$r->repeat_next_time, 'change_next_time'=>$r->change_next_time,
                    'reflected_on'=>$r->reflected_on?->toDateString(),
                ])->values() : collect(),

            'wellbeing_today' => ($allows('health') || $allows('wellbeing')) ? DailyWellbeingLog::where('user_id', $userId)
                ->whereDate('log_date', $now->toDateString())->first() : null,

            'savings_goals' => ($allows('finance') || $allows('goals')) ? SavingsGoal::where('user_id', $userId)
                ->withSum('contributions', 'amount')->limit(10)->get() : collect(),

            'upcoming_meetings' => $allows('meetings') ? Meeting::where('user_id', $userId)
                ->where('start_at', '>=', $now)
                ->orderBy('start_at')->limit(10)->get() : collect(),

            'meeting_recordings' => $allows('meetings') ? $recentRecordings : collect(),

            'active_reminders' => $allows('planning') ? Reminder::where('user_id', $userId)
                ->where('is_active', true)
                ->orderBy('next_run_at')->limit(10)->get() : collect(),

            'spiritual_practices_this_week' => $allows('spiritual') ? SpiritualPractice::where('user_id', $userId)
                ->where('practiced_at', '>=', $now->copy()->startOfWeek())->count() : 0,

            'recent_exercise' => ($allows('health') || $allows('wellbeing')) ? ExerciseLog::where('user_id', $userId)
                ->orderByDesc('performed_at')->limit(10)->get() : collect(),

            'billing' => [
                'subscription_plan' => $user->subscriptionPlan ?? null,
                'subscription_ends_at' => $user->subscription_ends_at ?? null,
                'recent_payments' => $recentPayments,
            ],

            'feature_summary' => collect(config('app_features', []))->pluck('label')->values(),
        ];
    }

    public function toJson(array $snapshot): string
    {
        return json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

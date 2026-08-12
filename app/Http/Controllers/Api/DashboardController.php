<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BusinessCard;
use App\Models\Expense;
use App\Models\Income;
use App\Models\DailyPlan;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A lighter-weight version of the web DashboardController's stat cards
 * PLUS the same chart data and recent-activity feed — the Flutter app
 * renders its own native charts (fl_chart) and cards from this JSON
 * rather than any Blade view or Chart.js config.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // ---- Spending by category (this month) — doughnut chart ----
        $expensesByCategory = Expense::where('user_id', $userId)
            ->whereBetween('spent_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // ---- Income vs Expenses, last 6 months — bar chart ----
        $trendMonths = collect(range(5, 0))->map(
            fn ($monthsAgo) => Carbon::now()->subMonthsNoOverflow($monthsAgo)->startOfMonth()
        );

        $trendLabels = $trendMonths->map(fn (Carbon $month) => $month->format('M Y'))->all();

        $incomeTrend = $trendMonths->map(function (Carbon $month) use ($userId) {
            return (float) Income::where('user_id', $userId)
                ->whereBetween('received_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->sum('amount');
        })->all();

        $expenseTrend = $trendMonths->map(function (Carbon $month) use ($userId) {
            return (float) Expense::where('user_id', $userId)
                ->whereBetween('spent_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->sum('amount');
        })->all();

        return response()->json([
            'monthly_income' => (float) Income::where('user_id', $userId)->whereBetween('received_at', [$startOfMonth, $endOfMonth])->sum('amount'),
            'monthly_expenses' => (float) Expense::where('user_id', $userId)->whereBetween('spent_at', [$startOfMonth, $endOfMonth])->sum('amount'),
            'monthly_budget' => (float) Budget::where('user_id', $userId)->where('period', 'monthly')->sum('amount'),
            'active_projects' => Project::where('user_id', $userId)->whereIn('status', ['planned', 'in_progress'])->count(),
            'upcoming_reminders' => Reminder::where('user_id', $userId)->where('is_active', true)->whereBetween('next_run_at', [now(), now()->addDays(7)])->count(),
            'expenses_by_category' => $expensesByCategory->map(fn ($row) => [
                'category' => $row->category,
                'total' => (float) $row->total,
            ]),
            'trend_labels' => $trendLabels,
            'income_trend' => $incomeTrend,
            'expense_trend' => $expenseTrend,
            'recent_activity' => $this->recentActivity($userId),
            'top_tasks' => $this->topTasksFor($userId),
        ]);
    }

    /**
     * Same idea as the web DashboardController's buildRecentActivity() —
     * a handful of the most recent items across a few modules, newest
     * first. Kept intentionally smaller/simpler than the web version
     * (fewer source modules) since this is a quick glance list, not a
     * full activity log.
     */
    /**
     * Same logic as SendDailyTopTasksDigest::topTasksFor() (duplicated
     * rather than shared, per this project's usual pattern for small
     * self-contained queries) — but called live here so the Home
     * screen can show "top 3 upcoming" any time the dashboard loads,
     * not only once a day via the async email/notification digest.
     */
    private function topTasksFor(int $userId): array
    {
        $today = Carbon::today();

        // Only items that are actually due/planned for TODAY belong in a
        // section labelled "Today's Top 3". Daily Planner items inherit
        // their due date from the parent daily plan; Project Tasks use their
        // own due_date column. Completed items are excluded.
        $daily = DailyPlan::where('user_id', $userId)
            ->whereDate('plan_date', $today)
            ->with(['items' => fn ($query) => $query
                ->where('is_completed', false)
                ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
                ->orderBy('start_time')
                ->orderBy('sort_order')
                ->orderBy('id')])
            ->first();

        $dailyItems = $daily
            ? $daily->items->map(fn ($item) => [
                'title' => $item->title,
                'source' => 'Daily Planner',
                'due_date' => $today->toDateString(),
                'sort' => $item->start_time ?: '23:59:59',
                'priority_rank' => match ($item->priority) { 'high' => 0, 'medium' => 1, default => 2 },
            ])
            : collect();

        $projectTasks = ProjectTask::where('user_id', $userId)
            ->whereDate('due_date', $today)
            ->whereIn('status', ['todo', 'in_progress'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->map(fn ($task) => [
                'title' => $task->title,
                'source' => 'Task',
                'due_date' => $task->due_date?->toDateString() ?? $today->toDateString(),
                'sort' => '23:59:59',
                'priority_rank' => 1,
            ]);

        return $dailyItems
            ->concat($projectTasks)
            ->sortBy(fn ($item) => sprintf('%d-%s', $item['priority_rank'], $item['sort']))
            ->take(3)
            ->map(fn ($item) => [
                'title' => $item['title'],
                'source' => $item['source'],
                'due_date' => $item['due_date'],
            ])
            ->values()
            ->all();
    }

    /**
     * Same logic as the compact recentActivity() shown inline on the
     * dashboard, just with a much higher cap — for a dedicated "View
     * All" screen rather than the home screen's quick-glance version.
     */
    public function recentActivityFull(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->recentActivity($request->user()->id, perModelLimit: 20, take: 50)]);
    }

    private function recentActivity(int $userId, int $perModelLimit = 5, int $take = 10): array
    {
        $items = collect();

        Expense::where('user_id', $userId)->latest()->limit($perModelLimit)->get()->each(function ($e) use (&$items) {
            $items->push(['icon' => 'receipt', 'text' => "Logged expense: {$e->category}", 'amount' => (float) $e->amount, 'time' => $e->created_at]);
        });

        Income::where('user_id', $userId)->latest()->limit($perModelLimit)->get()->each(function ($i) use (&$items) {
            $items->push(['icon' => 'income', 'text' => 'Logged income', 'amount' => (float) $i->amount, 'time' => $i->created_at]);
        });

        BusinessCard::where('user_id', $userId)->latest('updated_at')->limit($perModelLimit)->get()->each(function ($card) use (&$items) {
            $created = $card->created_at && $card->updated_at && $card->created_at->equalTo($card->updated_at);
            $items->push([
                'icon' => 'business_card',
                'text' => ($created ? 'Created business card: ' : 'Updated business card: ') . $card->name,
                'amount' => null,
                'time' => $card->updated_at ?? $card->created_at,
            ]);
        });

        Reminder::where('user_id', $userId)->latest()->limit($perModelLimit)->get()->each(function ($r) use (&$items) {
            $items->push(['icon' => 'reminder', 'text' => "Reminder: {$r->title}", 'amount' => null, 'time' => $r->created_at]);
        });

        return $items->sortByDesc('time')->take($take)->map(fn ($item) => [
            'icon' => $item['icon'],
            'text' => $item['text'],
            'amount' => $item['amount'],
            'time' => $item['time']->toIso8601String(),
        ])->values()->all();
    }
}

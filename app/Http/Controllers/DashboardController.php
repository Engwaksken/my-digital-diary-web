<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BusinessCard;
use App\Models\DietLog;
use App\Models\EducationPlan;
use App\Models\Expense;
use App\Models\HealthCheckup;
use App\Models\Income;
use App\Models\Meeting;
use App\Models\NetworkContact;
use App\Models\PersonalRelationship;
use App\Models\Plan;
use App\Models\DailyPlan;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Reminder;
use App\Models\SavingsGoal;
use App\Models\SleepLog;
use App\Models\SpiritualPractice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Whoever just logged in or registered from an emailed invite
        // link (see OrganizationController::acceptInvite()) gets linked
        // to that organization here, before they see anything else —
        // every login/registration path eventually lands on this route,
        // so this is a single, low-risk place to catch it rather than
        // touching the auth controllers themselves.
        if ($token = session('pending_org_invite_token')) {
            $member = \App\Models\OrganizationMember::where('invite_token', $token)->where('status', 'invited')->first();
            if ($member) {
                return app(\App\Http\Controllers\OrganizationController::class)->finalizeInviteAcceptance($request, $member);
            }
            session()->forget('pending_org_invite_token');
        }

        $userId = $request->user()->id;
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // --- Stat cards (always visible) -------------------------------------

        $monthlyIncome = Income::where('user_id', $userId)
            ->whereBetween('received_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthlyExpenses = Expense::where('user_id', $userId)
            ->whereBetween('spent_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthlyBudget = Budget::where('user_id', $userId)
            ->where('period', 'monthly')
            ->sum('amount');

        $activeProjects = Project::where('user_id', $userId)
            ->whereIn('status', ['planned', 'in_progress'])
            ->count();

        $goalsSummary = SavingsGoal::where('user_id', $userId)->withSum('contributions', 'amount')->get();
        $totalSaved = $goalsSummary->sum('contributions_sum_amount');
        $totalSavingsTarget = $goalsSummary->sum('target_amount');

        $upcomingReminderCount = Reminder::where('user_id', $userId)
            ->where('is_active', true)
            ->whereBetween('next_run_at', [now(), now()->addDays(7)])
            ->count();

        // --- Finance tab: charts ----------------------------------------------

        $expensesByCategory = Expense::where('user_id', $userId)
            ->whereBetween('spent_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // Last 6 calendar months, oldest first, for an income-vs-expense trend.
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

        // --- Productivity tab ---------------------------------------------------

        $todayPlan = DailyPlan::where('user_id', $userId)
            ->whereDate('plan_date', now()->toDateString())
            ->first();

        $todaysPlanItems = $todayPlan
            ? $todayPlan->items()->where('is_completed', false)->limit(5)->get()
            : collect();

        $todayPlanProgress = $todayPlan ? $todayPlan->progressPercent() : 0;

        $activeProjectsList = Project::where('user_id', $userId)
            ->whereIn('status', ['planned', 'in_progress'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $upcomingTasks = ProjectTask::where('user_id', $userId)
            ->whereIn('status', ['todo', 'in_progress'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $upcomingReminders = Reminder::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('next_run_at')
            ->limit(5)
            ->get();

        $upcomingMeetings = Meeting::where('user_id', $userId)
            ->where('status', 'scheduled')
            ->where('start_at', '>=', now())
            ->orderBy('start_at')
            ->limit(5)
            ->get();

        // --- Health & Wellness tab ----------------------------------------------

        $upcomingCheckups = HealthCheckup::where('user_id', $userId)
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '>=', Carbon::today())
            ->orderBy('next_due_date')
            ->limit(5)
            ->get();

        $avgSleepMinutes = SleepLog::where('user_id', $userId)
            ->where('sleep_date', '>=', Carbon::now()->subDays(7))
            ->avg('duration_minutes');

        $avgCaloriesLast7Days = DietLog::where('user_id', $userId)
            ->where('logged_at', '>=', Carbon::now()->subDays(7))
            ->avg('calories');

        // --- Growth tab -----------------------------------------------------

        $inProgressEducation = EducationPlan::where('user_id', $userId)
            ->where('status', 'in_progress')
            ->orderBy('target_completion_date')
            ->limit(5)
            ->get();

        $upcomingNetworkFollowUps = NetworkContact::where('user_id', $userId)
            ->whereNotNull('next_follow_up_date')
            ->orderBy('next_follow_up_date')
            ->limit(5)
            ->get();

        // Ordered ascending (oldest/overdue first) rather than filtered to the
        // future only, since a check-in you meant to have last week deserves
        // more attention than one three months out.
        $upcomingRelationshipCheckins = PersonalRelationship::where('user_id', $userId)
            ->whereNotNull('next_planned_interaction')
            ->orderBy('next_planned_interaction')
            ->limit(5)
            ->get();

        $spiritualPracticesThisWeek = SpiritualPractice::where('user_id', $userId)
            ->where('practiced_at', '>=', now()->startOfWeek())
            ->count();

        // --- AI Planner widget (latest plan preview + quick-generate) --------

        $latestAiPlan = \App\Models\AiPlan::where('user_id', $userId)->latest()->first();
        $hasAiAccess = $request->user()->activeApiCredential() || \App\Models\SiteSetting::current()->hasDefaultAiKey();

        // --- Subscription expiry notice (in-app "notification upon login") --
        // Unread database-channel notifications from
        // SendSubscriptionExpiryReminders — shown once, then marked read,
        // so it surfaces right after logging in without nagging on every
        // single page load (the persistent banner in layouts/app.blade.php
        // already covers ongoing visibility while actually expired).
        $expiryNotification = $request->user()->unreadNotifications()
            ->where('type', \App\Notifications\SubscriptionExpiryReminderNotification::class)
            ->first();
        if ($expiryNotification) {
            $expiryNotification->markAsRead();
        }

        // --- Annual Plans summary -------------------------------------------

        $annualPlans = Plan::where('user_id', $userId)
            ->where('plan_year', now()->year)
            ->orderByDesc('progress_percent')
            ->orderBy('target_date')
            ->limit(5)
            ->get();

        $annualPlanTotal = Plan::where('user_id', $userId)
            ->where('plan_year', now()->year)
            ->count();

        $annualPlanCompleted = Plan::where('user_id', $userId)
            ->where('plan_year', now()->year)
            ->where('status', 'completed')
            ->count();

        $annualPlanProgress = (int) round((float) (Plan::where('user_id', $userId)
            ->where('plan_year', now()->year)
            ->avg('progress_percent') ?? 0));

        // Only shown on the dashboard when actually relevant — a plain
        // individual subscriber never sees a "manage your team" prompt
        // for something they don't have.
        $ownedOrganization = \App\Models\Organization::where('owner_user_id', $userId)->with('plan')->first();

        // --- App highlights (only modules the user's current plan includes) ---

        $plan = $request->user()->subscriptionPlan;
        $highlights = collect(config('app_features'))
            ->filter(fn ($feature, $key) => ! $plan || $plan->includesFeature($key))
            ->map(fn ($feature, $key) => array_merge($feature, ['key' => $key]))
            ->values();

        return view('dashboard', compact(
            'monthlyIncome',
            'monthlyExpenses',
            'monthlyBudget',
            'activeProjects',
            'totalSaved',
            'totalSavingsTarget',
            'upcomingReminderCount',
            'expensesByCategory',
            'trendLabels',
            'incomeTrend',
            'expenseTrend',
            'todaysPlanItems',
            'todayPlanProgress',
            'activeProjectsList',
            'upcomingTasks',
            'upcomingReminders',
            'upcomingMeetings',
            'upcomingCheckups',
            'avgSleepMinutes',
            'avgCaloriesLast7Days',
            'inProgressEducation',
            'upcomingNetworkFollowUps',
            'upcomingRelationshipCheckins',
            'spiritualPracticesThisWeek',
            'annualPlans',
            'annualPlanTotal',
            'annualPlanCompleted',
            'annualPlanProgress',
            'latestAiPlan',
            'hasAiAccess',
            'expiryNotification',
            'highlights',
            'ownedOrganization'
        ));
    }

    /**
     * The full activity log — same underlying data as the dashboard's
     * "Recent Activity" preview, just pulling a much larger batch (up to
     * 300, vs. the dashboard's 5) and paginating it 20 per page. There's
     * no single `activity` table backing this — it's a merge across
     * several modules — so pagination (and search) are built by hand over
     * an in-memory collection, rather than Eloquent's usual ->paginate()/
     * ->where('...', 'like', ...), which only work against one query.
     */
    public function activity(Request $request)
    {
        $userId = $request->user()->id;
        $perPage = 20;
        $page = (int) $request->input('page', 1);
        $search = $request->query('q');

        $all = $this->buildRecentActivity($userId, 30, 300);

        // Stats reflect the FULL pulled batch (up to 300), not just the
        // current page or the search-filtered result — a summary of
        // "what's been happening lately" shouldn't shrink to near-zero
        // just because you typed a search term.
        $stats = [
            ['label' => 'Total shown', 'value' => (string) $all->count(), 'icon' => 'fa-solid fa-list', 'color' => 'slate'],
            ['label' => 'Today', 'value' => (string) $all->filter(fn ($item) => $item['time']->isToday())->count(), 'icon' => 'fa-solid fa-sun', 'color' => 'amber'],
            ['label' => 'This week', 'value' => (string) $all->filter(fn ($item) => $item['time']->isCurrentWeek())->count(), 'icon' => 'fa-solid fa-calendar-week', 'color' => 'blue'],
        ];

        if ($search) {
            $all = $all->filter(fn ($item) => str_contains(strtolower($item['text']), strtolower($search)))->values();
        }

        // Period filter — same daily/weekly/monthly/range vocabulary as
        // every module's own crud/index.blade.php filter, applied here
        // against each activity item's own timestamp instead of a single
        // DB column (this feed is merged in-memory from several models,
        // not one query, so it can't use whereBetween() the normal way).
        $period = $request->query('period');
        $from = $request->query('from');
        $to = $request->query('to');

        if ($period === 'daily') {
            $all = $all->filter(fn ($item) => $item['time']->isToday())->values();
        } elseif ($period === 'weekly') {
            $all = $all->filter(fn ($item) => $item['time']->isCurrentWeek())->values();
        } elseif ($period === 'monthly') {
            $all = $all->filter(fn ($item) => $item['time']->isCurrentMonth())->values();
        } elseif ($period === 'range' && $from && $to) {
            $rangeStart = \Illuminate\Support\Carbon::parse($from)->startOfDay();
            $rangeEnd = \Illuminate\Support\Carbon::parse($to)->endOfDay();
            $all = $all->filter(fn ($item) => $item['time']->between($rangeStart, $rangeEnd))->values();
        }

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('activity.index', [
            'activity' => $paginator,
            'stats' => $stats,
            'search' => $search,
            'period' => $period,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * A single chronological feed pulled from several modules at once, each
     * normalized into the same shape (icon, color, text, time) so the view
     * can render one uniform list rather than needing per-module markup.
     * Deliberately a representative sample of modules, not literally all
     * 20 — a "recent activity" feed is meant to give a quick pulse of what
     * you've been doing lately, not be an exhaustive audit log.
     *
     * @param int $perModelLimit how many rows to pull from EACH module
     *                           before merging — needs to be at least as
     *                           large as $take, or an early-created-but-
     *                           still-recent-overall item from a quiet
     *                           module could get excluded before the merge
     *                           even happens.
     * @param int $take how many combined, sorted items to return
     */
    private function buildRecentActivity(int $userId, int $perModelLimit = 5, int $take = 15)
    {
        $items = collect();

        Expense::where('user_id', $userId)->latest()->limit($perModelLimit)->get()->each(function ($e) use (&$items) {
            $items->push(['icon' => 'fa-solid fa-receipt', 'color' => 'rose', 'text' => "Logged expense: {$e->category} — " . format_money($e->amount), 'time' => $e->created_at]);
        });

        Income::where('user_id', $userId)->latest()->limit($perModelLimit)->get()->each(function ($i) use (&$items) {
            $items->push(['icon' => 'fa-solid fa-money-bill-trend-up', 'color' => 'emerald', 'text' => "Logged income: " . format_money($i->amount), 'time' => $i->created_at]);
        });

        BusinessCard::where('user_id', $userId)->latest('updated_at')->limit($perModelLimit)->get()->each(function ($card) use (&$items) {
            $created = $card->created_at && $card->updated_at && $card->created_at->equalTo($card->updated_at);
            $items->push([
                'icon' => 'fa-solid fa-id-card',
                'color' => 'teal',
                'text' => ($created ? 'Created business card: ' : 'Updated business card: ') . $card->name,
                'time' => $card->updated_at ?? $card->created_at,
            ]);
        });

        \App\Models\SignedDocument::where('user_id', $userId)->latest('signed_at')->limit($perModelLimit)->get()->each(function ($d) use (&$items) {
            $positionLabel = $d->position ? str_replace('-', ' ', $d->position) : 'no position';
            $items->push([
                'icon' => 'fa-solid fa-file-signature',
                'color' => 'indigo',
                'text' => $d->was_stamped
                    ? "Signed document: {$d->original_filename} ({$positionLabel})"
                    : "Uploaded document: {$d->original_filename} (not auto-stamped)",
                'time' => $d->signed_at ?? $d->created_at,
            ]);
        });

        DailyPlan::where('user_id', $userId)->latest()->limit($perModelLimit)->get()->each(function ($plan) use (&$items) {
            $date = optional($plan->plan_date)->format('d M Y') ?? 'daily plan';
            $items->push(['icon' => 'fa-solid fa-calendar-check', 'color' => 'indigo', 'text' => "Updated daily planner: {$date}", 'time' => $plan->updated_at ?? $plan->created_at]);
        });

        ProjectTask::where('user_id', $userId)->where('status', 'done')->latest()->limit($perModelLimit)->get()->each(function ($t) use (&$items) {
            $items->push(['icon' => 'fa-solid fa-clipboard-check', 'color' => 'cyan', 'text' => "Completed task: {$t->title}", 'time' => $t->updated_at]);
        });

        Meeting::where('user_id', $userId)->latest()->limit($perModelLimit)->get()->each(function ($m) use (&$items) {
            $items->push(['icon' => 'fa-solid fa-calendar-days', 'color' => 'blue', 'text' => "Scheduled meeting: {$m->title}", 'time' => $m->created_at]);
        });

        SleepLog::where('user_id', $userId)->latest()->limit($perModelLimit)->get()->each(function ($s) use (&$items) {
            $hours = round($s->duration_minutes / 60, 1);
            $items->push(['icon' => 'fa-solid fa-bed', 'color' => 'violet', 'text' => "Logged sleep: {$hours} hrs", 'time' => $s->created_at]);
        });

        DietLog::where('user_id', $userId)->latest()->limit($perModelLimit)->get()->each(function ($d) use (&$items) {
            $items->push(['icon' => 'fa-solid fa-utensils', 'color' => 'orange', 'text' => "Logged meal: {$d->meal_type} ({$d->calories} cal)", 'time' => $d->created_at]);
        });

        Reminder::where('user_id', $userId)->latest()->limit($perModelLimit)->get()->each(function ($r) use (&$items) {
            $items->push(['icon' => 'fa-solid fa-bell', 'color' => 'yellow', 'text' => "Created reminder: {$r->title}", 'time' => $r->created_at]);
        });

        return $items->sortByDesc('time')->take($take)->values();
    }
}

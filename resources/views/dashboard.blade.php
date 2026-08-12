@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @if (auth()->user()->offboarded_at && ! auth()->user()->personal_email_verified_at)
        <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm mb-6">
            <p class="font-medium mb-1">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                You've left your organization
            </p>
            <p class="mb-3">
                Your personal tracking data is untouched, but you'll need a personal email to keep
                using your account going forward — organization-owned information stays with the company.
            </p>
            <form method="POST" action="{{ route('personal-email.send-code') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="flex-1 min-w-[12rem]">
                    <label for="personal_email" class="sr-only">Personal email</label>
                    <input type="email" id="personal_email" name="personal_email" required aria-required="true"
                           placeholder="you@example.com" class="pm-input text-sm">
                </div>
                <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Send Verification Code
                </button>
            </form>
            @if (session('personal_email_code') ?? false)
                <form method="POST" action="{{ route('personal-email.verify') }}" class="flex flex-wrap items-end gap-2 mt-3">
                    @csrf
                    <div class="min-w-[8rem]">
                        <label for="code" class="sr-only">Verification code</label>
                        <input type="text" id="code" name="code" placeholder="6-digit code" maxlength="6" class="pm-input text-sm">
                    </div>
                    <button type="submit" class="bg-white border border-amber-300 text-amber-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-amber-50 transition-colors">
                        Verify
                    </button>
                </form>
            @endif
            @error('code')
                <p role="alert" class="text-sm text-rose-600 mt-2">{{ $message }}</p>
            @enderror
        </div>
    @endif

    @if ($expiryNotification)
        <dialog id="pm-expiry-notice-modal" aria-labelledby="pm-expiry-notice-title" class="rounded-2xl p-6 pm-dialog-sm shadow-2xl backdrop:bg-slate-900/50">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                </div>
                <h2 id="pm-expiry-notice-title" class="text-lg font-bold text-slate-800">
                    @if ($expiryNotification->data['days_remaining'] > 0)
                        Subscription expiring soon
                    @else
                        Subscription expired
                    @endif
                </h2>
            </div>
            <p class="text-sm text-slate-600 mb-5">
                @if ($expiryNotification->data['days_remaining'] > 0)
                    Your subscription expires on {{ \Illuminate\Support\Carbon::parse($expiryNotification->data['expiry_date'])->format('l, F j, Y') }}
                    — that's {{ $expiryNotification->data['days_remaining'] }} day(s) from now.
                @else
                    Your subscription expired on {{ \Illuminate\Support\Carbon::parse($expiryNotification->data['expiry_date'])->format('l, F j, Y') }}.
                    You can still view everything you've entered, but adding, editing, and premium features are on hold until you renew.
                @endif
            </p>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('pm-expiry-notice-modal').close()" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
                    Dismiss
                </button>
                <a href="{{ route('subscription.show') }}" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    Renew Subscription
                </a>
            </div>
        </dialog>
        <script>
            document.getElementById('pm-expiry-notice-modal').showModal();
        </script>
    @endif
    {{-- 1. Hero welcome banner --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[var(--brand-1)] via-[var(--brand-1)] to-[var(--brand-2)] px-6 py-6 sm:px-8 sm:py-8 mb-6 shadow-lg shadow-[var(--brand-1-tint-20)]">
        <div class="absolute inset-0 opacity-10" aria-hidden="true">
            <i class="fa-solid fa-chart-line absolute -right-4 -top-4 text-[10rem]"></i>
        </div>
        <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
                    Welcome back, {{ explode(' ', auth()->user()->name)[0] }} <span aria-hidden="true"></span>
                </h1>
                <p class="text-[#cfe0d6] text-sm mt-1">Here's where things stand today.</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <form method="POST" action="{{ route('reminders.toggle-mute') }}">
                    @csrf
                    <button type="submit"
                            class="w-11 h-11 rounded-lg flex items-center justify-center transition-all
                                {{ auth()->user()->alarms_muted
                                    ? 'bg-white/20 text-white/70 hover:bg-white/30'
                                    : 'bg-white/95 text-[var(--brand-1)] hover:bg-white shadow-sm hover:shadow-md' }}"
                            aria-pressed="{{ auth()->user()->alarms_muted ? 'true' : 'false' }}"
                            title="{{ auth()->user()->alarms_muted ? 'Reminder alarms are muted — click to unmute' : 'Reminder alarms are on — click to mute' }}">
                        <i class="fa-solid {{ auth()->user()->alarms_muted ? 'fa-bell-slash' : 'fa-bell' }}" aria-hidden="true"></i>
                        <span class="sr-only">
                            {{ auth()->user()->alarms_muted ? 'Unmute reminder alarms' : 'Mute reminder alarms' }}
                        </span>
                    </button>
                </form>
                <a href="{{ route('report.download') }}"
                   class="inline-flex items-center justify-center gap-2 bg-white/95 hover:bg-white text-[var(--brand-1)] px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                    <span>Download Report</span>
                </a>
            </div>
        </div>
    </div>

    {{-- AI Planner — placed right after the hero banner/bell so it's one
         of the first things visible on the dashboard, not buried inside
         a tab. --}}
    <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-robot text-violet-500" aria-hidden="true"></i> AI Planner
            </h2>
            <a href="{{ route('ai-plans.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1">
                View all plans <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
            </a>
        </div>

        @if (!$hasAiAccess)
            <p class="text-sm text-slate-500">
                Add your own AI API key at <a href="{{ route('api-credentials.index') }}" class="text-[var(--brand-1)] hover:underline">API Keys</a>
                to start generating plans — it looks across every module and writes a prioritized action
                plan for the next 7 and 30 days.
            </p>
        @elseif ($latestAiPlan)
            <p class="text-xs text-slate-400 mb-2">Latest plan — {{ $latestAiPlan->created_at->diffForHumans() }}</p>
            <p class="text-sm text-slate-700 whitespace-pre-line line-clamp-4">{{ \Illuminate\Support\Str::limit($latestAiPlan->cleanContent(), 280) }}</p>
            <form method="POST" action="{{ route('ai-plans.store') }}" class="mt-3">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                    Generate New Plan
                </button>
            </form>
        @else
            <p class="text-sm text-slate-500 mb-3">No plans generated yet — get a prioritized action plan across everything you track.</p>
            <form method="POST" action="{{ route('ai-plans.store') }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                    Generate My First Plan
                </button>
            </form>
        @endif
    </div>

    {{-- Billing quick-link — invoices/receipts live on the subscription
         page itself (see "Invoices & Receipts" there), this is just a
         visible way to reach that from the dashboard directly. --}}
    <a href="{{ route('subscription.show') }}" class="flex items-center justify-between gap-3 bg-white border border-slate-100 rounded-xl px-5 py-3 mb-6 shadow-sm hover:shadow-md transition-shadow group">
        <span class="flex items-center gap-3">
            <span class="w-9 h-9 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i>
            </span>
            <span class="text-sm font-medium text-slate-700 group-hover:text-[var(--brand-1)]">View Invoices &amp; Receipts</span>
        </span>
        <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-[var(--brand-1)]" aria-hidden="true"></i>
    </a>

    @if ($ownedOrganization)
        <a href="{{ route('organization.show') }}" class="flex items-center justify-between gap-3 bg-white border border-slate-100 rounded-xl px-5 py-3 mb-6 shadow-sm hover:shadow-md transition-shadow group">
            <span class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                </span>
                <span>
                    <span class="block text-sm font-medium text-slate-700 group-hover:text-[var(--brand-1)]">
                        {{ $ownedOrganization->plan?->category === 'organization' ? 'Manage Your Team' : 'Invite Your Family & Team Members' }}
                    </span>
                    <span class="block text-xs text-slate-400">{{ $ownedOrganization->seatsUsed() }} / {{ $ownedOrganization->seatLimit() }} members</span>
                </span>
            </span>
            <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-[var(--brand-1)]" aria-hidden="true"></i>
        </a>
    @endif

    {{-- 2. Statistics cards — always visible, never tabbed --}}
    @php
        $remaining = $monthlyBudget - $monthlyExpenses;
        $savingsPercent = $totalSavingsTarget > 0 ? round(($totalSaved / $totalSavingsTarget) * 100, 1) : null;
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
        <div class="pm-card-bg shadow-sm border border-slate-100 border-l-4 border-l-emerald-400 rounded-xl p-4 hover:shadow-md transition-shadow">
            <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2">
                <i class="fa-solid fa-money-bill-trend-up text-sm" aria-hidden="true"></i>
            </div>
            <p class="text-xs text-slate-500 uppercase tracking-wide truncate">Income (mo)</p>
            <p class="text-lg font-bold text-emerald-600 truncate">{{ format_money($monthlyIncome) }}</p>
        </div>
        <div class="pm-card-bg shadow-sm border border-slate-100 border-l-4 border-l-rose-400 rounded-xl p-4 hover:shadow-md transition-shadow">
            <div class="w-9 h-9 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center mb-2">
                <i class="fa-solid fa-receipt text-sm" aria-hidden="true"></i>
            </div>
            <p class="text-xs text-slate-500 uppercase tracking-wide truncate">Expenses (mo)</p>
            <p class="text-lg font-bold text-rose-600 truncate">{{ format_money($monthlyExpenses) }}</p>
        </div>
        <div class="pm-card-bg shadow-sm border border-slate-100 border-l-4 border-l-indigo-400 rounded-xl p-4 hover:shadow-md transition-shadow">
            <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center mb-2">
                <i class="fa-solid {{ $remaining >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }} text-sm" aria-hidden="true"></i>
            </div>
            <p class="text-xs text-slate-500 uppercase tracking-wide truncate">Budget {{ $remaining >= 0 ? 'Left' : 'Over' }}</p>
            <p class="text-lg font-bold {{ $remaining >= 0 ? 'text-indigo-600' : 'text-rose-600' }} truncate">{{ format_money(abs($remaining)) }}</p>
        </div>
        <div class="pm-card-bg shadow-sm border border-slate-100 border-l-4 border-l-blue-400 rounded-xl p-4 hover:shadow-md transition-shadow">
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-2">
                <i class="fa-solid fa-diagram-project text-sm" aria-hidden="true"></i>
            </div>
            <p class="text-xs text-slate-500 uppercase tracking-wide truncate">Active Projects</p>
            <p class="text-lg font-bold text-slate-800 truncate">{{ $activeProjects }}</p>
        </div>
        <div class="pm-card-bg shadow-sm border border-slate-100 border-l-4 border-l-green-400 rounded-xl p-4 hover:shadow-md transition-shadow">
            <div class="w-9 h-9 rounded-lg bg-green-50 text-green-600 flex items-center justify-center mb-2">
                <i class="fa-solid fa-piggy-bank text-sm" aria-hidden="true"></i>
            </div>
            <p class="text-xs text-slate-500 uppercase tracking-wide truncate">Savings</p>
            <p class="text-lg font-bold text-green-600 truncate">{{ $savingsPercent !== null ? $savingsPercent . '%' : format_money($totalSaved) }}</p>
        </div>
        <div class="pm-card-bg shadow-sm border border-slate-100 border-l-4 border-l-yellow-400 rounded-xl p-4 hover:shadow-md transition-shadow">
            <div class="w-9 h-9 rounded-lg bg-yellow-50 text-yellow-600 flex items-center justify-center mb-2">
                <i class="fa-solid fa-bell text-sm" aria-hidden="true"></i>
            </div>
            <p class="text-xs text-slate-500 uppercase tracking-wide truncate">Reminders (7d)</p>
            <p class="text-lg font-bold text-slate-800 truncate">{{ $upcomingReminderCount }}</p>
        </div>
    </div>

    {{-- 3. Tabbed navigation instead of many stacked cards --}}
    <div role="tablist" aria-label="Dashboard sections" class="flex gap-1 border-b border-slate-200 mb-6 overflow-x-auto">
        <button type="button" role="tab" id="pm-dash-tab-finance" aria-controls="pm-dash-panel-finance"
                aria-selected="true" tabindex="0" data-tab="finance"
                onclick="pmSelectDashboardTab('finance')" onkeydown="pmDashboardTabKeydown(event, 'finance')"
                class="pm-dash-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-[var(--brand-1)] text-[var(--brand-1)]">
            <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
            <span>Finance</span>
        </button>
        <button type="button" role="tab" id="pm-dash-tab-productivity" aria-controls="pm-dash-panel-productivity"
                aria-selected="false" tabindex="-1" data-tab="productivity"
                onclick="pmSelectDashboardTab('productivity')" onkeydown="pmDashboardTabKeydown(event, 'productivity')"
                class="pm-dash-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-list-check" aria-hidden="true"></i>
            <span>Productivity</span>
        </button>
        <button type="button" role="tab" id="pm-dash-tab-health" aria-controls="pm-dash-panel-health"
                aria-selected="false" tabindex="-1" data-tab="health"
                onclick="pmSelectDashboardTab('health')" onkeydown="pmDashboardTabKeydown(event, 'health')"
                class="pm-dash-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-heart-pulse" aria-hidden="true"></i>
            <span>Health &amp; Wellness</span>
        </button>
        <button type="button" role="tab" id="pm-dash-tab-growth" aria-controls="pm-dash-panel-growth"
                aria-selected="false" tabindex="-1" data-tab="growth"
                onclick="pmSelectDashboardTab('growth')" onkeydown="pmDashboardTabKeydown(event, 'growth')"
                class="pm-dash-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-seedling" aria-hidden="true"></i>
            <span>Growth</span>
        </button>
    </div>

    {{-- 4. Finance tab: charts live here --}}
    <div role="tabpanel" id="pm-dash-panel-finance" aria-labelledby="pm-dash-tab-finance" tabindex="0" class="pm-dash-panel">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-chart-pie text-rose-500" aria-hidden="true"></i> Spending by Category (this month)
                </h2>
                @if ($expensesByCategory->isEmpty())
                    <p class="text-sm text-slate-400">No expenses logged this month yet.</p>
                @else
                    @php
                        $categoryAriaLabel = 'Doughnut chart of this month\'s spending by category: '
                            . $expensesByCategory->map(fn ($c) => $c->category . ' $' . number_format($c->total, 2))->implode(', ')
                            . '.';
                    @endphp
                    <div class="h-56">
                        <canvas id="categoryChart" role="img" aria-label="{{ $categoryAriaLabel }}"></canvas>
                    </div>
                @endif
            </div>

            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-chart-column text-indigo-500" aria-hidden="true"></i> Income vs Expenses (last 6 months)
                </h2>
                @php
                    $trendAriaParts = [];
                    foreach ($trendLabels as $i => $label) {
                        $trendAriaParts[] = "{$label}: income \${$incomeTrend[$i]}, expenses \${$expenseTrend[$i]}";
                    }
                    $trendAriaLabel = 'Bar chart comparing monthly income and expenses for the last 6 months: '
                        . implode('; ', $trendAriaParts) . '.';
                @endphp
                <div class="h-56">
                    <canvas id="trendChart" role="img" aria-label="{{ $trendAriaLabel }}"></canvas>
                </div>
            </div>
        </div>

        <div class="mt-4 text-right">
            <a href="{{ route('savings-goals.index') }}" class="text-xs text-[var(--brand-1)] hover:underline mr-4">Savings Goals &rarr;</a>
            <a href="{{ route('debts.index') }}" class="text-xs text-[var(--brand-1)] hover:underline mr-4">Debts &rarr;</a>
            <a href="{{ route('budgets.index') }}" class="text-xs text-[var(--brand-1)] hover:underline">Budgets &rarr;</a>
        </div>
    </div>

    {{-- Productivity tab --}}
    <div role="tabpanel" id="pm-dash-panel-productivity" aria-labelledby="pm-dash-tab-productivity" tabindex="0" class="pm-dash-panel" hidden>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h2 class="font-semibold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-calendar-check text-indigo-500" aria-hidden="true"></i> Today's Daily Planner
                    </h2>
                    <span class="text-xs font-semibold text-indigo-600">{{ $todayPlanProgress }}%</span>
                </div>
                <div class="h-2 rounded-full bg-slate-100 overflow-hidden mb-3">
                    <div class="h-full bg-indigo-500 rounded-full" style="width: {{ min(100, max(0, $todayPlanProgress)) }}%"></div>
                </div>
                @forelse ($todaysPlanItems as $item)
                    <div class="text-sm py-1.5 border-b border-slate-50 last:border-0 flex items-center justify-between gap-3">
                        <span>{{ $item->title }}</span>
                        @if($item->start_time)
                            <span class="text-xs text-slate-500 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($item->start_time)->format('g:i A') }}</span>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No pending items for today.</p>
                @endforelse
                <a href="{{ route('daily-planner.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1 mt-3">
                    Open Daily Planner <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>

            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-diagram-project text-blue-500" aria-hidden="true"></i> Active Projects
                </h2>
                @forelse ($activeProjectsList as $project)
                    <div class="text-sm py-1.5 border-b border-slate-50 last:border-0">{{ $project->name }} — <span class="text-slate-500">{{ str_replace('_', ' ', $project->status) }}</span></div>
                @empty
                    <p class="text-sm text-slate-400">No active projects.</p>
                @endforelse
                <a href="{{ route('projects.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1 mt-3">
                    View all projects <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>

            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-clipboard-check text-cyan-500" aria-hidden="true"></i> Open Tasks
                </h2>
                @forelse ($upcomingTasks as $task)
                    <div class="text-sm py-1.5 border-b border-slate-50 last:border-0">{{ $task->title }} — <span class="text-slate-500">{{ str_replace('_', ' ', $task->status) }}</span></div>
                @empty
                    <p class="text-sm text-slate-400">No open tasks.</p>
                @endforelse
                <a href="{{ route('project-tasks.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1 mt-3">
                    View all tasks <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>

            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-calendar-days text-blue-500" aria-hidden="true"></i> Upcoming Meetings
                </h2>
                @forelse ($upcomingMeetings as $meeting)
                    <div class="text-sm py-1.5 border-b border-slate-50 last:border-0">{{ $meeting->title }} — <span class="text-slate-500">{{ $meeting->start_at->format('M j, g:ia') }}</span></div>
                @empty
                    <p class="text-sm text-slate-400">No meetings scheduled.</p>
                @endforelse
                <a href="{{ route('meetings.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1 mt-3">
                    View all meetings <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>

            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 md:col-span-2">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-bell text-amber-500" aria-hidden="true"></i> Upcoming Reminders
                </h2>
                @forelse ($upcomingReminders as $reminder)
                    <div class="text-sm py-1.5 border-b border-slate-50 last:border-0">
                        {{ $reminder->title }} — <span class="text-slate-500">{{ $reminder->next_run_at->format('Y-m-d H:i') }} ({{ $reminder->frequency }})</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No reminders set. <a href="{{ route('reminders.create') }}" class="text-[var(--brand-1)] hover:underline">Create one</a>.</p>
                @endforelse
                <a href="{{ route('reminders.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1 mt-3">
                    View all reminders <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>

    {{-- Health & Wellness tab --}}
    <div role="tabpanel" id="pm-dash-panel-health" aria-labelledby="pm-dash-tab-health" tabindex="0" class="pm-dash-panel" hidden>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-bed text-violet-500" aria-hidden="true"></i> Sleep (last 7 days)
                </h2>
                <p class="text-2xl font-bold text-slate-800">
                    {{ $avgSleepMinutes ? number_format($avgSleepMinutes / 60, 1) . ' hrs avg' : 'No data yet' }}
                </p>
                <a href="{{ route('sleep-logs.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1 mt-3">
                    View sleep logs <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>

            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-utensils text-orange-500" aria-hidden="true"></i> Diet (last 7 days)
                </h2>
                <p class="text-2xl font-bold text-slate-800">
                    {{ $avgCaloriesLast7Days ? number_format($avgCaloriesLast7Days) . ' cal avg' : 'No data yet' }}
                </p>
                <a href="{{ route('diet-logs.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1 mt-3">
                    View diet logs <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>

            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-stethoscope text-pink-500" aria-hidden="true"></i> Upcoming Health Checkups
                </h2>
                @forelse ($upcomingCheckups as $checkup)
                    <div class="text-sm py-1.5 border-b border-slate-50 last:border-0">
                        {{ $checkup->checkup_type }} — due {{ $checkup->next_due_date->format('Y-m-d H:i') }}
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No upcoming checkups scheduled.</p>
                @endforelse
                <a href="{{ route('health-checkups.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1 mt-3">
                    View all checkups <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>

    {{-- Growth tab --}}
    <div role="tabpanel" id="pm-dash-panel-growth" aria-labelledby="pm-dash-tab-growth" tabindex="0" class="pm-dash-panel" hidden>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-graduation-cap text-purple-500" aria-hidden="true"></i> Education in Progress
                </h2>
                @forelse ($inProgressEducation as $plan)
                    <div class="text-sm py-1.5 border-b border-slate-50 last:border-0">
                        {{ $plan->title }}
                        @if ($plan->target_completion_date)
                            <span class="text-slate-500">— target {{ $plan->target_completion_date->format('Y-m-d') }}</span>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No education plans in progress.</p>
                @endforelse
                <a href="{{ route('education-plans.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1 mt-3">
                    View all education plans <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>

            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-people-arrows text-sky-500" aria-hidden="true"></i> Network Follow-ups
                </h2>
                @forelse ($upcomingNetworkFollowUps as $contact)
                    <div class="text-sm py-1.5 border-b border-slate-50 last:border-0">
                        {{ $contact->name }}
                        <span class="text-slate-500">— {{ $contact->next_follow_up_date->format('Y-m-d') }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No follow-ups scheduled.</p>
                @endforelse
                <a href="{{ route('network-contacts.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1 mt-3">
                    View all contacts <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>

            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-heart text-red-500" aria-hidden="true"></i> Relationship Check-ins
                </h2>
                @forelse ($upcomingRelationshipCheckins as $relationship)
                    <div class="text-sm py-1.5 border-b border-slate-50 last:border-0">
                        {{ $relationship->name }}
                        <span class="text-slate-500">
                            — {{ $relationship->next_planned_interaction->format('Y-m-d') }}
                            @if ($relationship->next_planned_interaction->isPast())
                                <span class="text-rose-600 font-medium">(overdue)</span>
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No check-ins planned yet.</p>
                @endforelse
                <a href="{{ route('relationships.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1 mt-3">
                    View all relationships <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>

            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-hands-praying text-fuchsia-500" aria-hidden="true"></i> Spiritual Growth
                </h2>
                <p class="text-2xl font-bold text-slate-800">{{ $spiritualPracticesThisWeek }} <span class="text-sm font-normal text-slate-500">this week</span></p>
                <a href="{{ route('spiritual-practices.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1 mt-3">
                    View all practices <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>

    {{-- 5. Annual Plans --}}
    <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 mt-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h2 class="font-semibold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-bullseye text-indigo-500" aria-hidden="true"></i>
                    Annual Plans {{ now()->year }}
                </h2>
                <p class="text-xs text-slate-500 mt-1">{{ $annualPlanCompleted }} of {{ $annualPlanTotal }} completed</p>
            </div>
            <a href="{{ route('annual-plans.index') }}" class="text-xs text-[var(--brand-1)] hover:underline flex items-center gap-1">
                Manage Annual Plans <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
            </a>
        </div>

        <div class="flex items-center gap-3 mb-5">
            <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden flex-1">
                <div class="h-full bg-[var(--brand-1)] rounded-full" style="width: {{ $annualPlanProgress }}%"></div>
            </div>
            <span class="text-sm font-bold text-slate-700">{{ $annualPlanProgress }}%</span>
        </div>

        @forelse ($annualPlans as $annualPlan)
            <div class="flex items-center gap-3 py-2 border-t border-slate-100 first:border-t-0">
                <span class="w-5 h-5 rounded border flex items-center justify-center shrink-0 {{ $annualPlan->status === 'completed' ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-slate-300 text-transparent' }}">
                    <i class="fa-solid fa-check text-[10px]"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-slate-700 truncate {{ $annualPlan->status === 'completed' ? 'line-through text-slate-400' : '' }}">{{ $annualPlan->title }}</p>
                    <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden mt-1.5">
                        <div class="h-full bg-indigo-500" style="width: {{ $annualPlan->progress_percent }}%"></div>
                    </div>
                </div>
                <span class="text-xs font-semibold text-slate-500">{{ $annualPlan->progress_percent }}%</span>
            </div>
        @empty
            <p class="text-sm text-slate-400">No annual plans yet. Add your goals for {{ now()->year }} and track them throughout the year.</p>
        @endforelse
    </div>

    {{-- 6. App highlights — only shows modules the user's current plan
         includes (see DashboardController::index()'s $highlights). --}}
    @if ($highlights->isNotEmpty())
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 mt-8">
            <h2 class="font-semibold text-slate-800 flex items-center gap-2 mb-1">
                <i class="fa-solid fa-star text-amber-400" aria-hidden="true"></i>
                Explore What's Included
            </h2>
            <p class="text-sm text-slate-500 mb-4">Everything available on your current plan.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($highlights as $feature)
                    <a href="{{ route($feature['route']) }}"
                       class="flex items-start gap-3 p-3 rounded-lg border border-slate-100 hover:border-{{ $feature['color'] }}-200 hover:bg-{{ $feature['color'] }}-50/50 transition-colors group">
                        <span class="w-10 h-10 rounded-lg bg-{{ $feature['color'] }}-100 text-{{ $feature['color'] }}-600 flex items-center justify-center shrink-0">
                            <i class="{{ $feature['icon'] }}" aria-hidden="true"></i>
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-800 group-hover:text-{{ $feature['color'] }}-700">{{ $feature['label'] }}</span>
                            <span class="block text-xs text-slate-500 mt-0.5">{{ $feature['description'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
        var pmDashCategoryChart = null;
        var pmDashTrendChart = null;
        var pmDashCurrencySymbol = @json($siteSettings->default_currency_symbol ?? 'UGX');
        var pmDashCurrencyDecimals = {{ $siteSettings->default_currency_decimals ?? 0 }};

        function pmDashFormatMoney(amount) {
            return pmDashCurrencySymbol + ' ' + Number(amount).toLocaleString(undefined, { minimumFractionDigits: pmDashCurrencyDecimals, maximumFractionDigits: pmDashCurrencyDecimals });
        }

        // Deferred until the Finance tab is actually visible — same
        // reasoning as crud/index.blade.php: Chart.js measures its canvas
        // at construction time, and a canvas inside a hidden ancestor
        // measures 0x0. Finance is the default tab here, so this runs
        // immediately on load in practice, but stays guarded in case that
        // ever changes.
        function pmInitDashboardChartsIfNeeded() {
            if (pmDashTrendChart) { return; }

            @if ($expensesByCategory->isNotEmpty())
                pmDashCategoryChart = new Chart(document.getElementById('categoryChart'), {
                    type: 'doughnut',
                    data: {
                        labels: @json($expensesByCategory->pluck('category')),
                        datasets: [{
                            data: @json($expensesByCategory->pluck('total')),
                            backgroundColor: [
                                '#6366f1', '#f97316', '#10b981', '#f43f5e', '#3b82f6',
                                '#eab308', '#ec4899', '#14b8a6', '#8b5cf6', '#84cc16',
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff',
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 16,
                                    font: { size: 12 },
                                    generateLabels: function (chart) {
                                        var data = chart.data;
                                        if (!data.labels.length || !data.datasets.length) { return []; }
                                        var ds = data.datasets[0];
                                        return data.labels.map(function (label, i) {
                                            return {
                                                text: label + ': ' + pmDashFormatMoney(ds.data[i]),
                                                fillStyle: Array.isArray(ds.backgroundColor) ? ds.backgroundColor[i] : ds.backgroundColor,
                                                strokeStyle: ds.borderColor || '#ffffff',
                                                lineWidth: ds.borderWidth || 0,
                                                hidden: false,
                                                index: i,
                                            };
                                        });
                                    },
                                },
                            },
                        },
                    },
                });
            @endif

            pmDashTrendChart = new Chart(document.getElementById('trendChart'), {
                type: 'bar',
                data: {
                    labels: @json($trendLabels),
                    datasets: [
                        {
                            label: 'Income',
                            data: @json($incomeTrend),
                            backgroundColor: '#10b981',
                            borderRadius: 4,
                        },
                        {
                            label: 'Expenses',
                            data: @json($expenseTrend),
                            backgroundColor: '#f43f5e',
                            borderRadius: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } },
                    plugins: { legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } } },
                },
            });
        }

        function pmSelectDashboardTab(key) {
            document.querySelectorAll('.pm-dash-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected);
                btn.classList.toggle('border-transparent', !isSelected);
                btn.classList.toggle('text-slate-500', !isSelected);
                if (isSelected) { btn.focus(); }
            });
            document.querySelectorAll('.pm-dash-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-dash-panel-' + key;
            });
            if (key === 'finance') { pmInitDashboardChartsIfNeeded(); }
        }

        function pmDashboardTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-dash-tab'), function (t) { return t.dataset.tab; });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else if (event.key === 'Home') { nextIndex = 0; }
            else if (event.key === 'End') { nextIndex = tabs.length - 1; }
            else { return; }

            event.preventDefault();
            pmSelectDashboardTab(tabs[nextIndex]);
        }

        document.addEventListener('DOMContentLoaded', function () {
            var financePanel = document.getElementById('pm-dash-panel-finance');
            if (financePanel && !financePanel.hasAttribute('hidden')) {
                pmInitDashboardChartsIfNeeded();
            }
        });
    </script>
@endsection

@extends('layouts.app')
@section('title', 'My Month in Review')
@section('content')
@php
    $money = $review['money'] ?? [];
    $productivity = $review['productivity'] ?? [];
    $wellbeing = $review['wellbeing'] ?? [];
    $comparison = $review['comparison'] ?? [];
    $momentum = $review['momentum'] ?? [];
    $value = $review['value'] ?? [];
    $format = fn ($value) => \App\Models\SiteSetting::current()->formatMoneyForUser((float) $value, auth()->user());
    $deltaMeta = function ($value, $inverse = false, $suffix = '%') {
        if ($value === null) return ['No previous data', 'text-slate-400', 'fa-minus'];
        $value = (float) $value;
        if (abs($value) < 0.05) return ['No change', 'text-slate-500', 'fa-minus'];
        $positive = $inverse ? $value < 0 : $value > 0;
        return [($value > 0 ? '+' : '').number_format($value, 1).$suffix, $positive ? 'text-emerald-600' : 'text-rose-600', $value > 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'];
    };
@endphp
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-[var(--brand-1)]">Your progress</p>
            <h1 class="text-2xl font-bold text-slate-900">My Month in Review</h1>
            <p class="text-sm text-slate-500 mt-1">See what changed, what worked, and the few things worth focusing on next.</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <input type="month" name="month" value="{{ $selectedMonth }}" max="{{ now()->format('Y-m') }}" class="rounded-xl border-slate-200 text-sm focus:border-[var(--brand-1)] focus:ring-[var(--brand-1)]">
            <button class="btn-primary text-white px-4 py-2 rounded-xl text-sm font-semibold">View</button>
        </form>
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5 flex flex-wrap justify-between gap-3">
        <div><div class="text-xs uppercase tracking-wide text-slate-400">Month</div><div class="text-xl font-bold text-slate-900">{{ $review['month'] }}</div></div>
        <div class="text-sm text-slate-500 self-center">{{ $review['period'] }}</div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach ([
            ['Income', $money['income'] ?? 0, 'fa-arrow-trend-up', '#34d399'],
            ['Expenses', $money['expenses'] ?? 0, 'fa-receipt', '#fb7185'],
            ['Saved', $money['saved'] ?? 0, 'fa-piggy-bank', '#a78bfa'],
            ['Net', $money['net'] ?? 0, 'fa-scale-balanced', '#60a5fa'],
        ] as [$label, $valueAmount, $icon, $accent])
            <div class="rounded-2xl bg-white border border-slate-100 border-l-4 shadow-sm p-4" style="border-left-color: {{ $accent }}">
                <div class="flex items-center gap-2 text-slate-500 text-xs font-semibold uppercase"><i class="fa-solid {{ $icon }}"></i>{{ $label }}</div>
                <div class="mt-2 text-lg font-bold text-slate-900">{{ $format($valueAmount) }}</div>
            </div>
        @endforeach
    </div>

    <section class="rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm">
        <div class="grid lg:grid-cols-[180px_1fr] gap-5 items-center">
            <div>
                <p class="text-xs uppercase tracking-wide font-bold text-indigo-600">Monthly momentum</p>
                <div class="flex items-end gap-2 mt-1"><span class="text-4xl font-black text-slate-900">{{ $momentum['score'] ?? 0 }}</span><span class="text-sm text-slate-400 mb-1">/100</span></div>
                <p class="text-sm font-semibold text-indigo-700">{{ $momentum['label'] ?? 'Building momentum' }}</p>
            </div>
            <div>
                <div class="h-2.5 rounded-full bg-white overflow-hidden border border-indigo-100"><div class="h-full rounded-full bg-indigo-500" style="width: {{ min(100, max(0, (int)($momentum['score'] ?? 0))) }}%"></div></div>
                <p class="text-sm text-slate-600 mt-3">{{ $momentum['message'] ?? '' }}</p>
                @if (($momentum['change'] ?? 0) != 0)
                    <p class="text-xs mt-2 {{ ($momentum['change'] ?? 0) > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        <i class="fa-solid {{ ($momentum['change'] ?? 0) > 0 ? 'fa-arrow-up' : 'fa-arrow-down' }} mr-1"></i>
                        {{ abs((int)($momentum['change'] ?? 0)) }} points {{ ($momentum['change'] ?? 0) > 0 ? 'better' : 'lower' }} than {{ $comparison['previous_month'] ?? 'last month' }}
                    </p>
                @endif
            </div>
        </div>
    </section>

    <section>
        <div class="flex items-center justify-between mb-3">
            <div><h2 class="font-bold text-slate-900">Compared with {{ $comparison['previous_month'] ?? 'last month' }}</h2><p class="text-xs text-slate-500">Small changes are easier to act on than a long report.</p></div>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @php
                $incomeDelta = $deltaMeta($comparison['income_change_percent'] ?? null);
                $expenseDelta = $deltaMeta($comparison['expense_change_percent'] ?? null, true);
                $savingDelta = $deltaMeta($comparison['saving_change_percent'] ?? null);
                $taskDelta = $deltaMeta($comparison['task_completion_change_points'] ?? null, false, ' pts');
            @endphp
            @foreach ([
                ['Income', $incomeDelta, 'fa-wallet'],
                ['Spending', $expenseDelta, 'fa-receipt'],
                ['Savings', $savingDelta, 'fa-piggy-bank'],
                ['Task completion', $taskDelta, 'fa-list-check'],
            ] as [$label, $meta, $icon])
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-400"><i class="fa-solid {{ $icon }}"></i>{{ $label }}</div>
                    <div class="mt-2 font-bold {{ $meta[1] }}"><i class="fa-solid {{ $meta[2] }} mr-1"></i>{{ $meta[0] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="grid lg:grid-cols-3 gap-4">
        <section class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
            <div class="flex items-center justify-between mb-4"><h2 class="font-bold text-slate-900">Productivity</h2><span class="text-2xl font-black text-[var(--brand-1)]">{{ $productivity['completion_percent'] ?? 0 }}%</span></div>
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full bg-[var(--brand-1)]" style="width: {{ min(100, $productivity['completion_percent'] ?? 0) }}%"></div></div>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-400">Tasks</dt><dd class="font-semibold">{{ $productivity['completed_tasks'] ?? 0 }}/{{ $productivity['total_tasks'] ?? 0 }}</dd></div>
                <div><dt class="text-slate-400">Meetings</dt><dd class="font-semibold">{{ $productivity['meetings'] ?? 0 }}</dd></div>
                <div><dt class="text-slate-400">Plans completed</dt><dd class="font-semibold">{{ $productivity['plans_completed'] ?? 0 }}</dd></div>
                <div><dt class="text-slate-400">Savings rate</dt><dd class="font-semibold">{{ number_format($money['savings_rate'] ?? 0, 1) }}%</dd></div>
            </dl>
        </section>
        <section class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
            <h2 class="font-bold text-slate-900 mb-4">Wellbeing</h2>
            <div class="space-y-3">
                <div class="flex items-center justify-between rounded-xl bg-emerald-50 p-3"><span class="text-sm text-emerald-800"><i class="fa-solid fa-person-running mr-2"></i>Exercise sessions</span><strong>{{ $wellbeing['exercise_sessions'] ?? 0 }}</strong></div>
                <div class="flex items-center justify-between rounded-xl bg-violet-50 p-3"><span class="text-sm text-violet-800"><i class="fa-solid fa-hands-praying mr-2"></i>Spiritual entries</span><strong>{{ $wellbeing['spiritual_entries'] ?? 0 }}</strong></div>
            </div>
        </section>
        <section class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
            <h2 class="font-bold text-slate-900 mb-3">Your next 3 actions</h2>
            <ul class="space-y-3 text-sm text-slate-600">
                @foreach (($review['next_actions'] ?? $review['focus_next_month'] ?? []) as $item)
                    <li class="flex gap-2"><i class="fa-solid fa-arrow-right text-[var(--brand-1)] mt-1"></i><span>{{ $item }}</span></li>
                @endforeach
            </ul>
        </section>
    </div>

    <section class="rounded-2xl border border-sky-100 bg-sky-50/60 p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
            <div><h2 class="font-bold text-slate-900">What My Digital Diary helped you manage</h2><p class="text-xs text-slate-500">Your activity shows the value of keeping these parts of life connected.</p></div>
            <i class="fa-solid fa-sparkles text-sky-500"></i>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-sm">
            @foreach ([
                ['Tasks completed', $value['tasks_completed'] ?? 0],
                ['Financial records', $value['financial_records'] ?? 0],
                ['AI plans', $value['ai_plans'] ?? 0],
                ['Meetings', $value['meetings'] ?? 0],
                ['Notes created', $value['notes_created'] ?? 0],
            ] as [$label, $metric])
                <div class="rounded-xl bg-white border border-sky-100 p-3"><p class="text-xs text-slate-400">{{ $label }}</p><p class="text-xl font-black text-slate-800 mt-1">{{ $metric }}</p></div>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl border border-amber-100 bg-amber-50/60 p-5">
        <h2 class="font-bold text-slate-900 mb-3"><i class="fa-solid fa-trophy text-amber-500 mr-2"></i>Wins worth keeping</h2>
        <div class="grid md:grid-cols-2 gap-3">
            @foreach (($review['wins'] ?? []) as $item)
                <div class="rounded-xl bg-white/80 border border-amber-100 p-3 text-sm text-slate-700">{{ $item }}</div>
            @endforeach
        </div>
    </section>
</div>
@endsection

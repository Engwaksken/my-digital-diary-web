@extends('layouts.app')

@section('title', 'Financial Planner')

@section('content')
    <div class="mb-4 rounded-xl border border-violet-100 bg-violet-50/60 px-4 py-3 flex items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-slate-800">Goals aligned to this area</p>
            <p class="text-xs text-slate-500 mt-0.5">Connect your plans and daily actions to a clear outcome.</p>
        </div>
        <a href="{{ route('personal-goals.index', ['module' => 'finance']) }}" class="shrink-0 inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white border border-violet-200 text-violet-700 text-sm font-semibold hover:bg-violet-100">
            <i class="fa-solid fa-bullseye"></i> Goals
        </a>
    </div>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                <i class="fa-solid fa-chart-line mr-2" style="color:var(--brand-1)"></i>Financial Planner
            </h1>
            <p class="mt-1 text-sm text-slate-500">Retirement planning linked to your actual income, expenses, budgets, savings and debts.</p>
        </div>

        <form method="GET" action="{{ route('financial-planner.index') }}" class="pm-card-bg border border-slate-200 rounded-xl p-4 shadow-sm">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                <label class="block text-sm font-medium text-slate-700">
                    From
                    <input type="date" name="start_date" value="{{ $start->toDateString() }}" class="pm-input mt-1">
                </label>
                <label class="block text-sm font-medium text-slate-700">
                    To
                    <input type="date" name="end_date" value="{{ $end->toDateString() }}" class="pm-input mt-1">
                </label>
                <button type="submit" class="btn-primary inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm">
                    <i class="fa-solid fa-filter"></i>
                    Project period
                </button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        @foreach([
            ['Income', $metrics['income'], 'fa-money-bill-trend-up', '#047857', '#ecfdf5'],
            ['Expenses', $metrics['expenses'], 'fa-receipt', '#be123c', '#fff1f2'],
            ['Savings', $metrics['saved'], 'fa-piggy-bank', '#6d28d9', '#f5f3ff'],
            ['Outstanding debt', $metrics['outstandingDebt'], 'fa-hand-holding-dollar', '#c2410c', '#fff7ed'],
            ['Monthly income', $metrics['monthlyIncome'], 'fa-calendar-plus', '#0f766e', '#f0fdfa'],
            ['Monthly expenses', $metrics['monthlyExpense'], 'fa-calendar-minus', '#e11d48', '#fff1f2'],
            ['Monthly budget', $metrics['monthlyBudget'], 'fa-wallet', '#1d4ed8', '#eff6ff'],
            ['Monthly surplus', $metrics['monthlySurplus'], 'fa-scale-balanced', '#0e7490', '#ecfeff'],
        ] as $card)
            <div class="pm-card-bg border border-slate-200 border-l-4 rounded-xl p-3 shadow-sm flex items-center gap-3 min-w-0" style="border-left-color:{{ $card[3] }}">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" style="background:{{ $card[4] }};color:{{ $card[3] }}">
                    <i class="fa-solid {{ $card[2] }}"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500 truncate">{{ $card[0] }}</div>
                    <div class="mt-0.5 font-bold text-base text-slate-900 truncate">{{ format_money($card[1]) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <section class="xl:col-span-2 pm-card-bg border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4">
                <h2 class="font-bold text-lg text-slate-900">Retirement projection</h2>
                <span class="text-xs rounded-full px-3 py-1 font-medium" style="background:var(--brand-1-tint-10);color:var(--brand-1-dark)">
                    {{ $metrics['years'] }} years to retirement
                </span>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm text-slate-500">Starting retirement + linked savings</div>
                    <div class="mt-1 text-xl font-bold text-slate-900">{{ format_money($metrics['starting']) }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm text-slate-500">Monthly retirement contribution</div>
                    <div class="mt-1 text-xl font-bold text-slate-900">{{ format_money($metrics['contribution']) }}</div>
                </div>
                <div class="rounded-xl p-4" style="background:var(--brand-1-tint-10)">
                    <div class="text-sm font-medium" style="color:var(--brand-1-dark)">Projected savings at retirement</div>
                    <div class="mt-1 text-xl font-bold" style="color:var(--brand-1-dark)">{{ format_money($metrics['future']) }}</div>
                </div>
                <div class="rounded-xl bg-amber-50 p-4 border border-amber-100">
                    <div class="text-sm text-amber-700">Estimated retirement target</div>
                    <div class="mt-1 text-xl font-bold text-amber-900">{{ format_money($metrics['target']) }}</div>
                </div>
            </div>

            <div class="mt-5">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-slate-600">Estimated funding progress</span>
                    <strong class="text-slate-900">{{ number_format($metrics['funding'], 1) }}%</strong>
                </div>
                <div class="h-3 bg-slate-200 rounded-full overflow-hidden">
                    <div class="h-full rounded-full" style="width:{{ min(100, $metrics['funding']) }}%;background:var(--brand-1)"></div>
                </div>
            </div>

            <div class="grid sm:grid-cols-3 gap-3 mt-5 text-sm">
                <div class="border border-slate-200 rounded-xl p-3">
                    <span class="text-slate-500">Savings goals target</span>
                    <div class="font-semibold text-slate-900">{{ format_money($metrics['goalTarget']) }}</div>
                </div>
                <div class="border border-slate-200 rounded-xl p-3">
                    <span class="text-slate-500">Avg. monthly savings</span>
                    <div class="font-semibold text-slate-900">{{ format_money($metrics['monthlySaved']) }}</div>
                </div>
                <div class="border border-slate-200 rounded-xl p-3">
                    <span class="text-slate-500">Inflation-adjusted monthly need</span>
                    <div class="font-semibold text-slate-900">{{ format_money($metrics['inflated']) }}</div>
                </div>
            </div>

            <p class="mt-4 text-xs text-slate-500">
                <i class="fa-solid fa-circle-info mr-1" style="color:var(--brand-1)"></i>
                This is an estimate based on your selected records and assumptions. Investment returns, inflation and future spending can differ.
            </p>
        </section>

        <section class="pm-card-bg border border-slate-200 rounded-2xl p-5 shadow-sm">
            <h2 class="font-bold text-lg text-slate-900 mb-1">Retirement assumptions</h2>
            <p class="text-xs text-slate-500 mb-4">Update the assumptions below and recalculate your projection.</p>

            <form method="POST" action="{{ route('financial-planner.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                @foreach([
                    ['current_age', 'Current age', 'number'],
                    ['retirement_age', 'Retirement age', 'number'],
                    ['current_retirement_savings', 'Retirement savings already set aside', 'number'],
                    ['monthly_retirement_contribution', 'Monthly contribution (blank = use linked data)', 'number'],
                    ['expected_annual_return', 'Expected annual return %', 'number'],
                    ['inflation_rate', 'Inflation %', 'number'],
                    ['desired_monthly_retirement_income', 'Desired monthly retirement income', 'number'],
                    ['retirement_years', 'Years in retirement', 'number'],
                ] as $field)
                    <div>
                        <label for="fp-{{ $field[0] }}" class="block text-sm font-medium text-slate-700">{{ $field[1] }}</label>
                        <input
                            id="fp-{{ $field[0] }}"
                            type="{{ $field[2] }}"
                            step="any"
                            name="{{ $field[0] }}"
                            value="{{ old($field[0], $profile->{$field[0]}) }}"
                            class="pm-input mt-1 @error($field[0]) border-rose-500 @enderror"
                        >
                        @error($field[0])
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <button type="submit" class="btn-primary w-full inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 py-2.5 font-semibold text-white shadow-sm">
                    <i class="fa-solid fa-calculator"></i>
                    Recalculate retirement plan
                </button>
            </form>
        </section>
    </div>
</div>
@endsection

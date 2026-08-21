@extends('layouts.app')
@section('title', 'Make My Digital Diary Yours')
@section('content')
@php
    $selected = old('onboarding_focuses', $user->onboarding_focuses ?? []);
    $choices = [
        'money' => ['Manage my money','Income, spending, budgets, savings and debts','fa-wallet','#ecfdf5','#047857'],
        'day' => ['Organise my day','Tasks, reminders, meetings and daily priorities','fa-calendar-check','#eff6ff','#1d4ed8'],
        'goals' => ['Reach my goals','Annual plans, projects and progress','fa-bullseye','#f5f3ff','#6d28d9'],
        'health' => ['Improve my health','Exercise, diet, sleep and check-ups','fa-heart-pulse','#fff1f2','#be123c'],
        'work' => ['Manage work or business','Meetings, projects, contacts and business card','fa-briefcase','#f0f9ff','#0369a1'],
        'growth' => ['Personal growth','Notes, learning, reflection and spiritual growth','fa-seedling','#fdf4ff','#a21caf'],
        'everything' => ['Everything','Show me the full personal operating system','fa-grid-2','#fffbeb','#b45309'],
    ];
@endphp
<div class="max-w-4xl mx-auto">
    <div class="text-center mb-7">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-[var(--brand-1-tint-10)] text-[var(--brand-1)] flex items-center justify-center text-xl mb-3"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <p class="text-xs font-semibold uppercase tracking-widest text-[var(--brand-1)]">A quick start</p>
        <h1 class="text-3xl font-bold text-slate-900 mt-1">What would you like help with most?</h1>
        <p class="text-slate-500 mt-2 max-w-2xl mx-auto">Choose one or more areas. We’ll use this to prioritise your dashboard and guidance. You can change it later in Profile → Personalisation & AI.</p>
    </div>

    <form method="POST" action="{{ route('onboarding.store') }}">@csrf
        <div class="grid sm:grid-cols-2 gap-3">
            @foreach($choices as $key => [$title,$desc,$icon,$bg,$fg])
                <label class="cursor-pointer group">
                    <input type="checkbox" name="onboarding_focuses[]" value="{{ $key }}" class="peer sr-only" @checked(in_array($key,$selected))>
                    <div class="h-full rounded-2xl border-2 border-slate-100 bg-white p-4 shadow-sm transition peer-checked:border-[var(--brand-1)] peer-checked:ring-2 peer-checked:ring-[var(--brand-1-tint-10)] group-hover:-translate-y-0.5">
                        <div class="flex items-start gap-3">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0" style="background:{{ $bg }};color:{{ $fg }}"><i class="fa-solid {{ $icon }}"></i></div>
                            <div><div class="font-bold text-slate-900">{{ $title }}</div><div class="text-sm text-slate-500 mt-1">{{ $desc }}</div></div>
                        </div>
                    </div>
                </label>
            @endforeach
        </div>
        @error('onboarding_focuses')<p class="text-sm text-rose-600 mt-3">{{ $message }}</p>@enderror

        <div class="mt-6 rounded-2xl bg-slate-50 border border-slate-100 p-4">
            <div class="text-sm font-semibold text-slate-700 mb-2">Your first 10 minutes</div>
            <div class="grid sm:grid-cols-4 gap-2 text-xs text-slate-600">
                <div class="rounded-xl bg-white p-3"><strong class="block text-slate-800">1. Plan today</strong>Create your first Daily Planner item.</div>
                <div class="rounded-xl bg-white p-3"><strong class="block text-slate-800">2. Add money</strong>Record income or an expense.</div>
                <div class="rounded-xl bg-white p-3"><strong class="block text-slate-800">3. Set a goal</strong>Add a plan or savings goal.</div>
                <div class="rounded-xl bg-white p-3"><strong class="block text-slate-800">4. Ask AI</strong>Get a plan using the data you allow.</div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 mt-6">
            <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-700">Skip for now</a>
            <button class="btn-primary text-white px-6 py-3 rounded-xl font-semibold">Personalise my diary <i class="fa-solid fa-arrow-right ml-1"></i></button>
        </div>
    </form>
</div>
@endsection

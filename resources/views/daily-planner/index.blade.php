@extends('layouts.app')

@section('content')
@php
    $isToday = $date->isToday();
    $isPast = $date->lt(today());
    $isFuture = $date->gt(today());
    $tomorrowDate = $date->copy()->addDay()->toDateString();

    $formatTime = static function ($time) {
        if (!$time) return null;
        try {
            return \Carbon\Carbon::createFromFormat('H:i:s', $time)->format('g:i A');
        } catch (\Throwable $e) {
            try {
                return \Carbon\Carbon::createFromFormat('H:i', substr((string) $time, 0, 5))->format('g:i A');
            } catch (\Throwable $e2) {
                return substr((string) $time, 0, 5);
            }
        }
    };
@endphp

<style>
    .dp-btn-primary {
        background: var(--brand-1);
        color: #fff;
        transition: .2s ease;
    }
    .dp-btn-primary:hover { background: var(--brand-2); }
    .dp-primary-text { color: var(--brand-1); }
    .dp-primary-bg-soft { background: var(--brand-1-tint-10); }
    .dp-primary-border { border-color: var(--brand-1-tint-20); }
    .dp-progress-bar { background: var(--brand-1); }
    .dp-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 80;
        background: rgba(15, 23, 42, .55);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .dp-modal-backdrop.is-open { display: flex; }
    .dp-modal-panel {
        width: 100%;
        max-width: 640px;
        max-height: calc(100vh - 2rem);
        overflow-y: auto;
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 24px 70px rgba(15,23,42,.22);
    }
    .dp-stat-card {
        --dp-stat-accent: var(--brand-1);
        --dp-stat-soft: var(--brand-1-tint-10);
        background: #fff;
        border: 1px solid #e2e8f0;
        border-left: 4px solid var(--dp-stat-accent);
        border-radius: .85rem;
        padding: .8rem .9rem;
        display: flex;
        align-items: center;
        gap: .75rem;
        box-shadow: 0 3px 10px rgba(15,23,42,.04);
    }
    .dp-stat-icon {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: .65rem;
        background: var(--dp-stat-soft);
        color: var(--dp-stat-accent);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }
    .dp-time-badge {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .3rem .55rem;
        border-radius: .6rem;
        background: var(--brand-1-tint-10);
        color: var(--brand-1);
        font-weight: 700;
        white-space: nowrap;
    }
    .dp-timeline-row td { vertical-align: top; }

    .dp-tabs {
        display: flex;
        gap: .35rem;
        padding: .35rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: .85rem;
        width: fit-content;
        max-width: 100%;
        overflow-x: auto;
    }
    .dp-tab-button {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .65rem 1rem;
        border-radius: .65rem;
        color: #64748b;
        font-size: .875rem;
        font-weight: 700;
        white-space: nowrap;
        border: 0;
        background: transparent;
        cursor: pointer;
        transition: .2s ease;
    }
    .dp-tab-button:hover { color: var(--brand-1); }
    .dp-tab-button.is-active {
        background: #fff;
        color: var(--brand-1);
        box-shadow: 0 1px 4px rgba(15, 23, 42, .08);
    }
    .dp-tab-panel { display: none; }
    .dp-tab-panel.is-active { display: block; }
    .dp-filter-card {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 1.25rem;
    }
</style>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-5">

    {{-- Header / date navigation --}}
    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                <i class="fa-solid fa-calendar-check mr-2 dp-primary-text"></i>Daily Planner
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Plan tasks by time, track progress, and review previous days whenever you need them.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('daily-planner.index', ['date' => $date->copy()->subDay()->toDateString()]) }}"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border bg-white text-sm hover:bg-slate-50">
                <i class="fa-solid fa-chevron-left"></i> Previous day
            </a>

            @unless($isToday)
                <a href="{{ route('daily-planner.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border bg-white text-sm hover:bg-slate-50">
                    <i class="fa-solid fa-calendar-day"></i> Today
                </a>
            @endunless

            <form method="GET" class="m-0">
                <input type="date" name="date" value="{{ $date->toDateString() }}"
                       onchange="this.form.submit()"
                       class="pm-input rounded-lg text-sm">
            </form>

            <a href="{{ route('daily-planner.index', ['date' => $date->copy()->addDay()->toDateString()]) }}"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border bg-white text-sm hover:bg-slate-50">
                Next day <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>
    </div>

    {{-- Flash / validation --}}
    @if(session('success'))
        <div id="dpFlash" class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3">
            <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div id="dpError" class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3">
            <div class="font-semibold mb-1"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Please fix the following:</div>
            <ul class="list-disc pl-5 text-sm space-y-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Day summary --}}
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500 mb-1">
                    <span>{{ $date->format('l, d M Y') }}</span>
                    @if($isPast)
                        <span class="px-2 py-1 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold">Past plan</span>
                    @elseif($isToday)
                        <span class="px-2 py-1 rounded-full dp-primary-bg-soft dp-primary-text text-xs font-semibold">Today</span>
                    @else
                        <span class="px-2 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold">Upcoming</span>
                    @endif
                </div>
                <h2 class="text-xl font-bold text-slate-900">{{ $plan->title ?: 'My Daily Plan' }}</h2>
                @if($plan->notes)
                    <p class="text-sm text-slate-500 mt-1 max-w-3xl">{{ \Illuminate\Support\Str::limit($plan->notes, 160) }}</p>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="openDpModal('dayPlanModal')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border dp-primary-border dp-primary-text bg-white font-medium">
                    <i class="fa-solid fa-pen-to-square"></i> Save Day Plan
                </button>
                <button type="button" onclick="openDpModal('addTaskModal')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg dp-btn-primary font-medium shadow-sm">
                    <i class="fa-solid fa-plus"></i> Add Task
                </button>
            </div>
        </div>

        <div class="mt-4 h-3 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full dp-progress-bar transition-all" style="width: {{ $progress }}%"></div>
        </div>
        <div class="mt-2 text-xs text-slate-500">{{ $progress }}% completed</div>
    </div>

    {{-- Statistics --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="dp-stat-card" style="--dp-stat-accent:#0f766e;--dp-stat-soft:#f0fdfa">
            <div class="dp-stat-icon"><i class="fa-solid fa-list-check"></i></div>
            <div class="min-w-0"><div class="text-[11px] uppercase tracking-wide text-slate-500 truncate">Total Tasks</div><div class="text-xl font-bold text-slate-900">{{ $total }}</div></div>
        </div>
        <div class="dp-stat-card" style="--dp-stat-accent:#059669;--dp-stat-soft:#ecfdf5">
            <div class="dp-stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="min-w-0"><div class="text-[11px] uppercase tracking-wide text-slate-500 truncate">Completed</div><div class="text-xl font-bold text-slate-900">{{ $done }}</div></div>
        </div>
        <div class="dp-stat-card" style="--dp-stat-accent:#d97706;--dp-stat-soft:#fffbeb">
            <div class="dp-stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="min-w-0"><div class="text-[11px] uppercase tracking-wide text-slate-500 truncate">Pending</div><div class="text-xl font-bold text-slate-900">{{ $pending }}</div></div>
        </div>
        <div class="dp-stat-card" style="--dp-stat-accent:#2563eb;--dp-stat-soft:#eff6ff">
            <div class="dp-stat-icon"><i class="fa-solid fa-clock"></i></div>
            <div class="min-w-0"><div class="text-[11px] uppercase tracking-wide text-slate-500 truncate">Timed Tasks</div><div class="text-xl font-bold text-slate-900">{{ $scheduled }}</div></div>
        </div>
    </div>

    {{-- Planner tabs --}}
    <div class="space-y-4">
        <div class="dp-tabs" role="tablist" aria-label="Daily Planner sections">
            <button
                type="button"
                class="dp-tab-button {{ $activeTab === 'tasks' ? 'is-active' : '' }}"
                data-dp-tab="tasks"
                role="tab"
                aria-selected="{{ $activeTab === 'tasks' ? 'true' : 'false' }}"
            >
                <i class="fa-solid fa-list-check"></i>
                Tasks
                <span class="inline-flex items-center justify-center min-w-6 h-6 px-1.5 rounded-full dp-primary-bg-soft dp-primary-text text-xs">{{ $total }}</span>
            </button>
            <button
                type="button"
                class="dp-tab-button {{ $activeTab === 'history' ? 'is-active' : '' }}"
                data-dp-tab="history"
                role="tab"
                aria-selected="{{ $activeTab === 'history' ? 'true' : 'false' }}"
            >
                <i class="fa-solid fa-clock-rotate-left"></i>
                Past Tasks
                <span class="inline-flex items-center justify-center min-w-6 h-6 px-1.5 rounded-full bg-slate-100 text-slate-600 text-xs">{{ $pastPlans->total() }}</span>
            </button>
        </div>

        <section id="dp-tab-tasks" class="dp-tab-panel {{ $activeTab === 'tasks' ? 'is-active' : '' }}" role="tabpanel">
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="font-bold text-slate-900">
                    {{ $isToday ? "Today's Tasks" : 'Tasks for '.$date->format('d M Y') }}
                </h3>
                <p class="text-xs text-slate-500 mt-1">Timed tasks are shown first in the order they are scheduled.</p>
            </div>

            @if($total)
                <div class="flex flex-wrap items-center gap-2">
                    @if($pending)
                        <button type="button"
                                onclick="openBulkMoveModal('{{ $tomorrowDate }}', true)"
                                class="inline-flex items-center gap-2 text-sm dp-primary-text border dp-primary-border rounded-lg px-3 py-2 bg-white hover:bg-slate-50">
                            <i class="fa-solid fa-calendar-arrow-up"></i> Move selected
                        </button>
                        <button type="button"
                                onclick="moveAllPendingTomorrow()"
                                class="inline-flex items-center gap-2 text-sm text-amber-700 border border-amber-200 rounded-lg px-3 py-2 bg-amber-50 hover:bg-amber-100">
                            <i class="fa-solid fa-forward"></i> Move unfinished to tomorrow
                        </button>
                    @endif
                    <button type="submit" form="bulkDailyDelete"
                            class="inline-flex items-center gap-2 text-sm text-rose-700 border border-rose-200 rounded-lg px-3 py-2 bg-white hover:bg-rose-50"
                            data-confirm-click="Delete selected tasks? This action cannot be undone." data-confirm-title="Delete selected tasks?" data-confirm-text="Delete selected">
                        <i class="fa-solid fa-trash"></i> Delete selected
                    </button>
                </div>
            @endif
        </div>

        @if(!$total)
            <div class="text-center py-14 px-4 text-slate-500">
                <i class="fa-regular fa-calendar-check text-4xl mb-3 dp-primary-text"></i>
                <p class="font-medium text-slate-700">No tasks saved for this day.</p>
                <p class="text-sm mt-1">Use Add Task to build the day's schedule.</p>
            </div>
        @else
            <form id="bulkDailyDelete" method="POST" action="{{ route('daily-planner.items.bulk-destroy') }}">
                @csrf
                @method('DELETE')

                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[900px]">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-3 w-10"><input type="checkbox" id="dailySelectAll"></th>
                                <th class="px-3 py-3">Time</th>
                                <th class="px-3 py-3">Task</th>
                                <th class="px-3 py-3">Priority</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($plan->items as $item)
                                @php
                                    $start = $formatTime($item->start_time);
                                    $end = $formatTime($item->end_time);
                                @endphp
                                <tr class="dp-timeline-row border-t border-slate-100 {{ $item->is_completed ? 'bg-slate-50/70' : '' }}">
                                    <td class="px-4 py-4">
                                        <input class="daily-row" type="checkbox" name="ids[]" value="{{ $item->id }}" data-pending="{{ $item->is_completed ? '0' : '1' }}">
                                    </td>
                                    <td class="px-3 py-4 w-40">
                                        @if($start)
                                            <span class="dp-time-badge"><i class="fa-regular fa-clock"></i>{{ $start }}</span>
                                            @if($end)<div class="text-xs text-slate-400 mt-1 pl-1">to {{ $end }}</div>@endif
                                        @else
                                            <span class="text-xs text-slate-400 italic">Any time</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-4">
                                        <div class="font-semibold text-slate-800 {{ $item->is_completed ? 'line-through text-slate-400' : '' }}">
                                            {{ $item->title }}
                                        </div>
                                        @if($item->description)
                                            <div class="text-xs text-slate-500 mt-1 max-w-xl">{{ $item->description }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-4">
                                        @php
                                            $priorityClass = match($item->priority) {
                                                'high' => 'bg-rose-50 text-rose-700',
                                                'low' => 'bg-slate-100 text-slate-600',
                                                default => 'bg-amber-50 text-amber-700',
                                            };
                                        @endphp
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $priorityClass }}">{{ ucfirst($item->priority) }}</span>
                                    </td>
                                    <td class="px-3 py-4">
                                        @if($item->is_completed)
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700"><i class="fa-solid fa-circle-check"></i> Completed</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500"><i class="fa-regular fa-circle"></i> Pending</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-right whitespace-nowrap">
                                        @php
                                            $editTaskPayload = [
                                                'id' => $item->id,
                                                'title' => $item->title,
                                                'plan_date' => $item->plan->plan_date->toDateString(),
                                                'personal_goal_id' => $item->personal_goal_id,
                                                'description' => $item->description,
                                                'priority' => $item->priority,
                                                'start_time' => $item->start_time ? substr((string) $item->start_time, 0, 5) : '',
                                                'end_time' => $item->end_time ? substr((string) $item->end_time, 0, 5) : '',
                                                'action' => route('daily-planner.items.update', $item),
                                            ];
                                        @endphp
                                        <button type="button"
                                                class="px-2.5 py-2 rounded-lg border border-slate-200 dp-primary-text bg-white"
                                                title="Edit task"
                                                data-task='@json($editTaskPayload)'
                                                onclick="openEditTask(JSON.parse(this.dataset.task))">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>

                                        @if(!$item->is_completed)
                                            <button type="button"
                                                    onclick="openMoveTaskModal({{ $item->id }}, @js($item->title), @js(route('daily-planner.items.move', $item)), @js($item->plan->plan_date->copy()->addDay()->toDateString()))"
                                                    class="px-2.5 py-2 rounded-lg border border-amber-200 text-amber-700 bg-white"
                                                    title="Move task to another date">
                                                <i class="fa-solid fa-calendar-days"></i>
                                            </button>
                                        @endif

                                        <button type="submit" form="toggle-{{ $item->id }}"
                                                class="px-2.5 py-2 rounded-lg border border-slate-200 bg-white"
                                                title="{{ $item->is_completed ? 'Reopen task' : 'Mark complete' }}">
                                            <i class="fa-solid {{ $item->is_completed ? 'fa-rotate-left' : 'fa-check' }}"></i>
                                        </button>

                                        <button type="submit" form="delete-{{ $item->id }}"
                                                data-confirm-click="Delete this task? This action cannot be undone." data-confirm-title="Delete task?" data-confirm-text="Delete"
                                                class="px-2.5 py-2 rounded-lg border border-rose-200 text-rose-700 bg-white"
                                                title="Delete task">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            @foreach($plan->items as $item)
                <form id="toggle-{{ $item->id }}" method="POST" action="{{ route('daily-planner.items.toggle', $item) }}">
                    @csrf
                    @method('PATCH')
                </form>
                <form id="delete-{{ $item->id }}" method="POST" action="{{ route('daily-planner.items.destroy', $item) }}">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        @endif
    </div>

        </section>

        <section id="dp-tab-history" class="dp-tab-panel {{ $activeTab === 'history' ? 'is-active' : '' }}" role="tabpanel">
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-slate-900">
                            <i class="fa-solid fa-clock-rotate-left mr-2 dp-primary-text"></i>Past Tasks & Day Plans
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Search previous plans or tasks, filter by period, and reopen any saved day.
                        </p>
                    </div>
                    <div class="text-xs text-slate-500">
                        @if($pastPlans->total())
                            Showing {{ $pastPlans->firstItem() }}-{{ $pastPlans->lastItem() }} of {{ $pastPlans->total() }} saved plans
                        @else
                            0 saved plans found
                        @endif
                    </div>
                </div>

                <form method="GET" action="{{ route('daily-planner.index') }}" class="dp-filter-card">
                    <input type="hidden" name="tab" value="history">
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">

                    <div class="grid md:grid-cols-2 xl:grid-cols-5 gap-3 items-end">
                        <label class="block xl:col-span-2">
                            <span class="block text-xs font-semibold text-slate-600 mb-1">Search past tasks</span>
                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input
                                    type="search"
                                    name="history_search"
                                    value="{{ $historySearch }}"
                                    class="pm-input w-full pl-9"
                                    placeholder="Search plan title, notes or task..."
                                >
                            </div>
                        </label>

                        <label class="block">
                            <span class="block text-xs font-semibold text-slate-600 mb-1">Period</span>
                            <select name="history_period" id="historyPeriod" class="pm-input w-full" onchange="toggleHistoryCustomRange()">
                                <option value="all" @selected($historyPeriod === 'all')>All past dates</option>
                                <option value="last_7_days" @selected($historyPeriod === 'last_7_days')>Last 7 days</option>
                                <option value="last_30_days" @selected($historyPeriod === 'last_30_days')>Last 30 days</option>
                                <option value="last_90_days" @selected($historyPeriod === 'last_90_days')>Last 90 days</option>
                                <option value="this_month" @selected($historyPeriod === 'this_month')>This month</option>
                                <option value="last_month" @selected($historyPeriod === 'last_month')>Last month</option>
                                <option value="custom" @selected($historyPeriod === 'custom')>Custom range</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="block text-xs font-semibold text-slate-600 mb-1">Records per page</span>
                            <select name="history_per_page" class="pm-input w-full">
                                @foreach([10, 25, 50, 100] as $size)
                                    <option value="{{ $size }}" @selected($historyPerPage === $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="flex gap-2">
                            <button type="submit" class="dp-btn-primary rounded-lg px-4 py-2.5 font-semibold inline-flex items-center gap-2">
                                <i class="fa-solid fa-filter"></i> Filter
                            </button>
                            <a
                                href="{{ route('daily-planner.index', ['date' => $date->toDateString(), 'tab' => 'history']) }}"
                                class="rounded-lg px-4 py-2.5 border bg-white text-slate-600 font-semibold inline-flex items-center gap-2"
                                title="Clear filters"
                            >
                                <i class="fa-solid fa-rotate-left"></i>
                                <span class="hidden sm:inline">Reset</span>
                            </a>
                        </div>
                    </div>

                    <div id="historyCustomRange" class="grid sm:grid-cols-2 gap-3 mt-3 {{ $historyPeriod === 'custom' ? '' : 'hidden' }}">
                        <label class="block">
                            <span class="block text-xs font-semibold text-slate-600 mb-1">From date</span>
                            <input type="date" name="history_from" value="{{ $historyFrom }}" class="pm-input w-full" max="{{ today()->subDay()->toDateString() }}">
                        </label>
                        <label class="block">
                            <span class="block text-xs font-semibold text-slate-600 mb-1">To date</span>
                            <input type="date" name="history_to" value="{{ $historyTo }}" class="pm-input w-full" max="{{ today()->subDay()->toDateString() }}">
                        </label>
                    </div>
                </form>

                @if($pastPlans->count())
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm min-w-[760px]">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-3 text-left">Date</th>
                                    <th class="px-3 py-3 text-left">Plan</th>
                                    <th class="px-3 py-3 text-center">Tasks</th>
                                    <th class="px-3 py-3 text-center">Completed</th>
                                    <th class="px-3 py-3 text-center">Pending</th>
                                    <th class="px-3 py-3 text-center">Progress</th>
                                    <th class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pastPlans as $past)
                                    @php
                                        $pastPercent = $past->items_count
                                            ? (int) round(($past->completed_items_count / $past->items_count) * 100)
                                            : 0;
                                        $pastPending = max(0, $past->items_count - $past->completed_items_count);
                                    @endphp
                                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                                        <td class="px-5 py-3 font-medium text-slate-700 whitespace-nowrap">
                                            {{ $past->plan_date->format('D, d M Y') }}
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="font-medium text-slate-700">{{ $past->title ?: 'My Daily Plan' }}</div>
                                            @if($past->notes)
                                                <div class="text-xs text-slate-400 mt-1" title="{{ $past->notes }}">
                                                    {{ \Illuminate\Support\Str::limit($past->notes, 80) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-center font-semibold">{{ $past->items_count }}</td>
                                        <td class="px-3 py-3 text-center text-emerald-700 font-semibold">{{ $past->completed_items_count }}</td>
                                        <td class="px-3 py-3 text-center text-amber-600 font-semibold">{{ $pastPending }}</td>
                                        <td class="px-3 py-3 text-center">
                                            <span class="font-semibold dp-primary-text">{{ $pastPercent }}%</span>
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <a
                                                href="{{ route('daily-planner.index', ['date' => $past->plan_date->toDateString(), 'tab' => 'tasks']) }}"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border dp-primary-border dp-primary-text bg-white font-medium"
                                            >
                                                <i class="fa-regular fa-eye"></i> View Tasks
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($pastPlans->hasPages())
                        <div class="px-5 py-4 border-t border-slate-100">
                            {{ $pastPlans->links() }}
                        </div>
                    @endif
                @else
                    <div class="px-5 py-12 text-center text-slate-500">
                        <i class="fa-solid fa-magnifying-glass text-3xl mb-3 text-slate-300"></i>
                        <p class="font-medium text-slate-700">No past tasks found.</p>
                        <p class="text-sm mt-1">Try changing the search text or selected period.</p>
                    </div>
                @endif
            </div>
        </section>
    </div>

</div>

{{-- Add Task Modal --}}
<div id="addTaskModal" class="dp-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="addTaskTitle">
    <div class="dp-modal-panel">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <div>
                <h3 id="addTaskTitle" class="font-bold text-lg">Add Task</h3>
                <p class="text-xs text-slate-500">{{ $date->format('l, d M Y') }}</p>
            </div>
            <button type="button" class="p-2 text-slate-500" onclick="closeDpModal('addTaskModal')"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="POST" action="{{ route('daily-planner.items.store') }}" class="p-5 space-y-4">
            @csrf
            <input type="hidden" name="plan_date" value="{{ $date->toDateString() }}">

            <label class="block text-sm font-medium">Task <span class="text-rose-600">*</span>
                <input required name="title" value="{{ old('title') }}" class="pm-input mt-1 w-full" placeholder="What should I do?">
            </label>

            <label class="block text-sm font-medium">Description
                <textarea name="description" rows="3" class="pm-input mt-1 w-full" placeholder="Optional details">{{ old('description') }}</textarea>
            </label>

            <label class="block text-sm font-medium">Linked Goal <span class="text-slate-400 font-normal">(optional)</span>
                <select name="personal_goal_id" class="pm-input mt-1 w-full">
                    <option value="">No linked goal</option>
                    @foreach(($goalOptions ?? collect()) as $goalId => $goalTitle)<option value="{{ $goalId }}">{{ $goalTitle }}</option>@endforeach
                </select>
            </label>

            <div class="grid md:grid-cols-2 gap-3 mb-3"><div><label class="block text-sm font-medium text-slate-700 mb-1">Task Achievement</label><textarea name="achievements" rows="2" class="pm-input"></textarea></div><div><label class="block text-sm font-medium text-slate-700 mb-1">Task Challenge</label><textarea name="challenges" rows="2" class="pm-input"></textarea></div></div>
<div class="grid sm:grid-cols-3 gap-3">
                <label class="block text-sm font-medium">Priority
                    <select name="priority" class="pm-input mt-1 w-full">
                        <option value="high">High</option>
                        <option value="medium" selected>Medium</option>
                        <option value="low">Low</option>
                    </select>
                </label>
                <label class="block text-sm font-medium">Start Time
                    <input type="time" name="start_time" class="pm-input mt-1 w-full">
                </label>
                <label class="block text-sm font-medium">End Time
                    <input type="time" name="end_time" class="pm-input mt-1 w-full">
                </label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeDpModal('addTaskModal')" class="px-4 py-2.5 rounded-lg border bg-white">Cancel</button>
                <button type="submit" class="px-4 py-2.5 rounded-lg dp-btn-primary font-medium"><i class="fa-solid fa-plus mr-1"></i>Add Task</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Task Modal --}}
<div id="editTaskModal" class="dp-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="editTaskTitle">
    <div class="dp-modal-panel">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <h3 id="editTaskTitle" class="font-bold text-lg">Edit Task</h3>
            <button type="button" class="p-2 text-slate-500" onclick="closeDpModal('editTaskModal')"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form id="editTaskForm" method="POST" action="" class="p-5 space-y-4">
            @csrf
            @method('PUT')

            <label class="block text-sm font-medium">Task <span class="text-rose-600">*</span>
                <input id="editTaskName" required name="title" class="pm-input mt-1 w-full">
            </label>

            <label class="block text-sm font-medium">Description
                <textarea id="editTaskDescription" name="description" rows="3" class="pm-input mt-1 w-full"></textarea>
            </label>

            <label class="block text-sm font-medium">Task Date
                <input id="editTaskDate" type="date" name="plan_date" class="pm-input mt-1 w-full" required>
                <span class="block text-xs text-slate-500 mt-1">Change the date to move this pending task to another day.</span>
            </label>

            <label class="block text-sm font-medium">Linked Goal <span class="text-slate-400 font-normal">(optional)</span>
                <select id="editTaskGoal" name="personal_goal_id" class="pm-input mt-1 w-full">
                    <option value="">No linked goal</option>
                    @foreach(($goalOptions ?? collect()) as $goalId => $goalTitle)<option value="{{ $goalId }}">{{ $goalTitle }}</option>@endforeach
                </select>
            </label>

            <div class="grid sm:grid-cols-3 gap-3">
                <label class="block text-sm font-medium">Priority
                    <select id="editTaskPriority" name="priority" class="pm-input mt-1 w-full">
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </label>
                <label class="block text-sm font-medium">Start Time
                    <input id="editTaskStart" type="time" name="start_time" class="pm-input mt-1 w-full">
                </label>
                <label class="block text-sm font-medium">End Time
                    <input id="editTaskEnd" type="time" name="end_time" class="pm-input mt-1 w-full">
                </label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeDpModal('editTaskModal')" class="px-4 py-2.5 rounded-lg border bg-white">Cancel</button>
                <button type="submit" class="px-4 py-2.5 rounded-lg dp-btn-primary font-medium"><i class="fa-solid fa-floppy-disk mr-1"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- Move One Task Modal --}}
<div id="moveTaskModal" class="dp-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="moveTaskTitle">
    <div class="dp-modal-panel max-w-lg">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <div>
                <h3 id="moveTaskTitle" class="font-bold text-lg">Move Pending Task</h3>
                <p id="moveTaskName" class="text-xs text-slate-500 mt-1"></p>
            </div>
            <button type="button" class="p-2 text-slate-500" onclick="closeDpModal('moveTaskModal')"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form id="moveTaskForm" method="POST" action="" class="p-5 space-y-4">
            @csrf
            @method('PATCH')
            <label class="block text-sm font-medium">Move to date
                <input id="moveTaskDate" type="date" name="target_date" min="{{ today()->toDateString() }}" class="pm-input mt-1 w-full" required>
            </label>
            <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                The existing task will be rescheduled, not duplicated. Its times, priority and linked goal stay unchanged.
            </div>
            <div class="flex flex-wrap justify-end gap-2">
                <button id="moveTaskTomorrowButton" type="button" class="px-4 py-2.5 rounded-lg border border-amber-200 bg-amber-50 text-amber-700 font-medium">Move to Tomorrow</button>
                <button type="button" onclick="closeDpModal('moveTaskModal')" class="px-4 py-2.5 rounded-lg border bg-white">Cancel</button>
                <button type="submit" class="px-4 py-2.5 rounded-lg dp-btn-primary font-medium"><i class="fa-solid fa-calendar-check mr-1"></i>Move Task</button>
            </div>
        </form>
    </div>
</div>

{{-- Bulk Move Pending Tasks Modal --}}
<div id="bulkMoveTaskModal" class="dp-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="bulkMoveTaskTitle">
    <div class="dp-modal-panel max-w-lg">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <div>
                <h3 id="bulkMoveTaskTitle" class="font-bold text-lg">Move Pending Tasks</h3>
                <p id="bulkMoveCount" class="text-xs text-slate-500 mt-1"></p>
            </div>
            <button type="button" class="p-2 text-slate-500" onclick="closeDpModal('bulkMoveTaskModal')"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form id="bulkMoveTaskForm" method="POST" action="{{ route('daily-planner.items.bulk-move') }}" class="p-5 space-y-4">
            @csrf
            @method('PATCH')
            <div id="bulkMoveIds"></div>
            <label class="block text-sm font-medium">Move selected pending tasks to
                <input id="bulkMoveDate" type="date" name="target_date" min="{{ today()->toDateString() }}" class="pm-input mt-1 w-full" required>
            </label>
            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-sm text-slate-600">
                Completed tasks are left on their original date to preserve your history.
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeDpModal('bulkMoveTaskModal')" class="px-4 py-2.5 rounded-lg border bg-white">Cancel</button>
                <button id="bulkMoveSubmit" type="submit" class="px-4 py-2.5 rounded-lg dp-btn-primary font-medium"><i class="fa-solid fa-forward mr-1"></i>Move Tasks</button>
            </div>
        </form>
    </div>
</div>

<form id="moveAllPendingTomorrowForm" method="POST" action="{{ route('daily-planner.items.bulk-move') }}" class="hidden">
    @csrf
    @method('PATCH')
    <input type="hidden" name="target_date" value="{{ $tomorrowDate }}">
    @foreach($plan->items->where('is_completed', false) as $pendingItem)
        <input type="hidden" name="ids[]" value="{{ $pendingItem->id }}">
    @endforeach
</form>

{{-- Save Day Plan Modal --}}
<div id="dayPlanModal" class="dp-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="dayPlanTitle">
    <div class="dp-modal-panel">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <div>
                <h3 id="dayPlanTitle" class="font-bold text-lg">Save Day Plan</h3>
                <p class="text-xs text-slate-500">{{ $date->format('l, d M Y') }}</p>
            </div>
            <button type="button" class="p-2 text-slate-500" onclick="closeDpModal('dayPlanModal')"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="POST" action="{{ route('daily-planner.update') }}" class="p-5 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="plan_date" value="{{ $date->toDateString() }}">

            <label class="block text-sm font-medium">Plan Title
                <input type="text" name="title" value="{{ old('title', $plan->title ?: 'My Daily Plan') }}" class="pm-input mt-1 w-full" required>
            </label>

            <label class="block text-sm font-medium">Day Notes
                <textarea name="notes" rows="5" class="pm-input mt-1 w-full" placeholder="Focus, reminders, reflections or anything important for this day...">{{ old('notes', $plan->notes) }}</textarea>
                        <div class="grid md:grid-cols-2 gap-4 mt-4">
                            <div><label class="block text-sm font-medium text-slate-700 mb-1">Achievements</label><textarea name="achievements" rows="3" class="pm-input" placeholder="What did you achieve today?">{{ old('achievements', $plan->achievements) }}</textarea></div>
                            <div><label class="block text-sm font-medium text-slate-700 mb-1">Challenges</label><textarea name="challenges" rows="3" class="pm-input" placeholder="What challenges did you face?">{{ old('challenges', $plan->challenges) }}</textarea></div>
                        </div>
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeDpModal('dayPlanModal')" class="px-4 py-2.5 rounded-lg border bg-white">Cancel</button>
                <button type="submit" class="px-4 py-2.5 rounded-lg dp-btn-primary font-medium"><i class="fa-solid fa-floppy-disk mr-1"></i>Save Day Plan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openDpModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeDpModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    function openEditTask(task) {
        document.getElementById('editTaskForm').action = task.action;
        document.getElementById('editTaskName').value = task.title || '';
        document.getElementById('editTaskDescription').value = task.description || '';
        document.getElementById('editTaskDate').value = task.plan_date || '{{ $date->toDateString() }}';
        document.getElementById('editTaskGoal').value = task.personal_goal_id ? String(task.personal_goal_id) : '';
        document.getElementById('editTaskPriority').value = task.priority || 'medium';
        document.getElementById('editTaskStart').value = task.start_time || '';
        document.getElementById('editTaskEnd').value = task.end_time || '';
        openDpModal('editTaskModal');
    }

    window.openMoveTaskModal = function(id, title, action, tomorrow) {
        document.getElementById('moveTaskForm').action = action;
        document.getElementById('moveTaskName').textContent = title || 'Pending task';
        document.getElementById('moveTaskDate').value = tomorrow;
        document.getElementById('moveTaskTomorrowButton').onclick = function() {
            document.getElementById('moveTaskDate').value = tomorrow;
            document.getElementById('moveTaskForm').requestSubmit();
        };
        openDpModal('moveTaskModal');
    };

    window.openBulkMoveModal = function(defaultDate, selectedOnly) {
        const boxes = [...document.querySelectorAll('.daily-row')].filter(box => box.checked && box.dataset.pending === '1');
        const submit = document.getElementById('bulkMoveSubmit');
        if (boxes.length === 0) {
            document.getElementById('bulkMoveCount').textContent = 'Select at least one pending task first.';
            document.getElementById('bulkMoveIds').innerHTML = '';
            submit.disabled = true;
            submit.classList.add('opacity-50', 'cursor-not-allowed');
            openDpModal('bulkMoveTaskModal');
            return;
        }
        submit.disabled = false;
        submit.classList.remove('opacity-50', 'cursor-not-allowed');
        const holder = document.getElementById('bulkMoveIds');
        holder.innerHTML = '';
        boxes.forEach(box => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = box.value;
            holder.appendChild(input);
        });
        document.getElementById('bulkMoveDate').value = defaultDate;
        document.getElementById('bulkMoveCount').textContent = `${boxes.length} pending task${boxes.length === 1 ? '' : 's'} selected`;
        openDpModal('bulkMoveTaskModal');
    };

    window.moveAllPendingTomorrow = function() {
        const pending = [...document.querySelectorAll('.daily-row')].filter(box => box.dataset.pending === '1');
        if (pending.length === 0) return;
        pending.forEach(box => { box.checked = true; });
        openBulkMoveModal('{{ $tomorrowDate }}', false);
    };

    document.querySelectorAll('[data-dp-tab]').forEach(button => {
        button.addEventListener('click', function () {
            const target = this.dataset.dpTab;

            document.querySelectorAll('[data-dp-tab]').forEach(tabButton => {
                const active = tabButton.dataset.dpTab === target;
                tabButton.classList.toggle('is-active', active);
                tabButton.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            document.querySelectorAll('.dp-tab-panel').forEach(panel => {
                panel.classList.toggle('is-active', panel.id === `dp-tab-${target}`);
            });
        });
    });

    function toggleHistoryCustomRange() {
        const period = document.getElementById('historyPeriod');
        const customRange = document.getElementById('historyCustomRange');
        if (!period || !customRange) return;
        customRange.classList.toggle('hidden', period.value !== 'custom');
    }

    document.getElementById('dailySelectAll')?.addEventListener('change', function () {
        document.querySelectorAll('.daily-row').forEach(cb => cb.checked = this.checked);
    });

    document.querySelectorAll('.dp-modal-backdrop').forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) closeDpModal(modal.id);
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dp-modal-backdrop.is-open').forEach(modal => closeDpModal(modal.id));
        }
    });

    setTimeout(() => {
        document.getElementById('dpFlash')?.remove();
        document.getElementById('dpError')?.remove();
    }, 5000);
</script>
@endsection

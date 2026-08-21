@extends('layouts.app')
@section('title', 'Goals & Next Actions')
@section('content')
@php($summary = $goalData['summary'] ?? [])
<div class="max-w-7xl mx-auto space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-slate-900">Goals & Next Actions</h1><p class="text-sm text-slate-500 mt-1">One view of the goals that need your attention most.</p></div>
        <a href="{{ route('annual-plans.index') }}" class="px-4 py-2 rounded-xl text-white text-sm font-semibold" style="background:var(--brand-1)">Manage plans</a>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach([
            ['Active',$summary['total_active'] ?? 0,'fa-bullseye','border-indigo-500'],
            ['On track',$summary['on_track'] ?? 0,'fa-circle-check','border-emerald-500'],
            ['Needs attention',$summary['needs_attention'] ?? 0,'fa-triangle-exclamation','border-amber-500'],
            ['Average progress',($summary['average_progress'] ?? 0).'%' ,'fa-chart-line','border-sky-500'],
        ] as $stat)
        <div class="bg-white rounded-2xl border border-slate-200 border-l-4 {{ $stat[3] }} p-4 shadow-sm"><i class="fa-solid {{ $stat[2] }} text-slate-500"></i><div class="text-xs text-slate-500 mt-2">{{ $stat[0] }}</div><div class="text-xl font-bold text-slate-900">{{ $stat[1] }}</div></div>
        @endforeach
    </div>

    @if(!empty($goalData['next_actions']))
    <section class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
        <h2 class="font-bold text-slate-900 mb-3">Recommended next actions</h2>
        <div class="grid md:grid-cols-3 gap-3">
        @foreach($goalData['next_actions'] as $action)
            <a href="{{ !empty($action['detail_route']) && !empty($action['id']) ? route($action['detail_route'], $action['id']) : route($action['route']) }}" class="rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                <div class="text-[11px] uppercase tracking-wide text-slate-400">{{ $action['type'] }}</div>
                <div class="font-semibold text-slate-900 mt-1">{{ $action['title'] }}</div>
                <div class="text-sm text-slate-600 mt-1">{{ $action['message'] }}</div>
            </a>
        @endforeach
        </div>
    </section>
    @endif

    <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="p-4 border-b border-slate-100 font-bold text-slate-900">Active goals</div>
        <div class="divide-y divide-slate-100">
            @forelse($goalData['goals'] as $goal)
            <a href="{{ !empty($goal['detail_route']) && !empty($goal['id']) ? route($goal['detail_route'], $goal['id']) : route($goal['route']) }}" class="block p-4 hover:bg-slate-50">
                <div class="flex items-start justify-between gap-3"><div><div class="text-xs text-slate-400">{{ $goal['type'] }}</div><div class="font-semibold text-slate-900">{{ $goal['title'] }}</div></div><span class="text-xs font-semibold {{ $goal['state']==='overdue' ? 'text-rose-600' : ($goal['state']==='needs_attention' ? 'text-amber-600' : 'text-emerald-600') }}">{{ str_replace('_',' ',ucfirst($goal['state'])) }}</span></div>
                <div class="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full" style="width:{{ $goal['progress'] }}%;background:var(--brand-1)"></div></div>
                <div class="mt-2 flex justify-between text-xs text-slate-500"><span>{{ $goal['progress'] }}% complete</span><span>{{ $goal['due_date'] ? 'Due '.\Illuminate\Support\Carbon::parse($goal['due_date'])->format('d M Y') : 'No deadline' }}</span></div>
                @if(!empty($goal['execution']))
                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                        <span class="px-2 py-1 rounded-full bg-violet-50 text-violet-700">{{ $goal['execution']['plans'] ?? 0 }} linked plan(s)</span>
                        <span class="px-2 py-1 rounded-full bg-emerald-50 text-emerald-700">{{ $goal['execution']['completed_tasks'] ?? 0 }}/{{ $goal['execution']['tasks'] ?? 0 }} linked tasks done</span>
                        @if(($goal['execution']['milestones'] ?? 0) > 0)<span class="px-2 py-1 rounded-full bg-amber-50 text-amber-700">{{ $goal['execution']['completed_milestones'] ?? 0 }}/{{ $goal['execution']['milestones'] ?? 0 }} milestones</span>@endif
                    </div>
                @endif
                @if(!empty($goal['week_movement']))<div class="mt-2 text-xs text-slate-500"><i class="fa-solid fa-arrow-trend-up mr-1 text-emerald-500"></i>{{ $goal['week_movement']['message'] }}</div>@endif
            </a>
            @empty
            <div class="p-8 text-center text-slate-500">No active goals yet. Start with an Annual Plan, Savings Goal or Project.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection

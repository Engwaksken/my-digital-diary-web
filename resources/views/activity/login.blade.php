@extends('layouts.app')

@section('title', 'Login Activity')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[var(--brand-1)] flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-shield-halved text-xl" aria-hidden="true"></i>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Login Activity</h1>
            <p class="text-sm text-slate-500 mt-0.5">Review successful sign-ins to your account.</p>
        </div>
    </div>

    <a href="{{ route('user-guide') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50">
        <i class="fa-solid fa-book-open text-[var(--brand-1)]"></i>
        User Guide
    </a>
</div>

<div class="border-b border-slate-200 mb-6 overflow-x-auto">
    <nav class="flex gap-6 min-w-max" aria-label="Activity sections">
        <a href="{{ route('activity') }}" class="inline-flex items-center gap-2 py-3 text-sm text-slate-500 hover:text-[var(--brand-1)] border-b-2 border-transparent">
            <i class="fa-solid fa-clock-rotate-left"></i> Activity Log
        </a>
        <a href="{{ route('login-activity.index') }}" class="inline-flex items-center gap-2 py-3 text-sm font-semibold text-[var(--brand-1)] border-b-2" style="border-color: var(--brand-1);">
            <i class="fa-solid fa-shield-halved"></i> Login Activity
        </a>
    </nav>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    @foreach ([
        ['label' => 'Total logins', 'value' => $stats['total'], 'icon' => 'fa-right-to-bracket'],
        ['label' => 'Today', 'value' => $stats['today'], 'icon' => 'fa-calendar-day'],
        ['label' => 'This month', 'value' => $stats['this_month'], 'icon' => 'fa-calendar'],
    ] as $stat)
        <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1">{{ $stat['value'] }}</p>
                </div>
                <span class="w-10 h-10 rounded-lg bg-emerald-50 text-[var(--brand-1)] flex items-center justify-center">
                    <i class="fa-solid {{ $stat['icon'] }}"></i>
                </span>
            </div>
        </div>
    @endforeach
</div>

<div class="pm-card-bg rounded-xl border border-slate-100 shadow-sm p-4 mb-5">
    <form method="GET" action="{{ route('login-activity.index') }}" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3 items-end">
        <div class="xl:col-span-2">
            <label for="login-q" class="block text-xs font-medium text-slate-600 mb-1">Search</label>
            <input id="login-q" type="search" name="q" value="{{ $search }}" placeholder="IP, browser or device..." class="pm-input text-sm">
        </div>

        <div>
            <label for="login-period" class="block text-xs font-medium text-slate-600 mb-1">Period</label>
            <select id="login-period" name="period" class="pm-input text-sm" onchange="toggleLoginRange(this.value)">
                <option value="" @selected(!$period)>All time</option>
                <option value="daily" @selected($period === 'daily')>Today</option>
                <option value="weekly" @selected($period === 'weekly')>This week</option>
                <option value="monthly" @selected($period === 'monthly')>This month</option>
                <option value="range" @selected($period === 'range')>Custom range</option>
            </select>
        </div>

        <div id="login-from-wrap" style="{{ $period === 'range' ? '' : 'display:none;' }}">
            <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
            <input type="date" name="from" value="{{ $from }}" class="pm-input text-sm">
        </div>

        <div id="login-to-wrap" style="{{ $period === 'range' ? '' : 'display:none;' }}">
            <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
            <input type="date" name="to" value="{{ $to }}" class="pm-input text-sm">
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Per page</label>
            <select name="per_page" class="pm-input text-sm">
                @foreach ([10,25,50,100] as $size)
                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2 xl:col-span-6">
            <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium">
                <i class="fa-solid fa-filter mr-1"></i> Filter
            </button>
            @if ($search || $period || $perPage !== 10)
                <a href="{{ route('login-activity.index') }}" class="px-4 py-2.5 rounded-lg text-sm border border-slate-200 text-slate-600 hover:bg-slate-50">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="pm-card-bg rounded-xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-4 py-3">Date & Time</th>
                    <th class="text-left px-4 py-3">Device / Browser</th>
                    <th class="text-left px-4 py-3">IP Address</th>
                    <th class="text-left px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($activity as $login)
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p class="font-medium text-slate-700">{{ optional($login->logged_in_at)->format('d M Y, h:i A') }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ optional($login->logged_in_at)->diffForHumans() }}</p>
                        </td>
                        <td class="px-4 py-4 min-w-[220px]">
                            <p class="font-medium text-slate-700">{{ $login->deviceLabel() }}</p>
                            <p class="text-xs text-slate-400 mt-1 max-w-md truncate" title="{{ $login->user_agent }}">{{ $login->user_agent ?: 'User agent unavailable' }}</p>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-slate-600">{{ $login->ip_address ?: 'Unavailable' }}</td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-1 text-xs font-medium">
                                <i class="fa-solid fa-circle-check"></i> Successful
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-shield-halved text-3xl mb-3 block"></i>
                            No login activity matches your filters yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($activity->total() > 0)
    <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p class="text-sm text-slate-500">
            Showing {{ $activity->firstItem() }} to {{ $activity->lastItem() }} of {{ $activity->total() }} logins
        </p>
        {{ $activity->links() }}
    </div>
@endif

<div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 flex gap-3 items-start">
    <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
    <p>If you see a sign-in you do not recognise, change your password and review your account information.</p>
</div>

<script>
    function toggleLoginRange(value) {
        var show = value === 'range';
        document.getElementById('login-from-wrap').style.display = show ? '' : 'none';
        document.getElementById('login-to-wrap').style.display = show ? '' : 'none';
    }
</script>
@endsection

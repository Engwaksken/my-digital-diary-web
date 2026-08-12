@extends('layouts.app')

@section('title', 'Statistics')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-chart-line text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Statistics</h1>
    </div>

    {{-- Stat cards are always visible — not tabbed — so they read like the
         at-a-glance summary they're meant to be, with Breakdown/Chart as
         tabs underneath for the more detailed views. --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <div class="pm-card-bg shadow-sm border border-slate-100 border-l-4 border-l-blue-400 rounded-xl p-5">
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-2">
                <i class="fa-solid fa-users text-sm" aria-hidden="true"></i>
            </div>
            <p class="text-sm text-slate-500">Total users</p>
            <p class="text-2xl font-bold">{{ $totalUsers }}</p>
        </div>
        <div class="pm-card-bg shadow-sm border border-slate-100 border-l-4 border-l-indigo-400 rounded-xl p-5">
            <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center mb-2">
                <i class="fa-solid fa-user-shield text-sm" aria-hidden="true"></i>
            </div>
            <p class="text-sm text-slate-500">Admins</p>
            <p class="text-2xl font-bold">{{ $adminCount }}</p>
        </div>
        <div class="pm-card-bg shadow-sm border border-slate-100 border-l-4 border-l-rose-400 rounded-xl p-5">
            <div class="w-9 h-9 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center mb-2">
                <i class="fa-solid fa-user-slash text-sm" aria-hidden="true"></i>
            </div>
            <p class="text-sm text-slate-500">Suspended</p>
            <p class="text-2xl font-bold {{ $suspendedCount > 0 ? 'text-rose-600' : '' }}">{{ $suspendedCount }}</p>
        </div>
        <div class="pm-card-bg shadow-sm border border-slate-100 border-l-4 border-l-emerald-400 rounded-xl p-5">
            <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2">
                <i class="fa-solid fa-sack-dollar text-sm" aria-hidden="true"></i>
            </div>
            <p class="text-sm text-slate-500">Est. monthly revenue</p>
            <p class="text-2xl font-bold text-emerald-600">{{ format_money($estimatedMonthlyRevenue) }}</p>
            <p class="text-xs text-slate-400 mt-1">Active subscribers &times; base monthly price</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="pm-card-bg shadow-sm border border-slate-100 border-l-4 border-l-teal-400 rounded-xl p-5">
            <div class="w-9 h-9 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center mb-2">
                <i class="fa-solid fa-money-bill-trend-up text-sm" aria-hidden="true"></i>
            </div>
            <p class="text-sm text-slate-500">Total collected</p>
            <p class="text-2xl font-bold text-emerald-600">{{ format_money($totalCollected) }}</p>
            <p class="text-xs text-slate-400 mt-1">Completed payments, all methods</p>
        </div>
        <div class="pm-card-bg shadow-sm border border-slate-100 border-l-4 border-l-amber-400 rounded-xl p-5">
            <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center mb-2">
                <i class="fa-solid fa-hourglass-half text-sm" aria-hidden="true"></i>
            </div>
            <p class="text-sm text-slate-500">Pending verification</p>
            <p class="text-2xl font-bold {{ $pendingPaymentsCount > 0 ? 'text-amber-600' : '' }}">{{ $pendingPaymentsCount }}</p>
            <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}" class="text-xs text-[var(--brand-1)] hover:underline">Review queue &rarr;</a>
        </div>
    </div>

    <div role="tablist" aria-label="Statistics detail sections" class="flex gap-1 border-b border-slate-200 mb-6">
        <button type="button" role="tab" id="pm-stats-tab-breakdown" aria-controls="pm-stats-panel-breakdown"
                aria-selected="true" tabindex="0" data-tab="breakdown"
                onclick="pmSelectStatsTab('breakdown')" onkeydown="pmStatsTabKeydown(event, 'breakdown')"
                class="pm-stats-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-[var(--brand-1)] text-[var(--brand-1)]">
            <i class="fa-solid fa-table-list" aria-hidden="true"></i>
            <span>Breakdown</span>
        </button>
        <button type="button" role="tab" id="pm-stats-tab-chart" aria-controls="pm-stats-panel-chart"
                aria-selected="false" tabindex="-1" data-tab="chart"
                onclick="pmSelectStatsTab('chart')" onkeydown="pmStatsTabKeydown(event, 'chart')"
                class="pm-stats-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
            <span>Signups Chart</span>
        </button>
    </div>

    <div role="tabpanel" id="pm-stats-panel-breakdown" aria-labelledby="pm-stats-tab-breakdown" tabindex="0" class="pm-stats-panel">
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 max-w-md">
            <h2 class="font-semibold mb-3">Subscription mix</h2>
            @if ($byStatus->isEmpty())
                <p class="text-sm text-slate-500">No users yet.</p>
            @else
                <table class="w-full text-sm">
                    <caption class="sr-only">User count by subscription status.</caption>
                    <thead class="text-left text-slate-500">
                        <tr><th scope="col" class="pb-1">Status</th><th scope="col" class="pb-1 text-right">Users</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($byStatus as $status => $count)
                            <tr>
                                <td class="py-1.5">{{ ucfirst($status) }}</td>
                                <td class="py-1.5 text-right font-medium">{{ $count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div role="tabpanel" id="pm-stats-panel-chart" aria-labelledby="pm-stats-tab-chart" tabindex="0" class="pm-stats-panel" hidden>
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
            <h2 class="font-semibold mb-3">Signups (last 30 days)</h2>
            {{-- Fixed height + maintainAspectRatio:false (in the JS below)
                 gives precise control over the rendered size. --}}
            <div class="h-48">
                <canvas id="signupChart" role="img"
                    aria-label="Line chart of daily signups over the last 30 days. Total signups in this period: {{ array_sum($signupCounts) }}. Peak day: {{ $signupLabels[array_search(max($signupCounts), $signupCounts)] ?? 'none' }} with {{ max($signupCounts + [0]) }} signups."
                ></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
        var pmSignupChartInstance = null;

        // Deferred until the Chart tab is shown — see the same pattern
        // (and the reasoning why) in crud/index.blade.php.
        function pmInitSignupChartIfNeeded() {
            if (pmSignupChartInstance) { return; }

            pmSignupChartInstance = new Chart(document.getElementById('signupChart'), {
                type: 'line',
                data: {
                    labels: @json($signupLabels),
                    datasets: [{
                        label: 'Signups',
                        data: @json($signupCounts),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.1)',
                        fill: true,
                        tension: 0.3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                    plugins: { legend: { display: false } },
                },
            });
        }

        function pmSelectStatsTab(key) {
            document.querySelectorAll('.pm-stats-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected);
                btn.classList.toggle('border-transparent', !isSelected);
                btn.classList.toggle('text-slate-500', !isSelected);
                if (isSelected) { btn.focus(); }
            });
            document.querySelectorAll('.pm-stats-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-stats-panel-' + key;
            });
            if (key === 'chart') { pmInitSignupChartIfNeeded(); }
        }

        function pmStatsTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-stats-tab'), function (t) { return t.dataset.tab; });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else if (event.key === 'Home') { nextIndex = 0; }
            else if (event.key === 'End') { nextIndex = tabs.length - 1; }
            else { return; }

            event.preventDefault();
            pmSelectStatsTab(tabs[nextIndex]);
        }
    </script>
@endsection

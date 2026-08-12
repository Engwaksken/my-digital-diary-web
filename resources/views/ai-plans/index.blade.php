@extends('layouts.app')

@section('title', 'AI Planner')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-robot text-xl" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">AI Planner</h1>
        </div>
        <form method="POST" action="{{ route('ai-plans.store') }}">
            @csrf
            <button type="submit" class="inline-flex items-center justify-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                <span>Generate New Plan</span>
            </button>
        </form>
    </div>

    @if ($plans->isEmpty() && !$search)
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 text-sm text-slate-500">
            No plans generated yet. Add an <a href="{{ route('api-credentials.index') }}" class="text-[var(--brand-1)] hover:underline">API key</a>
            first, then click "Generate New Plan" it looks across every module (plans, income, budget,
            expenses, diet, sleep, health checkups, projects, education, network, and relationships) and
            writes a short, prioritized action plan for the next 7 and 30 days.
        </div>
    @else
        {{-- Stats always visible, never tabbed same reasoning as
             everywhere else in the app: a quick-reference summary
             shouldn't be hidden behind a click. --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            @foreach ($stats as $stat)
                <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-{{ $stat['color'] }}-400 p-4">
                    <div class="w-9 h-9 rounded-lg bg-{{ $stat['color'] }}-50 text-{{ $stat['color'] }}-600 flex items-center justify-center mb-2">
                        <i class="{{ $stat['icon'] }} text-sm" aria-hidden="true"></i>
                    </div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide truncate">{{ $stat['label'] }}</p>
                    <p class="text-xl font-bold text-slate-800 truncate">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        @php $pmShowTabs = !empty($chart); @endphp

        @if ($pmShowTabs)
            <div role="tablist" aria-label="AI Planner sections" class="flex gap-1 border-b border-slate-200 mb-6">
                <button type="button" role="tab" id="pm-ai-tab-chart" aria-controls="pm-ai-panel-chart"
                        aria-selected="false" tabindex="-1" data-tab="chart"
                        onclick="pmSelectAiTab('chart')" onkeydown="pmAiTabKeydown(event, 'chart')"
                        class="pm-ai-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
                    <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                    <span>Chart</span>
                </button>
                <button type="button" role="tab" id="pm-ai-tab-table" aria-controls="pm-ai-panel-table"
                        aria-selected="true" tabindex="0" data-tab="table"
                        onclick="pmSelectAiTab('table')" onkeydown="pmAiTabKeydown(event, 'table')"
                        class="pm-ai-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-[var(--brand-1)] text-[var(--brand-1)]">
                    <i class="fa-solid fa-table-list" aria-hidden="true"></i>
                    <span>Plans</span>
                </button>
            </div>
        @endif

        @if (!empty($chart))
            <div role="tabpanel" id="pm-ai-panel-chart" aria-labelledby="pm-ai-tab-chart" tabindex="0" class="pm-ai-panel" hidden>
                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 mb-6">
                    <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-robot text-violet-500" aria-hidden="true"></i>
                        {{ $chart['title'] }}
                    </h2>
                    <div class="h-48">
                        <canvas id="ai-plans-chart" role="img" aria-label="{{ $chart['title'] }}"></canvas>
                    </div>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
            <script>
                var pmAiChartInstance = null;
                function pmInitAiChartIfNeeded() {
                    if (pmAiChartInstance) { return; }
                    pmAiChartInstance = new Chart(document.getElementById('ai-plans-chart'), {
                        type: 'bar',
                        data: {
                            labels: @json($chart['labels']),
                            datasets: [{
                                label: 'Plans',
                                data: @json($chart['datasets'][0]['data']),
                                backgroundColor: '#8b5cf6',
                                borderRadius: 4,
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
            </script>
        @endif

        <div role="tabpanel" id="pm-ai-panel-table" aria-labelledby="pm-ai-tab-table" tabindex="0" class="pm-ai-panel">
        <form method="GET" action="{{ route('ai-plans.index') }}" class="mb-4 max-w-sm">
            <label for="q" class="sr-only">Search your plans</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true"></i>
                <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Search plan content..."
                       class="pl-9 pm-input text-sm">
            </div>
        </form>

        @if ($plans->isEmpty())
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 text-sm text-slate-500">
                No plans match "{{ $search }}". <a href="{{ route('ai-plans.index') }}" class="text-[var(--brand-1)] hover:underline">Clear search</a>.
            </div>
        @else
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl overflow-x-auto" role="region" aria-label="AI plans table" tabindex="0">
                <table class="min-w-full text-sm">
                    <caption class="sr-only">Your generated AI plans, with view, download, and delete actions for each.</caption>
                    <thead class="bg-slate-50 text-left border-b border-slate-100">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Date</th>
                            <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Provider</th>
                            <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Preview</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($plans as $plan)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ $plan->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ \App\Models\AiProvider::where('key', $plan->provider)->value('name') ?? ($plan->provider ?? '—') }}
                                </td>
                                <td class="px-4 py-3 text-slate-700 max-w-md">
                                    {{ \Illuminate\Support\Str::limit(str_replace(["\n", "\r"], ' ', $plan->cleanContent()), 90) }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button type="button"
                                            onclick="pmOpenAiPlanViewModal({{ json_encode($plan->created_at->format('Y-m-d H:i')) }}, {{ json_encode($plan->cleanContent()) }})"
                                            class="inline-flex items-center gap-1 text-[var(--brand-1)] hover:text-[var(--brand-1-dark)] mr-3 transition-colors">
                                        <i class="fa-solid fa-eye text-xs" aria-hidden="true"></i>
                                        <span class="sr-only">View plan from </span>{{ $plan->created_at->format('M j') }}
                                    </button>
                                    <a href="{{ route('ai-plans.pdf', $plan->id) }}"
                                       class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-700 mr-3 transition-colors">
                                        <i class="fa-solid fa-file-pdf text-xs" aria-hidden="true"></i>
                                        <span class="sr-only">Download PDF of plan from {{ $plan->created_at->format('Y-m-d H:i') }}</span>
                                    </a>
                                    <button type="button"
                                            onclick="pmOpenAiPlanDeleteModal({{ json_encode(route('ai-plans.destroy', $plan->id)) }}, {{ json_encode($plan->created_at->format('Y-m-d H:i')) }})"
                                            class="inline-flex items-center gap-1 text-rose-500 hover:text-rose-700 transition-colors">
                                        <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                                        <span class="sr-only">Delete plan from {{ $plan->created_at->format('Y-m-d H:i') }}</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <nav aria-label="Pagination" class="mt-4">
                {{ $plans->links() }}
            </nav>
        @endif
        </div>
    @endif

    {{-- View full plan modal read-only. --}}
    <dialog id="ai-plan-view-modal" aria-labelledby="ai-plan-view-title" class="rounded-2xl p-0 pm-dialog-xl shadow-2xl backdrop:bg-slate-900/50">
        <div class="p-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                <h2 id="ai-plan-view-title" class="text-lg font-bold text-slate-800">Plan</h2>
                <button type="button" onclick="document.getElementById('ai-plan-view-modal').close()"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" aria-label="Close dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div id="ai-plan-view-content" class="text-sm whitespace-pre-line leading-relaxed max-h-[60vh] overflow-y-auto"></div>
        </div>
    </dialog>

    {{-- Delete confirmation modal same pattern as crud/index.blade.php. --}}
    <dialog id="ai-plan-delete-modal" aria-labelledby="ai-plan-delete-title" class="rounded-2xl p-6 pm-dialog-sm shadow-2xl backdrop:bg-slate-900/50">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            </div>
            <h2 id="ai-plan-delete-title" class="text-lg font-bold text-slate-800">Delete plan?</h2>
        </div>
        <p id="ai-plan-delete-desc" class="text-sm text-slate-600 mb-5">This action cannot be undone.</p>
        <form method="POST" id="ai-plan-delete-form">
            @csrf
            @method('DELETE')
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('ai-plan-delete-modal').close()" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                    <span>Delete</span>
                </button>
            </div>
        </form>
    </dialog>

    <script>
        function pmOpenAiPlanViewModal(dateLabel, content) {
            document.getElementById('ai-plan-view-title').textContent = 'Plan ' + dateLabel;
            document.getElementById('ai-plan-view-content').textContent = content;
            document.getElementById('ai-plan-view-modal').showModal();
        }

        function pmOpenAiPlanDeleteModal(actionUrl, dateLabel) {
            document.getElementById('ai-plan-delete-form').action = actionUrl;
            document.getElementById('ai-plan-delete-desc').textContent =
                'Delete the plan from ' + dateLabel + '? This action cannot be undone.';
            document.getElementById('ai-plan-delete-modal').showModal();
        }

        function pmSelectAiTab(key) {
            document.querySelectorAll('.pm-ai-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected);
                btn.classList.toggle('border-transparent', !isSelected);
                btn.classList.toggle('text-slate-500', !isSelected);
                if (isSelected) { btn.focus(); }
            });
            document.querySelectorAll('.pm-ai-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-ai-panel-' + key;
            });
            if (key === 'chart' && typeof pmInitAiChartIfNeeded === 'function') {
                pmInitAiChartIfNeeded();
            }
        }

        function pmAiTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-ai-tab'), function (t) { return t.dataset.tab; });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else if (event.key === 'Home') { nextIndex = 0; }
            else if (event.key === 'End') { nextIndex = tabs.length - 1; }
            else { return; }

            event.preventDefault();
            pmSelectAiTab(tabs[nextIndex]);
        }
    </script>
@endsection

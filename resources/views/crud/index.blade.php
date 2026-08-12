@extends('layouts.app')

@section('title', $title . 's')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-{{ $accent }}-100 text-{{ $accent }}-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="{{ $icon }} text-xl" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">{{ $title }}s</h1>
        </div>
        <div class="flex items-center gap-2">
            @if (view()->exists('crud.extras.' . $routeName . '-header'))
                @include('crud.extras.' . $routeName . '-header')
            @endif
            @if (auth()->user()->hasActiveAccess())
                <button type="button" onclick="openCrudCreateModal()"
                        class="inline-flex items-center justify-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span>Add {{ $title }}</span>
                </button>
            @else
                <span class="inline-flex items-center gap-2 text-sm text-slate-400 px-4 py-2.5" title="Renew your subscription to add new records">
                    <i class="fa-solid fa-lock text-xs" aria-hidden="true"></i>
                    Renew to add
                </span>
            @endif
        </div>
    </div>

    @if (view()->exists('crud.extras.' . $routeName . '-top'))
        @include('crud.extras.' . $routeName . '-top')
    @endif

    {{-- Stats cards are always visible — not tabbed — so they read like
         the at-a-glance summary they're meant to be, with the Chart/Table
         tabs underneath for the more detailed views. --}}
    @if (!empty($stats))
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            @foreach ($stats as $stat)
                @php
                    $statColor = $stat['color'] ?? $accent;
                    $statIcon = $stat['icon'] ?? $icon;
                @endphp
                <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-{{ $statColor }}-400 p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-{{ $statColor }}-50 text-{{ $statColor }}-600 flex items-center justify-center shrink-0">
                            <i class="{{ $statIcon }} text-sm" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500 uppercase tracking-wide truncate">{{ $stat['label'] }}</p>
                            <p class="text-xl font-bold text-slate-800 truncate">{{ $stat['value'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @php
        // Only Chart and Table are tabbed now. Table is the default active
        // tab — visiting a module page is usually about the actual
        // records, with the chart as a secondary, occasional-use view. If
        // there's no chart yet, there's nothing to tab between at all —
        // Table is still the default active tab. Tabs are now always
        // shown (previously only when a chart existed) since Calendar is
        // always available, regardless of whether this module has a chart.
        $pmShowTabs = true;
    @endphp

    @if ($pmShowTabs)
        <div role="tablist" aria-label="{{ $title }} sections" class="flex gap-1 border-b border-slate-200 mb-6">
            @if (!empty($chart))
                <button
                    type="button" role="tab" id="pm-crud-tab-chart" aria-controls="pm-crud-panel-chart"
                    aria-selected="false" tabindex="-1" data-tab="chart"
                    onclick="pmSelectCrudTab('chart')" onkeydown="pmCrudTabKeydown(event, 'chart')"
                    class="pm-crud-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300"
                >
                    <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                    <span>Chart</span>
                </button>
            @endif
            <button
                type="button" role="tab" id="pm-crud-tab-table" aria-controls="pm-crud-panel-table"
                aria-selected="true" tabindex="0" data-tab="table"
                onclick="pmSelectCrudTab('table')" onkeydown="pmCrudTabKeydown(event, 'table')"
                class="pm-crud-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-[var(--brand-1)] text-[var(--brand-1)]"
            >
                <i class="fa-solid fa-table-list" aria-hidden="true"></i>
                <span>{{ $title }}s</span>
            </button>
            <button
                type="button" role="tab" id="pm-crud-tab-calendar" aria-controls="pm-crud-panel-calendar"
                aria-selected="false" tabindex="-1" data-tab="calendar"
                onclick="pmSelectCrudTab('calendar')" onkeydown="pmCrudTabKeydown(event, 'calendar')"
                class="pm-crud-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300"
            >
                <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                <span>Calendar</span>
            </button>
        </div>
    @endif

    @if (!empty($chart))
        @php
            // Built as a plain string here rather than nesting loop/conditional
            // directives directly inside the aria-label="..." attribute — that
            // inline-nested-directives-inside-an-attribute pattern is fragile
            // to parse and caused a real ParseError in testing.
            $chartAriaParts = [];
            foreach ($chart['labels'] as $i => $label) {
                $pointParts = [];
                foreach ($chart['datasets'] as $ds) {
                    $pointParts[] = trim(($ds['label'] ?? '') . ' ' . ($ds['data'][$i] ?? 0));
                }
                $chartAriaParts[] = $label . ' ' . implode(' ', $pointParts);
            }
            $chartAriaLabel = $chart['title'] . ': ' . implode(', ', $chartAriaParts) . '.';
        @endphp
        <div role="tabpanel" id="pm-crud-panel-chart" aria-labelledby="pm-crud-tab-chart" tabindex="0"
             class="pm-crud-panel" hidden>
            <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 p-5 mb-6">
                <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                    <i class="{{ $icon }} text-{{ $accent }}-500" aria-hidden="true"></i>
                    {{ $chart['title'] }}
                </h2>
                {{-- Fixed height + maintainAspectRatio:false (in the JS
                     below) gives precise control over the rendered size —
                     without both, Chart.js sizes itself from the
                     container's width using a fairly large default aspect
                     ratio, rendering much bigger than intended. --}}
                <div class="h-48">
                    <canvas id="crud-chart" role="img" aria-label="{{ $chartAriaLabel }}"></canvas>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
        <script>
            var pmCrudChartConfig = @json($chart);
            var pmCrudChartInstance = null;

            // Deferred until the Chart tab is actually shown (or immediately
            // if there are no tabs at all, i.e. this is the only section) —
            // Chart.js measures the canvas at construction time, and a
            // canvas inside a display:none ancestor measures as 0x0, which
            // renders blank even after the tab is later revealed.
            function pmInitCrudChartIfNeeded() {
                if (pmCrudChartInstance) { return; }

                var palette = ['#6366f1', '#10b981', '#f43f5e', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#84cc16', '#f97316'];
                var chartType = pmCrudChartConfig.type;
                var datasets = pmCrudChartConfig.datasets.map(function (ds, i) {
                    if (chartType === 'doughnut') {
                        return Object.assign({}, ds, { backgroundColor: palette, borderWidth: 2, borderColor: '#ffffff' });
                    }
                    return Object.assign({}, ds, {
                        backgroundColor: chartType === 'line' ? palette[i] + '22' : palette[i],
                        borderColor: palette[i],
                        fill: chartType === 'line',
                        tension: 0.3,
                        borderRadius: chartType === 'bar' ? 4 : 0,
                    });
                });

                pmCrudChartInstance = new Chart(document.getElementById('crud-chart'), {
                    type: chartType,
                    data: { labels: pmCrudChartConfig.labels, datasets: datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                // Always shown now — a doughnut's colored
                                // slices are meaningless without a key
                                // mapping each color back to its category,
                                // and this used to be hidden for every
                                // single-dataset doughnut (i.e. nearly all
                                // of them, since a doughnut chart only ever
                                // has one dataset here).
                                display: true,
                                position: 'bottom',
                                labels: chartType === 'doughnut' ? {
                                    // Default doughnut legend only shows the
                                    // label (e.g. "Groceries") — this adds
                                    // the actual value too (e.g.
                                    // "Groceries: 120"), so the key doubles
                                    // as a real reference, not just a color
                                    // guide.
                                    generateLabels: function (chart) {
                                        var data = chart.data;
                                        if (!data.labels.length || !data.datasets.length) { return []; }
                                        var ds = data.datasets[0];
                                        return data.labels.map(function (label, i) {
                                            return {
                                                text: label + ': ' + ds.data[i],
                                                fillStyle: Array.isArray(ds.backgroundColor) ? ds.backgroundColor[i] : ds.backgroundColor,
                                                strokeStyle: ds.borderColor || '#ffffff',
                                                lineWidth: ds.borderWidth || 0,
                                                hidden: false,
                                                index: i,
                                            };
                                        });
                                    },
                                } : {},
                            },
                        },
                        scales: chartType === 'doughnut' ? {} : { y: { beginAtZero: true } },
                    },
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                // No tabs at all (chart is the only section) means the
                // panel is never hidden in the first place — safe to
                // initialize right away.
                var chartPanel = document.getElementById('pm-crud-panel-chart');
                if (chartPanel && !chartPanel.hasAttribute('hidden')) {
                    pmInitCrudChartIfNeeded();
                }
            });
        </script>
    @endif

    <div role="tabpanel" id="pm-crud-panel-table" aria-labelledby="pm-crud-tab-table" tabindex="0" class="pm-crud-panel">
    <form method="GET" action="{{ route($routeName . '.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
        <div class="flex-1 min-w-[180px] max-w-xs">
            <label for="crud-search-{{ $routeName }}" class="sr-only">Search {{ strtolower($title) }}s</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true"></i>
                <input type="search" id="crud-search-{{ $routeName }}" name="q" value="{{ $search }}"
                       placeholder="Search {{ strtolower($title) }}s..." class="pl-9 pm-input text-sm">
            </div>
        </div>

        <div>
            <label for="crud-period-{{ $routeName }}" class="sr-only">Filter by period</label>
            <select id="crud-period-{{ $routeName }}" name="period" onchange="pmToggleCrudDateRange(this)" class="pm-input text-sm">
                <option value="" @selected(!$period)>All time</option>
                <option value="daily" @selected($period === 'daily')>Today</option>
                <option value="weekly" @selected($period === 'weekly')>This week</option>
                <option value="monthly" @selected($period === 'monthly')>This month</option>
                <option value="range" @selected($period === 'range')>Custom range...</option>
            </select>
        </div>

        <div id="crud-date-range-{{ $routeName }}" class="flex items-end gap-2" style="{{ $period === 'range' ? '' : 'display: none;' }}">
            <div>
                <label for="crud-from-{{ $routeName }}" class="sr-only">From date</label>
                <input type="date" id="crud-from-{{ $routeName }}" name="from" value="{{ $from }}" class="pm-input text-sm">
            </div>
            <span class="text-slate-400 text-sm pb-2">to</span>
            <div>
                <label for="crud-to-{{ $routeName }}" class="sr-only">To date</label>
                <input type="date" id="crud-to-{{ $routeName }}" name="to" value="{{ $to }}" class="pm-input text-sm">
            </div>
        </div>

        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
            Filter
        </button>
        @if ($search || $period)
            <a href="{{ route($routeName . '.index') }}" class="text-sm text-slate-500 hover:text-slate-700 transition-colors pb-2.5">
                Clear
            </a>
        @endif
    </form>

    @if (auth()->user()->hasActiveAccess())
        <div id="pm-crud-bulk-bar" class="hidden items-center justify-between gap-3 bg-rose-50 border border-rose-200 rounded-lg px-4 py-3 mb-3">
            <span id="pm-crud-bulk-count" class="text-sm font-medium text-rose-700">0 selected</span>
            <button type="button" onclick="pmSubmitCrudBulkDelete()"
                    class="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                Delete selected
            </button>
        </div>
    @endif

    <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 overflow-x-auto" role="region" aria-label="{{ $title }}s table" tabindex="0">
        <table class="min-w-full text-sm">
            <caption class="sr-only">List of your {{ strtolower($title) }}s, with edit and delete actions for each.</caption>
            <thead class="bg-slate-50 text-left border-b border-slate-100">
                <tr>
                    <th scope="col" class="px-4 py-3 w-10">
                        @if (auth()->user()->hasActiveAccess())
                            <input type="checkbox" id="pm-crud-select-all" onchange="pmToggleAllCrud(this)"
                                   class="rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                                   aria-label="Select all {{ strtolower($title) }}s on this page">
                        @endif
                    </th>
                    @foreach ($fields as $field)
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">{{ $field['label'] }}</th>
                    @endforeach
                    <th scope="col" class="px-4 py-3">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    @php
                        $rowLabel = $item->{$fields[0]['name']} ?? null;
                        $rowLabel = is_string($rowLabel) || is_numeric($rowLabel) ? (string) $rowLabel : ('#' . $item->id);

                        // Formatted values for THIS row, used by the JS modal
                        // to populate the shared edit form when its Edit
                        // button is clicked. Dates/times are turned into the
                        // plain strings their <input> types expect.
                        $rowValues = [];
                        foreach ($fields as $f) {
                            $v = $item->{$f['name']} ?? null;
                            if (is_object($v) && method_exists($v, 'format')) {
                                $v = $f['type'] === 'datetime-local' ? $v->format('Y-m-d\TH:i') : $v->format('Y-m-d');
                            }
                            $rowValues[$f['name']] = $v;
                        }
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 align-top">
                            @if (($item->user_id ?? null) == auth()->id() && auth()->user()->hasActiveAccess())
                                <input type="checkbox" value="{{ $item->id }}" class="pm-crud-row-checkbox rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                                       onchange="pmUpdateCrudBulkBar()" aria-label="Select {{ $rowLabel }}">
                            @endif
                        </td>
                        @foreach ($fields as $field)
                            <td class="px-4 py-3 align-top text-slate-700">
                                @php $value = $item->{$field['name']}; @endphp
                                @if ($routeName === 'meetings' && $field['name'] === 'location')
                                    @php
                                        $meetingLocation = trim((string) ($value ?? ''));
                                        $isMeetingUrl = \Illuminate\Support\Str::startsWith(strtolower($meetingLocation), ['http://', 'https://']);
                                    @endphp
                                    @if ($meetingLocation === '')
                                        —
                                    @elseif ($isMeetingUrl)
                                        <a href="{{ $meetingLocation }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           title="{{ $meetingLocation }}"
                                           class="inline-flex items-center gap-1 text-[var(--brand-1)] hover:underline font-medium whitespace-nowrap">
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]" aria-hidden="true"></i>
                                            Link
                                        </a>
                                    @else
                                        <span title="{{ $meetingLocation }}" class="cursor-help">
                                            {{ \Illuminate\Support\Str::limit($meetingLocation, 28) }}
                                        </span>
                                    @endif
                                @elseif ($routeName === 'meetings' && $field['name'] === 'attendees')
                                    @php
                                        $attendeeRaw = trim((string) ($value ?? ''));
                                        $attendeeList = $attendeeRaw === ''
                                            ? []
                                            : array_values(array_filter(preg_split('/[\s,;]+/', $attendeeRaw) ?: []));
                                    @endphp
                                    @if ($attendeeRaw === '')
                                        —
                                    @else
                                        <span title="{{ $attendeeRaw }}"
                                              class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 cursor-help whitespace-nowrap">
                                            <i class="fa-solid fa-users text-[10px]" aria-hidden="true"></i>
                                            Attendees List{{ count($attendeeList) ? ' (' . count($attendeeList) . ')' : '' }}
                                        </span>
                                    @endif
                                @elseif ($routeName === 'meetings' && $field['name'] === 'notes')
                                    @php $meetingNotes = trim((string) ($value ?? '')); @endphp
                                    @if ($meetingNotes === '')
                                        —
                                    @else
                                        <span title="{{ $meetingNotes }}" class="cursor-help">
                                            {{ \Illuminate\Support\Str::limit($meetingNotes, 32) }}
                                        </span>
                                    @endif
                                @elseif ($field['name'] === 'project_id' && isset($item->project))
                                    {{ $item->project->name }}
                                @elseif ($field['name'] === 'savings_goal_id' && isset($item->goal))
                                    {{ $item->goal->name }}
                                @elseif ($field['money'] ?? false)
                                    {{ $value !== null ? format_money($value) : '—' }}
                                @elseif ($field['type'] === 'checkbox')
                                    @if ($value)
                                        <i class="fa-solid fa-circle-check text-emerald-500" aria-hidden="true"></i>
                                        <span class="sr-only">Yes</span>
                                    @else
                                        <i class="fa-solid fa-circle-xmark text-slate-300" aria-hidden="true"></i>
                                        <span class="sr-only">No</span>
                                    @endif
                                @elseif (is_object($value) && method_exists($value, 'format'))
                                    {{ $field['type'] === 'datetime-local' ? $value->format('Y-m-d H:i') : $value->format('Y-m-d') }}
                                @elseif (is_array($value))
                                    @php
                                        $displayValue = collect($value)->map(function ($part) {
                                            if (is_array($part) || is_object($part)) {
                                                return json_encode($part, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                            }
                                            return (string) $part;
                                        })->implode(', ');
                                    @endphp
                                    {{ \Illuminate\Support\Str::limit($displayValue !== '' ? $displayValue : '—', 60) }}
                                @elseif (isset($field['options']) && $value !== null && (is_string($value) || is_int($value)) && array_key_exists($value, $field['options']))
                                    {{ $field['options'][$value] }}
                                @elseif ($value instanceof \Stringable)
                                    {{ \Illuminate\Support\Str::limit((string) $value, 60) }}
                                @elseif (is_scalar($value) || $value === null)
                                    {{ \Illuminate\Support\Str::limit((string) ($value ?? ''), 60) ?: '—' }}
                                @else
                                    {{ \Illuminate\Support\Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—', 60) }}
                                @endif
                            </td>
                        @endforeach
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button"
                                    onclick='openCrudViewModal({{ json_encode($rowValues) }}, {{ $item->id }}, {{ (($item->user_id ?? null) == auth()->id()) ? "true" : "false" }})'
                                    class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-800 mr-3 transition-colors"
                                    title="View {{ $rowLabel }}">
                                <i class="fa-solid fa-eye text-xs" aria-hidden="true"></i>
                                <span class="sr-only">View {{ $rowLabel }}</span>
                            </button>
                            @if (($item->user_id ?? null) == auth()->id())
                                @if (auth()->user()->hasActiveAccess())
                                    <button type="button"
                                            onclick='openCrudEditModal({{ json_encode(route($routeName . '.update', $item->id)) }}, {{ json_encode($rowValues) }})'
                                            class="inline-flex items-center gap-1 text-{{ $accent }}-600 hover:text-{{ $accent }}-800 mr-3 transition-colors">
                                        <i class="fa-solid fa-pen-to-square text-xs" aria-hidden="true"></i>
                                        <span class="sr-only">Edit {{ $rowLabel }}</span>
                                    </button>
                                    <button type="button"
                                            onclick='openCrudDeleteModal({{ json_encode(route($routeName . '.destroy', $item->id)) }}, {{ json_encode($rowLabel) }})'
                                            class="inline-flex items-center gap-1 text-rose-500 hover:text-rose-700 transition-colors">
                                        <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                                        <span class="sr-only">Delete {{ $rowLabel }}</span>
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400" title="Renew your subscription to edit or delete">
                                        <i class="fa-solid fa-lock text-xs" aria-hidden="true"></i>
                                    </span>
                                @endif
                            @else
                                {{-- Visible because it's shared with this user (e.g. a Meeting they're
                                     an attendee on), but not theirs to edit or delete. --}}
                                <span class="text-xs text-slate-400 italic inline-flex items-center gap-1">
                                    <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                                    Shared with you
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($fields) + 2 }}" class="px-4 py-10 text-center text-slate-400">
                            <i class="{{ $icon }} text-3xl mb-2 block opacity-30" aria-hidden="true"></i>
                            No {{ strtolower($title) }}s yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <nav aria-label="Pagination" class="mt-4">
        {{ $items->links() }}
    </nav>
    </div>

    <div role="tabpanel" id="pm-crud-panel-calendar" aria-labelledby="pm-crud-tab-calendar" tabindex="0" class="pm-crud-panel" hidden>
        <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <a href="{{ route($routeName . '.index', array_merge(request()->query(), ['cal_month' => $calendar['prevMonth']])) }}"
                   class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors" aria-label="Previous month">
                    <i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i>
                </a>
                <h2 class="font-semibold text-slate-800">{{ $calendar['month']->format('F Y') }}</h2>
                <a href="{{ route($routeName . '.index', array_merge(request()->query(), ['cal_month' => $calendar['nextMonth']])) }}"
                   class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-colors" aria-label="Next month">
                    <i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i>
                </a>
            </div>

            <p class="text-xs text-slate-400 mb-3">
                Click any day to add a new {{ strtolower($title) }} for that date{{ $dateFieldName ? '' : ' (date will need to be set manually — this module doesn\'t track a specific date field of its own beyond when it was added)' }}.
            </p>

            <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-slate-400 uppercase mb-1">
                @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
                    <div>{{ $dow }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-1">
                @php
                    $firstOfMonth = $calendar['month']->copy()->startOfMonth();
                    $leadingBlanks = $firstOfMonth->dayOfWeek;
                    $daysInMonth = $calendar['month']->daysInMonth;
                @endphp
                @for ($i = 0; $i < $leadingBlanks; $i++)
                    <div></div>
                @endfor
                @for ($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $dateKey = $firstOfMonth->copy()->day($day)->format('Y-m-d');
                        $dayItems = $calendar['byDay']->get($dateKey, collect());
                        $isToday = $dateKey === now()->format('Y-m-d');
                    @endphp
                    <div role="button" tabindex="0"
                         onclick="pmOpenCrudCalendarDay('{{ $dateKey }}')"
                         onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); pmOpenCrudCalendarDay('{{ $dateKey }}'); }"
                         aria-label="Add a new {{ strtolower($title) }} on {{ $dateKey }}"
                         class="text-left border rounded-lg p-1.5 min-h-[64px] cursor-pointer hover:bg-slate-50 transition-colors {{ $isToday ? 'border-[var(--brand-1)] bg-[var(--brand-1-tint-10)]' : 'border-slate-100' }}">
                        <span class="text-xs font-medium {{ $isToday ? 'text-[var(--brand-1)]' : 'text-slate-500' }}">{{ $day }}</span>
                        @foreach ($dayItems->take(2) as $dayItem)
                            {{-- Each existing item is its own clickable
                                 button, opening the SAME edit modal the
                                 table's Edit button uses (openCrudEditModal
                                 is already defined further down) —
                                 stopPropagation so clicking a pill doesn't
                                 ALSO trigger the day cell's "add new" click
                                 handler underneath it. --}}
                            <button type="button"
                                    onclick='event.stopPropagation(); openCrudEditModal({{ json_encode($dayItem["updateUrl"]) }}, {{ json_encode($dayItem["values"]) }})'
                                    class="block w-full text-left text-[10px] truncate bg-{{ $accent }}-50 text-{{ $accent }}-700 hover:bg-{{ $accent }}-100 rounded px-1 mt-0.5 transition-colors">
                                {{ $dayItem['label'] }}
                            </button>
                        @endforeach
                        @if ($dayItems->count() > 2)
                            <span class="block text-[10px] text-slate-400 mt-0.5">+{{ $dayItems->count() - 2 }} more</span>
                        @endif
                    </div>
                @endfor
            </div>
        </div>
    </div>

    {{-- Shared create/edit modal. Server-rendered with old()-filled fields
         so validation-error redisplay works even though the "normal" path
         to reach it is a JS click, not a page load. --}}
    <dialog id="crud-modal" aria-labelledby="crud-modal-title" class="rounded-2xl p-0 pm-dialog shadow-2xl backdrop:bg-slate-900/50">
        <form method="POST" id="crud-modal-form" action="{{ old('_dialog_action', route($routeName . '.store')) }}" class="p-6 space-y-5">
            @csrf
            @if (old('_method') === 'PUT')
                @method('PUT')
            @endif
            <input type="hidden" name="_dialog_action" id="crud-modal-dialog-action" value="{{ old('_dialog_action', '') }}">

            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-{{ $accent }}-100 text-{{ $accent }}-600 flex items-center justify-center shrink-0">
                        <i class="{{ $icon }} text-sm" aria-hidden="true"></i>
                    </div>
                    <h2 id="crud-modal-title" class="text-lg font-bold text-slate-800">
                        {{ old('_method') === 'PUT' ? 'Edit' : 'New' }} {{ $title }}
                    </h2>
                </div>
                <button type="button" onclick="document.getElementById('crud-modal').close()"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" aria-label="Close dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            @include('crud._fields', ['fields' => $fields, 'item' => null])

            @if ($dateFieldName)
                <div class="flex items-center gap-2 border-t border-slate-100 pt-4">
                    <input type="checkbox" id="crud-set-reminder" name="set_reminder" value="1"
                           class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                    <label for="crud-set-reminder" class="text-sm text-slate-700">
                        Also set a reminder for this {{ strtolower($title) }}
                    </label>
                </div>
            @endif

            <div class="flex items-center gap-3 pt-2 border-t border-slate-100 mt-2">
                <button type="submit" class="inline-flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    <span>Save</span>
                </button>
                <button type="button" onclick="document.getElementById('crud-modal').close()" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </dialog>

    {{-- Shared read-only View modal used by every generic table page. --}}
    <dialog id="crud-view-modal" aria-labelledby="crud-view-modal-title" class="rounded-2xl p-0 pm-dialog shadow-2xl backdrop:bg-slate-900/50">
        <div class="p-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-{{ $accent }}-100 text-{{ $accent }}-600 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-eye text-sm" aria-hidden="true"></i>
                    </div>
                    <h2 id="crud-view-modal-title" class="text-lg font-bold text-slate-800">View {{ $title }}</h2>
                </div>
                <button type="button" onclick="document.getElementById('crud-view-modal').close()"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" aria-label="Close dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <dl id="crud-view-fields" class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4"></dl>

            @if ($routeName === 'meetings')
                <div id="crud-meeting-view-actions" class="hidden mt-6 pt-4 border-t border-slate-100">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-3">Meeting tools</p>
                    <div class="flex flex-wrap gap-2">
                        <a id="crud-meeting-record-link" href="#"
                           class="inline-flex items-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium">
                            <i class="fa-solid fa-microphone" aria-hidden="true"></i> Record Meeting
                        </a>
                        <a id="crud-meeting-upload-link" href="#"
                           class="inline-flex items-center gap-2 border border-slate-200 text-slate-700 px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-50">
                            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i> Upload Recording
                        </a>
                        <a id="crud-meeting-transcript-link" href="#"
                           class="inline-flex items-center gap-2 border border-slate-200 text-slate-700 px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-50">
                            <i class="fa-solid fa-file-lines" aria-hidden="true"></i> Transcript &amp; AI Summary
                        </a>
                    </div>
                    <p class="text-xs text-slate-400 mt-2">Record live audio or upload an existing recording, then transcribe it and generate key points, decisions and action items with AI.</p>
                </div>
            @endif

            <div class="flex justify-end mt-6 pt-4 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('crud-view-modal').close()" class="text-sm text-slate-500 hover:text-slate-700">Close</button>
            </div>
        </div>
    </dialog>

    {{-- Shared delete-confirmation modal. --}}
    <dialog id="crud-delete-modal" aria-labelledby="crud-delete-modal-title" class="rounded-2xl p-6 pm-dialog-sm shadow-2xl backdrop:bg-slate-900/50">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            </div>
            <h2 id="crud-delete-modal-title" class="text-lg font-bold text-slate-800">Delete {{ strtolower($title) }}?</h2>
        </div>
        <p id="crud-delete-modal-desc" class="text-sm text-slate-600 mb-5">This action cannot be undone.</p>
        <form method="POST" id="crud-delete-modal-form">
            @csrf
            @method('DELETE')
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('crud-delete-modal').close()" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
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
        function pmToggleAllCrud(selectAllCheckbox) {
            document.querySelectorAll('.pm-crud-row-checkbox').forEach(function (checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
            pmUpdateCrudBulkBar();
        }

        function pmUpdateCrudBulkBar() {
            var all = Array.prototype.slice.call(document.querySelectorAll('.pm-crud-row-checkbox'));
            var checked = all.filter(function (checkbox) { return checkbox.checked; });
            var bar = document.getElementById('pm-crud-bulk-bar');
            var count = document.getElementById('pm-crud-bulk-count');
            var selectAll = document.getElementById('pm-crud-select-all');

            if (bar) { bar.classList.toggle('hidden', checked.length === 0); bar.classList.toggle('flex', checked.length > 0); }
            if (count) { count.textContent = checked.length + (checked.length === 1 ? ' item selected' : ' items selected'); }
            if (selectAll) {
                selectAll.checked = all.length > 0 && checked.length === all.length;
                selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
            }
        }

        function pmSubmitCrudBulkDelete() {
            var checked = document.querySelectorAll('.pm-crud-row-checkbox:checked');
            if (!checked.length) { return; }
            if (!confirm('Delete ' + checked.length + ' selected {{ strtolower($title) }}' + (checked.length === 1 ? '' : 's') + '? This action cannot be undone.')) { return; }

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = @json(route($routeName . '.bulk-destroy'));

            var token = document.createElement('input');
            token.type = 'hidden'; token.name = '_token'; token.value = @json(csrf_token());
            form.appendChild(token);

            var method = document.createElement('input');
            method.type = 'hidden'; method.name = '_method'; method.value = 'DELETE';
            form.appendChild(method);

            checked.forEach(function (checkbox) {
                var field = document.createElement('input');
                field.type = 'hidden'; field.name = 'ids[]'; field.value = checkbox.value;
                form.appendChild(field);
            });

            document.body.appendChild(form);
            form.submit();
        }

        function pmToggleCrudDateRange(select) {
            var wrapper = document.getElementById('crud-date-range-{{ $routeName }}');
            if (wrapper) { wrapper.style.display = select.value === 'range' ? 'flex' : 'none'; }
        }

        function pmSelectCrudTab(key) {
            document.querySelectorAll('.pm-crud-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected);
                btn.classList.toggle('border-transparent', !isSelected);
                btn.classList.toggle('text-slate-500', !isSelected);
                if (isSelected) { btn.focus(); }
            });
            document.querySelectorAll('.pm-crud-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-crud-panel-' + key;
            });
            if (key === 'chart' && typeof pmInitCrudChartIfNeeded === 'function') {
                pmInitCrudChartIfNeeded();
            }
        }

        // Standard WAI-ARIA tabs keyboard pattern: Left/Right/Home/End move
        // focus AND activate the tab (not just focus it).
        function pmCrudTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-crud-tab'), function (t) { return t.dataset.tab; });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else if (event.key === 'Home') { nextIndex = 0; }
            else if (event.key === 'End') { nextIndex = tabs.length - 1; }
            else { return; }

            event.preventDefault();
            pmSelectCrudTab(tabs[nextIndex]);
        }

        function openCrudCreateModal() {
            var dialog = document.getElementById('crud-modal');
            var form = document.getElementById('crud-modal-form');
            form.reset();
            form.action = @json(route($routeName . '.store'));
            document.getElementById('crud-modal-dialog-action').value = form.action;
            var methodInput = form.querySelector('input[name="_method"]');
            if (methodInput) { methodInput.remove(); }
            document.getElementById('crud-modal-title').textContent = 'New {{ $title }}';
            dialog.classList.remove('pm-dialog-quick');
            dialog.classList.add('pm-dialog');
            dialog.showModal();
        }

        // Calendar tab's "click a day to add" — opens the same create
        // modal as the "Add" button (same fields, same validation), then
        // pre-fills whichever field maps to this module's date column
        // (see CrudController's editableDateFieldName()) with the clicked
        // day. Modules with no editable date field of their own (e.g.
        // Feedback) just open the plain create modal — there's nothing to
        // pre-fill. Styled visibly smaller/lighter (.pm-dialog-quick) and
        // titled with the actual date, closer to the compact "quick add
        // event" popup Google Calendar/Teams show for this exact
        // interaction, rather than reusing the full-size form dialog as-is.
        function pmOpenCrudCalendarDay(dateKey) {
            openCrudCreateModal();

            var dialog = document.getElementById('crud-modal');
            dialog.classList.remove('pm-dialog');
            dialog.classList.add('pm-dialog-quick');

            var friendlyDate = new Date(dateKey + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
            document.getElementById('crud-modal-title').textContent = 'New {{ $title }} — ' + friendlyDate;

            @if ($dateFieldName)
                var dateField = document.getElementById('field-{{ $dateFieldName }}');
                if (dateField) {
                    dateField.value = dateField.type === 'datetime-local' ? (dateKey + 'T09:00') : dateKey;
                }
            @endif
        }

        function openCrudViewModal(values, itemId, isOwner) {
            var fields = @json($fields);
            var container = document.getElementById('crud-view-fields');
            container.innerHTML = '';

            fields.forEach(function (field) {
                var value = values[field.name];

                if (Array.isArray(value)) {
                    value = value.map(function (part) {
                        return (part !== null && typeof part === 'object') ? JSON.stringify(part) : String(part);
                    }).join(', ');
                } else if (value !== null && typeof value === 'object') {
                    value = JSON.stringify(value, null, 2);
                }

                if (value === null || value === undefined || value === '') { value = '—'; }
                if (field.options && (typeof value === 'string' || typeof value === 'number') && Object.prototype.hasOwnProperty.call(field.options, value)) {
                    value = field.options[value];
                }
                if (field.type === 'checkbox') { value = value && value !== '0' ? 'Yes' : 'No'; }

                var wrap = document.createElement('div');
                wrap.className = field.type === 'textarea' ? 'sm:col-span-2' : '';
                var dt = document.createElement('dt');
                dt.className = 'text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1';
                dt.textContent = field.label;
                var dd = document.createElement('dd');
                dd.className = 'text-sm text-slate-700 whitespace-pre-wrap break-words';
                dd.textContent = String(value);
                wrap.appendChild(dt);
                wrap.appendChild(dd);
                container.appendChild(wrap);
            });

            @if ($routeName === 'meetings')
                var tools = document.getElementById('crud-meeting-view-actions');
                if (tools) {
                    if (isOwner) {
                        tools.classList.remove('hidden');
                        var base = @json(url('/meetings')) + '/' + itemId + '/notes';
                        document.getElementById('crud-meeting-record-link').href = base + '#record-meeting';
                        document.getElementById('crud-meeting-upload-link').href = base + '#record-meeting';
                        document.getElementById('crud-meeting-transcript-link').href = base + '#transcripts-summary';
                    } else {
                        tools.classList.add('hidden');
                    }
                }
            @endif

            document.getElementById('crud-view-modal').showModal();
        }

        function openCrudEditModal(actionUrl, values) {
            var dialog = document.getElementById('crud-modal');
            dialog.classList.remove('pm-dialog-quick');
            dialog.classList.add('pm-dialog');
            var form = document.getElementById('crud-modal-form');
            form.reset();
            form.action = actionUrl;
            document.getElementById('crud-modal-dialog-action').value = actionUrl;

            var methodInput = form.querySelector('input[name="_method"]');
            if (!methodInput) {
                methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                form.appendChild(methodInput);
            }
            methodInput.value = 'PUT';

            Object.keys(values).forEach(function (name) {
                var el = form.elements[name];
                if (!el) { return; }
                // Checkbox fields render TWO inputs sharing the same name
                // (a hidden "0" fallback + the real checkbox), so
                // form.elements[name] is a RadioNodeList, not a single
                // element — .value doesn't check/uncheck it correctly.
                if (el instanceof RadioNodeList) {
                    var checkbox = form.querySelector('input[type="checkbox"][name="' + name + '"]');
                    if (checkbox) { checkbox.checked = !!values[name]; }
                    return;
                }
                if (el.type === 'checkbox') {
                    el.checked = !!values[name];
                    return;
                }
                el.value = values[name] === null ? '' : values[name];
            });

            document.getElementById('crud-modal-title').textContent = 'Edit {{ $title }}';
            dialog.showModal();
        }

        function openCrudDeleteModal(actionUrl, label) {
            var dialog = document.getElementById('crud-delete-modal');
            document.getElementById('crud-delete-modal-form').action = actionUrl;
            document.getElementById('crud-delete-modal-desc').textContent =
                'Delete "' + label + '"? This action cannot be undone.';
            dialog.showModal();
        }

        // If this page just reloaded after a failed validation on the
        // create/edit modal (old('_dialog_action') is present), reopen it
        // automatically instead of leaving the error silently at the top
        // of an otherwise-normal index page — and make sure the Table tab
        // (where the modal lives) is the visible one first.
        document.addEventListener('DOMContentLoaded', function () {
            @if ($errors->any() && old('_dialog_action'))
                pmSelectCrudTab('table');
                document.getElementById('crud-modal').showModal();
            @endif
        });
    </script>

    {{-- Optional per-module extras (e.g. Meetings' "Schedule Multiple" modal).
         Silently does nothing for every other module — view()->exists()
         just returns false when no matching file was created. --}}
    @if (view()->exists('crud.extras.' . $routeName . '-extra'))
        @include('crud.extras.' . $routeName . '-extra')
    @endif
@endsection

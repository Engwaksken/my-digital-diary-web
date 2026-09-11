@extends('layouts.app')

@section('title', 'Daily Eating History')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center shadow-sm">
                <i class="fa-solid fa-clock-rotate-left text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Daily Eating History</h1>
                <p class="text-sm text-slate-500 mt-1">Review the food notes you saved throughout each day.</p>
            </div>
        </div>

        <a href="{{ route('diet-logs.index', ['diet_tab' => 'today']) }}"
           class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Meal Logs
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
        <div class="pm-card-bg rounded-xl border border-slate-100 border-l-4 border-l-orange-400 p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-400">Entries in this period</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($filteredEntries) }}</p>
        </div>
        <div class="pm-card-bg rounded-xl border border-slate-100 border-l-4 border-l-emerald-400 p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-400">Days recorded</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($daysRecorded) }}</p>
        </div>
    </div>

    <form method="GET"
          action="{{ route('daily-food-history.index') }}"
          class="pm-card-bg rounded-xl border border-slate-100 shadow-sm p-4 mb-5">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3 items-end">
            <div>
                <label for="history-period" class="block text-xs font-semibold text-slate-500 mb-1">Period</label>
                <select id="history-period" name="period" class="pm-input" onchange="pmToggleEatingRange()">
                    <option value="today" @selected($period === 'today')>Today</option>
                    <option value="week" @selected($period === 'week')>Last 7 days</option>
                    <option value="month" @selected($period === 'month')>This month</option>
                    <option value="three_months" @selected($period === 'three_months')>Last 3 months</option>
                    <option value="range" @selected($period === 'range')>Custom range</option>
                    <option value="all" @selected($period === 'all')>All history</option>
                </select>
            </div>

            <div data-eating-range>
                <label for="history-from" class="block text-xs font-semibold text-slate-500 mb-1">From</label>
                <input id="history-from" type="date" name="from" value="{{ $from }}" class="pm-input">
            </div>

            <div data-eating-range>
                <label for="history-to" class="block text-xs font-semibold text-slate-500 mb-1">To</label>
                <input id="history-to" type="date" name="to" value="{{ $to }}" class="pm-input">
            </div>

            <div>
                <label for="history-per-page" class="block text-xs font-semibold text-slate-500 mb-1">Per page</label>
                <select id="history-per-page" name="per_page" class="pm-input">
                    @foreach ([10, 25, 50] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>

            <div class="xl:col-span-2 flex gap-2">
                <button type="submit"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg btn-primary px-4 py-2.5 text-sm font-semibold text-white">
                    <i class="fa-solid fa-filter"></i>
                    Apply filter
                </button>

                <a href="{{ route('daily-food-history.index') }}"
                   class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    Clear
                </a>
            </div>
        </div>
    </form>

    <div class="pm-card-bg rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-left text-xs uppercase tracking-wide text-slate-500">Saved at</th>
                        <th class="px-4 py-3 text-left text-xs uppercase tracking-wide text-slate-500">What you ate</th>
                        <th class="px-4 py-3 text-right text-xs uppercase tracking-wide text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($history as $entry)
                        @php $fullText = trim(strip_tags((string) $entry->daily_food_notes)); @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 whitespace-nowrap font-medium text-slate-700">
                                {{ optional($entry->journal_date)->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-slate-500">
                                {{ optional($entry->created_at)->format('g:i A') }}
                            </td>
                            <td class="px-4 py-3 text-slate-700 max-w-[720px]">
                                <span class="cursor-help" title="{{ $fullText }}">
                                    {{ \Illuminate\Support\Str::limit($fullText, 110) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('daily-food-history.update-entry', $entry) }}" class="inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="journal_date" value="{{ optional($entry->journal_date)->format('Y-m-d') }}">
                                    <button type="button"
                                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                                            onclick="document.getElementById('daily-food-inline-edit-{{ $entry->id }}').classList.toggle('hidden')">
                                        <i class="fa-solid fa-pen"></i>Edit
                                    </button>
                                    <div id="daily-food-inline-edit-{{ $entry->id }}" class="hidden mt-2 min-w-[280px]">
                                        <textarea name="daily_food_notes" class="pm-input" rows="5" maxlength="6000" required>{{ $entry->daily_food_notes }}</textarea>
                                        <button type="submit" class="mt-2 rounded-lg btn-primary px-3 py-2 text-xs font-semibold text-white">Save changes</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center text-slate-400">
                                <i class="fa-solid fa-bowl-food text-3xl block mb-3 opacity-30"></i>
                                No daily eating history found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($history->hasPages())
        <nav aria-label="Daily eating history pagination" class="mt-5">
            {{ $history->links() }}
        </nav>
    @endif
</div>

<script>
function pmToggleEatingRange() {
    const select = document.getElementById('history-period');
    const show = select && select.value === 'range';

    document.querySelectorAll('[data-eating-range]').forEach(function (field) {
        field.classList.toggle('hidden', !show);
    });
}

document.addEventListener('DOMContentLoaded', pmToggleEatingRange);
pmToggleEatingRange();
</script>
@endsection

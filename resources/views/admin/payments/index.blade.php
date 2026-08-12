@extends('layouts.app')

@section('title', 'Payments')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-money-check-dollar text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Payments</h1>
    </div>

    <div class="flex gap-2 mb-4 text-sm">
        <a href="{{ route('admin.payments.index', request()->except(['status', 'page'])) }}"
           class="px-3 py-1 rounded-md {{ !$status ? 'bg-[var(--brand-1)] text-white' : 'bg-white border border-slate-300 text-slate-600' }}">
            All
        </a>
        @foreach (['pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed', 'rejected' => 'Rejected'] as $value => $label)
            <a href="{{ route('admin.payments.index', array_merge(request()->except(['status', 'page']), ['status' => $value])) }}"
               class="px-3 py-1 rounded-md {{ $status === $value ? 'bg-[var(--brand-1)] text-white' : 'bg-white border border-slate-300 text-slate-600' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

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

    <form method="GET" action="{{ route('admin.payments.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
        @if ($status)
            <input type="hidden" name="status" value="{{ $status }}">
        @endif

        <div class="flex-1 min-w-[220px] max-w-sm">
            <label for="payment-q" class="sr-only">Search payments</label>
            <input type="search" id="payment-q" name="q" value="{{ $search }}"
                   placeholder="Search user, email, phone or reference..." class="pm-input text-sm">
        </div>

        <div>
            <label for="pay-period" class="sr-only">Filter by date</label>
            <select id="pay-period" name="period" onchange="pmTogglePaymentsDateRange(this)" class="pm-input text-sm">
                <option value="" @selected(!$period)>All time</option>
                <option value="daily" @selected($period === 'daily')>Today</option>
                <option value="weekly" @selected($period === 'weekly')>This week</option>
                <option value="monthly" @selected($period === 'monthly')>This month</option>
                <option value="annual" @selected($period === 'annual')>This year</option>
                <option value="range" @selected($period === 'range')>Custom range...</option>
            </select>
        </div>

        <div id="pay-date-range" class="flex items-end gap-2" style="{{ $period === 'range' ? '' : 'display: none;' }}">
            <input type="date" name="from" value="{{ $from }}" class="pm-input text-sm">
            <span class="text-slate-400 text-sm pb-2">to</span>
            <input type="date" name="to" value="{{ $to }}" class="pm-input text-sm">
        </div>


        <div>
            <label for="per_page" class="sr-only">Records per page</label>
            <select id="per_page" name="per_page" class="pm-input text-sm" onchange="this.form.submit()">
                @foreach ([10, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} / page</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">Filter</button>
        @if ($search || $period)
            <a href="{{ route('admin.payments.index', $status ? ['status' => $status] : []) }}" class="text-sm text-slate-500 hover:text-slate-700 transition-colors pb-2.5">Clear</a>
        @endif
    </form>

    <script>
        function pmTogglePaymentsDateRange(select) {
            var wrapper = document.getElementById('pay-date-range');
            if (wrapper) { wrapper.style.display = select.value === 'range' ? 'flex' : 'none'; }
        }
    </script>

    <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl overflow-x-auto" role="region" aria-label="Payments table" tabindex="0">
        <table class="min-w-full text-sm">
            <caption class="sr-only">Payment submissions with approve/reject actions for pending bank and mobile money payments.</caption>
            <thead class="bg-slate-50 text-left border-b border-slate-100">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">User</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Contact / Phone</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Method</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Amount</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Reference</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Status</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Date</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payments as $payment)
                    <tr>
                        <td class="px-4 py-3">{{ $payment->user->name ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($payment->contact_phone)
                                <a href="tel:{{ $payment->contact_phone }}" class="text-[var(--brand-1)] hover:underline">{{ $payment->contact_phone }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            {{ $payment->gateway->name ?? ucfirst(str_replace('_', ' ', $payment->method)) }}
                        </td>
                        <td class="px-4 py-3">{{ format_money_in($payment->amount, $payment->currency) }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $payment->reference ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $badgeColor = match($payment->status) {
                                    'completed' => 'text-emerald-700',
                                    'pending' => 'text-amber-600',
                                    'failed', 'rejected' => 'text-rose-600',
                                    default => 'text-slate-500',
                                };
                            @endphp
                            <span class="{{ $badgeColor }} font-medium">{{ ucfirst($payment->status) }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $payment->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($payment->status === 'pending' && $payment->method !== 'card')
                                <form action="{{ route('admin.payments.approve', $payment->id) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Approve this payment and activate the subscription?');">
                                    @csrf
                                    <button type="submit" class="text-emerald-700 hover:underline mr-3">Approve</button>
                                </form>
                                <button type="button" onclick="document.getElementById('reject-modal-{{ $payment->id }}').showModal()"
                                        class="text-rose-600 hover:underline">Reject</button>

                                <dialog id="reject-modal-{{ $payment->id }}" class="rounded-lg p-6 max-w-sm backdrop:bg-black/40">
                                    <h2 class="text-lg font-bold mb-2">Reject payment?</h2>
                                    <form action="{{ route('admin.payments.reject', $payment->id) }}" method="POST">
                                        @csrf
                                        <label for="notes-{{ $payment->id }}" class="block text-sm font-medium text-slate-700 mb-1">
                                            Reason (optional, visible to admins only)
                                        </label>
                                        <textarea id="notes-{{ $payment->id }}" name="notes" rows="2"
                                                  class="pm-input mb-4"></textarea>
                                        <div class="flex justify-end gap-3">
                                            <button type="button" onclick="document.getElementById('reject-modal-{{ $payment->id }}').close()"
                                                    class="text-sm text-slate-500 hover:underline">Cancel</button>
                                            <button type="submit" class="bg-rose-600 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md hover:bg-rose-700 transition-all">Reject</button>
                                        </div>
                                    </form>
                                </dialog>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">No payments yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

@if ($payments->total() > 0)
        <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-slate-500">
                Showing <span class="font-medium text-slate-700">{{ $payments->firstItem() }}</span>
                to <span class="font-medium text-slate-700">{{ $payments->lastItem() }}</span>
                of <span class="font-medium text-slate-700">{{ $payments->total() }}</span> records
            </p>
            <div>{{ $payments->links() }}</div>
        </div>
    @endif
@endsection

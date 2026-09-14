@extends('layouts.app')

@section('title', 'Request Extra Recording Quota')

@section('content')
<div class="max-w-xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-900">Request Extra Recording Quota</h1>
        <a href="{{ route('extra-requests.index') }}" class="text-sm text-slate-600 hover:underline">Back to Requests</a>
    </div>

    <div class="bg-white shadow rounded-lg p-6 border border-slate-200">
        <form action="{{ route('extra-requests.store') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Quota Package (Minutes)</label>
                <select name="quota_amount" id="quota_amount" class="w-full rounded-lg border-slate-300 focus:border-[var(--brand-1)] focus:ring-[var(--brand-1)]" onchange="updateAmount(this)">
                    <option value="60" data-amount="5000">60 Minutes - 5,000 UGX</option>
                    <option value="120" data-amount="9000">120 Minutes - 9,000 UGX</option>
                    <option value="300" data-amount="20000">300 Minutes - 20,000 UGX</option>
                    <option value="600" data-amount="35000">600 Minutes - 35,000 UGX</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Amount (UGX)</label>
                <input type="number" step="0.01" name="amount" id="amount" value="5000" class="w-full rounded-lg border-slate-300 focus:border-[var(--brand-1)] focus:ring-[var(--brand-1)]" required readonly>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description / Notes</label>
                <textarea name="description" rows="3" class="w-full rounded-lg border-slate-300 focus:border-[var(--brand-1)] focus:ring-[var(--brand-1)]" placeholder="Optional notes for your request...">Additional transcription minutes for meetings.</textarea>
            </div>

            <button type="submit" class="w-full py-3 bg-[var(--brand-1)] text-white font-medium rounded-lg shadow hover:opacity-90 transition">
                Create Request & Proceed to Payment
            </button>
        </form>
    </div>
</div>

<script>
function updateAmount(select) {
    const selectedOption = select.options[select.selectedIndex];
    const amount = selectedOption.getAttribute('data-amount');
    document.getElementById('amount').value = amount;
}
</script>
@endsection

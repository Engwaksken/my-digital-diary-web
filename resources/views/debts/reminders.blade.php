@extends('layouts.app')
@section('title','Debt Reminders')
@section('content')
<div class="max-w-6xl mx-auto px-4 py-6 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Debt Reminders</h1>
            <p class="text-sm text-slate-500">Send a demand/reminder manually to the borrower/lender, yourself, or both.</p>
        </div>
        <a href="{{ route('debts.index') }}" class="px-3 py-2 rounded-lg border bg-white text-sm font-semibold">Back to Debts</a>
    </div>
    @forelse($debts as $debt)
        <form method="POST" action="{{ route('debts.reminders.send',$debt) }}" class="bg-white border rounded-2xl p-4 grid md:grid-cols-4 gap-3 items-end">
            @csrf
            <div class="md:col-span-1">
                <div class="font-bold">{{ $debt->person_name }}</div>
                <div class="text-xs text-slate-500">{{ ucfirst($debt->type) }} · {{ format_money($debt->amount) }}</div>
                <div class="text-xs text-slate-400 mt-1">{{ $debt->contact_email ?: 'No email' }} · {{ $debt->contact_phone ?: 'No phone' }}</div>
            </div>
            <label class="text-sm">Channel<select name="channel" class="pm-input mt-1 w-full"><option value="email">Email</option><option value="sms">SMS</option><option value="both">Email + SMS</option></select></label>
            <label class="text-sm">Recipient<select name="recipient_scope" class="pm-input mt-1 w-full"><option value="counterparty">Borrower / lender</option><option value="me">Me</option><option value="both">Both</option></select></label>
            <button class="px-4 py-2.5 rounded-lg bg-amber-600 text-white font-semibold"><i class="fa-solid fa-paper-plane mr-1"></i>Send Reminder</button>
            <textarea name="message" class="pm-input md:col-span-4" rows="2" placeholder="Optional custom reminder message. Leave blank to use the standard wording."></textarea>
        </form>
    @empty
        <div class="bg-white border rounded-2xl">
            <x-empty-state icon="fa-solid fa-hand-holding-dollar" title="No outstanding debts found." />
        </div>
    @endforelse
</div>
@endsection

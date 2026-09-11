@extends('layouts.app')
@section('title', 'Request Independent Account')
@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-slate-800 mb-2">Request Independent Account</h1>
    <p class="text-sm text-slate-500 mb-6">
        If you are no longer part of a Family, Small Team or Enterprise workspace,
        you can request activation as an independent My Digital Diary user.
    </p>

    <div class="pm-card-bg rounded-xl border border-slate-100 shadow-sm p-5">
        @if($pendingRequest)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="font-semibold text-amber-800">Request pending</p>
                <p class="text-sm text-amber-700 mt-1">
                    Your personal account and personal records remain safe while the request is reviewed.
                </p>
            </div>
        @else
            <form method="POST" action="{{ route('account.independent-activation.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="reason" class="text-sm font-semibold text-slate-700">Reason or note</label>
                    <textarea id="reason" name="reason" rows="4" class="pm-input mt-1">{{ old('reason') }}</textarea>
                </div>
                <button class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold">
                    Request Independent Activation
                </button>
            </form>
        @endif
    </div>
</div>
@endsection

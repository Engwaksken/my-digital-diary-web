@extends('layouts.app')
@section('title', 'Invalid Invitation')
@section('content')
<div class="mx-auto max-w-xl apple-surface rounded-2xl p-6 text-center">
    <i class="fa-solid fa-link-slash text-3xl text-slate-300"></i>
    <h1 class="mt-3 text-xl font-black text-slate-900">Invitation unavailable</h1>
    <p class="mt-2 text-sm text-slate-500">This invitation is invalid, expired, or has already been used.</p>
    <a href="{{ route('dashboard') }}" class="mt-4 inline-flex apple-btn rounded-xl px-4 py-2.5 text-sm font-bold">Go to Dashboard</a>
</div>
@endsection

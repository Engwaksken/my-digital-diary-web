@extends('layouts.app')

@section('title', 'Accept Workspace Invitation')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <x-page-header
        title="Accept Workspace Invitation"
        subtitle="Confirm that you want to join this workspace."
        icon="fa-solid fa-user-plus"
    />

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="space-y-2 text-sm text-slate-600">
            <p>
                You have been invited to join a My Digital Diary workspace as
                <strong class="text-slate-900">{{ ucfirst((string) $invitation->role) }}</strong>.
            </p>
            <p>Your personal diary information remains private unless you explicitly share it.</p>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('workspace.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Cancel
            </a>

            <form method="POST" action="{{ route('workspace.invitations.accept.store', ['token' => $token]) }}">
                @csrf
                <x-button type="submit" icon="fa-solid fa-check">Accept Invitation</x-button>
            </form>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@php
    $isFamilyTeam = isset($organization) && $organization?->plan?->category === 'family_team';
@endphp

@section('title', $isFamilyTeam ? 'Family & Team' : 'Organization')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid {{ $isFamilyTeam ? 'fa-people-roof' : 'fa-building-user' }} text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">
            {{ $organization?->name ?? ($isFamilyTeam ? 'Family & Team' : 'Organization') }}
        </h1>
    </div>

    @if (! $organization)
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 max-w-2xl">
            <p class="text-sm text-slate-500 mb-3">
                You don't have any team members to manage yet. This page is where the owner (or an admin)
                of a Family &amp; Small Team or Enterprise plan invites and manages members.
            </p>
            <a href="{{ route('subscription.show') }}" class="inline-flex items-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                <i class="fa-solid fa-arrow-up-right-dots" aria-hidden="true"></i>
                Upgrade to Family &amp; Small Team
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 max-w-3xl">
            <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-blue-400 p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Plan</p>
                <p class="text-lg font-bold text-slate-800">{{ $organization->plan?->name ?? '—' }}</p>
            </div>
            <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-emerald-400 p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Members Used</p>
                <p class="text-lg font-bold text-slate-800">{{ $organization->seatsUsed() }} / {{ $organization->seatLimit() }}</p>
            </div>
            <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-amber-400 p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Members Available</p>
                <p class="text-lg font-bold text-slate-800">{{ $organization->remainingSeats() }}</p>
            </div>
        </div>

        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 max-w-3xl mb-6">
            <h2 class="font-semibold text-slate-800 mb-3">Invite Someone</h2>
            <form method="POST" action="{{ route('organization.invite') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="flex-1 min-w-[12rem]">
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input type="email" id="email" name="email" required aria-required="true" class="pm-input" placeholder="teammate@example.com">
                </div>
                <div>
                    <label for="role" class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                    <select id="role" name="role" class="pm-input">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin (can also manage members)</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all"
                        {{ $organization->hasSeatAvailable() ? '' : 'disabled' }}>
                    Send Invite
                </button>
            </form>
            @error('email')
                <p role="alert" class="text-sm text-rose-600 mt-2">{{ $message }}</p>
            @enderror
            @if (! $organization->hasSeatAvailable())
                <p class="text-xs text-amber-600 mt-2">No member slots available — remove someone or upgrade your plan first.</p>
            @endif
        </div>

        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl overflow-x-auto max-w-3xl">
            <table class="min-w-full text-sm">
                <caption class="sr-only">Organization members and their membership status.</caption>
                <thead class="bg-slate-50 text-left border-b border-slate-100">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Email</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Role</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Status</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($members as $member)
                        <tr>
                            <td class="px-4 py-3">{{ $member->user?->email ?? $member->invited_email }}</td>
                            <td class="px-4 py-3">{{ ucfirst($member->role) }}</td>
                            <td class="px-4 py-3">
                                <span class="{{ $member->status === 'active' ? 'text-emerald-700' : ($member->status === 'invited' ? 'text-amber-600' : 'text-slate-400') }} font-medium">
                                    {{ ucfirst($member->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button type="button" onclick="document.getElementById('member-view-{{ $member->id }}').showModal()" class="text-slate-500 hover:text-slate-800 mr-3" title="View member"><i class="fa-solid fa-eye"></i><span class="sr-only">View member</span></button>
                                <dialog id="member-view-{{ $member->id }}" class="rounded-2xl p-0 pm-dialog-sm shadow-2xl backdrop:bg-slate-900/50 text-left">
                                    <div class="p-6"><div class="flex items-center justify-between mb-4"><h3 class="text-lg font-bold text-slate-800">Member Details</h3><button type="button" onclick="this.closest('dialog').close()" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark"></i></button></div>
                                    <dl class="space-y-3 text-sm"><div><dt class="text-xs uppercase text-slate-400">Name</dt><dd>{{ $member->user?->name ?? 'Pending invitation' }}</dd></div><div><dt class="text-xs uppercase text-slate-400">Email</dt><dd>{{ $member->user?->email ?? $member->invited_email }}</dd></div><div><dt class="text-xs uppercase text-slate-400">Role</dt><dd>{{ ucfirst($member->role) }}</dd></div><div><dt class="text-xs uppercase text-slate-400">Status</dt><dd>{{ ucfirst($member->status) }}</dd></div></dl></div>
                                </dialog>
                                @if ($member->status === 'inactive')
                                    <form method="POST" action="{{ route('organization.members.activate', $member->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-emerald-700 hover:underline mr-3">Reactivate</button>
                                    </form>
                                @elseif ($member->status === 'active')
                                    <form method="POST" action="{{ route('organization.members.deactivate', $member->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-amber-600 hover:underline mr-3">Deactivate</button>
                                    </form>
                                @endif
                                <button type="button" onclick="document.getElementById('replace-modal-{{ $member->id }}').showModal()" class="text-blue-600 hover:underline mr-3">Replace</button>
                                <form method="POST" action="{{ route('organization.members.remove', $member->id) }}" class="inline" onsubmit="return confirm('Remove this person and free up their member slot?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:underline">Remove</button>
                                </form>

                                <dialog id="replace-modal-{{ $member->id }}" class="rounded-2xl p-0 pm-dialog-sm shadow-2xl backdrop:bg-slate-900/50">
                                    <div class="p-6 text-left">
                                        <h3 class="text-lg font-bold text-slate-800 mb-3">Replace this member</h3>
                                        <p class="text-sm text-slate-500 mb-4">
                                            Removes {{ $member->user?->email ?? $member->invited_email }} and immediately invites someone new to take their place.
                                        </p>
                                        <form method="POST" action="{{ route('organization.members.replace', $member->id) }}">
                                            @csrf
                                            <label for="new_email_{{ $member->id }}" class="block text-sm font-medium text-slate-700 mb-1">New person's email</label>
                                            <input type="email" id="new_email_{{ $member->id }}" name="new_email" required aria-required="true" class="pm-input mb-3">
                                            <label for="new_role_{{ $member->id }}" class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                                            <select id="new_role_{{ $member->id }}" name="new_role" class="pm-input mb-4">
                                                <option value="staff">Staff</option>
                                                <option value="admin">Admin</option>
                                            </select>
                                            <div class="flex items-center gap-3">
                                                <button type="submit" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium">Replace</button>
                                                <button type="button" onclick="document.getElementById('replace-modal-{{ $member->id }}').close()" class="text-sm text-slate-500 hover:text-slate-700">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </dialog>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">No team members yet — invite your first one above.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($members && $members->hasPages())
            <div class="mt-4">{{ $members->links() }}</div>
        @endif
    @endif
@endsection

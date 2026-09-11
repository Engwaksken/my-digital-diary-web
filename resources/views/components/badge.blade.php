{{--
    Status badge.

    Props:
        color : green | amber | red | blue | gray | brand (default: gray)
        icon  : optional Font Awesome icon class

    Slot: badge text.

    Usage:
        <x-badge color="green">Active</x-badge>
        <x-badge color="red" icon="fa-solid fa-ban">Suspended</x-badge>
--}}
@props([
    'color' => 'gray',
    'icon' => null,
])

@php
    $colors = [
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'red' => 'bg-red-50 text-red-700 ring-red-600/20',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'gray' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        'brand' => 'bg-[var(--brand-1-tint-10)] text-[var(--brand-1)] ring-[var(--brand-1)]/20',
    ][$color] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20';
@endphp

<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $colors }}">
    @if ($icon)
        <i class="{{ $icon }}" aria-hidden="true"></i>
    @endif
    {{ $slot }}
</span>

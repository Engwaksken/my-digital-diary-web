{{--
    Loading spinner.

    Props:
        label : optional text label
        size  : sm | md | lg (default: md)

    Accessible with role="status" and aria-live="polite".

    Usage:
        <x-loading-state label="Loading..." size="lg" />
--}}
@props([
    'label' => null,
    'size' => 'md',
])

@php
    $sizes = [
        'sm' => 'w-5 h-5 border-2',
        'md' => 'w-8 h-8 border-[3px]',
        'lg' => 'w-12 h-12 border-4',
    ][$size] ?? 'w-8 h-8 border-[3px]';
@endphp

<div role="status" aria-live="polite" class="pm-loading-state flex flex-col items-center justify-center gap-3 py-8">
    <div class="{{ $sizes }} rounded-full border-[var(--brand-1)] border-t-transparent animate-spin" aria-hidden="true"></div>

    @if ($label)
        <p class="text-sm text-slate-500">{{ $label }}</p>
    @endif

    <span class="sr-only">Loading</span>
</div>

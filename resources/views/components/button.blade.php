{{--
    Standardized button.

    Props:
        type     : button | submit | reset (default: button)
        variant  : primary | secondary | danger | ghost (default: primary)
        size     : sm | md | lg (default: md)
        icon     : optional Font Awesome icon class
        disabled : disabled state
        loading  : show loading spinner

    Uses the existing .btn-primary styling for the primary variant.

    Usage:
        <x-button type="submit" variant="primary" icon="fa-solid fa-save">Save</x-button>
        <x-button variant="danger" size="sm">Delete</x-button>
--}}
@props([
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'disabled' => false,
    'loading' => false,
])

@php
    $variants = [
        'primary' => 'btn-primary text-white border-transparent shadow-sm hover:shadow-md',
        'secondary' => 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50 shadow-sm',
        'danger' => 'bg-red-600 text-white border-transparent shadow-sm hover:bg-red-700',
        'ghost' => 'bg-transparent text-slate-600 border-transparent hover:bg-slate-100',
    ][$variant] ?? 'btn-primary text-white border-transparent shadow-sm hover:shadow-md';

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs rounded-lg gap-1.5',
        'md' => 'px-4 py-2 text-sm rounded-xl gap-2',
        'lg' => 'px-6 py-3 text-base rounded-xl gap-2.5',
    ][$size] ?? 'px-4 py-2 text-sm rounded-xl gap-2';
@endphp

<button
    type="{{ $type }}"
    @if ($disabled || $loading) disabled @endif
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center font-semibold border transition-colors focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed ' . $variants . ' ' . $sizes]) }}
>
    @if ($loading)
        <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
        <span class="sr-only">Loading</span>
    @elseif ($icon)
        <i class="{{ $icon }}" aria-hidden="true"></i>
    @endif

    {{ $slot }}
</button>

{{--
    Standardized page header.

    Props:
        title    : page title (required)
        subtitle : optional subtitle text
        icon     : optional Font Awesome icon class (e.g. 'fa-solid fa-user')

    Slots:
        actions  : right-aligned action buttons

    Responsive: stacks on mobile (title/subtitle on top, actions below).

    Usage:
        <x-page-header title="Dashboard" subtitle="Welcome back" icon="fa-solid fa-gauge-high">
            <x-slot name="actions">
                <x-button>New</x-button>
            </x-slot>
        </x-page-header>
--}}
@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

<header class="pm-page-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div class="flex items-start gap-3 min-w-0">
        @if ($icon)
            <div class="shrink-0 w-11 h-11 rounded-xl bg-[var(--brand-1-tint-10)] text-[var(--brand-1)] flex items-center justify-center text-lg" aria-hidden="true">
                <i class="{{ $icon }}"></i>
            </div>
        @endif

        <div class="min-w-0">
            <h1 class="pm-heading text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 leading-tight">
                {{ $title }}
            </h1>

            @if ($subtitle)
                <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            {{ $actions }}
        </div>
    @endisset
</header>

{{--
    Empty state component.

    Props:
        icon    : Font Awesome icon class (default: fa-inbox)
        title   : title text
        message : optional message text

    Slots:
        action : optional action button(s)

    Centered, muted, with icon.

    Usage:
        <x-empty-state icon="fa-solid fa-folder-open" title="No projects yet" message="Create your first project to get started.">
            <x-slot name="action">
                <x-button>Create Project</x-button>
            </x-slot>
        </x-empty-state>
--}}
@props([
    'icon' => 'fa-solid fa-inbox',
    'title',
    'message' => null,
])

<div class="pm-empty-state flex flex-col items-center justify-center text-center px-6 py-12">
    <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-2xl mb-4" aria-hidden="true">
        <i class="{{ $icon }}"></i>
    </div>

    <h3 class="pm-heading text-base font-bold text-slate-700">{{ $title }}</h3>

    @if ($message)
        <p class="mt-1.5 text-sm text-slate-500 max-w-sm">{{ $message }}</p>
    @endif

    @isset($action)
        <div class="mt-5">
            {{ $action }}
        </div>
    @endisset
</div>

{{--
    Search input for listing pages.

    Props:
        name        : input name (default: 'search')
        value       : current search value
        placeholder : placeholder text
        action      : form action URL
        method      : form method (default: GET)

    Form with input + submit button, inline.

    Usage:
        <x-search-box action="{{ route('users.index') }}" placeholder="Search users..." value="{{ request('search') }}" />
--}}
@props([
    'name' => 'search',
    'value' => null,
    'placeholder' => 'Search...',
    'action' => null,
    'method' => 'GET',
])

<form
    method="{{ $method }}"
    @if ($action) action="{{ $action }}" @endif
    class="pm-search-box flex items-center gap-2"
    role="search"
>
    <div class="relative flex-1 min-w-0">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true"></i>
        <input
            type="search"
            name="{{ $name }}"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            aria-label="{{ $placeholder }}"
            class="pm-input pl-9"
        >
    </div>

    <button type="submit" class="btn-primary inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-xl shadow-sm hover:shadow-md transition-colors shrink-0">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <span class="hidden sm:inline">Search</span>
    </button>
</form>

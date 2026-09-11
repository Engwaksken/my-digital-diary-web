{{--
    Form action buttons row.

    Props:
        submitLabel : submit button label (default: "Save")
        cancelUrl   : optional cancel link URL
        cancelLabel : cancel button label (default: "Cancel")

    Right-aligned, wraps on mobile.

    Usage:
        <x-form-actions submitLabel="Create" cancelUrl="{{ route('users.index') }}" />
--}}
@props([
    'submitLabel' => 'Save',
    'cancelUrl' => null,
    'cancelLabel' => 'Cancel',
])

<div class="pm-form-actions flex flex-wrap items-center justify-end gap-2 mt-6">
    @if ($cancelUrl)
        <a
            href="{{ $cancelUrl }}"
            class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold rounded-xl bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 shadow-sm transition-colors"
        >
            {{ $cancelLabel }}
        </a>
    @endif

    <button
        type="submit"
        class="btn-primary inline-flex items-center justify-center gap-2 px-5 py-2 text-sm font-semibold text-white rounded-xl shadow-sm hover:shadow-md transition-colors"
    >
        {{ $submitLabel }}
    </button>
</div>

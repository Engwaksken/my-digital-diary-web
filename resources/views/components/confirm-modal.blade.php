{{--
    Reusable confirmation modal.

    Props:
        id          : dialog id (required)
        title       : modal title (default: "Please confirm")
        message     : confirmation message
        confirmText : confirm button label (default: "Confirm")
        cancelText  : cancel button label (default: "Cancel")
        variant     : danger | primary (default: danger)
        formAction  : URL the form submits to
        formMethod  : form method (default: POST)

    Uses the modal component internally. Includes a hidden CSRF field.

    Usage:
        <x-confirm-modal
            id="delete-record"
            title="Delete record?"
            message="This action cannot be undone."
            confirmText="Delete"
            formAction="{{ route('records.destroy', $record) }}"
        />
--}}
@props([
    'id',
    'title' => 'Please confirm',
    'message' => 'Are you sure you want to continue?',
    'confirmText' => 'Confirm',
    'cancelText' => 'Cancel',
    'variant' => 'danger',
    'formAction' => null,
    'formMethod' => 'POST',
])

@php
    $confirmClasses = $variant === 'danger'
        ? 'bg-red-600 text-white border-transparent shadow-sm hover:bg-red-700'
        : 'btn-primary text-white border-transparent shadow-sm hover:shadow-md';
@endphp

<x-modal :id="$id" :title="$title" size="sm">
    <div class="flex items-start gap-3">
        <div class="shrink-0 w-10 h-10 rounded-xl {{ $variant === 'danger' ? 'bg-red-50 text-red-600' : 'bg-[var(--brand-1-tint-10)] text-[var(--brand-1)]' }} flex items-center justify-center" aria-hidden="true">
            <i class="fa-solid {{ $variant === 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-question' }}"></i>
        </div>
        <p class="text-sm text-slate-600 leading-relaxed">{{ $message }}</p>
    </div>

    <x-slot name="footer">
        <button
            type="button"
            class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold rounded-xl bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 shadow-sm transition-colors"
            onclick="document.getElementById('{{ $id }}').close()"
        >
            {{ $cancelText }}
        </button>

        @if ($formAction)
            <form method="{{ $formMethod }}" action="{{ $formAction }}" class="inline">
                @csrf
                @if (strtolower($formMethod) !== 'post')
                    @method($formMethod)
                @endif
                <button type="submit" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold rounded-xl {{ $confirmClasses }} transition-colors">
                    {{ $confirmText }}
                </button>
            </form>
        @else
            <button type="button" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold rounded-xl {{ $confirmClasses }} transition-colors">
                {{ $confirmText }}
            </button>
        @endif
    </x-slot>
</x-modal>

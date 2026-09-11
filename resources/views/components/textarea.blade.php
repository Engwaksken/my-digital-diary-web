{{--
    Standardized textarea.

    Props:
        name        : textarea name (required)
        label       : label text (required)
        value       : default value
        rows        : number of rows (default: 3)
        required    : whether the field is required
        help        : help text below the field
        placeholder : placeholder text
        disabled    : disabled state
        error       : error message

    Same styling as the input component.

    Usage:
        <x-textarea name="bio" label="Bio" rows="4" />
--}}
@props([
    'name',
    'label',
    'value' => null,
    'rows' => 3,
    'required' => false,
    'help' => null,
    'placeholder' => null,
    'disabled' => false,
    'error' => null,
])

@php
    $fieldId = $attributes->get('id', $name);
    $helpId = $help ? $fieldId . '-help' : null;
    $errorId = $error ? $fieldId . '-error' : null;
    $describedBy = trim(implode(' ', array_filter([$helpId, $errorId]))) ?: null;
@endphp

<div class="pm-field">
    <label for="{{ $fieldId }}" class="block text-sm font-medium text-slate-700 mb-1.5">
        {{ $label }}
        @if ($required)
            <span class="text-red-500" aria-hidden="true">*</span>
            <span class="sr-only">(required)</span>
        @endif
    </label>

    <textarea
        id="{{ $fieldId }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        @if ($disabled) disabled @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        aria-invalid="{{ $error ? 'true' : 'false' }}"
        class="pm-input resize-y {{ $error ? 'border-red-400 focus:border-red-400 focus:ring-red-200' : '' }}"
    >{{ $value }}</textarea>

    @if ($help)
        <p id="{{ $helpId }}" class="mt-1.5 text-xs text-slate-500">{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $errorId }}" class="mt-1.5 text-xs font-medium text-red-600" role="alert">{{ $error }}</p>
    @endif
</div>

{{-- Label + input + hint + error in one tag instead of hand-assembling all four every time. --}}
@props([
    'name',
    'label',
    'type' => 'text',
    'placeholder' => null,
    'hint' => null,
    'value' => null,
    'required' => false,
    'autofocus' => false,
    'autocomplete' => null,
    'icon' => null,
])

@php
    $errorMessages = $errors->get($name);
    $hasError = count($errorMessages) > 0;
    $fieldValue = $value ?? old($name);

    // A sensible icon by field name/type if the caller didn't pick one.
    $resolvedIcon = $icon ?? match (true) {
        $type === 'email' => 'fa-solid fa-envelope',
        $type === 'password' => 'fa-solid fa-lock',
        $name === 'name' => 'fa-solid fa-user',
        $name === 'code' => 'fa-solid fa-shield-halved',
        default => null,
    };

    $inputClass = 'pm-input block mt-1 w-full' . ($resolvedIcon ? ' has-icon' : '');
@endphp

<div>
    <x-input-label :for="$name" :value="$label" />

    <div class="{{ $resolvedIcon ? 'auth-field-icon-wrap' : '' }}">
        @if ($resolvedIcon)
            <span class="auth-field-icon"><i class="{{ $resolvedIcon }}" aria-hidden="true"></i></span>
        @endif

        {{-- A plain <input>, not a nested x-text-input call — attribute
             forwarding through an extra layer of component indirection
             was the actual cause of the field losing its styling
             entirely (this renders identically to how every other input
             in the app is built, just with the pm-input class applied
             directly). --}}
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $fieldValue }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required aria-required="true" @endif
            @if ($autofocus) autofocus @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
            {{ $attributes->merge(['class' => $inputClass]) }}
        >
    </div>

    @if ($hint)
        <p class="auth-hint">{{ $hint }}</p>
    @endif
    <x-input-error :messages="$errorMessages" id="{{ $name }}-error" class="mt-2" />
</div>

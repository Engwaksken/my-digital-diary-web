{{-- Drop-in replacement for Breeze's default input-label component. --}}
@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-slate-700 mb-1']) }}>
    {{ $value ?? $slot }}
</label>

{{-- Status message shown after e.g. requesting a password reset link. --}}
@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-emerald-600 flex items-center gap-2']) }}>
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span>{{ $status }}</span>
    </div>
@endif

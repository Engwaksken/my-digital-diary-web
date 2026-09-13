@props([
    'date',
    'status' => null,
])

@php
    $isComplete = in_array(strtolower((string) $status), ['complete', 'completed'], true);
    $countdownDate = $date ? \Illuminate\Support\Carbon::parse($date)->startOfDay() : null;
    $days = $countdownDate ? today()->diffInDays($countdownDate, false) : null;
    $classes = $days === null
        ? 'text-slate-400'
        : ($days < 0 ? 'text-rose-600' : ($days === 0 ? 'text-amber-600' : 'text-emerald-600'));
    $text = $days === null
        ? null
        : ($days < 0 ? abs($days) . ' ' . \Illuminate\Support\Str::plural('day', abs($days)) . ' overdue' : ($days === 0 ? 'Due today' : $days . ' ' . \Illuminate\Support\Str::plural('day', $days) . ' left'));
@endphp

@if ($text && ! $isComplete)
    <span class="{{ $classes }} font-semibold whitespace-nowrap">
        <i class="fa-regular fa-clock mr-1" aria-hidden="true"></i>{{ $text }}
    </span>
@endif

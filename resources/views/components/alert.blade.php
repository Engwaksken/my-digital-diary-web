{{--
    Reusable alert system.

    Props:
        type        : success | error | warning | info  (default: info)
        message     : optional string message (falls back to slot)
        dismissible : whether to show a dismiss button (default: true)
        autoDismiss : auto-dismiss after 5s for success (default: true)

    Slot: message content (used when `message` prop is not provided).

    Accessibility:
        - role="alert" for errors/warnings, role="status" for success/info
        - aria-live="polite" for auto-dismissing alerts
        - Dismiss button labelled for screen readers

    Usage:
        <x-alert type="success" message="Saved successfully!" />
        <x-alert type="error">Something went wrong.</x-alert>
--}}
@props([
    'type' => 'info',
    'message' => null,
    'dismissible' => true,
    'autoDismiss' => true,
])

@php
    $config = [
        'success' => [
            'icon' => 'fa-circle-check',
            'role' => 'status',
            'classes' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            'iconClasses' => 'text-emerald-500',
        ],
        'error' => [
            'icon' => 'fa-circle-exclamation',
            'role' => 'alert',
            'classes' => 'bg-red-50 text-red-800 border-red-200',
            'iconClasses' => 'text-red-500',
        ],
        'warning' => [
            'icon' => 'fa-triangle-exclamation',
            'role' => 'alert',
            'classes' => 'bg-amber-50 text-amber-800 border-amber-200',
            'iconClasses' => 'text-amber-500',
        ],
        'info' => [
            'icon' => 'fa-circle-info',
            'role' => 'status',
            'classes' => 'bg-blue-50 text-blue-800 border-blue-200',
            'iconClasses' => 'text-blue-500',
        ],
    ][$type] ?? [
        'icon' => 'fa-circle-info',
        'role' => 'status',
        'classes' => 'bg-blue-50 text-blue-800 border-blue-200',
        'iconClasses' => 'text-blue-500',
    ];

    $shouldAutoDismiss = $autoDismiss && $type === 'success';
    $alertId = 'alert-' . \Illuminate\Support\Str::random(8);
@endphp

<div
    id="{{ $alertId }}"
    role="{{ $config['role'] }}"
    @if ($shouldAutoDismiss) aria-live="polite" @endif
    class="pm-alert relative flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {{ $config['classes'] }}"
    @if ($shouldAutoDismiss)
        data-auto-dismiss="5000"
    @endif
>
    <i class="fa-solid {{ $config['icon'] }} mt-0.5 {{ $config['iconClasses'] }}" aria-hidden="true"></i>

    <div class="flex-1 min-w-0">
        @if ($message)
            {{ $message }}
        @else
            {{ $slot }}
        @endif
    </div>

    @if ($dismissible)
        <button
            type="button"
            class="shrink-0 inline-flex items-center justify-center w-6 h-6 rounded-md text-current/60 hover:text-current/90 hover:bg-black/5 transition-colors"
            aria-label="Dismiss alert"
            onclick="this.closest('.pm-alert').remove()"
        >
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    @endif
</div>

@if ($shouldAutoDismiss)
    <script>
        (function () {
            var el = document.getElementById('{{ $alertId }}');
            if (!el) return;
            setTimeout(function () {
                el.style.transition = 'opacity 0.3s ease';
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 300);
            }, 5000);
        })();
    </script>
@endif

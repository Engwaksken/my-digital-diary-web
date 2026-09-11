{{--
    Accessible tab navigation.

    Props:
        tabs : array of [id => label] pairs, or array of ['id' => ..., 'label' => ..., 'active' => bool]
               e.g. [['id' => 'overview', 'label' => 'Overview', 'active' => true], ['id' => 'details', 'label' => 'Details']]

    Slots:
        content : tab panels. Each panel should have id="tab-panel-{id}" and
                  be shown/hidden via the component's JS state.

    Usage:
        <x-tabs :tabs="[['id' => 'overview', 'label' => 'Overview', 'active' => true], ['id' => 'details', 'label' => 'Details']]">
            <x-slot name="content">
                <div id="tab-panel-overview">Overview content</div>
                <div id="tab-panel-details">Details content</div>
            </x-slot>
        </x-tabs>
--}}
@props([
    'tabs' => [],
])

@php
    // Normalize tabs to a consistent shape.
    $normalized = collect($tabs)->map(function ($tab) {
        if (is_array($tab)) {
            return [
                'id' => $tab['id'] ?? key($tab),
                'label' => $tab['label'] ?? reset($tab),
                'active' => $tab['active'] ?? false,
            ];
        }
        return ['id' => $tab, 'label' => $tab, 'active' => false];
    })->values();

    $defaultActive = $normalized->firstWhere('active', true)['id'] ?? $normalized->first()['id'] ?? null;
    $tabsId = 'tabs-' . \Illuminate\Support\Str::random(6);
@endphp

<div
    id="{{ $tabsId }}"
    class="pm-tabs"
    data-active-tab="{{ $defaultActive }}"
>
    <div role="tablist" aria-label="Tabs" class="flex gap-1 border-b border-slate-200 overflow-x-auto">
        @foreach ($normalized as $tab)
            <button
                type="button"
                role="tab"
                id="tab-{{ $tab['id'] }}"
                aria-selected="{{ $tab['active'] ? 'true' : 'false' }}"
                aria-controls="tab-panel-{{ $tab['id'] }}"
                tabindex="{{ $tab['active'] ? '0' : '-1' }}"
                data-tab-id="{{ $tab['id'] }}"
                class="px-4 py-2.5 text-sm font-semibold whitespace-nowrap border-b-2 -mb-px transition-colors {{ $tab['active'] ? 'border-[var(--brand-1)] text-[var(--brand-1)]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}"
            >
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

    <div class="pt-4">
        {{ $content }}
    </div>
</div>

<script>
    (function () {
        var tabsEl = document.getElementById('{{ $tabsId }}');
        if (!tabsEl) return;

        var buttons = tabsEl.querySelectorAll('[role="tab"]');
        var activeTab = tabsEl.dataset.activeTab;

        function showTab(tabId) {
            activeTab = tabId;

            buttons.forEach(function (btn) {
                var isActive = btn.dataset.tabId === tabId;
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                btn.setAttribute('tabindex', isActive ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isActive);
                btn.classList.toggle('text-[var(--brand-1)]', isActive);
                btn.classList.toggle('border-transparent', !isActive);
                btn.classList.toggle('text-slate-500', !isActive);
                btn.classList.toggle('hover:text-slate-700', !isActive);
                btn.classList.toggle('hover:border-slate-300', !isActive);
            });

            // Show/hide panels.
            var panels = tabsEl.querySelectorAll('[id^="tab-panel-"]');
            panels.forEach(function (panel) {
                panel.style.display = panel.id === 'tab-panel-' + tabId ? '' : 'none';
            });
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                showTab(btn.dataset.tabId);
            });

            btn.addEventListener('keydown', function (e) {
                var idx = Array.prototype.indexOf.call(buttons, btn);
                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    var next = buttons[(idx + 1) % buttons.length];
                    next.focus();
                    showTab(next.dataset.tabId);
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    var prev = buttons[(idx - 1 + buttons.length) % buttons.length];
                    prev.focus();
                    showTab(prev.dataset.tabId);
                }
            });
        });

        // Initialize: hide all panels except the active one.
        var panels = tabsEl.querySelectorAll('[id^="tab-panel-"]');
        panels.forEach(function (panel) {
            panel.style.display = panel.id === 'tab-panel-' + activeTab ? '' : 'none';
        });
    })();
</script>

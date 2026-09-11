@php
    $pmAiModule = $aiModule ?? $routeName ?? '';
    $pmAiTriggerFields = $aiTriggerFields ?? [];
    $pmAiContextFields = $aiContextFields ?? [];
    $pmAiButtonLabel = $aiButtonLabel ?? 'AI Generate';
    $pmAiHelpText = $aiHelpText ?? 'Enter a topic or key detail first, then let AI prepare an editable draft.';
@endphp

<div class="mt-4 rounded-xl border border-violet-100 bg-violet-50/60 p-4"
     data-pm-ai-form-assist
     data-module="{{ $pmAiModule }}"
     data-trigger-fields='@json(array_values($pmAiTriggerFields))'
     data-context-fields='@json(array_values($pmAiContextFields))'>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-slate-800">
                <i class="fa-solid fa-wand-magic-sparkles mr-1.5 text-violet-600"></i>
                AI writing support
            </p>
            <p class="mt-1 text-xs leading-5 text-slate-600">
                {{ $pmAiHelpText }}
            </p>
        </div>

        <button type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                data-pm-ai-generate
                disabled>
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <span>{{ $pmAiButtonLabel }}</span>
        </button>
    </div>

    <p class="mt-2 hidden text-xs font-medium text-slate-500"
       data-pm-ai-status
       aria-live="polite"></p>
</div>

<script>
(function () {
    'use strict';

    const script = document.currentScript;
    const root = script?.previousElementSibling;

    if (!root || !root.matches('[data-pm-ai-form-assist]')) {
        return;
    }

    const triggerFields = JSON.parse(root.dataset.triggerFields || '[]');
    const contextFields = JSON.parse(root.dataset.contextFields || '[]');
    const button = root.querySelector('[data-pm-ai-generate]');
    const status = root.querySelector('[data-pm-ai-status]');
    const moduleName = root.dataset.module || '';

    function fieldElement(name) {
        return document.getElementById('field-' + name)
            || document.querySelector('[name="' + CSS.escape(name) + '"]');
    }

    function readValue(name) {
        const el = fieldElement(name);

        if (!el) {
            return '';
        }

        if (el instanceof RadioNodeList) {
            return el.value || '';
        }

        if (el.type === 'checkbox') {
            return el.checked ? (el.value || '1') : '';
        }

        return String(el.value || '').trim();
    }

    function firstTopic() {
        for (const field of triggerFields) {
            const value = readValue(field);

            if (value !== '') {
                return value;
            }
        }

        return '';
    }

    function refreshButton() {
        if (!button) {
            return;
        }

        button.disabled = firstTopic() === '';
    }

    function showStatus(message, isError) {
        if (!status) {
            return;
        }

        status.textContent = message || '';
        status.classList.toggle('hidden', !message);
        status.classList.toggle('text-rose-600', !!isError);
        status.classList.toggle('text-emerald-700', !isError && !!message);
    }

    function setField(name, value) {
        const el = fieldElement(name);

        if (!el || value === null || typeof value === 'undefined') {
            return;
        }

        const stringValue = String(value).trim();

        if (stringValue === '') {
            return;
        }

        /*
         * Never overwrite information the user has already written.
         * AI fills only blank fields, so New and Edit both remain safe.
         */
        if (String(el.value || '').trim() !== '') {
            return;
        }

        if (el.tagName === 'SELECT') {
            const optionExists = Array.from(el.options || [])
                .some(option => option.value === stringValue);

            if (!optionExists) {
                return;
            }
        }

        el.value = stringValue;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    async function generate() {
        const topic = firstTopic();

        if (!topic) {
            showStatus('Add a title, topic or connection type first.', true);
            refreshButton();
            return;
        }

        const context = {};

        contextFields.forEach(function (field) {
            const value = readValue(field);

            if (value !== '') {
                context[field] = value;
            }
        });

        button.disabled = true;
        button.dataset.originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Generating...</span>';
        showStatus('Preparing an editable draft...', false);

        try {
            const response = await fetch('{{ url('/ai/form-assist') }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    module: moduleName,
                    topic: topic,
                    context: context
                })
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok || payload.ok === false) {
                throw new Error(payload.message || 'AI could not prepare a draft right now.');
            }

            const fields = payload.data || {};

            Object.keys(fields).forEach(function (name) {
                setField(name, fields[name]);
            });

            showStatus(
                payload.message || 'AI draft generated. Review and edit it before saving.',
                false
            );
        } catch (error) {
            showStatus(
                error?.message || 'AI could not prepare a draft right now. Your form was not changed.',
                true
            );
        } finally {
            button.innerHTML = button.dataset.originalHtml || '<i class="fa-solid fa-wand-magic-sparkles"></i><span>AI Generate</span>';
            refreshButton();
        }
    }

    triggerFields.forEach(function (field) {
        const el = fieldElement(field);

        if (!el) {
            return;
        }

        el.addEventListener('input', refreshButton);
        el.addEventListener('change', refreshButton);
    });

    if (button) {
        button.addEventListener('click', generate);
    }

    /*
     * The same CRUD form is reused for both New and Edit modals.
     * Re-check state whenever a dialog opens so AI Generate works in both.
     */
    const observer = new MutationObserver(refreshButton);
    observer.observe(document.body, {
        subtree: true,
        attributes: true,
        attributeFilter: ['open']
    });

    document.addEventListener('DOMContentLoaded', refreshButton);
    window.setTimeout(refreshButton, 0);
})();
</script>

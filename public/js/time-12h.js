(() => {
    'use strict';

    const READY = 'pmTime12Ready';

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function normaliseTime(raw) {
        const value = String(raw || '').trim();
        if (!value) return '';

        // Accept database values such as 21:00:00 and normal HTML values 21:00.
        let match = value.match(/(?:^|[ T])(\d{1,2}):(\d{2})(?::\d{2})?(?:$|\s)/);
        if (!match) match = value.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
        if (!match) return '';

        const hour = Number(match[1]);
        const minute = Number(match[2]);
        if (hour < 0 || hour > 23 || minute < 0 || minute > 59) return '';
        return `${pad(hour)}:${pad(minute)}`;
    }

    function parseDateTime(raw) {
        const value = String(raw || '').trim();
        if (!value) return { date: '', time: '' };

        const match = value.match(/^(\d{4}-\d{2}-\d{2})[T ](\d{1,2}:\d{2})(?::\d{2})?/);
        if (!match) return { date: '', time: '' };
        return { date: match[1], time: normaliseTime(match[2]) };
    }

    function to12Hour(value24) {
        const value = normaliseTime(value24);
        if (!value) return { hour: '', minute: '', meridiem: 'AM' };
        const [h, m] = value.split(':').map(Number);
        return {
            hour: String((h % 12) || 12),
            minute: pad(m),
            meridiem: h >= 12 ? 'PM' : 'AM',
        };
    }

    function to24Hour(hour12, minute, meridiem) {
        const h = Number(hour12);
        const m = Number(minute);
        if (!h || h < 1 || h > 12 || Number.isNaN(m) || m < 0 || m > 59) return '';
        let hour = h % 12;
        if (String(meridiem).toUpperCase() === 'PM') hour += 12;
        return `${pad(hour)}:${pad(m)}`;
    }

    function makeOption(value, label = value) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        return option;
    }

    function buildClock(required) {
        const clock = document.createElement('div');
        clock.className = 'pm-time12-clock';

        const hour = document.createElement('select');
        hour.className = 'pm-time12-hour';
        hour.setAttribute('aria-label', 'Hour');
        hour.appendChild(makeOption('', 'Hour'));
        for (let i = 1; i <= 12; i++) hour.appendChild(makeOption(String(i), String(i)));

        const separator = document.createElement('span');
        separator.className = 'pm-time12-separator';
        separator.textContent = ':';
        separator.setAttribute('aria-hidden', 'true');

        const minute = document.createElement('select');
        minute.className = 'pm-time12-minute';
        minute.setAttribute('aria-label', 'Minute');
        minute.appendChild(makeOption('', 'Min'));
        for (let i = 0; i < 60; i++) minute.appendChild(makeOption(pad(i), pad(i)));

        const meridiem = document.createElement('select');
        meridiem.className = 'pm-time12-meridiem';
        meridiem.setAttribute('aria-label', 'AM or PM');
        meridiem.appendChild(makeOption('AM', 'AM'));
        meridiem.appendChild(makeOption('PM', 'PM'));

        if (required) {
            hour.required = true;
            minute.required = true;
            meridiem.required = true;
        }

        clock.append(hour, separator, minute, meridiem);
        return { clock, hour, minute, meridiem };
    }

    function enhance(source) {
        if (!(source instanceof HTMLInputElement)) return;
        if (source.dataset[READY] === '1') return;
        if (source.type !== 'time' && source.type !== 'datetime-local') return;

        const originalType = source.type;
        const wasRequired = source.required;
        const wasDisabled = source.disabled;
        const initialValue = source.value || source.getAttribute('value') || '';

        source.dataset[READY] = '1';
        source.dataset.pmOriginalType = originalType;
        source.classList.add('pm-time12-source');
        source.required = false;

        const wrapper = document.createElement('div');
        wrapper.className = `pm-time12-control ${originalType === 'datetime-local' ? 'pm-time12-datetime' : 'pm-time12-only'}`;
        wrapper.dataset.for = source.id || source.name || '';

        let date = null;
        if (originalType === 'datetime-local') {
            date = document.createElement('input');
            date.type = 'date';
            date.className = 'pm-time12-date';
            date.setAttribute('aria-label', 'Date');
            date.required = wasRequired;
            date.disabled = wasDisabled;
            wrapper.appendChild(date);
        }

        const parts = buildClock(wasRequired);
        [parts.hour, parts.minute, parts.meridiem].forEach((el) => {
            el.disabled = wasDisabled;
        });
        wrapper.appendChild(parts.clock);
        source.insertAdjacentElement('afterend', wrapper);

        function syncFromSource() {
            if (originalType === 'datetime-local') {
                const parsed = parseDateTime(source.value || source.getAttribute('value') || '');
                if (date) date.value = parsed.date;
                const t12 = to12Hour(parsed.time);
                parts.hour.value = t12.hour;
                parts.minute.value = t12.minute;
                parts.meridiem.value = t12.meridiem;
            } else {
                const t12 = to12Hour(source.value || source.getAttribute('value') || '');
                parts.hour.value = t12.hour;
                parts.minute.value = t12.minute;
                parts.meridiem.value = t12.meridiem;
            }
        }

        function syncToSource(dispatch = true) {
            const time = to24Hour(parts.hour.value, parts.minute.value, parts.meridiem.value);
            source.value = originalType === 'datetime-local'
                ? ((date && date.value && time) ? `${date.value}T${time}` : '')
                : time;

            if (dispatch) {
                source.dispatchEvent(new Event('input', { bubbles: true }));
                source.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        wrapper._pmSyncFromSource = syncFromSource;
        wrapper._pmSyncToSource = syncToSource;

        if (date) date.addEventListener('change', () => syncToSource());
        parts.hour.addEventListener('change', () => syncToSource());
        parts.minute.addEventListener('change', () => syncToSource());
        parts.meridiem.addEventListener('change', () => syncToSource());

        source.addEventListener('pm-time12-sync', syncFromSource);
        source.addEventListener('invalid', () => {
            wrapper.dataset.invalid = 'true';
            (date || parts.hour).focus();
        });
        wrapper.addEventListener('change', () => { wrapper.dataset.invalid = 'false'; });

        const form = source.form;
        if (form && !form.dataset.pmTime12ResetHook) {
            form.dataset.pmTime12ResetHook = '1';
            form.addEventListener('reset', () => {
                window.setTimeout(() => syncAll(form), 0);
            });
            form.addEventListener('submit', () => {
                syncAll(form, true);
            }, true);
        }

        // Store a canonical value immediately. This specifically converts
        // database strings such as 21:00:00 into 21:00 before Laravel validation.
        if (originalType === 'time') {
            source.value = normaliseTime(initialValue);
        } else {
            const parsed = parseDateTime(initialValue);
            source.value = parsed.date && parsed.time ? `${parsed.date}T${parsed.time}` : '';
        }
        syncFromSource();
    }

    function syncAll(root = document, toSource = false) {
        root.querySelectorAll('.pm-time12-control').forEach((wrapper) => {
            if (toSource && typeof wrapper._pmSyncToSource === 'function') wrapper._pmSyncToSource(false);
            if (!toSource && typeof wrapper._pmSyncFromSource === 'function') wrapper._pmSyncFromSource();
        });
    }

    function enhanceAll(root = document) {
        if (root.matches && root.matches('input[type="time"], input[type="datetime-local"]')) enhance(root);
        if (root.querySelectorAll) {
            root.querySelectorAll('input[type="time"], input[type="datetime-local"]').forEach(enhance);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        enhanceAll(document);
        syncAll(document);

        document.querySelectorAll('dialog').forEach((dialog) => {
            dialog.addEventListener('close', () => syncAll(dialog));
        });
    });

    // Forms such as Meetings add time fields dynamically.
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) enhanceAll(node);
            });

            if (mutation.type === 'attributes' && mutation.attributeName === 'open') {
                const dialog = mutation.target;
                if (dialog instanceof HTMLDialogElement && dialog.open) {
                    // Existing page scripts usually fill the hidden source values
                    // immediately before showModal(); refresh the AM/PM UI now.
                    window.setTimeout(() => syncAll(dialog), 0);
                }
            }
        }
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['open'],
    });

    // Public helper for custom page scripts that change a time value directly.
    window.pmSync12HourTimeControls = (root = document) => syncAll(root);
})();

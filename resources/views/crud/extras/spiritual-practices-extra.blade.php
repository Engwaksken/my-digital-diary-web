<script>
(function () {
    'use strict';

    function closestFieldWrapper(input) {
        return input ? input.closest('div') : null;
    }

    function syncSpiritualRecurrenceFields() {
        const repeat = document.getElementById('field-recurrence_frequency');
        const weekly = document.getElementById('field-recurrence_days_of_week');
        const until = document.getElementById('field-recurrence_ends_at');

        if (!repeat) return;

        const weeklyWrap = closestFieldWrapper(weekly);
        const untilWrap = closestFieldWrapper(until);
        const recurring = repeat.value !== '';

        if (weeklyWrap) {
            weeklyWrap.style.display = repeat.value === 'weekly' ? '' : 'none';
            if (repeat.value !== 'weekly' && weekly) weekly.value = '';
        }

        if (untilWrap) {
            untilWrap.style.display = recurring ? '' : 'none';
            if (!recurring && until) until.value = '';
        }
    }

    document.addEventListener('change', function (event) {
        if (event.target && event.target.id === 'field-recurrence_frequency') {
            syncSpiritualRecurrenceFields();
        }
    });

    document.addEventListener('DOMContentLoaded', syncSpiritualRecurrenceFields);

    const observer = new MutationObserver(syncSpiritualRecurrenceFields);
    observer.observe(document.body, { subtree: true, attributes: true, attributeFilter: ['open'] });
})();
</script>

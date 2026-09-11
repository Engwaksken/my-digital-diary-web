<script>
(function () {
    'use strict';

    function wrap(el) {
        return el ? el.closest('div') : null;
    }

    function syncSpiritualFields() {
        const repeat = document.getElementById('field-recurrence_frequency');
        const weekly = document.getElementById('field-recurrence_days_of_week');
        const until = document.getElementById('field-recurrence_ends_at');
        const faith = document.getElementById('field-faith_path');
        const custom = document.getElementById('field-custom_faith_path');

        if (repeat) {
            if (wrap(weekly)) {
                wrap(weekly).style.display = repeat.value === 'weekly' ? '' : 'none';
            }

            if (wrap(until)) {
                wrap(until).style.display = repeat.value ? '' : 'none';
            }
        }

        if (faith && wrap(custom)) {
            wrap(custom).style.display = faith.value === 'Custom' ? '' : 'none';
        }
    }

    document.addEventListener('change', function (event) {
        if ([
            'field-recurrence_frequency',
            'field-faith_path'
        ].includes(event.target?.id)) {
            syncSpiritualFields();
        }
    });

    document.addEventListener('DOMContentLoaded', syncSpiritualFields);

    new MutationObserver(syncSpiritualFields).observe(document.body, {
        subtree: true,
        attributes: true,
        attributeFilter: ['open']
    });
})();
</script>

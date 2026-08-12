{{--
    Adds a "which specific item(s)" multi-select to the reminder create/
    edit modal, appearing right after "Related Module" — when that
    dropdown changes, this fetches the user's actual records from the
    chosen module (via ReminderController::itemsForModule) and lets them
    link the reminder to one or more specific ones. Also warns (a
    non-blocking flash message, shown after saving — see
    ReminderController::syncItemsAndCheckOverlap()) if the selected
    items' own date/times land suspiciously close together.
--}}
<script>
    (function () {
        function pmInjectReminderItemsUi() {
            var moduleField = document.getElementById('field-module');
            if (!moduleField || document.getElementById('pm-reminder-items-wrapper')) { return; }

            var moduleWrapper = moduleField.closest('div');
            if (!moduleWrapper) { return; }

            var wrapper = document.createElement('div');
            wrapper.id = 'pm-reminder-items-wrapper';
            wrapper.style.display = 'none';
            wrapper.innerHTML =
                '<label for="pm-reminder-item-ids" class="block text-sm font-medium text-slate-700 mb-1">Specific item(s) (optional)</label>' +
                '<select id="pm-reminder-item-ids" name="item_ids[]" multiple size="4" class="pm-input"></select>' +
                '<p class="text-xs text-slate-400 mt-1">Hold Ctrl/Cmd to select more than one. Leave nothing selected to keep this reminder general to the whole module.</p>';
            moduleWrapper.parentNode.insertBefore(wrapper, moduleWrapper.nextSibling);

            moduleField.addEventListener('change', function () {
                pmLoadReminderItemsForModule(moduleField.value);
            });

            // If editing an existing reminder that already has a module
            // selected, load its items immediately rather than waiting
            // for a change event that may never fire.
            if (moduleField.value) {
                pmLoadReminderItemsForModule(moduleField.value);
            }
        }

        function pmLoadReminderItemsForModule(module) {
            var wrapper = document.getElementById('pm-reminder-items-wrapper');
            var select = document.getElementById('pm-reminder-item-ids');
            if (!wrapper || !select) { return; }

            if (!module || module === 'budget' || module === 'custom') {
                wrapper.style.display = 'none';
                select.innerHTML = '';
                return;
            }

            select.innerHTML = '<option disabled>Loading...</option>';
            wrapper.style.display = 'block';

            fetch('{{ route('reminders.items-for-module') }}?module=' + encodeURIComponent(module))
                .then(function (response) { return response.json(); })
                .then(function (items) {
                    if (!items.length) {
                        select.innerHTML = '<option disabled>No records found in this module yet.</option>';
                        return;
                    }
                    select.innerHTML = items.map(function (item) {
                        return '<option value="' + item.id + '">' + item.label.replace(/</g, '&lt;') + '</option>';
                    }).join('');
                })
                .catch(function () {
                    select.innerHTML = '<option disabled>Could not load items.</option>';
                });
        }

        document.addEventListener('DOMContentLoaded', pmInjectReminderItemsUi);
        document.addEventListener('click', function (event) {
            if (event.target.closest('[onclick*="openCrudCreateModal"], [onclick*="openCrudEditModal"]')) {
                setTimeout(pmInjectReminderItemsUi, 50);
            }
        });
    })();
</script>

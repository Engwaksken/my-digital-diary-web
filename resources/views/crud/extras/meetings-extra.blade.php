{{--
    "Schedule Multiple" — lets a user set up one meeting's shared details
    (title, location, attendees, status, notes) once, then add as many
    date+time slots as needed (e.g. a recurring standing meeting, or a few
    proposed times) — submits all of them in one request, creating one
    independent Meeting row per slot.
--}}
<dialog id="meeting-multi-modal" aria-labelledby="meeting-multi-title" class="rounded-2xl p-0 pm-dialog-xl shadow-2xl backdrop:bg-slate-900/50">
    <form method="POST" action="{{ route('meetings.store-multiple') }}" id="meeting-multi-form" class="p-6 space-y-5">
        @csrf

        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-calendar-plus text-sm" aria-hidden="true"></i>
                </div>
                <h2 id="meeting-multi-title" class="text-lg font-bold text-slate-800">Schedule Multiple Meetings</h2>
            </div>
            <button type="button" onclick="document.getElementById('meeting-multi-modal').close()"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" aria-label="Close dialog">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <p class="text-sm text-slate-500">
            Shared details below apply to every time slot — useful for a recurring standing
            meeting or a few proposed times for the same topic.
        </p>

        <div>
            <label for="multi-title" class="block text-sm font-medium text-slate-700 mb-1">
                Title <span class="text-rose-500" aria-hidden="true">*</span>
            </label>
            <input type="text" id="multi-title" name="title" required aria-required="true"
                   class="pm-input">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="multi-location" class="block text-sm font-medium text-slate-700 mb-1">Location / Video Link</label>
                <input type="text" id="multi-location" name="location"
                       class="pm-input">
            </div>
            <div>
                <label for="multi-attendees" class="block text-sm font-medium text-slate-700 mb-1">Attendees</label>
                <input type="text" id="multi-attendees" name="attendees"
                       class="pm-input">
            </div>
        </div>

        <div>
            <label for="multi-status" class="block text-sm font-medium text-slate-700 mb-1">Status</label>
            <select id="multi-status" name="status"
                    class="pm-input">
                <option value="scheduled" selected>Scheduled</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <div>
            <span class="block text-sm font-medium text-slate-700 mb-2">Date &amp; Time Slots</span>
            <div id="meeting-slots" class="space-y-3">
                <div class="meeting-slot flex flex-col sm:flex-row gap-3 items-start sm:items-end border border-slate-200 rounded-lg p-3">
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Start</label>
                        <input type="datetime-local" name="slots[0][start_at]" required
                               class="pm-input text-sm">
                    </div>
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-medium text-slate-500 mb-1">End (optional)</label>
                        <input type="datetime-local" name="slots[0][end_at]"
                               class="pm-input text-sm">
                    </div>
                    <button type="button" onclick="pmRemoveMeetingSlot(this)"
                            class="text-rose-500 hover:text-rose-700 text-sm px-2 py-2 shrink-0" aria-label="Remove this time slot">
                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <button type="button" onclick="pmAddMeetingSlot()"
                    class="mt-3 inline-flex items-center gap-2 text-sm text-[var(--brand-1)] hover:text-[var(--brand-1-dark)] transition-colors">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span>Add another time</span>
            </button>
        </div>

        <div>
            <label for="multi-notes" class="block text-sm font-medium text-slate-700 mb-1">Notes / Agenda</label>
            <textarea id="multi-notes" name="notes" rows="3"
                      class="pm-input"></textarea>
        </div>

        <div class="flex items-center gap-3 pt-2 border-t border-slate-100 mt-2">
            <button type="submit" class="inline-flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                <span>Schedule All</span>
            </button>
            <button type="button" onclick="document.getElementById('meeting-multi-modal').close()" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
                Cancel
            </button>
        </div>
    </form>
</dialog>

<script>
    var pmMeetingSlotIndex = 1;

    function pmAddMeetingSlot() {
        var container = document.getElementById('meeting-slots');
        var index = pmMeetingSlotIndex++;
        var row = document.createElement('div');
        row.className = 'meeting-slot flex flex-col sm:flex-row gap-3 items-start sm:items-end border border-slate-200 rounded-lg p-3';
        row.innerHTML =
            '<div class="flex-1 w-full">' +
                '<label class="block text-xs font-medium text-slate-500 mb-1">Start</label>' +
                '<input type="datetime-local" name="slots[' + index + '][start_at]" required ' +
                    'class="pm-input text-sm">' +
            '</div>' +
            '<div class="flex-1 w-full">' +
                '<label class="block text-xs font-medium text-slate-500 mb-1">End (optional)</label>' +
                '<input type="datetime-local" name="slots[' + index + '][end_at]" ' +
                    'class="pm-input text-sm">' +
            '</div>' +
            '<button type="button" onclick="pmRemoveMeetingSlot(this)" ' +
                'class="text-rose-500 hover:text-rose-700 text-sm px-2 py-2 shrink-0" aria-label="Remove this time slot">' +
                '<i class="fa-solid fa-trash-can" aria-hidden="true"></i>' +
            '</button>';
        container.appendChild(row);
    }

    function pmRemoveMeetingSlot(button) {
        var container = document.getElementById('meeting-slots');
        if (container.querySelectorAll('.meeting-slot').length <= 1) {
            return; // always keep at least one slot
        }
        button.closest('.meeting-slot').remove();
    }
</script>

<script>
    // Injects a "Notes" link into each of the user's OWN meeting rows
    // (skipped for "Shared with you" rows, which have no Edit button to
    // key off of) — the generic crud table's Actions column only knows
    // about Edit/Delete, so this is the lightest way to add a per-module
    // action without touching the shared crud/index.blade.php template.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('#pm-crud-panel-table tbody tr').forEach(function (row) {
            var editBtn = row.querySelector('button[onclick^="openCrudEditModal"]');
            if (!editBtn) { return; }

            var match = editBtn.getAttribute('onclick').match(/\/meetings\/(\d+)/);
            if (!match) { return; }

            var notesLink = document.createElement('a');
            notesLink.href = '/meetings/' + match[1] + '/notes';
            notesLink.className = 'inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 hover:underline mr-3 transition-colors whitespace-nowrap';
            // Was icon-only with sr-only text before — genuinely
            // functional (recording, transcription, and AI summary
            // all live on that page) but easy to miss entirely since
            // nothing visible hinted at what it led to.
            notesLink.innerHTML = '<i class="fa-solid fa-microphone-lines text-xs" aria-hidden="true"></i> Recording &amp; Notes';

            var actionsCell = row.querySelector('td:last-child');
            if (actionsCell) {
                actionsCell.insertBefore(notesLink, actionsCell.firstChild);
            }
        });
    });
</script>

<script>
    (function () {
        // Replaces the plain "Attendees" text field with dynamic add/remove
        // email rows — serialized back into that SAME hidden text field
        // (comma-separated) right before submit, so the backend doesn't
        // need to change at all; MeetingController still just sees
        // `attendees` as a plain string either way.
        function pmAddAttendeeRow(container, prefillEmail) {
            var row = document.createElement('div');
            row.className = 'flex items-center gap-2 pm-attendee-row mb-1.5';
            row.innerHTML =
                '<input type="email" placeholder="participant@example.com" class="pm-input text-sm pm-attendee-email" value="' + (prefillEmail || '').replace(/"/g, '&quot;') + '">' +
                '<button type="button" class="text-rose-500 hover:text-rose-700 shrink-0" aria-label="Remove attendee" onclick="this.closest(\'.pm-attendee-row\').remove(); pmSyncAttendeesField();">' +
                    '<i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>' +
                '</button>';
            container.appendChild(row);
            row.querySelector('.pm-attendee-email').addEventListener('input', pmSyncAttendeesField);
        }

        window.pmSyncAttendeesField = function () {
            var hiddenField = document.getElementById('field-attendees');
            if (!hiddenField) { return; }
            var emails = Array.prototype.map.call(document.querySelectorAll('.pm-attendee-email'), function (input) {
                return input.value.trim();
            }).filter(Boolean);
            hiddenField.value = emails.join(', ');
        };

        function pmInjectAttendeesUi() {
            var hiddenField = document.getElementById('field-attendees');
            if (!hiddenField || document.getElementById('pm-attendees-ui-wrapper')) { return; }

            var fieldWrapper = hiddenField.closest('div');
            if (!fieldWrapper) { return; }

            hiddenField.type = 'hidden';

            var uiWrapper = document.createElement('div');
            uiWrapper.id = 'pm-attendees-ui-wrapper';
            uiWrapper.innerHTML =
                '<label class="block text-sm font-medium text-slate-700 mb-1">Attendees</label>' +
                '<div id="pm-attendees-rows"></div>' +
                '<button type="button" class="text-sm text-[var(--brand-1)] hover:underline" onclick="pmAddAttendeeRowHandler()">' +
                    '<i class="fa-solid fa-plus text-xs" aria-hidden="true"></i> Add participant' +
                '</button>' +
                '<p class="text-xs text-slate-400 mt-1">Each one gets an email invitation when you save.</p>';
            fieldWrapper.parentNode.insertBefore(uiWrapper, fieldWrapper.nextSibling);
            fieldWrapper.style.display = 'none';

            var container = document.getElementById('pm-attendees-rows');
            var existingEmails = hiddenField.value.split(',').map(function (e) { return e.trim(); }).filter(Boolean);
            if (existingEmails.length) {
                existingEmails.forEach(function (email) { pmAddAttendeeRow(container, email); });
            } else {
                pmAddAttendeeRow(container, '');
            }
        }

        window.pmAddAttendeeRowHandler = function () {
            var container = document.getElementById('pm-attendees-rows');
            if (container) { pmAddAttendeeRow(container, ''); }
        };

        document.addEventListener('click', function (event) {
            if (event.target.closest('[onclick*="openCrudCreateModal"], [onclick*="openCrudEditModal"]')) {
                setTimeout(function () {
                    // Re-injecting on every open would duplicate rows —
                    // clear the wrapper first so edit correctly re-seeds
                    // rows from THAT record's own attendees each time.
                    var existingWrapper = document.getElementById('pm-attendees-ui-wrapper');
                    if (existingWrapper) { existingWrapper.remove(); }
                    var hiddenField = document.getElementById('field-attendees');
                    if (hiddenField) { hiddenField.closest('div').style.display = ''; }
                    pmInjectAttendeesUi();
                }, 50);
            }
        });

        // Emails are only serialized into the real field right before
        // submit — not on every keystroke via a form-level listener —
        // since the form is shared across all crud modals and we don't
        // want to attach a submit listener that assumes attendees exists.
        document.addEventListener('submit', function (event) {
            if (event.target && event.target.id === 'crud-modal-form') {
                pmSyncAttendeesField();
            }
        }, true);
    })();
</script>

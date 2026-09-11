{{-- Meetings-specific additions for the generic CRUD page. --}}

<div class="mt-3 flex flex-wrap items-center gap-2">
    <button
        type="button"
        id="open-multiple-meetings"
        onclick="window.openMultipleMeetings?.()"
        class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold"
    >
        <i class="fa-solid fa-calendar-plus mr-1"></i>
        Schedule Multiple
    </button>
</div>

<dialog
    id="meeting-multi-modal"
    class="meeting-multi-dialog"
    aria-labelledby="meeting-multi-title"
>
    <form
        method="POST"
        action="{{ route('meetings.store-multiple') }}"
        id="meeting-multi-form"
        class="meeting-multi-form"
    >
        @csrf

        <div class="meeting-multi-header">
            <div class="flex items-center gap-3 min-w-0">
                <div class="meeting-modal-icon">
                    <i class="fa-solid fa-calendar-plus"></i>
                </div>

                <div class="min-w-0">
                    <h2
                        id="meeting-multi-title"
                        class="text-lg font-black text-slate-900"
                    >
                        Schedule Multiple Meetings
                    </h2>

                    <p class="mt-0.5 text-xs text-slate-500">
                        Shared details apply to every time slot.
                    </p>
                </div>
            </div>

            <button
                type="button"
                data-close-meeting-multi
                class="meeting-modal-close"
                aria-label="Close"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="meeting-multi-body">
            @if($errors->any())
                <x-alert type="error" :dismissible="false" :auto-dismiss="false">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="meeting-label">
                        Title <span class="text-rose-500">*</span>
                    </label>
                    <input
                        name="title"
                        value="{{ old('title') }}"
                        required
                        class="pm-input mt-1 w-full"
                    >
                </div>

                <div>
                    <label class="meeting-label">
                        Location / Video Link
                    </label>
                    <input
                        name="location"
                        value="{{ old('location') }}"
                        class="pm-input mt-1 w-full"
                    >
                </div>

                <div>
                    <label class="meeting-label">Attendees</label>
                    <input
                        name="attendees"
                        value="{{ old('attendees') }}"
                        class="pm-input mt-1 w-full"
                    >
                </div>

                <div>
                    <label class="meeting-label">Status</label>
                    <select
                        name="status"
                        class="pm-input mt-1 w-full"
                    >
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="flex items-center">
                    <label class="mt-5 flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2">
                        <input
                            type="checkbox"
                            name="set_reminder"
                            value="1"
                            @checked(old('set_reminder'))
                        >
                        <span class="text-sm font-bold">
                            Set reminder for each meeting
                        </span>
                    </label>
                </div>

                <div class="md:col-span-2">
                    <label class="meeting-label">
                        Notes / Agenda
                    </label>
                    <textarea
                        name="notes"
                        rows="3"
                        class="pm-input mt-1 w-full"
                    >{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="mt-5 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-black">
                        Date & Time Slots
                    </h3>
                    <p class="text-[11px] text-slate-500">
                        Uses a single 12-hour clock. End is optional.
                    </p>
                </div>

                <button
                    type="button"
                    id="add-meeting-slot"
                    class="apple-btn rounded-xl px-3 py-2 text-xs font-bold"
                >
                    <i class="fa-solid fa-plus mr-1"></i>
                    Add Slot
                </button>
            </div>

            <div
                id="meeting-slots"
                class="mt-3 space-y-3"
                data-next-index="1"
            >
                <div class="meeting-slot">
                    <div class="meeting-slot-head">
                        <strong>Slot 1</strong>
                        <button
                            type="button"
                            class="meeting-slot-remove"
                            hidden
                        >
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>

                    <div class="meeting-slot-grid">
                        <div>
                            <label class="meeting-label">
                                Start <span class="text-rose-500">*</span>
                            </label>

                            <div class="meeting-date-row">
                                <input
                                    type="date"
                                    name="slots[0][start_date]"
                                    required
                                    class="pm-input"
                                >
                            </div>

                            <div class="meeting-12h-row">
                                <select
                                    name="slots[0][start_hour]"
                                    required
                                    class="pm-input meeting-hour"
                                >
                                    <option value="">Hour</option>
                                    @for($h = 1; $h <= 12; $h++)
                                        <option value="{{ $h }}">{{ $h }}</option>
                                    @endfor
                                </select>

                                <span class="meeting-time-separator">:</span>

                                <select
                                    name="slots[0][start_minute]"
                                    required
                                    class="pm-input meeting-minute"
                                >
                                    <option value="">Min</option>
                                    @for($m = 0; $m < 60; $m += 5)
                                        <option value="{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}">
                                            {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                        </option>
                                    @endfor
                                </select>

                                <select
                                    name="slots[0][start_meridiem]"
                                    required
                                    class="pm-input meeting-meridiem"
                                >
                                    <option value="AM">AM</option>
                                    <option value="PM">PM</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="meeting-label">End</label>

                            <div class="meeting-date-row">
                                <input
                                    type="date"
                                    name="slots[0][end_date]"
                                    class="pm-input"
                                >
                            </div>

                            <div class="meeting-12h-row">
                                <select
                                    name="slots[0][end_hour]"
                                    class="pm-input meeting-hour"
                                >
                                    <option value="">Hour</option>
                                    @for($h = 1; $h <= 12; $h++)
                                        <option value="{{ $h }}">{{ $h }}</option>
                                    @endfor
                                </select>

                                <span class="meeting-time-separator">:</span>

                                <select
                                    name="slots[0][end_minute]"
                                    class="pm-input meeting-minute"
                                >
                                    <option value="">Min</option>
                                    @for($m = 0; $m < 60; $m += 5)
                                        <option value="{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}">
                                            {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                        </option>
                                    @endfor
                                </select>

                                <select
                                    name="slots[0][end_meridiem]"
                                    class="pm-input meeting-meridiem"
                                >
                                    <option value="AM">AM</option>
                                    <option value="PM">PM</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="meeting-multi-footer">
            <button
                type="button"
                data-close-meeting-multi
                class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold"
            >
                Cancel
            </button>

            <button
                type="submit"
                class="btn-primary rounded-xl px-5 py-2.5 text-sm font-bold text-white"
            >
                <i class="fa-solid fa-calendar-check mr-1"></i>
                Schedule Meetings
            </button>
        </div>
    </form>
</dialog>

<template id="meeting-slot-template">
    <div class="meeting-slot">
        <div class="meeting-slot-head">
            <strong>Slot __NUMBER__</strong>
            <button
                type="button"
                class="meeting-slot-remove"
            >
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>

        <div class="meeting-slot-grid">
            <div>
                <label class="meeting-label">
                    Start <span class="text-rose-500">*</span>
                </label>

                <div class="meeting-date-row">
                    <input
                        type="date"
                        name="slots[__INDEX__][start_date]"
                        required
                        class="pm-input"
                    >
                </div>

                <div class="meeting-12h-row">
                    <select
                        name="slots[__INDEX__][start_hour]"
                        required
                        class="pm-input meeting-hour"
                    >
                        <option value="">Hour</option>
                        @for($h = 1; $h <= 12; $h++)
                            <option value="{{ $h }}">{{ $h }}</option>
                        @endfor
                    </select>

                    <span class="meeting-time-separator">:</span>

                    <select
                        name="slots[__INDEX__][start_minute]"
                        required
                        class="pm-input meeting-minute"
                    >
                        <option value="">Min</option>
                        @for($m = 0; $m < 60; $m += 5)
                            <option value="{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}">
                                {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                            </option>
                        @endfor
                    </select>

                    <select
                        name="slots[__INDEX__][start_meridiem]"
                        required
                        class="pm-input meeting-meridiem"
                    >
                        <option value="AM">AM</option>
                        <option value="PM">PM</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="meeting-label">End</label>

                <div class="meeting-date-row">
                    <input
                        type="date"
                        name="slots[__INDEX__][end_date]"
                        class="pm-input"
                    >
                </div>

                <div class="meeting-12h-row">
                    <select
                        name="slots[__INDEX__][end_hour]"
                        class="pm-input meeting-hour"
                    >
                        <option value="">Hour</option>
                        @for($h = 1; $h <= 12; $h++)
                            <option value="{{ $h }}">{{ $h }}</option>
                        @endfor
                    </select>

                    <span class="meeting-time-separator">:</span>

                    <select
                        name="slots[__INDEX__][end_minute]"
                        class="pm-input meeting-minute"
                    >
                        <option value="">Min</option>
                        @for($m = 0; $m < 60; $m += 5)
                            <option value="{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}">
                                {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                            </option>
                        @endfor
                    </select>

                    <select
                        name="slots[__INDEX__][end_meridiem]"
                        class="pm-input meeting-meridiem"
                    >
                        <option value="AM">AM</option>
                        <option value="PM">PM</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</template>

@once
<style>
.meeting-label{display:block;font-size:.75rem;font-weight:800;color:#334155}
.meeting-multi-dialog{width:min(94vw,860px);max-width:860px;max-height:90dvh;padding:0;border:0;border-radius:20px;background:transparent}
.meeting-multi-dialog::backdrop{background:rgba(15,23,42,.58)}
.meeting-multi-form{display:grid;grid-template-rows:auto minmax(0,1fr) auto;max-height:90dvh;overflow:hidden;border-radius:20px;background:#fff}
.meeting-multi-header,.meeting-multi-footer{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:14px 18px;background:#fff}
.meeting-multi-header{border-bottom:1px solid #e2e8f0}
.meeting-multi-footer{justify-content:flex-end;border-top:1px solid #e2e8f0}
.meeting-multi-body{overflow-y:auto;padding:18px}
.meeting-modal-icon{display:grid;place-items:center;width:40px;height:40px;border-radius:12px;background:#eff6ff;color:#2563eb}
.meeting-modal-close{display:grid;place-items:center;width:36px;height:36px;border:1px solid #e2e8f0;border-radius:11px;background:#fff}
.meeting-slot{border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;overflow:hidden}
.meeting-slot-head{display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-bottom:1px solid #e2e8f0}
.meeting-slot-remove{color:#be123c}
.meeting-slot-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:12px}
.meeting-date-row{margin-top:4px}
.meeting-date-row .pm-input{width:100%}
.meeting-12h-row{display:grid;grid-template-columns:minmax(78px,1fr) auto minmax(78px,1fr) minmax(84px,.9fr);gap:7px;align-items:center;margin-top:8px}
.meeting-time-separator{font-weight:900;color:#64748b;text-align:center}
#meeting-multi-modal .pm-input{min-height:42px!important;padding-top:.5rem!important;padding-bottom:.5rem!important}
@media(max-width:640px){.meeting-slot-grid{grid-template-columns:1fr}.meeting-multi-body{padding:14px}}
@media(max-width:390px){.meeting-12h-row{grid-template-columns:1fr auto 1fr 82px;gap:5px}}
</style>

<script>
(function(){
    'use strict';

    const dialog = document.getElementById('meeting-multi-modal');
    const slots = document.getElementById('meeting-slots');
    const template = document.getElementById('meeting-slot-template');

    function openDialog(){
        if(!dialog) return;
        if(dialog.showModal){
            if(!dialog.open) dialog.showModal();
        } else {
            dialog.setAttribute('open','open');
        }
    }

    function closeDialog(){
        if(!dialog) return;
        if(dialog.close && dialog.open){
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
    }

    window.openMultipleMeetings = openDialog;
    window.openMeetingMultiModal = openDialog;

    document
        .querySelectorAll('[data-close-meeting-multi]')
        .forEach(button => button.addEventListener('click', closeDialog));

    function updateLabels(){
        const rows = slots?.querySelectorAll('.meeting-slot') || [];
        rows.forEach((row,index)=>{
            const label = row.querySelector('.meeting-slot-head strong');
            if(label) label.textContent = `Slot ${index + 1}`;

            const remove = row.querySelector('.meeting-slot-remove');
            if(remove) remove.hidden = rows.length <= 1;
        });
    }

    document.getElementById('add-meeting-slot')
        ?.addEventListener('click', ()=>{
            const index = Number(slots.dataset.nextIndex || '1');

            slots.insertAdjacentHTML(
                'beforeend',
                template.innerHTML
                    .replaceAll('__INDEX__', String(index))
                    .replaceAll('__NUMBER__', String(index + 1))
            );

            slots.dataset.nextIndex = String(index + 1);
            updateLabels();
        });

    slots?.addEventListener('click', event=>{
        const remove = event.target.closest('.meeting-slot-remove');
        if(!remove) return;

        remove.closest('.meeting-slot')?.remove();
        updateLabels();
    });

    updateLabels();

    @if($errors->has('slots') || $errors->has('slots.*'))
        openDialog();
    @endif
})();
</script>

<style>
/*
 * Normal New/Edit Meeting:
 * the form now contains NO input[type=time], so the global datetime enhancer
 * has nothing to duplicate.
 */
.meeting-normal-12h-group {
    display: grid;
    grid-template-columns: minmax(78px, 1fr) minmax(78px, 1fr) minmax(84px, .9fr);
    gap: 8px;
    align-items: end;
}

.meeting-reminder-control {
    position: relative;
    overflow: hidden;
    border: 1px solid #cbd5e1;
    border-radius: 13px;
    background: #f8fafc;
}

.meeting-reminder-control.is-enabled {
    border-color: #5eead4;
    background: #f0fdfa;
}

.meeting-reminder-control select {
    width: 100% !important;
    min-height: 46px !important;
    border: 0 !important;
    background: transparent !important;
    padding-left: 42px !important;
    font-weight: 800 !important;
    color: #334155 !important;
}

.meeting-reminder-control::before {
    content: '\f0f3';
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #0d9488;
    pointer-events: none;
}

@media (max-width: 640px) {
    .meeting-normal-12h-group {
        grid-template-columns: 1fr 1fr 90px;
    }
}
</style>

<script>
(function () {
    'use strict';

    function findField(name) {
        return document.querySelector(
            `form input[name="${name}"], form select[name="${name}"]`
        );
    }

    function fieldWrapper(control) {
        if (!control) return null;

        return control.closest(
            '[data-field], .form-group, .space-y-1, .mb-3, .mb-4'
        ) || control.parentElement;
    }

    function enhanceNormalMeetingForm() {
        /*
         * Defensive cleanup: no normal Meeting input[type=time] should exist
         * after this package. If an old cached modal still contains one,
         * remove it instead of allowing the global enhancer to duplicate it.
         */
        document
            .querySelectorAll(
                'form input[type="time"][name^="start_"], ' +
                'form input[type="time"][name^="end_"]'
            )
            .forEach((input) => {
                const wrapper = fieldWrapper(input);
                if (wrapper) wrapper.style.display = 'none';
            });

        const startHour = findField('start_hour');
        const startMinute = findField('start_minute');
        const startMeridiem = findField('start_meridiem');

        const endHour = findField('end_hour');
        const endMinute = findField('end_minute');
        const endMeridiem = findField('end_meridiem');

        /*
         * Keep the generic CRUD labels/validation intact but visually group
         * the three selectors into one obvious 12-hour clock row.
         */
        [
            [startHour, startMinute, startMeridiem],
            [endHour, endMinute, endMeridiem],
        ].forEach((group) => {
            const wrappers = group
                .map(fieldWrapper)
                .filter(Boolean);

            if (
                wrappers.length !== 3
                || wrappers[0].dataset.meetingGrouped === '1'
            ) {
                return;
            }

            const row = document.createElement('div');
            row.className = 'meeting-normal-12h-group';
            row.dataset.meeting12hRow = '1';

            wrappers[0].parentElement?.insertBefore(
                row,
                wrappers[0]
            );

            wrappers.forEach((wrapper) => {
                wrapper.dataset.meetingGrouped = '1';
                row.appendChild(wrapper);
            });
        });

        const reminder = findField('set_reminder');
        const reminderWrapper = fieldWrapper(reminder);

        if (
            reminder
            && reminderWrapper
            && reminderWrapper.dataset.meetingReminderStyled !== '1'
        ) {
            reminderWrapper.dataset.meetingReminderStyled = '1';
            reminderWrapper.classList.add(
                'meeting-reminder-control'
            );

            const syncReminder = () => {
                reminderWrapper.classList.toggle(
                    'is-enabled',
                    reminder.value === '1'
                );
            };

            reminder.addEventListener(
                'change',
                syncReminder
            );

            syncReminder();
        }
    }

    enhanceNormalMeetingForm();

    const observer = new MutationObserver(() => {
        enhanceNormalMeetingForm();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
})();
</script>

@endonce

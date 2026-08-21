<?php

namespace App\Http\Controllers;

use App\Mail\MeetingInvitationMail;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MeetingController extends CrudController
{
    protected string $model = Meeting::class;
    protected string $routeName = 'meetings';
    protected string $title = 'Meeting';
    protected string $icon = 'fa-solid fa-calendar-days';
    protected string $accent = 'blue';
    protected string $dateField = 'start_at';

    protected array $fields = [
        ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
        ['name' => 'start_at', 'label' => 'Start', 'type' => 'datetime-local', 'required' => true],
        ['name' => 'end_at', 'label' => 'End', 'type' => 'datetime-local'],
        ['name' => 'location', 'label' => 'Location / Video Link', 'type' => 'text', 'placeholder' => 'e.g. Conference Room B, or a Zoom/Meet link'],
        ['name' => 'attendees', 'label' => 'Attendees', 'type' => 'text', 'hint' => 'Comma-separated emails — anyone whose email matches will see this meeting on their own Meetings page too.'],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => [
            'scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
        ]],
        ['name' => 'notes', 'label' => 'Notes / Agenda', 'type' => 'textarea'],
        ['name' => 'recurrence_frequency', 'label' => 'Repeat', 'type' => 'select', 'options' => [
            '' => 'Does not repeat', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly',
        ]],
        ['name' => 'recurrence_days_of_week', 'label' => 'Repeat on (weekly only)', 'type' => 'text', 'placeholder' => 'e.g. 1,3,5', 'hint' => 'Comma-separated day numbers: 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat, 7=Sun. Leave blank to repeat on the same weekday as the Start date above.'],
        ['name' => 'recurrence_ends_at', 'label' => 'Repeat until (optional)', 'type' => 'date'],
    ];

    protected array $rules = [
        'title' => 'required|string|max:255',
        'start_at' => 'required|date',
        'end_at' => 'nullable|date|after:start_at',
        'location' => 'nullable|string|max:255',
        'attendees' => 'nullable|string',
        'status' => 'required|in:scheduled,completed,cancelled',
        'notes' => 'nullable|string',
        'recurrence_frequency' => 'nullable|in:daily,weekly,monthly',
        'recurrence_days_of_week' => 'nullable|string|max:50',
        'recurrence_ends_at' => 'nullable|date|after:start_at',
    ];

    /**
     * Parses the comma-separated "1,3,5" text field into the actual
     * int array the model casts recurrence_days_of_week to and
     * RecurringMeetingService expects — invalid/out-of-range entries
     * are silently dropped rather than rejecting the whole save over
     * a typo in one number.
     */
    private function parseDaysOfWeek(?string $raw): ?array
    {
        if (empty($raw)) {
            return null;
        }

        $days = array_filter(array_map('intval', explode(',', $raw)), fn ($d) => $d >= 1 && $d <= 7);

        return empty($days) ? null : array_values(array_unique($days));
    }

    /**
     * Overrides the generic CrudController::store() — a recurring
     * meeting needs its upcoming instances generated immediately
     * after being saved (via RecurringMeetingService), rather than
     * waiting for tomorrow's scheduled top-up to run — otherwise the
     * person who just set up a recurring meeting would see an empty
     * list until the next day.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules);
        $data['user_id'] = $request->user()->id;
        if (empty($data['recurrence_frequency'])) {
            $data['recurrence_days_of_week'] = null;
            $data['recurrence_ends_at'] = null;
        } else {
            $data['recurrence_days_of_week'] = $this->parseDaysOfWeek($data['recurrence_days_of_week'] ?? null);
        }

        $meeting = Meeting::create($data);

        if ($meeting->isRecurring()) {
            app(\App\Services\RecurringMeetingService::class)->generateUpcoming($meeting);
        }

        if ($request->boolean('set_reminder')) {
            $this->createLinkedReminder($request, $meeting);
        }

        $this->afterSave($request, $meeting, wasCreated: true);

        return redirect()->route('meetings.index')->with('success', 'Meeting created.');
    }

    /**
     * Same day-of-week parsing as store() above. Deliberately does
     * NOT regenerate already-created future instances just because
     * the rule changed on an edit — that could destructively wipe
     * out instances a person already customized individually.
     * Tomorrow's scheduled top-up naturally uses whatever rule is
     * current when it runs, so a rule change take effect for future
     * instances without needing special handling here.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $meeting = Meeting::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate($this->rules);
        if (empty($data['recurrence_frequency'])) {
            $data['recurrence_days_of_week'] = null;
            $data['recurrence_ends_at'] = null;
        } else {
            $data['recurrence_days_of_week'] = $this->parseDaysOfWeek($data['recurrence_days_of_week'] ?? null);
        }

        $meeting->update($data);

        if ($request->boolean('set_reminder')) {
            $this->createLinkedReminder($request, $meeting);
        }

        $this->afterSave($request, $meeting, wasCreated: false);

        return redirect()->route('meetings.index')->with('success', 'Meeting updated.');
    }

    /**
     * Every meeting the user created, PLUS every meeting anyone else
     * created where the user's own email shows up in that meeting's
     * free-text "attendees" field — so being invited to someone else's
     * meeting (by email) surfaces it in this user's own Meetings page too,
     * for one-place tracking instead of only ever seeing meetings you
     * personally scheduled. `attendees` is a plain text field (not a
     * structured list), so this is a case-insensitive LIKE match, not an
     * exact-email guarantee — a false positive is possible if someone's
     * email happens to be a substring of another attendee's text, but
     * this is a reasonable trade-off given the field's free-text design.
     *
     * Edit/delete stay owner-only regardless — CrudController's default
     * edit()/update()/destroy() still filter by user_id, so an invited
     * (non-owner) user can see but never modify someone else's meeting.
     */
    private function visibleMeetingsQuery(Request $request): Builder
    {
        $userId = $request->user()->id;
        $email = $request->user()->email;

        return Meeting::where(function (Builder $query) use ($userId, $email) {
            $query->where('user_id', $userId)
                ->orWhere('attendees', 'like', '%' . $email . '%');
        });
    }

    public function index(Request $request)
    {
        $query = $this->visibleMeetingsQuery($request);

        // "Missed" isn't a stored value (see Meeting::displayStatus()),
        // so this filters in PHP after fetching rather than in SQL —
        // fine at this app's scale, and keeps the derivation in one
        // place instead of duplicating the "past + still scheduled"
        // logic as a raw SQL condition too.
        $statusFilter = $request->query('status_filter');
        if ($statusFilter && in_array($statusFilter, ['scheduled', 'completed', 'cancelled', 'missed'], true)) {
            $matchingIds = $this->visibleMeetingsQuery($request)->get()->filter(fn ($m) => $m->displayStatus() === $statusFilter)->pluck('id');
            $query = $this->visibleMeetingsQuery($request)->whereIn('id', $matchingIds);
        }

        $connections = \App\Models\UserMeetingConnection::where('user_id', $request->user()->id)->get()->keyBy('platform');
        $enabledPlatforms = \App\Models\MeetingPlatformConfig::where('is_enabled', true)->get();

        return $this->renderIndex($request, $query, [
            'connections' => $connections,
            'enabledPlatforms' => $enabledPlatforms,
            'statusFilter' => $statusFilter,
        ], fn ($q) => $q->orderByDesc('start_at')->orderByDesc('id'), 10);
    }

    protected function stats(Request $request): array
    {
        $base = $this->visibleMeetingsQuery($request)->where('status', 'scheduled');

        return [
            ['label' => 'Today', 'value' => (string) (clone $base)->whereDate('start_at', now()->toDateString())->count(), 'icon' => 'fa-solid fa-calendar-day', 'color' => 'blue'],
            ['label' => 'Next 7 days', 'value' => (string) (clone $base)->whereBetween('start_at', [now(), now()->addDays(7)])->count(), 'icon' => 'fa-solid fa-calendar-week', 'color' => 'sky'],
            ['label' => 'Total scheduled', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-calendar-days', 'color' => 'indigo'],
        ];
    }

    protected function chart(Request $request): ?array
    {
        $days = collect(range(0, 6))->map(fn ($d) => now()->addDays($d)->startOfDay());

        $counts = $days->map(function ($day) use ($request) {
            return (clone $this->visibleMeetingsQuery($request))
                ->where('status', 'scheduled')
                ->whereDate('start_at', $day->toDateString())
                ->count();
        });

        if ($counts->sum() <= 0) {
            return null;
        }

        return [
            'type' => 'bar',
            'title' => 'Meetings (next 7 days)',
            'labels' => $days->map(fn ($d) => $d->format('D, M j'))->all(),
            'datasets' => [['label' => 'Meetings', 'data' => $counts->all()]],
        ];
    }

    /**
     * Schedules one meeting across MULTIPLE date/time slots in a single
     * submission (e.g. a recurring standing meeting, or several proposed
     * times) — shared details (title, location, attendees, notes, status)
     * plus N separate start/end datetime pairs, added dynamically in the
     * UI via crud/extras/meetings-extra.blade.php. Creates one Meeting row
     * per slot; each is independently editable/deletable afterward through
     * the normal single-meeting modal.
     */
    public function storeMultiple(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'attendees' => ['nullable', 'string'],
            'status' => ['required', 'in:scheduled,completed,cancelled'],
            'notes' => ['nullable', 'string'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.start_at' => ['required', 'date'],
            'slots.*.end_at' => ['nullable', 'date'],
        ]);

        $created = 0;

        foreach ($data['slots'] as $slot) {
            if (empty($slot['start_at'])) {
                continue;
            }

            $meeting = Meeting::create([
                'user_id' => $request->user()->id,
                'title' => $data['title'],
                'start_at' => $slot['start_at'],
                'end_at' => $slot['end_at'] ?? null,
                'location' => $data['location'] ?? null,
                'attendees' => $data['attendees'] ?? null,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->sendInvitations($meeting, $request->user()->name);
            $created++;
        }

        return redirect()->route('meetings.index')->with('success', "{$created} meeting(s) scheduled.");
    }

    /**
     * Hook from CrudController::store()/update() — sends an invitation
     * email to every address in the attendees field whenever a meeting
     * is created OR its attendee list changes on edit. Doesn't fire for
     * every OTHER field edit (e.g. just fixing a typo in the notes)
     * being resent as a "you're invited" email would be noise/confusing
     * for anyone already invited.
     */
    protected function afterSave(Request $request, $item, bool $wasCreated): void
    {
        if ($wasCreated || $item->wasChanged('attendees')) {
            $this->sendInvitations($item, $request->user()->name);
        }
    }

    private function sendInvitations(Meeting $meeting, string $organizerName): void
    {
        $emails = collect(explode(',', (string) $meeting->attendees))
            ->map(fn ($email) => trim($email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

        foreach ($emails as $email) {
            Mail::to($email)->send(new MeetingInvitationMail($meeting, $organizerName));
        }
    }

    /**
     * Dedicated notes page — the same 'notes' column already editable via
     * the standard modal, but in a full-page textarea (easier to write a
     * real set of meeting notes in than a small modal field) plus
     * download/share actions.
     */
    public function notes(Request $request, int $meeting)
    {
        $item = Meeting::where('user_id', $request->user()->id)->findOrFail($meeting);

        $recordings = $item->recordings()
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'recordings_page')
            ->withQueryString();

        return view('meetings.notes', [
            'meeting' => $item,
            'recordings' => $recordings,
        ]);
    }

    public function updateNotes(Request $request, int $meeting): RedirectResponse
    {
        $item = Meeting::where('user_id', $request->user()->id)->findOrFail($meeting);

        $data = $request->validate(['notes' => ['nullable', 'string']]);
        $item->update(['notes' => $data['notes']]);

        return back()->with('success', 'Notes saved.');
    }

    public function downloadNotesPdf(Request $request, int $meeting)
    {
        $item = Meeting::where('user_id', $request->user()->id)->findOrFail($meeting);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('meetings.notes-pdf', ['meeting' => $item, 'user' => $request->user()])
            ->setPaper('a4');

        return $pdf->download('meeting-notes-' . $item->start_at->format('Y-m-d') . '.pdf');
    }
}

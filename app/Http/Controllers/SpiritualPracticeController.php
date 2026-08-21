<?php

namespace App\Http\Controllers;

use App\Models\SpiritualPractice;
use App\Services\RecurringSpiritualPracticeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpiritualPracticeController extends CrudController
{
    protected string $model = SpiritualPractice::class;
    protected string $routeName = 'spiritual-practices';
    protected string $title = 'Spiritual Practice';
    protected string $icon = 'fa-solid fa-hands-praying';
    protected string $accent = 'fuchsia';
    protected string $dateField = 'practiced_at';

    protected array $fields = [
        ['name' => 'practice_type', 'label' => 'Practice', 'type' => 'select', 'required' => true, 'options' => [
            'prayer' => 'Prayer',
            'meditation' => 'Meditation',
            'scripture_reading' => 'Scripture Reading',
            'worship' => 'Worship',
            'fasting' => 'Fasting',
            'service' => 'Service / Volunteering',
            'journaling' => 'Journaling',
            'other' => 'Other',
        ]],
        ['name' => 'title', 'label' => 'Title (e.g. Morning Devotion)', 'type' => 'text', 'placeholder' => 'e.g. Morning Devotion'],
        ['name' => 'preacher', 'label' => 'Preacher', 'type' => 'text'],
        ['name' => 'theme_topic', 'label' => 'Theme / Topic', 'type' => 'text'],
        ['name' => 'practiced_at', 'label' => 'Start date', 'type' => 'date', 'required' => true],
        ['name' => 'practice_time', 'label' => 'Time', 'type' => 'time'],
        ['name' => 'duration_minutes', 'label' => 'Duration (minutes)', 'type' => 'number'],
        ['name' => 'recurrence_frequency', 'label' => 'Repeat', 'type' => 'select', 'options' => [
            '' => 'Does not repeat',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
        ], 'hint' => 'Choose how often My Digital Diary should create the next spiritual-growth sessions.'],
        ['name' => 'recurrence_days_of_week', 'label' => 'Repeat on (weekly only)', 'type' => 'text', 'placeholder' => 'e.g. 1,3,5', 'hint' => '1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat, 7=Sun. Leave blank to use the same weekday as the start date.'],
        ['name' => 'recurrence_ends_at', 'label' => 'Repeat until (optional)', 'type' => 'date'],
        ['name' => 'scriptures', 'label' => 'Bible Readings / Scriptures', 'type' => 'textarea'],
        ['name' => 'lessons_learnt', 'label' => 'Lessons Learnt', 'type' => 'textarea'],
        ['name' => 'next_planned_date', 'label' => 'Next Planned', 'type' => 'date'],
        ['name' => 'reflection', 'label' => 'Reflection / Journal', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'practice_type' => 'required|in:prayer,meditation,scripture_reading,worship,fasting,service,journaling,other',
        'title' => 'nullable|string|max:255',
        'preacher' => 'nullable|string|max:255',
        'theme_topic' => 'nullable|string|max:255',
        'scriptures' => 'nullable|string',
        'lessons_learnt' => 'nullable|string',
        'practiced_at' => 'required|date',
        'practice_time' => ['nullable', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
        'duration_minutes' => 'nullable|integer|min:0',
        'next_planned_date' => 'nullable|date',
        'reflection' => 'nullable|string',
        'recurrence_frequency' => 'nullable|in:daily,weekly,monthly',
        'recurrence_days_of_week' => 'nullable|string|max:50',
        'recurrence_ends_at' => 'nullable|date|after_or_equal:practiced_at',
    ];

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules);
        $data['user_id'] = $request->user()->id;
        $this->normaliseTime($data);
        $this->normaliseRecurrence($data);

        $practice = SpiritualPractice::create($data);

        if ($practice->isRecurring()) {
            app(RecurringSpiritualPracticeService::class)
                ->generateUpcoming($practice);
        }

        return redirect()
            ->route('spiritual-practices.index')
            ->with('success', 'Spiritual growth entry created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $practice = SpiritualPractice::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $data = $request->validate($this->rules);
        $this->normaliseTime($data);
        $this->normaliseRecurrence($data);

        $practice->update($data);

        if (! $practice->recurrence_parent_id && $practice->isRecurring()) {
            app(RecurringSpiritualPracticeService::class)
                ->generateUpcoming($practice);
        }

        return redirect()
            ->route('spiritual-practices.index')
            ->with('success', 'Spiritual growth entry updated.');
    }


    private function normaliseTime(array &$data): void
    {
        if (! empty($data['practice_time'])) {
            $data['practice_time'] = substr((string) $data['practice_time'], 0, 5);
        }
    }

    private function normaliseRecurrence(array &$data): void
    {
        if (empty($data['recurrence_frequency'])) {
            $data['recurrence_frequency'] = null;
            $data['recurrence_days_of_week'] = null;
            $data['recurrence_ends_at'] = null;
            return;
        }

        $raw = trim((string) ($data['recurrence_days_of_week'] ?? ''));
        if ($raw === '') {
            $data['recurrence_days_of_week'] = null;
            return;
        }

        $days = collect(preg_split('/[\s,]+/', $raw) ?: [])
            ->filter(fn ($day) => $day !== '')
            ->map(fn ($day) => (int) $day)
            ->filter(fn ($day) => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $data['recurrence_days_of_week'] = $days ?: null;
    }

    protected function stats(Request $request): array
    {
        $userId = $request->user()->id;
        $base = SpiritualPractice::where('user_id', $userId);

        $thisWeek = (clone $base)
            ->where('practiced_at', '>=', now()->startOfWeek())
            ->count();

        $recurring = (clone $base)
            ->whereNull('recurrence_parent_id')
            ->whereNotNull('recurrence_frequency')
            ->count();

        $last = (clone $base)->orderByDesc('practiced_at')->first();

        return [
            ['label' => 'This week', 'value' => (string) $thisWeek, 'icon' => 'fa-solid fa-hands-praying', 'color' => 'fuchsia'],
            ['label' => 'Recurring', 'value' => (string) $recurring, 'icon' => 'fa-solid fa-repeat', 'color' => 'violet'],
            ['label' => 'Total logged', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-list-ol', 'color' => 'slate'],
            ['label' => 'Last practice', 'value' => $last ? $last->practiced_at->format('Y-m-d') : '—', 'icon' => 'fa-solid fa-calendar-day', 'color' => 'purple'],
        ];
    }

    protected function chart(Request $request): ?array
    {
        $counts = SpiritualPractice::where('user_id', $request->user()->id)
            ->selectRaw('practice_type, COUNT(*) as total')
            ->groupBy('practice_type')
            ->pluck('total', 'practice_type');

        if ($counts->isEmpty()) {
            return null;
        }

        return [
            'type' => 'doughnut',
            'title' => 'Practices by Type',
            'labels' => $counts->keys()
                ->map(fn ($label) => ucwords(str_replace('_', ' ', $label)))
                ->all(),
            'datasets' => [['data' => $counts->values()->all()]],
        ];
    }
}

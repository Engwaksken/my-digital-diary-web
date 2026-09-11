<?php

namespace App\Http\Controllers;

use App\Models\SpiritualPractice;
use App\Services\DailyInsightService;
use App\Services\RecurringSpiritualPracticeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SpiritualPracticeController extends Controller
{
    public function index(Request $request): View
    {
        $tableExists = Schema::hasTable('spiritual_practices');
        $columns = $tableExists
            ? Schema::getColumnListing('spiritual_practices')
            : [];

        $items = collect();
        $stats = [
            'total' => 0,
            'this_week' => 0,
            'this_month' => 0,
            'recurring' => 0,
        ];

        if ($tableExists) {
            $query = SpiritualPractice::query()
                ->where('user_id', $request->user()->id);

            if (in_array('deleted_at', $columns, true)) {
                $query->whereNull('deleted_at');
            }

            if (
                $request->filled('q') &&
                trim((string) $request->query('q')) !== ''
            ) {
                $search = trim((string) $request->query('q'));
                $searchable = array_values(array_filter([
                    'practice_title',
                    'title',
                    'practice_type',
                    'faith_path',
                    'theme_topic',
                    'reflection',
                    'source_tradition',
                    'notes',
                ], fn ($column) => in_array($column, $columns, true)));

                if ($searchable) {
                    $query->where(function ($sub) use ($search, $searchable) {
                        foreach ($searchable as $index => $column) {
                            $method = $index === 0 ? 'where' : 'orWhere';
                            $sub->{$method}($column, 'like', "%{$search}%");
                        }
                    });
                }
            }

            $dateColumn = $this->dateColumn($columns);
            $period = (string) $request->query('period', '');

            if ($dateColumn) {
                $today = now();

                match ($period) {
                    'today' => $query->whereDate(
                        $dateColumn,
                        $today->toDateString()
                    ),
                    'week' => $query->whereBetween(
                        $dateColumn,
                        [
                            $today->copy()->startOfWeek(),
                            $today->copy()->endOfWeek(),
                        ]
                    ),
                    'month' => $query->whereBetween(
                        $dateColumn,
                        [
                            $today->copy()->startOfMonth(),
                            $today->copy()->endOfMonth(),
                        ]
                    ),
                    default => null,
                };
            }

            $items = $query
                ->orderByDesc($dateColumn ?: 'id')
                ->paginate(15)
                ->withQueryString();

            $statsBase = SpiritualPractice::query()
                ->where('user_id', $request->user()->id);

            if (in_array('deleted_at', $columns, true)) {
                $statsBase->whereNull('deleted_at');
            }

            $stats['total'] = (clone $statsBase)->count();

            if ($dateColumn) {
                $stats['this_week'] = (clone $statsBase)
                    ->whereBetween(
                        $dateColumn,
                        [now()->startOfWeek(), now()->endOfWeek()]
                    )
                    ->count();

                $stats['this_month'] = (clone $statsBase)
                    ->whereBetween(
                        $dateColumn,
                        [now()->startOfMonth(), now()->endOfMonth()]
                    )
                    ->count();
            }

            if (in_array('recurrence_frequency', $columns, true)) {
                $stats['recurring'] = (clone $statsBase)
                    ->whereNotNull('recurrence_frequency')
                    ->where('recurrence_frequency', '!=', '')
                    ->count();
            }
        }

        return view('spiritual-practices.index', [
            'tableExists' => $tableExists,
            'columns' => $columns,
            'items' => $items,
            'stats' => $stats,
            'faithPaths' => $this->faithPaths(),
            'practiceTypes' => $this->practiceTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureTable();

        $columns = Schema::getColumnListing('spiritual_practices');
        $data = $request->validate(
            $this->rulesForExistingColumns($columns)
        );

        $payload = $this->normalisePayload(
            $data,
            $columns,
            $request->user()->id
        );

        $item = SpiritualPractice::create($payload);

        $this->runRecurrenceSafely($item);
        $this->invalidateInsight($request);

        return redirect()
            ->route('spiritual-practices.index')
            ->with('success', 'Spiritual growth entry added.');
    }

    public function update(
        Request $request,
        SpiritualPractice $spiritualPractice
    ): RedirectResponse {
        abort_unless(
            (int) $spiritualPractice->user_id ===
                (int) $request->user()->id,
            404
        );

        $columns = Schema::getColumnListing('spiritual_practices');
        $data = $request->validate(
            $this->rulesForExistingColumns($columns)
        );

        $payload = $this->normalisePayload(
            $data,
            $columns,
            $request->user()->id
        );

        unset($payload['user_id']);

        $spiritualPractice->update($payload);

        $this->runRecurrenceSafely($spiritualPractice->fresh());
        $this->invalidateInsight($request);

        return redirect()
            ->route('spiritual-practices.index')
            ->with('success', 'Spiritual growth entry updated.');
    }

    public function destroy(
        Request $request,
        SpiritualPractice $spiritualPractice
    ): RedirectResponse {
        abort_unless(
            (int) $spiritualPractice->user_id ===
                (int) $request->user()->id,
            404
        );

        $columns = Schema::getColumnListing('spiritual_practices');

        if (in_array('deleted_at', $columns, true)) {
            $spiritualPractice->forceFill([
                'deleted_at' => now(),
            ])->saveQuietly();
        } else {
            $spiritualPractice->delete();
        }

        $this->invalidateInsight($request);

        return back()->with(
            'success',
            'Spiritual growth entry deleted.'
        );
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $query = SpiritualPractice::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $data['ids']);

        $columns = Schema::getColumnListing('spiritual_practices');

        if (in_array('deleted_at', $columns, true)) {
            $query->update(['deleted_at' => now()]);
        } else {
            $query->delete();
        }

        $this->invalidateInsight($request);

        return back()->with(
            'success',
            'Selected spiritual growth entries deleted.'
        );
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('spiritual-practices.index');
    }

    public function edit(SpiritualPractice $spiritualPractice): RedirectResponse
    {
        return redirect()->route('spiritual-practices.index');
    }

    public function show(SpiritualPractice $spiritualPractice): RedirectResponse
    {
        return redirect()->route('spiritual-practices.index');
    }

    private function rulesForExistingColumns(array $columns): array
    {
        $rules = [];

        $map = [
            'faith_path' => ['nullable', 'string', 'max:120'],
            'custom_faith_path' => ['nullable', 'string', 'max:120'],
            'practice_type' => ['nullable', 'string', 'max:120'],
            'practice_title' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'theme_topic' => ['nullable', 'string', 'max:255'],
            'practiced_at' => ['nullable', 'date'],
            'practice_time' => ['nullable'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'inspirational_text' => ['nullable', 'string'],
            'scriptures' => ['nullable', 'string'],
            'source_tradition' => ['nullable', 'string', 'max:255'],
            'reflection' => ['nullable', 'string'],
            'gratitude' => ['nullable', 'string'],
            'intention' => ['nullable', 'string'],
            'community_place' => ['nullable', 'string', 'max:255'],
            'mood_before' => ['nullable', 'string', 'max:100'],
            'mood_after' => ['nullable', 'string', 'max:100'],
            'recurrence_frequency' => [
                'nullable',
                'in:daily,weekly,monthly',
            ],
            'recurrence_days_of_week' => ['nullable', 'array'],
            'recurrence_days_of_week.*' => ['integer', 'between:1,7'],
            'recurrence_ends_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];

        foreach ($map as $column => $columnRules) {
            if (in_array($column, $columns, true)) {
                $rules[$column] = $columnRules;
            }
        }

        return $rules;
    }

    private function normalisePayload(
        array $data,
        array $columns,
        int $userId
    ): array {
        $payload = $data;
        $payload['user_id'] = $userId;

        if (
            in_array('title', $columns, true) &&
            empty($payload['title']) &&
            ! empty($payload['practice_title'])
        ) {
            $payload['title'] = $payload['practice_title'];
        }

        if (
            in_array('scriptures', $columns, true) &&
            empty($payload['scriptures']) &&
            ! empty($payload['inspirational_text'])
        ) {
            $payload['scriptures'] = $payload['inspirational_text'];
        }

        if (
            ($payload['faith_path'] ?? null) !== 'Custom'
        ) {
            $payload['custom_faith_path'] = null;
        }

        if (
            empty($payload['recurrence_frequency'])
        ) {
            $payload['recurrence_days_of_week'] = null;
            $payload['recurrence_ends_at'] = null;
        }

        $payload = array_filter(
            $payload,
            fn ($value, $key) =>
                $key === 'user_id' ||
                in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );

        return $payload;
    }

    private function runRecurrenceSafely(
        SpiritualPractice $item
    ): void {
        try {
            app(RecurringSpiritualPracticeService::class)
                ->generateFutureOccurrences($item);
        } catch (\Throwable) {
            // Entry save must not fail because recurrence generation failed.
        }
    }

    private function dateColumn(array $columns): ?string
    {
        foreach (['practiced_at', 'created_at'] as $column) {
            if (in_array($column, $columns, true)) {
                return $column;
            }
        }

        return null;
    }

    private function ensureTable(): void
    {
        abort_unless(
            Schema::hasTable('spiritual_practices'),
            503,
            'The spiritual_practices table is missing.'
        );
    }

    private function invalidateInsight(Request $request): void
    {
        try {
            app(DailyInsightService::class)
                ->invalidateFor($request->user());
        } catch (\Throwable) {
            // Spiritual Growth must remain usable if insight invalidation fails.
        }
    }

    private function faithPaths(): array
    {
        return [
            'Prefer not to specify',
            'Christianity',
            'Islam',
            'Judaism',
            'Hinduism',
            'Buddhism',
            'Sikhism',
            'Baháʼí Faith',
            'African Traditional / Indigenous Spirituality',
            'Other religion',
            'Spiritual but not religious',
            'Secular reflection',
            'Custom',
        ];
    }

    private function practiceTypes(): array
    {
        return [
            'prayer' => 'Prayer',
            'meditation' => 'Meditation',
            'worship' => 'Worship',
            'sacred_text_reading' => 'Sacred / Inspirational Text Reading',
            'reflection' => 'Reflection',
            'gratitude' => 'Gratitude',
            'fasting' => 'Fasting',
            'mindfulness' => 'Mindfulness',
            'community_gathering' => 'Community Gathering',
            'service' => 'Service / Charity',
            'chanting' => 'Chanting',
            'pilgrimage' => 'Pilgrimage',
            'study' => 'Study',
            'personal_ritual' => 'Personal Ritual',
            'other' => 'Other',
        ];
    }
}

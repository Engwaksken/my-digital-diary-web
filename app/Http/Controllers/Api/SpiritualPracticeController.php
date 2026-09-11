<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpiritualPractice;
use App\Services\DailyInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SpiritualPracticeController extends Controller
{
    private array $rules = [
        'faith_path' => ['nullable','string','max:100'],
        'custom_faith_path' => ['nullable','string','max:150'],
        'practice_type' => ['required','string','max:120'],
        'practice_title' => ['nullable','string','max:255'],
        'theme_topic' => ['nullable','string','max:255'],
        'inspirational_text' => ['nullable','string'],
        'source_tradition' => ['nullable','string','max:255'],
        'reflection' => ['nullable','string'],
        'gratitude' => ['nullable','string'],
        'intention' => ['nullable','string'],
        'community_place' => ['nullable','string','max:255'],
        'duration_minutes' => ['nullable','integer','min:0','max:1440'],
        'practice_date' => ['nullable','date'],
        'practiced_at' => ['nullable','date'],
        'practice_time' => ['nullable','string','max:20'],
        'mood_before' => ['nullable','string','max:60'],
        'mood_after' => ['nullable','string','max:60'],
        'notes' => ['nullable','string'],
        'recurrence_frequency' => ['nullable','in:daily,weekly,monthly'],
        'recurrence_days_of_week' => ['nullable','array'],
        'recurrence_days_of_week.*' => ['integer','min:1','max:7'],
        'recurrence_ends_at' => ['nullable','date'],
    ];

    public function index(Request $request): JsonResponse
    {
        $query = SpiritualPractice::query()
            ->where('user_id', $request->user()->id);

        if (Schema::hasColumn('spiritual_practices', 'is_archived')) {
            $query->where('is_archived', $request->boolean('archived'));
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                foreach ([
                    'practice_title',
                    'practice_type',
                    'faith_path',
                    'reflection',
                    'notes',
                ] as $column) {
                    if (Schema::hasColumn('spiritual_practices', $column)) {
                        $builder->orWhere($column, 'like', '%'.$search.'%');
                    }
                }
            });
        }

        $items = $query
            ->orderByDesc('practiced_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'data' => $this->owned($request, $id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->normalise($request->validate($this->rules));
        $data['user_id'] = $request->user()->id;

        $item = SpiritualPractice::query()->create($data);

        $this->invalidateInsight($request);

        return response()->json([
            'message' => 'Spiritual practice saved.',
            'data' => $item->fresh(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = $this->owned($request, $id);

        $item->update(
            $this->normalise($request->validate($this->rules))
        );

        $this->invalidateInsight($request);

        return response()->json([
            'message' => 'Spiritual practice updated.',
            'data' => $item->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = $this->owned($request, $id);
        $item->delete();

        $this->invalidateInsight($request);

        return response()->json([
            'message' => 'Spiritual practice deleted.',
        ]);
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        $item = $this->owned($request, $id);

        if (Schema::hasColumn('spiritual_practices', 'is_archived')) {
            $item->forceFill([
                'is_archived' => true,
                'archived_at' => now(),
            ])->save();
        }

        return response()->json(['data' => $item->fresh()]);
    }

    public function unarchive(Request $request, int $id): JsonResponse
    {
        $item = $this->owned($request, $id);

        if (Schema::hasColumn('spiritual_practices', 'is_archived')) {
            $item->forceFill([
                'is_archived' => false,
                'archived_at' => null,
            ])->save();
        }

        return response()->json(['data' => $item->fresh()]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required','array','min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $items = SpiritualPractice::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $ids)
            ->get();

        foreach ($items as $item) {
            $item->delete();
        }

        $this->invalidateInsight($request);

        return response()->json([
            'message' => $items->count().' spiritual practice(s) deleted.',
            'deleted' => $items->count(),
        ]);
    }

    private function owned(Request $request, int $id): SpiritualPractice
    {
        return SpiritualPractice::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    private function normalise(array $data): array
    {
        $data['practiced_at'] =
            $data['practice_date']
            ?? $data['practiced_at']
            ?? now()->toDateString();

        unset($data['practice_date']);

        $data['title'] = $data['practice_title'] ?? null;
        $data['scriptures'] = $data['inspirational_text'] ?? null;

        if (($data['faith_path'] ?? '') !== 'Custom') {
            $data['custom_faith_path'] = null;
        }

        return $data;
    }

    private function invalidateInsight(Request $request): void
    {
        try {
            app(DailyInsightService::class)
                ->invalidateFor($request->user());
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpiritualPractice;
use App\Services\RecurringSpiritualPracticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpiritualPracticeController extends Controller
{
    private array $rules = [
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
        'recurrence_days_of_week' => 'nullable',
        'recurrence_ends_at' => 'nullable|date|after_or_equal:practiced_at',
    ];

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            SpiritualPractice::where('user_id', $request->user()->id)
                ->where('is_archived', false)
                ->orderByDesc('practiced_at')
                ->orderByDesc('id')
                ->paginate(20)
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            SpiritualPractice::where('user_id', $request->user()->id)
                ->findOrFail($id)
        );
    }

    public function store(Request $request): JsonResponse
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

        return response()->json($practice->fresh(), 201);
    }

    public function update(Request $request, int $id): JsonResponse
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

        return response()->json($practice->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $practice = SpiritualPractice::where('user_id', $request->user()->id)
            ->findOrFail($id);
        $practice->delete();

        return response()->json(['message' => 'Deleted.']);
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

        $raw = $data['recurrence_days_of_week'] ?? null;

        if (is_array($raw)) {
            $days = $raw;
        } else {
            $days = preg_split('/[\s,]+/', trim((string) $raw)) ?: [];
        }

        $days = collect($days)
            ->map(fn ($day) => (int) $day)
            ->filter(fn ($day) => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $data['recurrence_days_of_week'] = $days ?: null;
    }
}

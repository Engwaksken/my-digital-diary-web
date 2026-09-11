<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyWellbeingLog;
use App\Services\DailyWellbeingSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyWellbeingLogController extends Controller
{
    private array $rules = [
        'log_date' => ['nullable', 'date'],
        'water_ml' => ['nullable', 'integer', 'min:0', 'max:20000'],
        'water_target_ml' => ['nullable', 'integer', 'min:250', 'max:20000'],
        'mood' => ['nullable', 'in:low,okay,good,great'],
        'energy_level' => ['nullable', 'integer', 'min:1', 'max:5'],
        'stress_level' => ['nullable', 'integer', 'min:1', 'max:5'],
        'pain_level' => ['nullable', 'integer', 'min:0', 'max:10'],
        'wellbeing_score' => ['nullable', 'integer', 'min:1', 'max:10'],
        'symptoms' => ['nullable', 'string', 'max:2000'],
        'self_care_done' => ['nullable', 'boolean'],
        'screen_break_done' => ['nullable', 'boolean'],
        'reflection_done' => ['nullable', 'boolean'],
        'self_care_activity' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string', 'max:5000'],
    ];

    public function index(Request $request): JsonResponse
    {
        app(DailyWellbeingSyncService::class)->syncRecent($request->user(), 7);

        $items = DailyWellbeingLog::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->paginate(max(1, min(50, (int) $request->integer('per_page', 10))))
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
        return response()->json(['data' => $this->owned($request, $id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $item = $this->saveForDate(
            $request,
            $request->validate($this->rules)
        );

        return response()->json([
            'message' => 'Daily wellbeing saved.',
            'data' => $item,
        ], $item->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $current = $this->owned($request, $id);
        $data = $request->validate($this->rules);

        $item = $this->saveForDate(
            $request,
            $data,
            $data['log_date'] ?? optional($current->log_date)->toDateString(),
            $current
        );

        return response()->json([
            'message' => 'Daily wellbeing updated.',
            'data' => $item,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->owned($request, $id)->delete();

        return response()->json(['message' => 'Daily wellbeing entry deleted.']);
    }

    private function saveForDate(
        Request $request,
        array $data,
        ?string $forcedDate = null,
        ?DailyWellbeingLog $current = null
    ): DailyWellbeingLog {
        $timezone = $request->user()->timezone
            ?: config('app.timezone', 'Africa/Kampala');

        $date = $forcedDate
            ?: ($data['log_date'] ?? now($timezone)->toDateString());

        $existing = DailyWellbeingLog::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('log_date', $date)
            ->first();

        $source = $existing ?? $current;

        $payload = [
            'user_id' => $request->user()->id,
            'log_date' => $date,
            'water_ml' => array_key_exists('water_ml', $data)
                ? (int) ($data['water_ml'] ?? 0)
                : (int) ($source?->water_ml ?? 0),
            'water_target_ml' => array_key_exists('water_target_ml', $data)
                ? (int) ($data['water_target_ml'] ?? 2000)
                : (int) ($source?->water_target_ml ?? 2000),
        ];

        foreach ([
            'mood', 'energy_level', 'stress_level', 'pain_level',
            'wellbeing_score', 'symptoms', 'self_care_done',
            'screen_break_done', 'reflection_done', 'self_care_activity',
            'notes',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            } elseif ($source) {
                $payload[$field] = $source->{$field};
            }
        }

        $item = DailyWellbeingLog::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'log_date' => $date,
            ],
            $payload
        );

        app(DailyWellbeingSyncService::class)
            ->sync($request->user(), $date);

        return $item->fresh();
    }

    private function owned(Request $request, int $id): DailyWellbeingLog
    {
        return DailyWellbeingLog::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }
}

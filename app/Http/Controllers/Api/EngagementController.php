<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DailyEngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngagementController extends Controller
{
    public function __construct(
        private readonly DailyEngagementService $engagement
    ) {}

    public function today(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->engagement->dashboard($request->user()),
        ]);
    }

    public function checkin(Request $request, string $type): JsonResponse
    {
        abort_unless(in_array($type, ['start-day', 'close-day'], true), 404);

        $data = $request->validate([
            'mood' => ['nullable', 'integer', 'between:1,5'],
            'reflection' => ['nullable', 'string', 'max:3000'],
            'gratitude' => ['nullable', 'string', 'max:2000'],
            'tomorrow_focus' => ['nullable', 'string', 'max:2000'],
            'meta' => ['nullable', 'array'],
        ]);

        return response()->json([
            'data' => $this->engagement->saveCheckin(
                $request->user(),
                $type === 'start-day' ? 'start_day' : 'close_day',
                $data
            ),
        ]);
    }

    public function meaningfulAction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', 'string', 'max:60'],
            'source_type' => ['nullable', 'string', 'max:80'],
            'source_id' => ['nullable', 'integer', 'min:1'],
            'meta' => ['nullable', 'array'],
        ]);

        return response()->json([
            'data' => [
                'streak' => $this->engagement->markMeaningfulAction(
                    $request->user(),
                    $data['event_type'],
                    $data['source_type'] ?? null,
                    $data['source_id'] ?? null,
                    $data['meta'] ?? []
                ),
            ],
        ]);
    }

    public function weekly(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->engagement->weeklyReview($request->user()),
        ]);
    }

    public function monthly(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->engagement->monthlyReview($request->user()),
        ]);
    }

    public function shareCard(Request $request, string $period): JsonResponse
    {
        abort_unless(in_array($period, ['week', 'month'], true), 404);

        return response()->json([
            'data' => $this->engagement->shareCard(
                $request->user(),
                $period
            ),
        ]);
    }
}

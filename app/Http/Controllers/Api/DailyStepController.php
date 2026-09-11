<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DailyStepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyStepController extends Controller
{
    public function __construct(private readonly DailyStepService $steps)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->steps->payload($request->user())]);
    }

    public function history(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 30);

        return response()->json([
            'data' => $this->steps->history($request->user(), $days),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $row = $this->steps->forToday($request->user());

        $validated = $request->validate([
            'device_steps' => ['nullable','integer','min:0'],
        ]);

        $row->forceFill([
            'is_tracking' => true,
            'tracking_started_at' => now(),
            'tracking_stopped_at' => null,
            'device_baseline_steps' => $validated['device_steps'] ?? $row->device_baseline_steps,
        ])->save();

        return response()->json([
            'message' => 'Step tracking started.',
            'data' => $this->steps->payload($request->user()),
        ]);
    }

    public function stop(Request $request): JsonResponse
    {
        $row = $this->steps->forToday($request->user());

        $row->forceFill([
            'is_tracking' => false,
            'tracking_stopped_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Step tracking paused.',
            'data' => $this->steps->payload($request->user()),
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'steps' => ['required','integer','min:0','max:1000000'],
        ]);

        // Laravel owns the progressive target. Flutter only sends the latest
        // observed daily step total.
        $this->steps->recordSteps(
            $request->user(),
            (int) $validated['steps']
        );

        return response()->json([
            'message' => 'Steps synchronised.',
            'data' => $this->steps->payload($request->user()),
        ]);
    }
}

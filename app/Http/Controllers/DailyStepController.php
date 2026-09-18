<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
        return $this->response($request);
    }

    public function live(Request $request): JsonResponse
    {
        return $this->response($request);
    }

    private function response(Request $request): JsonResponse
    {
        try {
            $data = $this->steps->payload($request->user());
            $success = true;
        } catch (\Throwable $exception) {
            logger()->warning('Live step payload unavailable', [
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            $data = [
                'date' => now($request->user()?->timezone ?: 'Africa/Kampala')->toDateString(),
                'steps' => 0,
                'daily_goal' => 5000,
                'next_daily_goal' => 5000,
                'goal_achieved' => false,
                'progress_percent' => 0,
                'remaining_steps' => 5000,
                'distance_m' => 0,
                'distance_km' => 0,
                'stride_m' => \App\Services\DailyStepService::DEFAULT_STRIDE_M,
                'is_tracking' => false,
                'last_synced_at' => null,
            ];
            $success = false;
        }

        return response()
            ->json([
                'success' => $success,
                'data' => $data,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\DailyEngagementService;
use App\Services\PeriodReviewMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngagementReviewController extends Controller
{
    public function __construct(
        private readonly DailyEngagementService $engagement,
        private readonly PeriodReviewMetricsService $metrics
    ) {
    }

    public function week(Request $request): JsonResponse
    {
        return $this->respond($request, 'week');
    }

    public function month(Request $request): JsonResponse
    {
        return $this->respond($request, 'month');
    }

    private function respond(
        Request $request,
        string $period
    ): JsonResponse {
        $user = $request->user();

        $base = $period === 'month'
            ? $this->engagement->monthlyReview($user)
            : $this->engagement->weeklyReview($user);

        return response()->json([
            'data' => array_replace(
                $base,
                $this->metrics->review($user, $period)
            ),
        ]);
    }
}

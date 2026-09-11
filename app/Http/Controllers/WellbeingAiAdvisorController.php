<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DailyWellbeingSyncService;
use App\Services\WellbeingAiAdvisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WellbeingAiAdvisorController extends Controller
{
    public function __invoke(
        Request $request,
        DailyWellbeingSyncService $sync,
        WellbeingAiAdvisorService $advisor
    ): JsonResponse {
        // Refresh connected data before asking for advice so the response uses
        // the latest Steps, Diet and Exercise values for this user.
        $sync->syncRecent($request->user(), 7);

        try {
            return response()->json([
                'success' => true,
                'data' => $advisor->advise($request->user()),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Your wellbeing advice could not be generated right now. Your records are still safe and available.',
            ], 422);
        }
    }
}

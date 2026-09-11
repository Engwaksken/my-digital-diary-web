<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HealthWellbeingSummaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthWellbeingController extends Controller
{
    public function summary(Request $request, HealthWellbeingSummaryService $service): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable','date'],
            'days' => ['nullable','integer','min:7','max:30'],
        ]);

        $timezone = $request->user()->timezone ?: config('app.timezone', 'Africa/Kampala');
        $date = ! empty($validated['date']) ? Carbon::parse($validated['date'], $timezone) : null;

        return response()->json([
            'data' => $service->summary($request->user(), $date, (int) ($validated['days'] ?? 7)),
        ]);
    }
}

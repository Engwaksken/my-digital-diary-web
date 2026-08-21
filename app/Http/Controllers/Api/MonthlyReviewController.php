<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MonthlyReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MonthlyReviewController extends Controller
{
    public function show(Request $request, MonthlyReviewService $service): JsonResponse
    {
        $timezone = $request->user()->timezone ?: 'Africa/Kampala';
        $request->validate(['month' => ['nullable','date_format:Y-m']]);
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month'), $timezone)->startOfMonth()
            : Carbon::now($timezone)->startOfMonth();

        return response()->json(['data' => $service->build($request->user(), $month)]);
    }
}

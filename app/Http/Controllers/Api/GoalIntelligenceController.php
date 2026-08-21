<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoalIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalIntelligenceController extends Controller
{
    public function show(Request $request, GoalIntelligenceService $service): JsonResponse
    {
        return response()->json(['data' => $service->build($request->user())]);
    }
}

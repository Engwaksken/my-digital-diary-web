<?php
namespace App\Http\Controllers;

use App\Services\GoalIntelligenceService;
use Illuminate\Http\Request;

class GoalIntelligenceController extends Controller
{
    public function __invoke(Request $request, GoalIntelligenceService $service)
    {
        return view('goal-intelligence.index', ['goalData' => $service->build($request->user())]);
    }
}

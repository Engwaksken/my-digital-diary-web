<?php

namespace App\Http\Controllers;

use App\Services\HealthAiCoachService;
use Illuminate\Http\Request;

class HealthAiAdviceController extends Controller
{
    public function sleep(Request $request, HealthAiCoachService $coach)
    {
        $advice = $coach->sleepAdvice($request->user(), force: true);

        return back()->with(
            $advice ? 'success' : 'error',
            $advice
                ? 'Sleep advice refreshed.'
                : 'Add your weight and health profile first so AI can personalise general sleep guidance.'
        );
    }

    public function diet(Request $request, HealthAiCoachService $coach)
    {
        $advice = $coach->dietAdvice($request->user(), force: true);

        return back()->with(
            $advice ? 'success' : 'error',
            $advice
                ? 'Diet advice refreshed.'
                : 'Add your weight and food profile first so AI can personalise general eating guidance.'
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\HealthWellbeingSummaryService;
use Illuminate\Http\Request;

class HealthWellbeingController extends Controller
{
    public function index(Request $request, HealthWellbeingSummaryService $summary)
    {
        return view('health-wellbeing.index', [
            'summary' => $summary->summary($request->user(), null, (int) $request->integer('days', 7)),
        ]);
    }
}

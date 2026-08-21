<?php

namespace App\Http\Controllers;

use App\Services\MonthlyReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MonthlyReviewController extends Controller
{
    public function __invoke(Request $request, MonthlyReviewService $service)
    {
        $timezone = $request->user()->timezone ?: 'Africa/Kampala';
        $request->validate(['month' => ['nullable','date_format:Y-m']]);
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month'), $timezone)->startOfMonth()
            : Carbon::now($timezone)->startOfMonth();

        return view('monthly-review.index', [
            'review' => $service->build($request->user(), $month),
            'selectedMonth' => $month->format('Y-m'),
        ]);
    }
}

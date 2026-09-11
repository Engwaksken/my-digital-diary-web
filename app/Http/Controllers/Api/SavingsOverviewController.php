<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavingsGoal;
use Illuminate\Http\Request;

class SavingsOverviewController extends Controller
{
    public function index(Request $request)
    {
        $goals = SavingsGoal::where('user_id', $request->user()->id)
            ->where('is_archived', false)
            ->with(['contributions' => fn ($q) => $q->where('is_archived', false)->latest('contributed_at')])
            ->orderBy('target_date')->get();

        $data = $goals->map(function (SavingsGoal $goal): array {
            return [
                ...$goal->toArray(),
                'saved_amount' => $goal->totalContributed(),
                'remaining_amount' => $goal->remainingAmount(),
                'progress_percent' => $goal->progressPercent(),
            ];
        });

        return response()->json(['data' => $data]);
    }
}

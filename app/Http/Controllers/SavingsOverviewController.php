<?php

namespace App\Http\Controllers;

use App\Models\SavingsGoal;
use Illuminate\Http\Request;

class SavingsOverviewController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = 9;

        $baseQuery = SavingsGoal::query()
            ->where('user_id', $request->user()->id)
            ->where('is_archived', false);

        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%');
            });
        }

        $goals = (clone $baseQuery)
            ->withSum(
                ['contributions as saved_amount' => fn ($q) => $q->where('is_archived', false)],
                'amount'
            )
            ->with([
                'contributions' => fn ($q) => $q
                    ->where('is_archived', false)
                    ->latest('contributed_at')
                    ->latest('id'),
            ])
            ->orderByRaw(
                "CASE status
                    WHEN 'in_progress' THEN 1
                    WHEN 'paused' THEN 2
                    WHEN 'completed' THEN 3
                    ELSE 4
                END"
            )
            ->orderBy('target_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $goals->getCollection()->transform(function (SavingsGoal $goal): SavingsGoal {
            $saved = (float) ($goal->saved_amount ?? 0);
            $target = (float) ($goal->target_amount ?? 0);
            $remaining = max(0, $target - $saved);
            $progress = $target > 0
                ? min(100, round(($saved / $target) * 100, 1))
                : 0;

            $goal->setAttribute('saved_amount', $saved);
            $goal->setAttribute('remaining_amount', $remaining);
            $goal->setAttribute('progress_percent', $progress);

            return $goal;
        });

        /*
         * Statistics must represent all active savings goals, not only the
         * current paginated page or current search results.
         */
        $allGoals = SavingsGoal::query()
            ->where('user_id', $request->user()->id)
            ->where('is_archived', false)
            ->withSum(
                ['contributions as saved_amount' => fn ($q) => $q->where('is_archived', false)],
                'amount'
            )
            ->get();

        $totalSaved = (float) $allGoals->sum(
            fn (SavingsGoal $goal) => (float) ($goal->saved_amount ?? 0)
        );

        $totalTarget = (float) $allGoals->sum('target_amount');
        $totalRemaining = max(0, $totalTarget - $totalSaved);

        return view('savings.index', [
            'goals' => $goals,
            'search' => $search,
            'perPage' => $perPage,
            'totalSaved' => $totalSaved,
            'totalTarget' => $totalTarget,
            'totalRemaining' => $totalRemaining,
            'totalGoals' => $allGoals->count(),
        ]);
    }
}

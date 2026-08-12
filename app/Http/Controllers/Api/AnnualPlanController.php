<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnualPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->integer('year', now()->year);
        $search = trim((string) $request->query('q', ''));

        $query = Plan::query()
            ->where('user_id', $request->user()->id)
            ->where('plan_year', $year)
            ->orderBy('status')
            ->orderBy('target_date')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $plans = $query->get();
        $allForYear = Plan::query()
            ->where('user_id', $request->user()->id)
            ->where('plan_year', $year)
            ->get();

        $total = $allForYear->count();
        $completed = $allForYear->where('status', 'completed')->count();
        $overallProgress = $total > 0
            ? (int) round((float) $allForYear->avg('progress_percent'))
            : 0;

        $availableYears = Plan::query()
            ->where('user_id', $request->user()->id)
            ->select('plan_year')
            ->whereNotNull('plan_year')
            ->distinct()
            ->orderByDesc('plan_year')
            ->pluck('plan_year')
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        return response()->json([
            'year' => $year,
            'total' => $total,
            'completed' => $completed,
            'overall_progress' => $overallProgress,
            'available_years' => $availableYears,
            'plans' => $plans,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data['period'] = 'annually';
        $data['status'] = ($data['progress_percent'] ?? 0) >= 100 ? 'completed' : 'in_progress';

        $plan = Plan::create($data);

        return response()->json($plan->fresh(), 201);
    }

    public function update(Request $request, Plan $annualPlan): JsonResponse
    {
        $this->authorizeOwner($request, $annualPlan);
        $data = $this->validated($request);
        $data['period'] = 'annually';
        $data['status'] = ($data['progress_percent'] ?? 0) >= 100 ? 'completed' : 'in_progress';

        $annualPlan->update($data);

        return response()->json($annualPlan->fresh());
    }

    public function toggle(Request $request, Plan $annualPlan): JsonResponse
    {
        $this->authorizeOwner($request, $annualPlan);
        $data = $request->validate([
            'completed' => ['required', 'boolean'],
        ]);

        $completed = (bool) $data['completed'];
        $annualPlan->update([
            'status' => $completed ? 'completed' : 'in_progress',
            'progress_percent' => $completed
                ? 100
                : min(99, max(0, (int) $annualPlan->progress_percent)),
        ]);

        return response()->json($annualPlan->fresh());
    }

    public function destroy(Request $request, Plan $annualPlan): JsonResponse
    {
        $this->authorizeOwner($request, $annualPlan);
        $annualPlan->delete();

        return response()->json(['message' => 'Annual plan deleted.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'plan_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'description' => ['nullable', 'string'],
            'target_date' => ['nullable', 'date'],
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);
    }

    private function authorizeOwner(Request $request, Plan $plan): void
    {
        abort_unless((int) $plan->user_id === (int) $request->user()->id, 403);
    }
}

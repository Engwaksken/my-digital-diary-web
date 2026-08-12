<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->integer('year', now()->year);
        $search = trim((string) $request->query('q', ''));

        $query = Plan::where('user_id', $request->user()->id)
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

        $plans = $query->paginate(15)->withQueryString();

        $allForYear = Plan::where('user_id', $request->user()->id)
            ->where('plan_year', $year)
            ->get();

        $total = $allForYear->count();
        $completed = $allForYear->where('status', 'completed')->count();
        $overallProgress = $total > 0
            ? (int) round($allForYear->avg('progress_percent'))
            : 0;

        $availableYears = Plan::where('user_id', $request->user()->id)
            ->select('plan_year')->distinct()->orderByDesc('plan_year')->pluck('plan_year')
            ->push(now()->year)->unique()->sortDesc()->values();

        return view('annual-plans.index', compact(
            'plans', 'year', 'search', 'total', 'completed', 'overallProgress', 'availableYears'
        ));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data['period'] = 'annually';
        $data['status'] = ($data['progress_percent'] ?? 0) >= 100 ? 'completed' : 'in_progress';

        Plan::create($data);

        return back()->with('success', 'Annual plan added successfully.');
    }

    public function update(Request $request, Plan $annualPlan)
    {
        $this->authorizeOwner($request, $annualPlan);
        $data = $this->validated($request);
        $data['period'] = 'annually';
        $data['status'] = ($data['progress_percent'] ?? 0) >= 100 ? 'completed' : 'in_progress';
        $annualPlan->update($data);

        return back()->with('success', 'Annual plan updated.');
    }

    public function toggle(Request $request, Plan $annualPlan)
    {
        $this->authorizeOwner($request, $annualPlan);
        $completed = $request->boolean('completed');

        $annualPlan->update([
            'status' => $completed ? 'completed' : 'in_progress',
            'progress_percent' => $completed ? 100 : min(99, max(0, (int) $annualPlan->progress_percent)),
        ]);

        return back()->with('success', $completed ? 'Annual plan marked complete.' : 'Annual plan reopened.');
    }

    public function destroy(Request $request, Plan $annualPlan)
    {
        $this->authorizeOwner($request, $annualPlan);
        $annualPlan->delete();
        return back()->with('success', 'Annual plan deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $deleted = Plan::where('user_id', $request->user()->id)
            ->whereIn('id', $ids)
            ->delete();

        return back()->with('success', "Deleted {$deleted} annual plan(s).");
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

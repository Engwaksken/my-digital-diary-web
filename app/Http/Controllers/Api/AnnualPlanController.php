<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Reminder;
use App\Models\PersonalGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnnualPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->integer('year', now()->year);
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $period = trim((string) $request->query('period', ''));
        $month = max(0, min(12, (int) $request->integer('month', 0)));
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $base = Plan::query()->where('user_id', $request->user()->id)
            ->where('plan_year', $year)->whereIn('period', ['annually', 'monthly']);
        $query = clone $base;

        if ($search !== '') {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }
        if (in_array($status, ['pending', 'in_progress', 'completed'], true)) $query->where('status', $status);
        if (in_array($period, ['annually', 'monthly'], true)) $query->where('period', $period);
        if ($month >= 1 && $month <= 12) $query->where('plan_month', $month);

        $paginator = $query
            ->orderByRaw("CASE WHEN period = 'monthly' THEN 0 ELSE 1 END")
            ->orderByRaw('COALESCE(plan_month, 13)')
            ->orderBy('target_date')->orderByDesc('id')
            ->paginate($perPage)->withQueryString();

        $all = $base->get();
        $total = $all->count();
        $availableYears = Plan::where('user_id', $request->user()->id)
            ->whereIn('period', ['annually', 'monthly'])->whereNotNull('plan_year')
            ->select('plan_year')->distinct()->orderByDesc('plan_year')->pluck('plan_year')
            ->push(now()->year)->unique()->sortDesc()->values();

        return response()->json([
            'year' => $year,
            'total' => $total,
            'completed' => $all->where('status', 'completed')->count(),
            'monthly' => $all->where('period', 'monthly')->count(),
            'with_reminder' => $all->filter(fn ($p) => $p->reminder_at !== null)->count(),
            'overall_progress' => $total ? (int) round((float) $all->avg('progress_percent')) : 0,
            'available_years' => $availableYears,
            'plans' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->normalize($this->validated($request));
        $data['user_id'] = $request->user()->id;
        $plan = Plan::create($data);
        $this->syncReminder($request, $plan);
        return response()->json($plan->fresh(), 201);
    }

    public function update(Request $request, Plan $annualPlan): JsonResponse
    {
        $this->authorizeOwner($request, $annualPlan);
        $annualPlan->update($this->normalize($this->validated($request)));
        $this->syncReminder($request, $annualPlan->fresh());
        return response()->json($annualPlan->fresh());
    }

    public function toggle(Request $request, Plan $annualPlan): JsonResponse
    {
        $this->authorizeOwner($request, $annualPlan);
        $completed = (bool) $request->validate(['completed' => ['required', 'boolean']])['completed'];
        $annualPlan->update([
            'status' => $completed ? 'completed' : 'in_progress',
            'progress_percent' => $completed ? 100 : min(99, max(0, (int) $annualPlan->progress_percent)),
        ]);
        $this->syncReminder($request, $annualPlan->fresh());
        return response()->json($annualPlan->fresh());
    }

    public function destroy(Request $request, Plan $annualPlan): JsonResponse
    {
        $this->authorizeOwner($request, $annualPlan);
        Reminder::where('user_id', $request->user()->id)->where('source_type', 'annual_plan')->where('source_id', $annualPlan->id)->delete();
        $annualPlan->delete();
        return response()->json(['message' => 'Plan deleted.']);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required','array','min:1'], 'ids.*' => ['integer']]);
        $ids = Plan::where('user_id', $request->user()->id)->whereIn('id', $data['ids'])->pluck('id');
        Reminder::where('user_id', $request->user()->id)->where('source_type', 'annual_plan')->whereIn('source_id', $ids)->delete();
        $deleted = Plan::where('user_id', $request->user()->id)->whereIn('id', $ids)->delete();
        return response()->json(['message' => "$deleted plan(s) deleted.", 'deleted' => $deleted]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'personal_goal_id' => ['nullable', 'integer', 'exists:personal_goals,id'],
            'title' => ['required', 'string', 'max:255'],
            'plan_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period' => ['required', 'in:annually,monthly'],
            'plan_month' => ['nullable', 'integer', 'min:1', 'max:12', 'required_if:period,monthly'],
            'description' => ['nullable', 'string'],
            'target_date' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date'],
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);
        if (!empty($data['personal_goal_id'])) {
            abort_unless(PersonalGoal::where('user_id', $request->user()->id)->whereKey($data['personal_goal_id'])->exists(), 403);
        }
        return $data;
    }

    private function normalize(array $data): array
    {
        if (($data['period'] ?? 'annually') === 'annually') $data['plan_month'] = null;
        $data['status'] = ($data['progress_percent'] ?? 0) >= 100 ? 'completed' : 'in_progress';
        return $data;
    }

    private function syncReminder(Request $request, Plan $plan): void
    {
        $query = Reminder::where('user_id', $request->user()->id)->where('source_type', 'annual_plan')->where('source_id', $plan->id);
        if (! $plan->reminder_at) { $query->delete(); return; }
        $when = Carbon::parse($plan->reminder_at);
        $label = $plan->period === 'monthly' && $plan->plan_month ? Carbon::create()->month($plan->plan_month)->format('F').' plan' : 'Annual plan';
        $query->updateOrCreate([], [
            'title' => $plan->title, 'module' => 'annual-plan', 'message' => "{$label}: {$plan->title}",
            'frequency' => 'once', 'next_run_at' => $when, 'channel' => 'mail',
            'is_active' => $plan->status !== 'completed' && $when->isFuture(),
            'source_signature' => $plan->id.':'.$when->format('Y-m-d H:i:s'),
        ]);
    }

    private function authorizeOwner(Request $request, Plan $plan): void
    {
        abort_unless((int) $plan->user_id === (int) $request->user()->id, 403);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Reminder;
use App\Models\PersonalGoal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\AnnualPlanWellbeingSyncService;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->integer('year', now()->year);
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $period = trim((string) $request->query('period', ''));
        $month = max(0, min(12, (int) $request->integer('month', 0)));
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $base = Plan::query()
            ->where('user_id', $request->user()->id)
            ->where('plan_year', $year)
            ->whereIn('period', ['annually', 'monthly']);

        $query = (clone $base);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if (in_array($status, ['pending', 'in_progress', 'completed'], true)) {
            $query->where('status', $status);
        }
        if (in_array($period, ['annually', 'monthly'], true)) {
            $query->where('period', $period);
        }
        if ($month >= 1 && $month <= 12) {
            $query->where('plan_month', $month);
        }

        $plans = $query
            ->orderByRaw("CASE WHEN period = 'monthly' THEN 0 ELSE 1 END")
            ->orderByRaw('COALESCE(plan_month, 13)')
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderBy('target_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $allForYear = $base->get();
        $total = $allForYear->count();
        $completed = $allForYear->where('status', 'completed')->count();
        $monthly = $allForYear->where('period', 'monthly')->count();
        $withReminder = $allForYear->filter(fn ($p) => $p->reminder_at !== null)->count();
        $overallProgress = $total > 0 ? (int) round((float) $allForYear->avg('progress_percent')) : 0;

        $availableYears = Plan::where('user_id', $request->user()->id)
            ->whereIn('period', ['annually', 'monthly'])
            ->select('plan_year')->whereNotNull('plan_year')->distinct()
            ->orderByDesc('plan_year')->pluck('plan_year')
            ->push(now()->year)->unique()->sortDesc()->values();

        $goalOptions = PersonalGoal::where('user_id', $request->user()->id)->where('is_archived', false)->whereIn('status', ['not_started','in_progress'])->orderBy('title')->pluck('title','id');

        return view('annual-plans.index', compact(
            'plans', 'year', 'search', 'status', 'period', 'month', 'perPage',
            'total', 'completed', 'monthly', 'withReminder', 'overallProgress', 'availableYears', 'goalOptions'
        ));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data = $this->normalize($data);

        $plan = DB::transaction(function () use ($data) {
            return Plan::create($data);
        });
        $this->syncReminder($request, $plan);

        if ((int) $plan->progress_percent >= 100) {
            app(AnnualPlanWellbeingSyncService::class)
                ->syncCompletion($request->user(), $plan->fresh(), true);
        }

        return back()->with('success', 'Plan added successfully.');
    }

    public function update(Request $request, Plan $annualPlan)
    {
        $this->authorizeOwner($request, $annualPlan);
        $data = $this->normalize($this->validated($request));
        $annualPlan->update($data);
        $freshPlan = $annualPlan->fresh();
        $this->syncReminder($request, $freshPlan);

        app(AnnualPlanWellbeingSyncService::class)
            ->syncCompletion(
                $request->user(),
                $freshPlan,
                (int) $freshPlan->progress_percent >= 100
            );

        return back()->with('success', 'Plan updated.');
    }

    public function toggle(Request $request, Plan $annualPlan)
    {
        $this->authorizeOwner($request, $annualPlan);
        $completed = $request->boolean('completed');
        $annualPlan->update([
            'status' => $completed ? 'completed' : 'in_progress',
            'progress_percent' => $completed ? 100 : min(99, max(0, (int) $annualPlan->progress_percent)),
        ]);

        $reminder = Reminder::where('user_id', $request->user()->id)
            ->where('source_type', 'annual_plan')->where('source_id', $annualPlan->id)->first();
        if ($reminder) {
            $reminder->is_active = ! $completed && $annualPlan->reminder_at && $annualPlan->reminder_at->isFuture();
            $reminder->save();
        }

        $syncMessage = app(AnnualPlanWellbeingSyncService::class)
            ->syncCompletion($request->user(), $annualPlan->fresh(), $completed);

        return back()->with(
            'success',
            $syncMessage ?: ($completed ? 'Plan marked complete.' : 'Plan reopened.')
        );
    }

    public function destroy(Request $request, Plan $annualPlan)
    {
        $this->authorizeOwner($request, $annualPlan);
        Reminder::where('user_id', $request->user()->id)->where('source_type', 'annual_plan')->where('source_id', $annualPlan->id)->delete();
        $annualPlan->delete();
        return back()->with('success', 'Plan deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];
        $owned = Plan::where('user_id', $request->user()->id)->whereIn('id', $ids)->pluck('id');
        Reminder::where('user_id', $request->user()->id)->where('source_type', 'annual_plan')->whereIn('source_id', $owned)->delete();
        $deleted = Plan::where('user_id', $request->user()->id)->whereIn('id', $owned)->delete();
        return back()->with('success', "Deleted {$deleted} plan(s).");
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
        if (($data['period'] ?? 'annually') === 'annually') {
            $data['plan_month'] = null;
        }
        $data['status'] = ($data['progress_percent'] ?? 0) >= 100 ? 'completed' : 'in_progress';
        return $data;
    }

    private function syncReminder(Request $request, Plan $plan): void
    {
        $query = Reminder::where('user_id', $request->user()->id)
            ->where('source_type', 'annual_plan')->where('source_id', $plan->id);

        if (! $plan->reminder_at) {
            $query->delete();
            return;
        }

        $when = Carbon::parse($plan->reminder_at);
        $label = $plan->period === 'monthly' && $plan->plan_month
            ? Carbon::create()->month($plan->plan_month)->format('F').' plan'
            : 'Annual plan';

        $query->updateOrCreate([], [
            'title' => $plan->title,
            'module' => 'annual-plan',
            'message' => "{$label}: {$plan->title}",
            'frequency' => 'once',
            'next_run_at' => $when,
            'channel' => 'mail',
            'is_active' => $plan->status !== 'completed' && $when->isFuture(),
            'source_signature' => $plan->id.':'.$when->format('Y-m-d H:i:s'),
        ]);
    }

    private function authorizeOwner(Request $request, Plan $plan): void
    {
        abort_unless((int) $plan->user_id === (int) $request->user()->id, 403);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\Reminder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Services\OfflineConflictGuard;

class DailyPlannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $date = $request->filled('date') ? Carbon::parse($request->date) : today();
        $dateString = $date->toDateString();

        $plan = DailyPlan::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('plan_date', $dateString)
            ->first();

        if (! $plan) {
            return response()->json([
                'plan' => [
                    'id' => 0,
                    'user_id' => $request->user()->id,
                    'plan_date' => $dateString,
                    'title' => 'My Daily Plan',
                    'notes' => null,
                    'achievements' => null,
                    'challenges' => null,
                    'items' => [],
                ],
                'items' => [],
                'total' => 0,
                'completed' => 0,
                'pending' => 0,
                'timed' => 0,
                'progress' => 0,
            ]);
        }

        return $this->respond($plan);
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'period' => ['nullable', 'in:all,7_days,30_days,90_days,this_month,last_month,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);

        $today = today();
        $search = trim((string) ($validated['q'] ?? ''));
        $period = (string) ($validated['period'] ?? 'all');
        $perPage = (int) ($validated['per_page'] ?? 10);

        $query = DailyPlan::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('plan_date', '<', $today->toDateString());

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('items', function ($itemQuery) use ($search) {
                        $itemQuery->where('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
            });
        }

        switch ($period) {
            case '7_days':
                $query->whereDate('plan_date', '>=', $today->copy()->subDays(7)->toDateString());
                break;
            case '30_days':
                $query->whereDate('plan_date', '>=', $today->copy()->subDays(30)->toDateString());
                break;
            case '90_days':
                $query->whereDate('plan_date', '>=', $today->copy()->subDays(90)->toDateString());
                break;
            case 'this_month':
                $query->whereBetween('plan_date', [
                    $today->copy()->startOfMonth()->toDateString(),
                    $today->copy()->endOfMonth()->toDateString(),
                ]);
                break;
            case 'last_month':
                $lastMonth = $today->copy()->subMonthNoOverflow();
                $query->whereBetween('plan_date', [
                    $lastMonth->copy()->startOfMonth()->toDateString(),
                    $lastMonth->copy()->endOfMonth()->toDateString(),
                ]);
                break;
            case 'custom':
                if (! empty($validated['from'])) {
                    $query->whereDate('plan_date', '>=', Carbon::parse($validated['from'])->toDateString());
                }
                if (! empty($validated['to'])) {
                    $query->whereDate('plan_date', '<=', Carbon::parse($validated['to'])->toDateString());
                }
                break;
        }

        $plans = $query
            ->withCount([
                'items as total',
                'items as completed' => fn ($q) => $q->where('is_completed', true),
            ])
            ->orderByDesc('plan_date')
            ->paginate($perPage);

        $plans->getCollection()->transform(function (DailyPlan $plan) {
            $total = (int) $plan->total;
            $completed = (int) $plan->completed;
            $plan->setAttribute('pending', max(0, $total - $completed));
            $plan->setAttribute('progress', $total > 0 ? (int) round(($completed / $total) * 100) : 0);
            return $plan;
        });

        return response()->json($plans);
    }

    public function updatePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_date' => 'required|date',
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'achievements' => 'nullable|string',
            'challenges' => 'nullable|string',
        ]);

        $plan = DailyPlan::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'plan_date' => Carbon::parse($data['plan_date'])->toDateString(),
            ],
            ['title' => 'My Daily Plan']
        );

        if ($conflict = OfflineConflictGuard::check($request, $plan)) return $conflict;

        $plan->update([
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
                'achievements' => $data['achievements'] ?? null,
                'challenges' => $data['challenges'] ?? null,
        ]);

        return $this->respond($plan);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_date' => 'required|date',
            'title' => 'required|string|max:255',
            'personal_goal_id' => 'nullable|integer|exists:personal_goals,id',
            'description' => 'nullable|string',
            'achievements' => 'nullable|string',
            'challenges' => 'nullable|string',
            'priority' => 'required|in:low,medium,high',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
        ]);

        $plan = DailyPlan::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'plan_date' => Carbon::parse($data['plan_date'])->toDateString(),
            ],
            ['title' => 'My Daily Plan']
        );

        unset($data['plan_date']);
        $data['sort_order'] = ($plan->items()->max('sort_order') ?? 0) + 1;
        $item = $plan->items()->create($data);

        return response()->json($item, 201);
    }

    public function updateItem(Request $request, DailyPlanItem $item): JsonResponse
    {
        $this->owned($request, $item);
        if ($conflict = OfflineConflictGuard::check($request, $item)) return $conflict;

        $data = $request->validate([
            'plan_date' => 'nullable|date',
            'title' => 'required|string|max:255',
            'personal_goal_id' => 'nullable|integer|exists:personal_goals,id',
            'description' => 'nullable|string',
            'achievements' => 'nullable|string',
            'challenges' => 'nullable|string',
            'priority' => 'required|in:low,medium,high',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
        ]);

        $oldDate = $item->plan->plan_date->toDateString();
        $targetDate = isset($data['plan_date']) ? Carbon::parse($data['plan_date'])->toDateString() : null;
        unset($data['plan_date']);
        if ($targetDate && $targetDate !== $oldDate) {
            if ($item->is_completed) {
                return response()->json(['message' => 'Completed tasks cannot be moved. Reopen the task first if it needs rescheduling.'], 422);
            }
            $targetPlan = DailyPlan::firstOrCreate(['user_id' => $request->user()->id, 'plan_date' => $targetDate], ['title' => 'My Daily Plan']);
            $data['daily_plan_id'] = $targetPlan->id;
            $data['sort_order'] = ($targetPlan->items()->max('sort_order') ?? 0) + 1;
        }
        $item->update($data);
        if ($targetDate && $targetDate !== $oldDate) {
            $this->rescheduleReminderDate($request, $item, $targetDate);
        }
        return response()->json($item->fresh()->load('plan'));
    }

    public function toggle(Request $request, DailyPlanItem $item): JsonResponse
    {
        $this->owned($request, $item);
        if ($conflict = OfflineConflictGuard::check($request, $item)) return $conflict;
        $completed = ! (bool) $item->is_completed;
        $item->update([
            'is_completed' => $completed,
            'completed_at' => $completed ? now() : null,
        ]);

        return response()->json($item->fresh());
    }

    public function moveItem(Request $request, DailyPlanItem $item): JsonResponse
    {
        $this->owned($request, $item);
        if ($conflict = OfflineConflictGuard::check($request, $item)) return $conflict;

        if ($item->is_completed) {
            return response()->json(['message' => 'Completed tasks cannot be moved. Reopen the task first if it needs rescheduling.'], 422);
        }

        $data = $request->validate(['target_date' => ['required', 'date']]);
        $targetDate = Carbon::parse($data['target_date'])->toDateString();
        $this->movePendingItemToDate($request, $item, $targetDate);

        return response()->json([
            'message' => 'Task moved successfully.',
            'item' => $item->fresh()->load('plan'),
        ]);
    }

    public function bulkMove(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'target_date' => ['required', 'date'],
        ]);

        $targetDate = Carbon::parse($data['target_date'])->toDateString();
        $items = DailyPlanItem::query()
            ->with('plan')
            ->whereIn('id', $data['ids'])
            ->whereHas('plan', fn ($q) => $q->where('user_id', $request->user()->id))
            ->get();

        $moved = 0;
        $skipped = 0;
        foreach ($items as $item) {
            if ($item->is_completed || $item->plan->plan_date->toDateString() === $targetDate) {
                $skipped++;
                continue;
            }
            $this->movePendingItemToDate($request, $item, $targetDate);
            $moved++;
        }

        return response()->json([
            'message' => $moved.' task'.($moved === 1 ? '' : 's').' moved.',
            'moved' => $moved,
            'skipped' => $skipped,
            'target_date' => $targetDate,
        ]);
    }

    private function movePendingItemToDate(Request $request, DailyPlanItem $item, string $targetDate): void
    {
        $targetPlan = DailyPlan::firstOrCreate(
            ['user_id' => $request->user()->id, 'plan_date' => $targetDate],
            ['title' => 'My Daily Plan']
        );

        $item->update([
            'daily_plan_id' => $targetPlan->id,
            'sort_order' => ($targetPlan->items()->max('sort_order') ?? 0) + 1,
        ]);

        $this->rescheduleReminderDate($request, $item, $targetDate);
    }

    private function rescheduleReminderDate(Request $request, DailyPlanItem $item, string $targetDate): void
    {
        if (!Schema::hasColumns('reminders', ['source_type', 'source_id'])) return;

        Reminder::query()
            ->where('user_id', $request->user()->id)
            ->where('source_type', 'daily_plan_item')
            ->where('source_id', $item->id)
            ->where('is_active', true)
            ->get()
            ->each(function (Reminder $reminder) use ($targetDate) {
                if (!$reminder->next_run_at) return;
                $reminder->next_run_at = Carbon::parse($targetDate.' '.$reminder->next_run_at->format('H:i:s'));
                $reminder->save();
            });
    }

    public function destroyItem(Request $request, DailyPlanItem $item): JsonResponse
    {
        $this->owned($request, $item);
        if ($conflict = OfflineConflictGuard::check($request, $item)) return $conflict;
        $item->delete();
        return response()->json(['message' => 'Deleted.']);
    }


    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required','array','min:1'], 'ids.*' => ['integer']])['ids'];
        $deleted = DailyPlanItem::whereIn('id', $ids)->whereHas('plan', fn ($q) => $q->where('user_id', $request->user()->id))->delete();
        return response()->json(['message' => "{$deleted} task(s) deleted.", 'deleted' => $deleted]);
    }

    private function owned(Request $request, DailyPlanItem $item): void
    {
        abort_unless($item->plan && (int) $item->plan->user_id === (int) $request->user()->id, 403);
    }

    private function respond(DailyPlan $plan): JsonResponse
    {
        $plan->load(['items' => function ($query) {
            $query->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')
                ->orderBy('start_time')
                ->orderBy('sort_order')
                ->orderBy('id');
        }]);

        $total = $plan->items->count();
        $completed = $plan->items->where('is_completed', true)->count();
        $timed = $plan->items->filter(fn ($item) => filled($item->start_time))->count();

        return response()->json([
            'plan' => $plan,
            'items' => $plan->items->values(),
            'total' => $total,
            'completed' => $completed,
            'pending' => max(0, $total - $completed),
            'timed' => $timed,
            'progress' => $total ? (int) round(($completed / $total) * 100) : 0,
        ]);
    }
}

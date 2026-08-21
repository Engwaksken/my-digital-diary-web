<?php

namespace App\Http\Controllers;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\PersonalGoal;
use App\Models\Reminder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DailyPlannerController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : today();

        $plan = DailyPlan::where('user_id', $request->user()->id)
            ->whereDate('plan_date', $date->toDateString())
            ->first();

        if (!$plan) {
            $plan = new DailyPlan([
                'user_id'   => $request->user()->id,
                'plan_date' => $date->toDateString(),
                'title'     => 'My Daily Plan',
                'notes'     => null,
            ]);
            $plan->setRelation('items', collect());
        } else {
            // Timed tasks first, in chronological order. Untimed tasks appear last.
            $items = $plan->items()
                ->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')
                ->orderBy('start_time')
                ->orderBy('end_time')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $plan->setRelation('items', $items);
        }

        $total = $plan->items->count();
        $done = $plan->items->where('is_completed', true)->count();
        $pending = max(0, $total - $done);
        $scheduled = $plan->items->filter(fn ($item) => !empty($item->start_time))->count();
        $progress = $total ? (int) round(($done / $total) * 100) : 0;

        $historySearch = trim((string) $request->input('history_search', ''));
        $historyPeriod = (string) $request->input('history_period', 'all');
        $historyFrom = $request->input('history_from');
        $historyTo = $request->input('history_to');
        $historyPerPage = (int) $request->input('history_per_page', 10);

        if (!in_array($historyPerPage, [10, 25, 50, 100], true)) {
            $historyPerPage = 10;
        }

        $pastPlansQuery = DailyPlan::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('plan_date', '<', today()->toDateString());

        if ($historySearch !== '') {
            $pastPlansQuery->where(function ($query) use ($historySearch) {
                $query->where('title', 'like', '%' . $historySearch . '%')
                    ->orWhere('notes', 'like', '%' . $historySearch . '%')
                    ->orWhereHas('items', function ($itemQuery) use ($historySearch) {
                        $itemQuery->where('title', 'like', '%' . $historySearch . '%')
                            ->orWhere('description', 'like', '%' . $historySearch . '%');
                    });
            });
        }

        switch ($historyPeriod) {
            case 'last_7_days':
                $pastPlansQuery->whereDate('plan_date', '>=', today()->subDays(7)->toDateString());
                break;

            case 'last_30_days':
                $pastPlansQuery->whereDate('plan_date', '>=', today()->subDays(30)->toDateString());
                break;

            case 'last_90_days':
                $pastPlansQuery->whereDate('plan_date', '>=', today()->subDays(90)->toDateString());
                break;

            case 'this_month':
                $pastPlansQuery->whereDate('plan_date', '>=', today()->startOfMonth()->toDateString());
                break;

            case 'last_month':
                $start = today()->subMonthNoOverflow()->startOfMonth();
                $end = $start->copy()->endOfMonth();
                $pastPlansQuery->whereBetween('plan_date', [
                    $start->toDateString(),
                    $end->toDateString(),
                ]);
                break;

            case 'custom':
                if ($historyFrom) {
                    $pastPlansQuery->whereDate('plan_date', '>=', Carbon::parse($historyFrom)->toDateString());
                }
                if ($historyTo) {
                    $pastPlansQuery->whereDate('plan_date', '<=', Carbon::parse($historyTo)->toDateString());
                }
                break;
        }

        $pastPlans = $pastPlansQuery
            ->withCount([
                'items',
                'items as completed_items_count' => fn ($q) => $q->where('is_completed', true),
            ])
            ->orderByDesc('plan_date')
            ->paginate($historyPerPage, ['*'], 'past_page')
            ->withQueryString();

        $activeTab = $request->input('tab') === 'history'
            || $request->filled('past_page')
            || $historySearch !== ''
            || $historyPeriod !== 'all'
            || $request->filled('history_from')
            || $request->filled('history_to')
            ? 'history'
            : 'tasks';

        $goalOptions = PersonalGoal::where('user_id', $request->user()->id)->where('is_archived', false)->whereIn('status',['not_started','in_progress'])->orderBy('title')->pluck('title','id');

        return view('daily-planner.index', compact(
            'plan',
            'date',
            'total',
            'done',
            'pending',
            'scheduled',
            'progress',
            'pastPlans',
            'historySearch',
            'historyPeriod',
            'historyFrom',
            'historyTo',
            'historyPerPage',
            'activeTab',
            'goalOptions'
        ));
    }

    public function updatePlan(Request $request)
    {
        $data = $request->validate([
            'plan_date' => 'required|date',
            'title'     => 'required|string|max:255',
            'notes'     => 'nullable|string',
            'achievements' => 'nullable|string',
            'challenges' => 'nullable|string',
        ]);

        DailyPlan::updateOrCreate(
            [
                'user_id'   => $request->user()->id,
                'plan_date' => $data['plan_date'],
            ],
            [
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'achievements' => $data['achievements'] ?? null,
                'challenges' => $data['challenges'] ?? null,
            ]
        );

        return back()->with('success', 'Day plan saved successfully.');
    }

    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'plan_date'   => 'required|date',
            'title'       => 'required|string|max:255',
            'personal_goal_id' => 'nullable|integer|exists:personal_goals,id',
            'description' => 'nullable|string',
            'achievements' => 'nullable|string',
            'challenges' => 'nullable|string',
            'priority'    => 'required|in:low,medium,high',
            'start_time'  => 'nullable|date_format:H:i',
            'end_time'    => 'nullable|date_format:H:i|after:start_time',
        ]);

        $plan = DailyPlan::firstOrCreate(
            [
                'user_id'   => $request->user()->id,
                'plan_date' => $data['plan_date'],
            ],
            ['title' => 'My Daily Plan']
        );

        unset($data['plan_date']);
        $data['sort_order'] = ($plan->items()->max('sort_order') ?? 0) + 1;
        $plan->items()->create($data);

        return back()->with('success', 'Task added successfully.');
    }

    private function owned(Request $request, DailyPlanItem $item): DailyPlanItem
    {
        abort_unless(
            $item->plan && (int) $item->plan->user_id === (int) $request->user()->id,
            403
        );

        return $item;
    }

    public function toggle(Request $request, DailyPlanItem $item)
    {
        $this->owned($request, $item);

        $newState = !$item->is_completed;

        $item->update([
            'is_completed' => $newState,
            'completed_at' => $newState ? now() : null,
        ]);

        return back()->with('success', $newState ? 'Task marked as completed.' : 'Task reopened.');
    }

    public function updateItem(Request $request, DailyPlanItem $item)
    {
        $this->owned($request, $item);

        $data = $request->validate([
            'plan_date'   => 'nullable|date',
            'title'       => 'required|string|max:255',
            'personal_goal_id' => 'nullable|integer|exists:personal_goals,id',
            'description' => 'nullable|string',
            'achievements' => 'nullable|string',
            'challenges' => 'nullable|string',
            'priority'    => 'required|in:low,medium,high',
            'start_time'  => 'nullable|date_format:H:i',
            'end_time'    => 'nullable|date_format:H:i|after:start_time',
        ]);

        $oldDate = $item->plan->plan_date->toDateString();
        $targetDate = isset($data['plan_date']) ? Carbon::parse($data['plan_date'])->toDateString() : null;
        unset($data['plan_date']);
        if ($targetDate && $targetDate !== $oldDate) {
            if ($item->is_completed) {
                return back()->with('error', 'Completed tasks stay on their original date. Reopen the task first if it needs rescheduling.');
            }
            $targetPlan = DailyPlan::firstOrCreate(['user_id' => $request->user()->id, 'plan_date' => $targetDate], ['title' => 'My Daily Plan']);
            $data['daily_plan_id'] = $targetPlan->id;
            $data['sort_order'] = ($targetPlan->items()->max('sort_order') ?? 0) + 1;
        }
        $item->update($data);
        if ($targetDate && $targetDate !== $oldDate) {
            $this->rescheduleLinkedReminders($request, $item, $targetDate);
        }

        return redirect()->route('daily-planner.index', ['date' => $targetDate ?: $oldDate])->with('success', 'Task updated successfully.');
    }

    public function moveItem(Request $request, DailyPlanItem $item)
    {
        $this->owned($request, $item);

        if ($item->is_completed) {
            return back()->with('error', 'Completed tasks stay on their original date so your history remains accurate.');
        }

        $data = $request->validate([
            'target_date' => ['required', 'date'],
        ]);

        $oldDate = $item->plan->plan_date->toDateString();
        $targetDate = Carbon::parse($data['target_date'])->toDateString();

        if ($targetDate === $oldDate) {
            return back()->with('success', 'Task is already scheduled for that date.');
        }

        $this->movePendingItemToDate($request, $item, $targetDate);

        return redirect()
            ->route('daily-planner.index', ['date' => $oldDate])
            ->with('success', 'Task moved to '.Carbon::parse($targetDate)->format('d M Y').'.');
    }

    public function bulkMove(Request $request)
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
            if ($item->is_completed) {
                $skipped++;
                continue;
            }
            if ($item->plan->plan_date->toDateString() === $targetDate) {
                $skipped++;
                continue;
            }
            $this->movePendingItemToDate($request, $item, $targetDate);
            $moved++;
        }

        $message = $moved.' pending task'.($moved === 1 ? '' : 's').' moved to '.Carbon::parse($targetDate)->format('d M Y').'.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' completed/already scheduled task'.($skipped === 1 ? ' was' : 's were').' left unchanged.';
        }

        return back()->with($moved > 0 ? 'success' : 'error', $message);
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

        $this->rescheduleLinkedReminders($request, $item, $targetDate);
    }

    private function rescheduleLinkedReminders(Request $request, DailyPlanItem $item, string $targetDate): void
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
                $time = $reminder->next_run_at->format('H:i:s');
                $reminder->next_run_at = Carbon::parse($targetDate.' '.$time);
                $reminder->save();
            });
    }

    public function destroyItem(Request $request, DailyPlanItem $item)
    {
        $this->owned($request, $item);
        $item->delete();

        return back()->with('success', 'Task deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ])['ids'];

        DailyPlanItem::whereIn('id', $ids)
            ->whereHas('plan', fn ($q) => $q->where('user_id', $request->user()->id))
            ->delete();

        return back()->with('success', 'Selected tasks deleted successfully.');
    }
}

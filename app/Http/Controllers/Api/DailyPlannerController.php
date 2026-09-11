<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\Reminder;
use App\Services\DailyPlannerRecurrenceService;
use App\Services\DailyPlannerTaskReminderService;
use App\Services\DailyPlannerWellbeingSyncService;
use App\Services\OfflineConflictGuard;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class DailyPlannerController extends Controller
{
    public function __construct(
        private readonly DailyPlannerRecurrenceService $recurrence,
        private readonly DailyPlannerTaskReminderService $taskReminders
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : today();

        $dateString = $date->toDateString();

        $plan = DailyPlan::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'plan_date' => $dateString,
            ],
            ['title' => 'My Daily Plan']
        );

        $items = $this->recurrence->itemsForDate(
            $request->user()->id,
            $date
        );

        $plan->setRelation(
            'items',
            new \Illuminate\Database\Eloquent\Collection(
                $items->all()
            )
        );

        $stats = $this->recurrence->statistics($items);

        return response()->json([
            'plan' => $plan,
            'items' => $items->values(),
            'total' => $stats['total'],
            'completed' => $stats['completed'],
            'pending' => $stats['pending'],
            'timed' => $stats['timed'],
            'progress' => $stats['progress'],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'period' => [
                'nullable',
                'in:all,7_days,30_days,90_days,this_month,last_month,custom',
            ],
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
            ->whereDate(
                'plan_date',
                '<',
                $today->toDateString()
            );

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas(
                        'items',
                        function ($itemQuery) use ($search) {
                            $itemQuery
                                ->where(
                                    'title',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'description',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
            });
        }

        $this->applyHistoryPeriod(
            $query,
            $period,
            $validated,
            $today
        );

        $plans = $query
            ->orderByDesc('plan_date')
            ->paginate($perPage);

        $plans->getCollection()->transform(
            function (DailyPlan $plan) use ($request) {
                $items = $this->recurrence->itemsForDate(
                    $request->user()->id,
                    $plan->plan_date
                );

                $stats = $this->recurrence->statistics($items);

                $plan->setAttribute('total', $stats['total']);
                $plan->setAttribute(
                    'completed',
                    $stats['completed']
                );
                $plan->setAttribute(
                    'pending',
                    $stats['pending']
                );
                $plan->setAttribute(
                    'progress',
                    $stats['progress']
                );

                return $plan;
            }
        );

        return response()->json($plans);
    }

    public function updatePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'achievements' => ['nullable', 'string'],
            'challenges' => ['nullable', 'string'],
        ]);

        $plan = DailyPlan::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'plan_date' =>
                    Carbon::parse($data['plan_date'])
                        ->toDateString(),
            ],
            ['title' => 'My Daily Plan']
        );

        if (
            $conflict = OfflineConflictGuard::check(
                $request,
                $plan
            )
        ) {
            return $conflict;
        }

        $plan->update([
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'achievements' => $data['achievements'] ?? null,
            'challenges' => $data['challenges'] ?? null,
        ]);

        return $this->index(
            Request::createFromBase(
                $request
            )->merge([
                'date' => $plan->plan_date->toDateString(),
            ])
        );
    }

    public function storeItem(Request $request): JsonResponse
    {
        $data = $this->validateItem($request, true);

        $planDate = Carbon::parse(
            $data['plan_date']
        )->toDateString();

        $plan = DailyPlan::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'plan_date' => $planDate,
            ],
            ['title' => 'My Daily Plan']
        );

        $recurrence = $this->recurrence->normalizeRecurrence(
            $data,
            $planDate
        );

        unset(
            $data['plan_date'],
            $data['repeat_days'],
            $data['repeat_type'],
            $data['repeat_interval'],
            $data['repeat_starts_on'],
            $data['repeat_ends_on'],
            $data['edit_scope'],
            $data['occurrence_date']
        );

        $data = array_merge($data, $recurrence);

        $data['sort_order'] =
            ($plan->items()->max('sort_order') ?? 0) + 1;

        $item = $plan->items()->create($data);

        $this->taskReminders->sync($item, $request->user(), Carbon::parse($planDate));

        return response()->json(
            $item->fresh([
                'plan',
                'personalGoal',
            ]),
            201
        );
    }

    public function updateItem(
        Request $request,
        DailyPlanItem $item
    ): JsonResponse {
        $this->owned($request, $item);

        if (
            $conflict = OfflineConflictGuard::check(
                $request,
                $item
            )
        ) {
            return $conflict;
        }

        $data = $this->validateItem($request, false);

        $scope = $data['edit_scope'] ?? 'series';
        $occurrenceDate = $data['occurrence_date']
            ?? $data['plan_date']
            ?? $item->plan->plan_date->toDateString();

        if ($item->isRecurring() && $scope === 'occurrence') {
            $occurrence = $this->recurrence->updateOccurrenceOnly(
                $item,
                $request->user()->id,
                Carbon::parse($occurrenceDate),
                $data
            );

            return response()->json([
                'message' => 'This occurrence was updated.',
                'occurrence' => $occurrence,
            ]);
        }

        $oldDate = $item->plan->plan_date->toDateString();
        $targetDate = isset($data['plan_date'])
            ? Carbon::parse($data['plan_date'])->toDateString()
            : null;

        $recurrence = $this->recurrence->normalizeRecurrence(
            $data,
            $targetDate ?: $oldDate
        );

        unset(
            $data['plan_date'],
            $data['repeat_days'],
            $data['repeat_type'],
            $data['repeat_interval'],
            $data['repeat_starts_on'],
            $data['repeat_ends_on'],
            $data['edit_scope'],
            $data['occurrence_date']
        );

        $data = array_merge($data, $recurrence);

        if (
            ! $item->isRecurring()
            && $targetDate
            && $targetDate !== $oldDate
        ) {
            if ($item->is_completed) {
                return response()->json([
                    'message' =>
                        'Completed tasks cannot be moved. Reopen the task first if it needs rescheduling.',
                ], 422);
            }

            $targetPlan = DailyPlan::firstOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'plan_date' => $targetDate,
                ],
                ['title' => 'My Daily Plan']
            );

            $data['daily_plan_id'] = $targetPlan->id;
            $data['sort_order'] =
                ($targetPlan->items()->max('sort_order') ?? 0) + 1;
        }

        $item->update($data);

        $this->taskReminders->sync($item->fresh(), $request->user(), Carbon::parse($viewDate ?? $occurrenceDate ?? $targetDate ?? $oldDate));

        if (
            ! $item->isRecurring()
            && $targetDate
            && $targetDate !== $oldDate
        ) {
            $this->rescheduleReminderDate(
                $request,
                $item,
                $targetDate
            );
        }

        return response()->json(
            $item->fresh([
                'plan',
                'personalGoal',
            ])
        );
    }

    public function toggle(
        Request $request,
        DailyPlanItem $item
    ): JsonResponse {
        $this->owned($request, $item);

        if (
            $conflict = OfflineConflictGuard::check(
                $request,
                $item
            )
        ) {
            return $conflict;
        }

        $date = Carbon::parse(
            $request->input(
                'occurrence_date',
                $item->plan->plan_date
            )
        );

        $result = $this->recurrence->toggleOccurrence(
            $item,
            $request->user()->id,
            $date
        );

        $freshItem = $item->fresh(['plan', 'personalGoal']);
        if ($freshItem->is_completed) {
            $this->taskReminders->cancel($freshItem, $request->user());
        } else {
            $this->taskReminders->sync($freshItem, $request->user(), $date);
        }

        $syncMessage = app(DailyPlannerWellbeingSyncService::class)
            ->syncCompletion(
                $request->user(),
                $freshItem,
                $date,
                (bool) $freshItem->is_completed
            );

        return response()->json([
            'message' => $syncMessage ?: 'Task status updated.',
            'result' => $result,
            'occurrence_date' => $date->toDateString(),
        ]);
    }

    public function moveItem(
        Request $request,
        DailyPlanItem $item
    ): JsonResponse {
        $this->owned($request, $item);

        if ($item->isRecurring()) {
            return response()->json([
                'message' =>
                    'Recurring tasks follow their repeat schedule. Edit the series instead of moving one recurring task.',
            ], 422);
        }

        if (
            $conflict = OfflineConflictGuard::check(
                $request,
                $item
            )
        ) {
            return $conflict;
        }

        if ($item->is_completed) {
            return response()->json([
                'message' =>
                    'Completed tasks cannot be moved. Reopen the task first if it needs rescheduling.',
            ], 422);
        }

        $data = $request->validate([
            'target_date' => ['required', 'date'],
        ]);

        $targetDate = Carbon::parse(
            $data['target_date']
        )->toDateString();

        $this->movePendingItemToDate(
            $request,
            $item,
            $targetDate
        );

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

        $targetDate = Carbon::parse(
            $data['target_date']
        )->toDateString();

        $items = DailyPlanItem::query()
            ->with('plan')
            ->whereIn('id', $data['ids'])
            ->whereHas(
                'plan',
                fn ($q) =>
                    $q->where(
                        'user_id',
                        $request->user()->id
                    )
            )
            ->get();

        $moved = 0;
        $skipped = 0;

        foreach ($items as $item) {
            if (
                $item->isRecurring()
                || $item->is_completed
                || $item->plan->plan_date->toDateString()
                    === $targetDate
            ) {
                $skipped++;
                continue;
            }

            $this->movePendingItemToDate(
                $request,
                $item,
                $targetDate
            );

            $moved++;
        }

        return response()->json([
            'message' =>
                $moved.' task'.($moved === 1 ? '' : 's').' moved.',
            'moved' => $moved,
            'skipped' => $skipped,
            'target_date' => $targetDate,
        ]);
    }

    public function destroyItem(
        Request $request,
        DailyPlanItem $item
    ): JsonResponse {
        $this->owned($request, $item);

        if (
            $conflict = OfflineConflictGuard::check(
                $request,
                $item
            )
        ) {
            return $conflict;
        }

        $scope = $request->input('delete_scope', 'series');
        $date = Carbon::parse(
            $request->input(
                'occurrence_date',
                $item->plan->plan_date
            )
        );

        if ($item->isRecurring() && $scope === 'occurrence') {
            $this->recurrence->skipOccurrence(
                $item,
                $request->user()->id,
                $date
            );

            return response()->json([
                'message' => 'This occurrence was removed.',
            ]);
        }

        if (
            $item->isRecurring()
            && $scope === 'future'
        ) {
            $item->update([
                'repeat_ends_on' =>
                    $date->copy()->subDay()->toDateString(),
            ]);

            return response()->json([
                'message' =>
                    'This and future occurrences were removed.',
            ]);
        }

        $this->taskReminders->cancel($item, $request->user());
        $item->delete();

        return response()->json([
            'message' => 'Task deleted.',
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'reminder_enabled' => ['nullable', 'boolean'],
            'reminder_offset_minutes' => ['nullable', 'integer', Rule::in(DailyPlannerTaskReminderService::OFFSETS)],
            'reminder_custom_at' => ['nullable', 'date'],
            'reminder_channels' => ['nullable', 'array'],
            'reminder_channels.*' => [Rule::in(['in_app', 'push', 'email'])],
            'occurrence_date' => ['nullable', 'date'],
        ]);

        $items = DailyPlanItem::query()
            ->with('plan')
            ->whereIn('id', $data['ids'])
            ->whereHas(
                'plan',
                fn ($q) =>
                    $q->where(
                        'user_id',
                        $request->user()->id
                    )
            )
            ->get();

        $deleted = 0;
        $skippedOccurrences = 0;

        foreach ($items as $item) {
            if (
                $item->isRecurring()
                && filled($data['occurrence_date'] ?? null)
            ) {
                $this->recurrence->skipOccurrence(
                    $item,
                    $request->user()->id,
                    Carbon::parse($data['occurrence_date'])
                );

                $skippedOccurrences++;
                continue;
            }

            $item->delete();
            $deleted++;
        }

        return response()->json([
            'message' =>
                ($deleted + $skippedOccurrences).' task(s) updated.',
            'deleted_series_or_once' => $deleted,
            'removed_occurrences' => $skippedOccurrences,
        ]);
    }

    private function validateItem(
        Request $request,
        bool $creating
    ): array {
        return $request->validate([
            'plan_date' => [
                $creating ? 'required' : 'nullable',
                'date',
            ],
            'title' => ['required', 'string', 'max:255'],
            'personal_goal_id' => [
                'nullable',
                'integer',
                'exists:personal_goals,id',
            ],
            'description' => ['nullable', 'string'],
            'achievements' => ['nullable', 'string'],
            'challenges' => ['nullable', 'string'],
            'priority' => [
                'required',
                Rule::in(['low', 'medium', 'high']),
            ],
            'start_time' => [
                'nullable',
                'date_format:H:i',
            ],
            'end_time' => [
                'nullable',
                'date_format:H:i',
                'after:start_time',
            ],

            'repeat_type' => [
                'nullable',
                Rule::in(
                    DailyPlannerRecurrenceService::REPEAT_TYPES
                ),
            ],

            'repeat_days' => [
                'nullable',
                'array',
                Rule::requiredIf(
                    fn () =>
                        $request->input('repeat_type')
                        === 'specific_days'
                ),
            ],

            'repeat_days.*' => [
                Rule::in(
                    DailyPlannerRecurrenceService::WEEK_DAYS
                ),
            ],

            'repeat_interval' => [
                'nullable',
                'integer',
                'min:1',
                'max:52',
            ],

            'repeat_starts_on' => [
                'nullable',
                'date',
            ],

            'repeat_ends_on' => [
                'nullable',
                'date',
                'after_or_equal:repeat_starts_on',
            ],

            'occurrence_date' => [
                'nullable',
                'date',
            ],

            'edit_scope' => [
                'nullable',
                Rule::in([
                    'occurrence',
                    'series',
                ]),
            ],
        ]);
    }

    private function movePendingItemToDate(
        Request $request,
        DailyPlanItem $item,
        string $targetDate
    ): void {
        $targetPlan = DailyPlan::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'plan_date' => $targetDate,
            ],
            ['title' => 'My Daily Plan']
        );

        $item->update([
            'daily_plan_id' => $targetPlan->id,
            'sort_order' =>
                ($targetPlan->items()->max('sort_order') ?? 0) + 1,
        ]);

        $this->rescheduleReminderDate(
            $request,
            $item,
            $targetDate
        );
    }

    private function rescheduleReminderDate(
        Request $request,
        DailyPlanItem $item,
        string $targetDate
    ): void {
        if (
            ! Schema::hasColumns(
                'reminders',
                ['source_type', 'source_id']
            )
        ) {
            return;
        }

        Reminder::query()
            ->where('user_id', $request->user()->id)
            ->where('source_type', 'daily_plan_item')
            ->where('source_id', $item->id)
            ->where('is_active', true)
            ->get()
            ->each(
                function (Reminder $reminder) use ($targetDate) {
                    if (! $reminder->next_run_at) {
                        return;
                    }

                    $reminder->next_run_at = Carbon::parse(
                        $targetDate.' '
                        .$reminder->next_run_at->format('H:i:s')
                    );

                    $reminder->save();
                }
            );
    }

    private function owned(
        Request $request,
        DailyPlanItem $item
    ): void {
        $item->loadMissing('plan');

        abort_unless(
            $item->plan
            && (int) $item->plan->user_id
                === (int) $request->user()->id,
            403
        );
    }

    private function applyHistoryPeriod(
        $query,
        string $period,
        array $validated,
        Carbon $today
    ): void {
        switch ($period) {
            case '7_days':
                $query->whereDate(
                    'plan_date',
                    '>=',
                    $today->copy()
                        ->subDays(7)
                        ->toDateString()
                );
                break;

            case '30_days':
                $query->whereDate(
                    'plan_date',
                    '>=',
                    $today->copy()
                        ->subDays(30)
                        ->toDateString()
                );
                break;

            case '90_days':
                $query->whereDate(
                    'plan_date',
                    '>=',
                    $today->copy()
                        ->subDays(90)
                        ->toDateString()
                );
                break;

            case 'this_month':
                $query->whereBetween('plan_date', [
                    $today->copy()
                        ->startOfMonth()
                        ->toDateString(),
                    $today->copy()
                        ->endOfMonth()
                        ->toDateString(),
                ]);
                break;

            case 'last_month':
                $lastMonth =
                    $today->copy()->subMonthNoOverflow();

                $query->whereBetween('plan_date', [
                    $lastMonth->copy()
                        ->startOfMonth()
                        ->toDateString(),
                    $lastMonth->copy()
                        ->endOfMonth()
                        ->toDateString(),
                ]);
                break;

            case 'custom':
                if (! empty($validated['from'])) {
                    $query->whereDate(
                        'plan_date',
                        '>=',
                        Carbon::parse(
                            $validated['from']
                        )->toDateString()
                    );
                }

                if (! empty($validated['to'])) {
                    $query->whereDate(
                        'plan_date',
                        '<=',
                        Carbon::parse(
                            $validated['to']
                        )->toDateString()
                    );
                }
                break;
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class MobileDashboardController extends Controller
{
    /**
     * Wrap the existing API DashboardController so every existing dashboard
     * field is preserved, then guarantee a stable `top_tasks` array for Mobile.
     */
    public function index(Request $request): JsonResponse
    {
        $response = app()->call(
            [app(DashboardController::class), 'index'],
            ['request' => $request]
        );

        $status = $response instanceof Response
            ? $response->getStatusCode()
            : 200;

        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
        } elseif (is_array($response)) {
            $payload = $response;
        } else {
            $payload = [];
        }

        $tasks = $this->todayTasks($request);

        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload['data']['top_tasks'] = $tasks;
            $payload['data']['today_focus'] = $tasks;
        } else {
            $payload['top_tasks'] = $tasks;
            $payload['today_focus'] = $tasks;
        }

        return response()->json($payload, $status);
    }

    /**
     * Dedicated endpoint kept for clients that want to refresh Focus alone.
     */
    public function todayFocus(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->todayTasks($request),
        ]);
    }

    private function todayTasks(Request $request): array
    {
        $user = $request->user();

        if (! $user || ! Schema::hasTable('daily_plan_items')) {
            return [];
        }

        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');

        if ($timezone === 'UTC') {
            // My Digital Diary's operational fallback when a user has not
            // explicitly selected a timezone.
            $timezone = 'Africa/Kampala';
        }

        $today = Carbon::now($timezone)->toDateString();
        $columns = Schema::getColumnListing('daily_plan_items');

        $dateColumn = $this->firstExisting($columns, [
            'plan_date',
            'task_date',
            'scheduled_date',
            'due_date',
            'date',
        ]);

        /*
         * Most installations store the day on daily_plan_items itself.
         */
        if ($dateColumn !== null) {
            $query = DB::table('daily_plan_items')
                ->where('user_id', $user->id)
                ->whereDate($dateColumn, $today);

            $this->onlyPending($query, $columns);
            $this->orderTasks($query, $columns);

            return $query
                ->limit(6)
                ->get()
                ->map(fn ($row) => $this->normaliseTask($row))
                ->values()
                ->all();
        }

        /*
         * Compatibility fallback for schemas where an item belongs to a
         * daily_plans parent and the date is stored on that parent.
         */
        if (
            in_array('daily_plan_id', $columns, true) &&
            Schema::hasTable('daily_plans')
        ) {
            $planColumns = Schema::getColumnListing('daily_plans');
            $planDateColumn = $this->firstExisting($planColumns, [
                'plan_date',
                'task_date',
                'scheduled_date',
                'date',
            ]);

            if ($planDateColumn !== null) {
                $query = DB::table('daily_plan_items as dpi')
                    ->join('daily_plans as dp', 'dp.id', '=', 'dpi.daily_plan_id')
                    ->where('dpi.user_id', $user->id)
                    ->whereDate('dp.'.$planDateColumn, $today)
                    ->select('dpi.*');

                $prefixed = array_map(
                    static fn (string $column): string => 'dpi.'.$column,
                    $columns
                );

                $this->onlyPending($query, $columns, 'dpi.');
                $this->orderTasks($query, $columns, 'dpi.');

                return $query
                    ->limit(6)
                    ->get()
                    ->map(fn ($row) => $this->normaliseTask($row))
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    private function onlyPending($query, array $columns, string $prefix = ''): void
    {
        if (in_array('is_completed', $columns, true)) {
            $query->where(function ($q) use ($prefix) {
                $q->whereNull($prefix.'is_completed')
                    ->orWhere($prefix.'is_completed', false)
                    ->orWhere($prefix.'is_completed', 0);
            });
            return;
        }

        if (in_array('completed', $columns, true)) {
            $query->where(function ($q) use ($prefix) {
                $q->whereNull($prefix.'completed')
                    ->orWhere($prefix.'completed', false)
                    ->orWhere($prefix.'completed', 0);
            });
            return;
        }

        if (in_array('status', $columns, true)) {
            $query->where(function ($q) use ($prefix) {
                $q->whereNull($prefix.'status')
                    ->orWhereNotIn(
                        DB::raw('LOWER('.$prefix.'status)'),
                        ['completed', 'done', 'cancelled']
                    );
            });
        }
    }

    private function orderTasks($query, array $columns, string $prefix = ''): void
    {
        foreach (['sort_order', 'position', 'priority_order'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderBy($prefix.$column);
                break;
            }
        }

        foreach (['start_time', 'due_time', 'time'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderBy($prefix.$column);
                break;
            }
        }

        $query->orderBy($prefix.'id');
    }

    private function normaliseTask(object $row): array
    {
        $title = $this->firstValue($row, [
            'title',
            'task',
            'name',
            'subject',
            'description',
        ]) ?: 'Daily task';

        return [
            'id' => $row->id ?? null,
            'title' => $title,
            'source' => 'Daily Planner',
            'type' => 'daily_planner',
            'time' => $this->firstValue($row, [
                'start_time',
                'due_time',
                'time',
            ]),
            'priority' => $this->firstValue($row, ['priority']),
            'status' => $this->firstValue($row, ['status']),
        ];
    }

    private function firstExisting(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function firstValue(object $row, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (! property_exists($row, $candidate)) {
                continue;
            }

            $value = trim((string) ($row->{$candidate} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}

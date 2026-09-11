<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailyPlanItem;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ReminderConsolidationService
{
    public function syncUser(User $user): void
    {
        $this->syncDailyPlanner($user);
        $this->syncProjectTasks($user);
    }

    private function syncDailyPlanner(User $user): void
    {
        if (! Schema::hasTable('daily_plan_items')
            || ! Schema::hasColumn('daily_plan_items', 'reminder_enabled')) {
            return;
        }

        try {
            DailyPlanItem::query()
                ->whereHas('plan', fn ($query) => $query->where('user_id', $user->id))
                ->where('reminder_enabled', true)
                ->where(function ($query): void {
                    $query->whereNull('is_completed')->orWhere('is_completed', false);
                })
                ->with('plan')
                ->orderByDesc('id')
                ->limit(300)
                ->get()
                ->each(function (DailyPlanItem $item) use ($user): void {
                    try {
                        app(DailyPlannerTaskReminderService::class)->sync($item, $user);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                });
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function syncProjectTasks(User $user): void
    {
        if (! Schema::hasTable('project_tasks')
            || ! Schema::hasColumn('project_tasks', 'reminder_enabled')) {
            return;
        }

        try {
            ProjectTask::query()
                ->where('user_id', $user->id)
                ->where('reminder_enabled', true)
                ->where(function ($query): void {
                    $query->whereNull('status')->orWhere('status', '!=', 'done');
                })
                ->orderByDesc('id')
                ->limit(300)
                ->get()
                ->each(function (ProjectTask $task) use ($user): void {
                    try {
                        app(ProjectTaskReminderService::class)->sync($task, $user);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                });
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}

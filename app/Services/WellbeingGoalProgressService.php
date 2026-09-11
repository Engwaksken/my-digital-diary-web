<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailyWellbeingLog;
use App\Models\HealthCheckup;
use App\Models\User;
use App\Models\WellbeingGoal;
use Carbon\Carbon;

class WellbeingGoalProgressService
{
    public function goals(User $user): ?WellbeingGoal
    {
        return WellbeingGoal::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
    }

    public function progress(User $user, ?WellbeingGoal $goals = null): array
    {
        $goals ??= $this->goals($user);

        if (! $goals) {
            return [];
        }

        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        $today = Carbon::now($timezone)->toDateString();

        app(DailyWellbeingSyncService::class)->sync($user, $today);

        $daily = DailyWellbeingLog::query()
            ->where('user_id', $user->id)
            ->whereDate('log_date', $today)
            ->first();

        $steps = (int) ($daily?->steps ?? 0);
        $meals = (int) ($daily?->meals_logged ?? 0);
        $sleepMinutes = (int) ($daily?->sleep_minutes ?? 0);
        $exerciseMinutes = (int) ($daily?->exercise_minutes ?? 0);
        $waterMl = (int) ($daily?->water_ml ?? 0);

        $latestCheckup = HealthCheckup::query()
            ->where('user_id', $user->id)
            ->orderByDesc('checkup_date')
            ->first();

        $checkupCompleted = false;

        if ($goals->health_checkup_due_date && $goals->health_checkup_goal_set_at && $latestCheckup?->checkup_date) {
            $checkupCompleted = $latestCheckup->checkup_date->greaterThanOrEqualTo(
                $goals->health_checkup_goal_set_at->copy()->startOfDay()
            );
        }

        $checkupStatus = 'No date set';
        if ($goals->health_checkup_due_date) {
            if ($checkupCompleted) {
                $checkupStatus = 'Completed';
            } else {
                $days = Carbon::now($timezone)
                    ->startOfDay()
                    ->diffInDays($goals->health_checkup_due_date->copy()->startOfDay(), false);

                $checkupStatus = match (true) {
                    $days < 0 => abs($days) . ' day(s) overdue',
                    $days === 0 => 'Due today',
                    default => $days . ' day(s) left',
                };
            }
        }

        return [
            'steps' => $this->metric($steps, (int) $goals->steps_target, 'steps'),
            'meals' => $this->metric($meals, (int) $goals->meals_target, 'meals'),
            'sleep' => $this->metric(
                round($sleepMinutes / 60, 1),
                (float) $goals->sleep_hours_target,
                'hrs'
            ),
            'exercise' => $this->metric(
                $exerciseMinutes,
                (int) $goals->exercise_minutes_target,
                'min'
            ),
            'water' => $this->metric($waterMl, (int) $goals->water_ml_target, 'ml'),
            'health_checkup' => [
                'completed' => $checkupCompleted,
                'status' => $checkupStatus,
                'due_date' => optional($goals->health_checkup_due_date)->format('d M Y'),
                'latest_date' => optional($latestCheckup?->checkup_date)->format('d M Y'),
                'percent' => $checkupCompleted ? 100 : 0,
            ],
        ];
    }

    private function metric(int|float $current, int|float $target, string $unit): array
    {
        $target = max(0, $target);
        $percent = $target > 0
            ? min(100, round(($current / $target) * 100))
            : 0;

        return [
            'current' => $current,
            'target' => $target,
            'unit' => $unit,
            'percent' => $percent,
            'achieved' => $target > 0 && $current >= $target,
        ];
    }
}

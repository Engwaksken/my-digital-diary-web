<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailyStep;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DailyStepService
{
    public const STARTING_GOAL = 5000;
    public const GOAL_INCREMENT = 100;
    public const MAX_GOAL = 10000;

    public function forToday(User $user): DailyStep
    {
        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        $date = Carbon::now($timezone)->toDateString();

        $existing = DailyStep::query()
            ->where('user_id', $user->id)
            ->whereDate('tracking_date', $date)
            ->first();

        if ($existing) {
            return $existing;
        }

        $goal = $this->currentGoal($user);

        return DailyStep::firstOrCreate(
            [
                'user_id' => $user->id,
                'tracking_date' => $date,
            ],
            [
                'steps' => 0,
                'daily_goal' => $goal,
                'is_tracking' => false,
            ]
        );
    }

    public function currentGoal(User $user): int
    {
        if (Schema::hasColumn('users', 'current_step_goal')) {
            $saved = (int) ($user->current_step_goal ?? 0);

            if ($saved >= self::STARTING_GOAL) {
                return min(self::MAX_GOAL, $saved);
            }
        }

        /*
         * Existing accounts may already have step history from before the
         * progressive-goal feature. Preserve their most recent target.
         */
        $latestGoal = (int) (
            DailyStep::query()
                ->where('user_id', $user->id)
                ->orderByDesc('tracking_date')
                ->value('daily_goal') ?? 0
        );

        $goal = $latestGoal >= self::STARTING_GOAL
            ? min(self::MAX_GOAL, $latestGoal)
            : self::STARTING_GOAL;

        $this->saveCurrentGoal($user, $goal);

        return $goal;
    }

    public function recordSteps(User $user, int $steps): DailyStep
    {
        return DB::transaction(function () use ($user, $steps): DailyStep {
            $row = $this->forToday($user);

            // Never move the same day's count backwards.
            $row->steps = max((int) $row->steps, max(0, $steps));
            $row->last_synced_at = now();
            $row->save();

            /*
             * The user's NEXT daily target rises once today's target is met.
             * Today's row keeps its original goal, making the progress display
             * stable and ensuring the target only advances one level per day.
             */
            if ((int) $row->steps >= (int) $row->daily_goal) {
                $nextGoal = min(
                    self::MAX_GOAL,
                    max(self::STARTING_GOAL, (int) $row->daily_goal)
                        + self::GOAL_INCREMENT
                );

                $this->saveCurrentGoal($user, $nextGoal);
            }

            $fresh = $row->fresh();

            try {
                app(DailyWellbeingSyncService::class)->sync(
                    $user,
                    $fresh->tracking_date ?? now()
                );
            } catch (\Throwable $exception) {
                report($exception);
            }

            return $fresh;
        });
    }

    public function payload(User $user): array
    {
        $row = $this->forToday($user);
        $nextGoal = $this->currentGoal($user);

        return [
            'date' => optional($row->tracking_date)->toDateString(),
            'steps' => (int) $row->steps,
            'daily_goal' => (int) $row->daily_goal,
            'next_daily_goal' => $nextGoal,
            'goal_achieved' => (int) $row->steps >= (int) $row->daily_goal,
            'progress_percent' => $row->progressPercent(),
            'remaining_steps' => max(
                0,
                (int) $row->daily_goal - (int) $row->steps
            ),
            'is_tracking' => (bool) $row->is_tracking,
            'tracking_started_at' => optional($row->tracking_started_at)?->toIso8601String(),
            'tracking_stopped_at' => optional($row->tracking_stopped_at)?->toIso8601String(),
            'last_synced_at' => optional($row->last_synced_at)?->toIso8601String(),
            'goal_rules' => [
                'starting_goal' => self::STARTING_GOAL,
                'increment' => self::GOAL_INCREMENT,
                'maximum_goal' => self::MAX_GOAL,
            ],
            'tracking_window' => [
                'starts_at' => '06:00',
                'resets_at' => '00:00',
            ],
        ];
    }

    public function history(User $user, int $days = 30): array
    {
        $days = max(7, min(90, $days));

        return DailyStep::query()
            ->where('user_id', $user->id)
            ->orderByDesc('tracking_date')
            ->limit($days)
            ->get()
            ->map(function (DailyStep $row): array {
                return [
                    'date' => optional($row->tracking_date)->toDateString(),
                    'tracking_date' => optional($row->tracking_date)->toDateString(),
                    'steps' => (int) $row->steps,
                    'daily_goal' => max(
                        self::STARTING_GOAL,
                        min(self::MAX_GOAL, (int) $row->daily_goal)
                    ),
                    'progress_percent' => $row->progressPercent(),
                    'goal_achieved' => (int) $row->steps >= (int) $row->daily_goal,
                    'last_synced_at' => optional($row->last_synced_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    private function saveCurrentGoal(User $user, int $goal): void
    {
        $goal = max(
            self::STARTING_GOAL,
            min(self::MAX_GOAL, $goal)
        );

        if (! Schema::hasColumn('users', 'current_step_goal')) {
            return;
        }

        if ((int) ($user->current_step_goal ?? 0) === $goal) {
            return;
        }

        $user->forceFill([
            'current_step_goal' => $goal,
        ])->saveQuietly();

        $user->current_step_goal = $goal;
    }
}

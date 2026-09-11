<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyStep;
use App\Models\DailyWellbeingLog;
use App\Models\DietLog;
use App\Models\ExerciseLog;
use App\Models\HealthCheckup;
use App\Models\SleepLog;
use App\Models\User;
use App\Models\WellbeingGoal;
use App\Models\WellbeingNudgeLog;
use App\Notifications\SimpleDatabaseNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SendWellbeingEncouragements extends Command
{
    protected $signature = 'wellbeing:send-encouragements';

    protected $description = 'Send gentle wellbeing reminders after configured periods without progress.';

    public function handle(): int
    {
        WellbeingGoal::query()
            ->where('is_active', true)
            ->where('reminders_enabled', true)
            ->with('user')
            ->chunkById(100, function ($goals): void {
                foreach ($goals as $goal) {
                    $user = $goal->user;

                    if (! $user) {
                        continue;
                    }

                    $this->checkUser($user, $goal);
                }
            });

        return self::SUCCESS;
    }

    private function checkUser(User $user, WellbeingGoal $goal): void
    {
        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        $now = Carbon::now($timezone);

        // Avoid overnight nudges. Sleep notifications are handled only after
        // the morning has passed.
        if ($now->hour < 6 || $now->hour >= 22) {
            return;
        }

        $interval = max(6, (int) $goal->reminder_interval_hours);
        $dayStart = $now->copy()->startOfDay()->setTime(6, 0);

        if ($now->diffInHours($dayStart) < $interval) {
            return;
        }

        $date = $now->toDateString();

        $daily = DailyWellbeingLog::query()
            ->where('user_id', $user->id)
            ->whereDate('log_date', $date)
            ->first();

        // WATER
        if ((int) ($daily?->water_ml ?? 0) < (int) $goal->water_ml_target) {
            $lastWater = $daily?->last_water_logged_at?->copy()->setTimezone($timezone) ?? $dayStart;

            $this->maybeNotify(
                $user,
                'water',
                $lastWater,
                $interval,
                'Hydration check',
                'It has been about ' . $interval . ' hours since you last logged water. If you are able, have some water and record it when convenient.'
            );
        }

        // STEPS / MOVEMENT
        $todaySteps = Schema::hasTable('daily_steps')
            ? DailyStep::query()
                ->where('user_id', $user->id)
                ->whereDate('tracking_date', $date)
                ->first()
            : null;

        $steps = (int) ($todaySteps?->steps ?? $daily?->steps ?? 0);

        if ($steps < (int) $goal->steps_target) {
            $lastStepActivity = $daily?->last_step_activity_at?->copy()->setTimezone($timezone) ?? $dayStart;

            $this->maybeNotify(
                $user,
                'steps',
                $lastStepActivity,
                $interval,
                'A little movement can help',
                'You have had a long stretch without recorded steps. If it feels comfortable and safe, a short walk or gentle stretch counts toward today’s goal.'
            );
        }

        // MEALS: remind to LOG, not to force eating.
        $mealCount = DietLog::query()
            ->where('user_id', $user->id)
            ->whereDate('logged_at', $date)
            ->count();

        if ($mealCount < (int) $goal->meals_target) {
            $lastMeal = DietLog::query()
                ->where('user_id', $user->id)
                ->whereDate('logged_at', $date)
                ->latest('created_at')
                ->value('created_at');

            $lastMeal = $lastMeal
                ? Carbon::parse($lastMeal)->setTimezone($timezone)
                : $dayStart;

            $this->maybeNotify(
                $user,
                'meals',
                $lastMeal,
                $interval,
                'Meal log reminder',
                'You have not logged a meal for a while. If you have eaten, adding it will keep your Daily Wellbeing progress and eating guidance accurate.'
            );
        }

        // EXERCISE
        $exerciseMinutes = (int) ExerciseLog::query()
            ->where('user_id', $user->id)
            ->whereDate('performed_at', $date)
            ->sum('duration_minutes');

        if ($exerciseMinutes < (int) $goal->exercise_minutes_target) {
            $lastExercise = ExerciseLog::query()
                ->where('user_id', $user->id)
                ->whereDate('performed_at', $date)
                ->latest('created_at')
                ->value('created_at');

            $lastExercise = $lastExercise
                ? Carbon::parse($lastExercise)->setTimezone($timezone)
                : $dayStart;

            $this->maybeNotify(
                $user,
                'exercise',
                $lastExercise,
                $interval,
                'Exercise goal check',
                'You still have exercise minutes available in today’s goal. If it suits your day and feels comfortable, even a short session can count.'
            );
        }

        // SLEEP: only nudge after midday so we do not repeatedly remind during
        // normal waking hours.
        if ($now->hour >= 12) {
            $sleepMinutes = (int) SleepLog::query()
                ->where('user_id', $user->id)
                ->whereDate('sleep_date', $date)
                ->sum('duration_minutes');

            $sleepTargetMinutes = (int) round(((float) $goal->sleep_hours_target) * 60);

            if ($sleepMinutes < $sleepTargetMinutes) {
                $lastSleep = SleepLog::query()
                    ->where('user_id', $user->id)
                    ->whereDate('sleep_date', $date)
                    ->latest('created_at')
                    ->value('created_at');

                $lastSleep = $lastSleep
                    ? Carbon::parse($lastSleep)->setTimezone($timezone)
                    : $dayStart;

                $this->maybeNotify(
                    $user,
                    'sleep',
                    $lastSleep,
                    max(12, $interval),
                    'Sleep log check',
                    'Your sleep goal is not yet reflected in today’s records. If you have already slept, add the sleep period so your Daily Wellbeing progress stays complete.'
                );
            }
        }

        // HEALTH CHECKUP: due/overdue reminders are once per day, not every
        // six hours.
        if ($goal->health_checkup_due_date) {
            $latestCheckupDate = HealthCheckup::query()
                ->where('user_id', $user->id)
                ->orderByDesc('checkup_date')
                ->value('checkup_date');

            $completed = $latestCheckupDate && $goal->health_checkup_goal_set_at
                ? Carbon::parse($latestCheckupDate)->greaterThanOrEqualTo(
                    $goal->health_checkup_goal_set_at->copy()->startOfDay()
                )
                : false;

            if (! $completed && $goal->health_checkup_due_date->lessThanOrEqualTo($now->copy()->startOfDay())) {
                $this->maybeNotify(
                    $user,
                    'health_checkup',
                    $dayStart,
                    24,
                    'Health checkup goal',
                    'Your health checkup goal is due. When convenient, review or arrange the checkup and record it in My Digital Diary.'
                );
            }
        }
    }

    private function maybeNotify(
        User $user,
        string $metric,
        Carbon $lastActivity,
        int $intervalHours,
        string $title,
        string $body
    ): void {
        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        $now = Carbon::now($timezone);

        if ($lastActivity->diffInHours($now) < $intervalHours) {
            return;
        }

        $log = WellbeingNudgeLog::firstOrNew([
            'user_id' => $user->id,
            'metric' => $metric,
        ]);

        if ($log->last_notified_at) {
            $lastNotification = $log->last_notified_at->copy()->setTimezone($timezone);

            if ($lastNotification->diffInHours($now) < $intervalHours) {
                return;
            }
        }

        $user->notify(new SimpleDatabaseNotification(
            $title,
            $body,
            'wellbeing_' . $metric
        ));

        $log->last_notified_at = now();
        $log->save();
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailyStep;
use App\Models\DailyWellbeingLog;
use App\Models\DietLog;
use App\Models\ExerciseLog;
use App\Models\SleepLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DailyWellbeingSyncService
{
    /**
     * Keep Daily Wellbeing as the user's one daily health summary.
     *
     * Authoritative source modules:
     * - Steps: daily_steps
     * - Diet: diet_logs
     * - Sleep: sleep_logs
     * - Exercise: exercise_logs
     *
     * DailyWellbeingLog stores a daily snapshot while the source records remain
     * the source of truth. This means the wellbeing screen is fast and all four
     * modules remain connected to the same user/day.
     */
    public function sync(User $user, Carbon|string|null $date = null): DailyWellbeingLog
    {
        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');

        $day = $date instanceof Carbon
            ? $date->copy()->setTimezone($timezone)
            : Carbon::parse($date ?: 'now', $timezone);

        $dateString = $day->toDateString();
        $userId = (int) $user->id;

        $existing = DailyWellbeingLog::query()
            ->where('user_id', $userId)
            ->whereDate('log_date', $dateString)
            ->first();

        $steps = 0;
        if (Schema::hasTable('daily_steps')) {
            $steps = (int) (DailyStep::query()
                ->where('user_id', $userId)
                ->whereDate('tracking_date', $dateString)
                ->value('steps') ?? 0);
        }

        $dietQuery = DietLog::query()
            ->where('user_id', $userId)
            ->whereDate('logged_at', $dateString);

        if (Schema::hasColumn('diet_logs', 'is_archived')) {
            $dietQuery->where('is_archived', false);
        }

        $mealsLogged = (int) (clone $dietQuery)->count();
        $caloriesLogged = (int) (clone $dietQuery)->sum('calories');

        $dailyFoodEntries = 0;
        if (Schema::hasTable('daily_food_journals')) {
            $dailyFoodEntries = (int) \DB::table('daily_food_journals')
                ->where('user_id', $userId)
                ->whereDate('journal_date', $dateString)
                ->count();
        }

        $sleepQuery = SleepLog::query()
            ->where('user_id', $userId)
            ->whereDate('sleep_date', $dateString);

        if (Schema::hasColumn('sleep_logs', 'is_archived')) {
            $sleepQuery->where('is_archived', false);
        }

        $sleepMinutes = (int) (clone $sleepQuery)->sum('duration_minutes');
        $sleepQuality = (string) ((clone $sleepQuery)
            ->whereNotNull('quality')
            ->latest('id')
            ->value('quality') ?? '');

        $exerciseQuery = ExerciseLog::query()
            ->where('user_id', $userId)
            ->whereDate('performed_at', $dateString);

        if (Schema::hasColumn('exercise_logs', 'is_archived')) {
            $exerciseQuery->where('is_archived', false);
        }

        $exerciseMinutes = (int) (clone $exerciseQuery)->sum('duration_minutes');
        $exerciseSessions = (int) (clone $exerciseQuery)->count();

        $values = [
            'steps' => $steps,
            'meals_logged' => $mealsLogged,
            'calories_logged' => $caloriesLogged,
            'daily_food_entries' => $dailyFoodEntries,
            'sleep_minutes' => $sleepMinutes,
            'sleep_quality' => $sleepQuality !== '' ? $sleepQuality : null,
            'exercise_minutes' => $exerciseMinutes,
            'exercise_sessions' => $exerciseSessions,
            'source_synced_at' => now(),
        ];

        if ($existing) {
            $existing->forceFill($values)->save();
            $result = $existing->fresh();
        } else {
            $createValues = [
                'user_id' => $userId,
                'log_date' => $dateString,
                'water_ml' => 0,
                'water_target_ml' => 2000,
                ...$values,
            ];

            if (Schema::hasColumn('daily_wellbeing_logs', 'manual_entry')) {
                $createValues['manual_entry'] = false;
            }

            if (Schema::hasColumn('daily_wellbeing_logs', 'entry_frequency')) {
                $createValues['entry_frequency'] = 'once';
            }

            $result = DailyWellbeingLog::create($createValues);
        }

        try {
            app(PersonalHealthGoalProgressService::class)->sync($user);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $result;
    }

    public function syncToday(User $user): DailyWellbeingLog
    {
        return $this->sync($user);
    }

    public function syncRecent(User $user, int $days = 7): void
    {
        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        $today = Carbon::now($timezone)->startOfDay();

        foreach (range(max(0, $days - 1), 0) as $offset) {
            $this->sync($user, $today->copy()->subDays($offset));
        }
    }
}

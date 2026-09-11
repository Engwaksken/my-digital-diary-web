<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailyPlanItem;
use App\Models\DietLog;
use App\Models\ExerciseLog;
use App\Models\SleepLog;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DailyPlannerWellbeingSyncService
{
    public function syncCompletion(
        User $user,
        DailyPlanItem $item,
        CarbonInterface $date,
        bool $completed
    ): ?string {
        $item->loadMissing('personalGoal', 'plan');

        $goal = $item->personalGoal;
        if (! $goal) {
            return null;
        }

        $module = strtolower(trim((string) $goal->module));
        if (! in_array($module, ['exercise', 'diet', 'sleep'], true)) {
            return null;
        }

        $date = Carbon::parse($date)->startOfDay();
        $sourceKey = "daily-plan:{$item->id}:{$date->toDateString()}:{$module}";

        try {
            if (! $completed) {
                $this->removeSyncedLog($module, (int) $user->id, $sourceKey);
                app(DailyWellbeingSyncService::class)->sync($user, $date->toDateString());

                return 'Task reopened. Its linked wellbeing entry was removed.';
            }

            $message = match ($module) {
                'exercise' => $this->syncExercise($user, $item, $date, $sourceKey),
                'diet' => $this->syncDiet($user, $item, $date, $sourceKey),
                'sleep' => $this->syncSleep($user, $item, $date, $sourceKey),
            };

            app(DailyWellbeingSyncService::class)->sync($user, $date->toDateString());

            return $message;
        } catch (Throwable $e) {
            Log::warning('Daily Planner wellbeing auto-sync failed', [
                'user_id' => $user->id,
                'daily_plan_item_id' => $item->id,
                'goal_module' => $module,
                'message' => $e->getMessage(),
            ]);

            return 'Task completed. The linked wellbeing entry could not be added automatically.';
        }
    }

    private function syncExercise(User $user, DailyPlanItem $item, Carbon $date, string $sourceKey): string
    {
        if (! Schema::hasTable('exercise_logs')
            || ! Schema::hasColumn('exercise_logs', 'daily_plan_source_key')) {
            return 'Task completed. Run the latest migration to enable automatic exercise logging.';
        }

        $duration = $this->durationMinutes($item);
        if ($duration < 1) {
            return 'Task completed. Add a start and end time, or write the duration such as “30 minutes”, so Exercise Logs can be updated automatically.';
        }

        $performedAt = $date->copy();
        if ($item->start_time) {
            [$hour, $minute] = array_map('intval', explode(':', substr((string) $item->start_time, 0, 5)));
            $performedAt->setTime($hour, $minute);
        }

        $log = ExerciseLog::query()
            ->where('user_id', $user->id)
            ->where('daily_plan_source_key', $sourceKey)
            ->first() ?? new ExerciseLog();

        $log->forceFill([
            'user_id' => $user->id,
            'daily_plan_source_key' => $sourceKey,
            'activity' => $item->title,
            'duration_minutes' => $duration,
            'intensity' => $this->exerciseIntensity($item),
            'calories_burned' => null,
            'performed_at' => $performedAt,
            'notes' => $this->autoNotes($item, 'Automatically added from a completed Daily Planner task.'),
            'is_archived' => false,
        ])->save();

        return 'Task completed and added to Exercise Logs and Daily Wellbeing.';
    }

    private function syncDiet(User $user, DailyPlanItem $item, Carbon $date, string $sourceKey): string
    {
        if (! Schema::hasTable('diet_logs')
            || ! Schema::hasColumn('diet_logs', 'daily_plan_source_key')) {
            return 'Task completed. Run the latest migration to enable automatic meal logging.';
        }

        $food = trim((string) $item->description);
        if ($food === '') {
            $food = $item->title;
        }

        $log = DietLog::query()
            ->where('user_id', $user->id)
            ->where('daily_plan_source_key', $sourceKey)
            ->first() ?? new DietLog();

        $log->forceFill([
            'user_id' => $user->id,
            'daily_plan_source_key' => $sourceKey,
            'meal_type' => $this->mealType($item),
            'food_items' => $food,
            'calories' => null,
            'logged_at' => $date->toDateString(),
            'notes' => 'Automatically added from a completed Daily Planner task.',
            'is_archived' => false,
        ])->save();

        return 'Task completed and added to Meal Logs and Daily Wellbeing.';
    }

    private function syncSleep(User $user, DailyPlanItem $item, Carbon $date, string $sourceKey): string
    {
        if (! Schema::hasTable('sleep_logs')
            || ! Schema::hasColumn('sleep_logs', 'daily_plan_source_key')) {
            return 'Task completed. Run the latest migration to enable automatic sleep logging.';
        }

        $duration = $this->durationMinutes($item);
        if ($duration < 1) {
            return 'Task completed. Add bedtime and wake time so Sleep Logs and Daily Wellbeing can be updated automatically.';
        }

        $log = SleepLog::query()
            ->where('user_id', $user->id)
            ->where('daily_plan_source_key', $sourceKey)
            ->first() ?? new SleepLog();

        $log->forceFill([
            'user_id' => $user->id,
            'daily_plan_source_key' => $sourceKey,
            'sleep_date' => $date->toDateString(),
            'bed_time' => $item->start_time ? substr((string) $item->start_time, 0, 5) : null,
            'wake_time' => $item->end_time ? substr((string) $item->end_time, 0, 5) : null,
            'duration_minutes' => $duration,
            'quality' => 'fair',
            'notes' => $this->autoNotes(
                $item,
                'Automatically added from Daily Planner. Sleep quality was not separately rated in the planner.'
            ),
            'is_archived' => false,
        ])->save();

        return 'Task completed and added to Sleep Logs and Daily Wellbeing.';
    }

    private function removeSyncedLog(string $module, int $userId, string $sourceKey): void
    {
        $model = match ($module) {
            'exercise' => ExerciseLog::class,
            'diet' => DietLog::class,
            'sleep' => SleepLog::class,
        };

        $table = (new $model)->getTable();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'daily_plan_source_key')) {
            return;
        }

        $model::query()
            ->where('user_id', $userId)
            ->where('daily_plan_source_key', $sourceKey)
            ->delete();
    }

    private function durationMinutes(DailyPlanItem $item): int
    {
        $start = trim(substr((string) ($item->start_time ?? ''), 0, 5));
        $end = trim(substr((string) ($item->end_time ?? ''), 0, 5));

        if ($start !== '' && $end !== '') {
            $from = Carbon::createFromFormat('H:i', $start);
            $to = Carbon::createFromFormat('H:i', $end);

            if ($to->lessThanOrEqualTo($from)) {
                $to->addDay();
            }

            return (int) $from->diffInMinutes($to);
        }

        $text = trim($item->title.' '.($item->description ?? ''));

        if (preg_match('/\b(\d{1,4})\s*(?:min|mins|minute|minutes)\b/i', $text, $match)) {
            return min(1440, max(1, (int) $match[1]));
        }

        return 0;
    }

    private function mealType(DailyPlanItem $item): string
    {
        $text = strtolower($item->title.' '.($item->description ?? ''));

        foreach (['breakfast', 'lunch', 'snack', 'dinner', 'supper'] as $meal) {
            if (str_contains($text, $meal)) {
                return $meal;
            }
        }

        $hour = $item->start_time
            ? (int) substr((string) $item->start_time, 0, 2)
            : 12;

        return match (true) {
            $hour < 11 => 'breakfast',
            $hour < 15 => 'lunch',
            $hour < 18 => 'snack',
            $hour < 21 => 'dinner',
            default => 'supper',
        };
    }

    private function exerciseIntensity(DailyPlanItem $item): string
    {
        $text = strtolower($item->title.' '.($item->description ?? ''));

        if (preg_match('/\b(intense|high intensity|hiit|hard)\b/', $text)) {
            return 'intense';
        }

        if (preg_match('/\b(light|gentle|easy|stretch|walk)\b/', $text)) {
            return 'light';
        }

        return 'moderate';
    }

    private function autoNotes(DailyPlanItem $item, string $prefix): string
    {
        $description = trim((string) $item->description);

        return $description !== ''
            ? $prefix.' '.$description
            : $prefix;
    }
}

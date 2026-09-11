<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailyWellbeingLog;
use App\Models\DailyStep;
use App\Models\DietLog;
use App\Models\ExerciseLog;
use App\Models\HealthCheckup;
use App\Models\SleepLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class HealthWellbeingSummaryService
{
    public function summary(User $user, ?Carbon $date = null, int $days = 7): array
    {
        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        $day = ($date ?: Carbon::now($timezone))->copy()->setTimezone($timezone)->startOfDay();
        $days = max(7, min(30, $days));

        $daily = $this->daily($user, $day);
        $trend = $this->trend($user, $day, $days);

        return [
            'date' => $day->toDateString(),
            'timezone' => $timezone,
            'daily' => $daily,
            'trend' => $trend,
            'observations' => $this->observations($daily, $trend),
            'notice' => 'This wellbeing summary is based on your own records and is not a medical diagnosis.',
        ];
    }

    public function daily(User $user, Carbon $day): array
    {
        $userId = (int) $user->id;
        $date = $day->toDateString();

        $diet = DietLog::where('user_id', $userId)
            ->where('is_archived', false)
            ->whereDate('logged_at', $date);
        $exercise = ExerciseLog::where('user_id', $userId)
            ->where('is_archived', false)
            ->whereDate('performed_at', $date);
        $sleep = SleepLog::where('user_id', $userId)
            ->where('is_archived', false)
            ->whereDate('sleep_date', $date);

        $wellbeing = DailyWellbeingLog::where('user_id', $userId)
            ->where('is_archived', false)
            ->whereDate('log_date', $date)
            ->first();

        $latestHealth = HealthCheckup::where('user_id', $userId)
            ->where('is_archived', false)
            ->whereDate('checkup_date', '<=', $date)
            ->latest('checkup_date')
            ->first();

        $exerciseMinutes = (int) (clone $exercise)->sum('duration_minutes');
        if ($exerciseMinutes <= 0 && $wellbeing?->exercise_minutes) {
            $exerciseMinutes = (int) $wellbeing->exercise_minutes;
        }

        $sleepMinutes = (int) (clone $sleep)->sum('duration_minutes');
        $sleepHours = $sleepMinutes > 0 ? round($sleepMinutes / 60, 1) : null;
        $sleepQuality = (clone $sleep)->whereNotNull('quality')->latest('id')->value('quality');

        return [
            'meals_logged' => (int) (clone $diet)->count(),
            'calories_logged' => (int) (clone $diet)->sum('calories'),
            'sleep_minutes' => $sleepMinutes,
            'sleep_hours' => $sleepHours,
            'sleep_quality' => $sleepQuality,
            'exercise_minutes' => $exerciseMinutes,
            'exercise_sessions' => (int) (clone $exercise)->count(),
            'steps' => Schema::hasTable('daily_steps')
                ? (int) (DailyStep::where('user_id', $userId)
                    ->whereDate('tracking_date', $date)
                    ->value('steps') ?? 0)
                : (int) ($wellbeing?->steps ?? 0),
            'water_ml' => (int) ($wellbeing?->water_ml ?? 0),
            'water_target_ml' => (int) ($wellbeing?->water_target_ml ?? 0),
            'water_percent' => $wellbeing?->water_target_ml
                ? min(100, (int) round(((int) $wellbeing->water_ml / (int) $wellbeing->water_target_ml) * 100))
                : 0,
            'mood' => $wellbeing?->mood,
            'energy_level' => $wellbeing?->energy_level,
            'stress_level' => $wellbeing?->stress_level,
            'pain_level' => $wellbeing?->pain_level,
            'wellbeing_score' => $wellbeing?->wellbeing_score,
            'symptoms' => $wellbeing?->symptoms,
            'self_care_done' => (bool) ($wellbeing?->self_care_done ?? false),
            'screen_break_done' => (bool) ($wellbeing?->screen_break_done ?? false),
            'reflection_done' => (bool) ($wellbeing?->reflection_done ?? false),
            'latest_health' => $latestHealth ? [
                'date' => optional($latestHealth->checkup_date)->toDateString(),
                'type' => $latestHealth->checkup_type,
                'weight_kg' => $latestHealth->weight_kg,
                'blood_pressure_systolic' => $latestHealth->blood_pressure_systolic,
                'blood_pressure_diastolic' => $latestHealth->blood_pressure_diastolic,
                'heart_rate_bpm' => $latestHealth->heart_rate_bpm,
                'next_due_date' => optional($latestHealth->next_due_date)->toDateString(),
            ] : null,
        ];
    }

    private function trend(User $user, Carbon $end, int $days): array
    {
        $rows = collect(range($days - 1, 0))->map(function (int $offset) use ($user, $end): array {
            $day = $end->copy()->subDays($offset);
            return ['date' => $day->toDateString()] + $this->daily($user, $day);
        });

        return [
            'days' => $days,
            'from' => $end->copy()->subDays($days - 1)->toDateString(),
            'to' => $end->toDateString(),
            'avg_sleep_hours' => $this->average($rows->pluck('sleep_hours')),
            'avg_exercise_minutes' => $this->average($rows->pluck('exercise_minutes')),
            'exercise_days' => $rows->where('exercise_minutes', '>', 0)->count(),
            'avg_water_percent' => $this->average($rows->pluck('water_percent')),
            'avg_wellbeing_score' => $this->average($rows->pluck('wellbeing_score')),
            'avg_energy_level' => $this->average($rows->pluck('energy_level')),
            'avg_stress_level' => $this->average($rows->pluck('stress_level')),
            'meals_logged' => (int) $rows->sum('meals_logged'),
            'days_with_sleep' => $rows->where('sleep_minutes', '>', 0)->count(),
            'days_with_wellbeing' => $rows->filter(fn (array $row) => $row['wellbeing_score'] !== null || $row['mood'] !== null)->count(),
            'series' => $rows->values()->all(),
        ];
    }

    private function average(Collection $values): ?float
    {
        $clean = $values->filter(fn ($value) => $value !== null && is_numeric($value));
        return $clean->isEmpty() ? null : round((float) $clean->avg(), 1);
    }

    private function observations(array $daily, array $trend): array
    {
        $items = [];

        if (($daily['sleep_hours'] ?? null) !== null && ($trend['avg_sleep_hours'] ?? null) !== null) {
            $difference = round((float) $daily['sleep_hours'] - (float) $trend['avg_sleep_hours'], 1);
            if (abs($difference) >= 0.5) {
                $items[] = $difference < 0
                    ? 'Last night’s recorded sleep was shorter than your recent average.'
                    : 'Last night’s recorded sleep was longer than your recent average.';
            }
        }

        if (($daily['exercise_minutes'] ?? 0) > 0) {
            $items[] = 'You recorded exercise today, and it is included in your daily wellbeing summary.';
        }

        if (($daily['water_target_ml'] ?? 0) > 0) {
            $items[] = 'Your recorded water intake is ' . ($daily['water_percent'] ?? 0) . '% of your daily target.';
        }

        if (($daily['meals_logged'] ?? 0) > 0) {
            $items[] = 'Your meal records are linked into today’s wellbeing view.';
        }

        return array_slice($items, 0, 4);
    }
}

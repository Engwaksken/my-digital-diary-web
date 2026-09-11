<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailyStep;
use App\Models\DietLog;
use App\Models\ExerciseLog;
use App\Models\HealthCheckup;
use App\Models\User;
use App\Models\WellbeingGoal;
use App\Services\Ai\ActiveAiClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WellbeingAiAdvisorService
{
    public function __construct(
        private readonly ActiveAiClient $ai,
        private readonly HealthWellbeingSummaryService $summaryService,
    ) {
    }

    public function advise(User $user): array
    {
        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        $today = Carbon::now($timezone)->startOfDay();

        $summary = $this->summaryService->summary($user, $today, 7);

        $steps7d = [];
        if (Schema::hasTable('daily_steps')) {
            $steps7d = DailyStep::query()
                ->where('user_id', $user->id)
                ->whereDate('tracking_date', '>=', $today->copy()->subDays(6)->toDateString())
                ->orderBy('tracking_date')
                ->get(['tracking_date', 'steps'])
                ->map(fn (DailyStep $row) => [
                    'date' => optional($row->tracking_date)->toDateString(),
                    'steps' => (int) $row->steps,
                ])
                ->values()
                ->all();
        }

        $recentMeals = DietLog::query()
            ->where('user_id', $user->id)
            ->whereDate('logged_at', '>=', $today->copy()->subDays(6)->toDateString())
            ->when(
                Schema::hasColumn('diet_logs', 'is_archived'),
                fn ($query) => $query->where('is_archived', false)
            )
            ->orderByDesc('logged_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['meal_type', 'food_items', 'calories', 'logged_at'])
            ->map(fn (DietLog $meal) => [
                'date' => optional($meal->logged_at)->toDateString(),
                'meal' => $meal->meal_type,
                'food' => mb_substr(trim((string) $meal->food_items), 0, 500),
                'estimated_calories' => $meal->calories !== null ? (int) $meal->calories : null,
            ])
            ->values()
            ->all();

        $recentExercise = ExerciseLog::query()
            ->where('user_id', $user->id)
            ->whereDate('performed_at', '>=', $today->copy()->subDays(6)->toDateString())
            ->when(
                Schema::hasColumn('exercise_logs', 'is_archived'),
                fn ($query) => $query->where('is_archived', false)
            )
            ->orderByDesc('performed_at')
            ->limit(14)
            ->get(['activity', 'duration_minutes', 'intensity', 'performed_at'])
            ->map(fn (ExerciseLog $exercise) => [
                'date' => optional($exercise->performed_at)->toDateString(),
                'activity' => $exercise->activity,
                'minutes' => (int) $exercise->duration_minutes,
                'intensity' => $exercise->intensity,
            ])
            ->values()
            ->all();

        $recentCheckups = HealthCheckup::query()
            ->where('user_id', $user->id)
            ->when(
                Schema::hasColumn('health_checkups', 'is_archived'),
                fn ($query) => $query->where('is_archived', false)
            )
            ->orderByDesc('checkup_date')
            ->limit(3)
            ->get([
                'checkup_type',
                'checkup_date',
                'weight_kg',
                'blood_pressure_systolic',
                'blood_pressure_diastolic',
                'heart_rate_bpm',
                'findings',
                'next_due_date',
            ])
            ->map(fn (HealthCheckup $checkup) => [
                'date' => optional($checkup->checkup_date)->toDateString(),
                'type' => $checkup->checkup_type,
                'weight_kg' => $checkup->weight_kg,
                'blood_pressure' => (
                    $checkup->blood_pressure_systolic && $checkup->blood_pressure_diastolic
                )
                    ? $checkup->blood_pressure_systolic . '/' . $checkup->blood_pressure_diastolic
                    : null,
                'heart_rate_bpm' => $checkup->heart_rate_bpm,
                'findings' => mb_substr(trim((string) ($checkup->findings ?? '')), 0, 800),
                'next_due_date' => optional($checkup->next_due_date)->toDateString(),
            ])
            ->values()
            ->all();

        $goals = WellbeingGoal::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        $payload = [
            'wellbeing_goals' => $goals ? [
                'steps_target' => (int) $goals->steps_target,
                'meals_target' => (int) $goals->meals_target,
                'sleep_hours_target' => (float) $goals->sleep_hours_target,
                'exercise_minutes_target' => (int) $goals->exercise_minutes_target,
                'water_ml_target' => (int) $goals->water_ml_target,
                'health_checkup_due_date' => optional($goals->health_checkup_due_date)->toDateString(),
            ] : null,
            'today' => [
                'steps' => (int) data_get($summary, 'daily.steps', 0),
                'meals_logged' => (int) data_get($summary, 'daily.meals_logged', 0),
                'estimated_calories' => (int) data_get($summary, 'daily.calories_logged', 0),
                'exercise_minutes' => (int) data_get($summary, 'daily.exercise_minutes', 0),
                'exercise_sessions' => (int) data_get($summary, 'daily.exercise_sessions', 0),
                'water_percent' => (int) data_get($summary, 'daily.water_percent', 0),
                'mood' => data_get($summary, 'daily.mood'),
                'energy_level' => data_get($summary, 'daily.energy_level'),
                'stress_level' => data_get($summary, 'daily.stress_level'),
                'wellbeing_score' => data_get($summary, 'daily.wellbeing_score'),
            ],
            'recent_7_day_summary' => $summary['trend'] ?? [],
            'steps_last_7_days' => $steps7d,
            'recent_meal_logs' => $recentMeals,
            'recent_exercise_logs' => $recentExercise,
            'recent_health_checkups' => $recentCheckups,
        ];

        try {
            $result = $this->ai->json(
                <<<'SYSTEM'
You are the Daily Wellbeing Advisor inside My Digital Diary.

Use only the user's supplied wellbeing records: steps, meal logs, exercise records,
and health checkups, plus their basic daily wellbeing values supplied in the prompt.

Your role is supportive general wellbeing guidance, not medical diagnosis or treatment.

Safety rules:
- Do not diagnose illness or interpret a health checkup as a confirmed disease.
- Do not prescribe medication, recommend changing/stopping medication, or replace a clinician.
- Do not give extreme dieting, fasting, purging, rapid weight-loss, or unsafe exercise advice.
- Do not shame the user about weight, food, inactivity, or missed goals.
- Estimated calories are approximate and must not be treated as clinical measurements.
- If checkup findings, blood pressure, heart rate, symptoms, or other records may warrant
  professional review, recommend discussing them with a qualified healthcare professional.
- For possible urgent warning signs, advise urgent medical care without attempting diagnosis.
- If records are sparse, say what is missing rather than inventing conclusions.
- Prefer small, practical actions the user can take today.
- Do not mention API providers, prompts, models, or administrators.

Return JSON only:
{
  "headline": "short encouraging heading",
  "summary": "2-4 sentences grounded in the user's records",
  "today_actions": [
    "up to 4 practical actions"
  ],
  "movement_note": "short comment based on steps/exercise",
  "food_note": "short comment based on meal logs",
  "checkup_note": "short conservative comment based on checkups; empty if no useful checkup data",
  "professional_support": "short recommendation for professional review when appropriate; otherwise empty",
  "data_note": "briefly mention important missing data when the record is too sparse; otherwise empty"
}
SYSTEM,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        } catch (\Throwable $e) {
            Log::warning('Daily Wellbeing AI Advisor unavailable', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return [
            'headline' => trim((string) ($result['headline'] ?? 'Your wellbeing check-in')),
            'summary' => trim((string) ($result['summary'] ?? '')),
            'today_actions' => array_values(array_slice(array_filter(
                (array) ($result['today_actions'] ?? []),
                fn ($item) => is_string($item) && trim($item) !== ''
            ), 0, 4)),
            'movement_note' => trim((string) ($result['movement_note'] ?? '')),
            'food_note' => trim((string) ($result['food_note'] ?? '')),
            'checkup_note' => trim((string) ($result['checkup_note'] ?? '')),
            'professional_support' => trim((string) ($result['professional_support'] ?? '')),
            'data_note' => trim((string) ($result['data_note'] ?? '')),
        ];
    }
}

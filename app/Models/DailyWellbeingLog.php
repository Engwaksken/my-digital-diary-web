<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyWellbeingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'log_date', 'entry_frequency', 'manual_entry', 'water_ml', 'water_target_ml',
        'exercise_minutes', 'steps', 'meals_logged', 'calories_logged', 'daily_food_entries',
        'sleep_minutes', 'sleep_quality', 'exercise_sessions', 'source_synced_at',
        'mood', 'energy_level', 'stress_level',
        'pain_level', 'wellbeing_score', 'symptoms', 'self_care_done',
        'screen_break_done', 'reflection_done', 'self_care_activity', 'notes',
        'is_archived',
    ];

    protected $casts = [
        'log_date' => 'date',
        'manual_entry' => 'boolean',
        'water_ml' => 'integer',
        'water_target_ml' => 'integer',
        'exercise_minutes' => 'integer',
        'steps' => 'integer',
        'meals_logged' => 'integer',
        'calories_logged' => 'integer',
        'daily_food_entries' => 'integer',
        'sleep_minutes' => 'integer',
        'exercise_sessions' => 'integer',
        'source_synced_at' => 'datetime',
        'energy_level' => 'integer',
        'stress_level' => 'integer',
        'pain_level' => 'integer',
        'wellbeing_score' => 'integer',
        'self_care_done' => 'boolean',
        'screen_break_done' => 'boolean',
        'reflection_done' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

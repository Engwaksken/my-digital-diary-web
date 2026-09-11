<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WellbeingGoal extends Model
{
    protected $fillable = [
        'user_id',
        'steps_target',
        'meals_target',
        'sleep_hours_target',
        'exercise_minutes_target',
        'water_ml_target',
        'health_checkup_due_date',
        'health_checkup_goal_set_at',
        'reminders_enabled',
        'reminder_interval_hours',
        'is_active',
    ];

    protected $casts = [
        'steps_target' => 'integer',
        'meals_target' => 'integer',
        'sleep_hours_target' => 'decimal:1',
        'exercise_minutes_target' => 'integer',
        'water_ml_target' => 'integer',
        'health_checkup_due_date' => 'date',
        'health_checkup_goal_set_at' => 'datetime',
        'reminders_enabled' => 'boolean',
        'reminder_interval_hours' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

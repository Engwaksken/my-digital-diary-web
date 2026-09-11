<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HealthProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'weight_kg',
        'height_cm',
        'age_range',
        'activity_level',
        'health_goal',
        'food_allergies',
        'dietary_preferences',
        'health_conditions',
        'sleep_challenges',
        'usual_wake_time',
        'usual_bed_time',
        'sleep_advice',
        'sleep_advice_generated_at',
        'diet_advice',
        'diet_advice_generated_at',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:1',
        'height_cm' => 'decimal:1',
        'sleep_advice' => 'array',
        'diet_advice' => 'array',
        'sleep_advice_generated_at' => 'datetime',
        'diet_advice_generated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

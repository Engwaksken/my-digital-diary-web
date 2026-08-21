<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpiritualPractice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'practice_type',
        'title',
        'preacher',
        'theme_topic',
        'scriptures',
        'lessons_learnt',
        'practiced_at',
        'practice_time',
        'duration_minutes',
        'reflection',
        'next_planned_date',
        'recurrence_frequency',
        'recurrence_days_of_week',
        'recurrence_ends_at',
        'recurrence_parent_id',
        'is_archived',
    ];

    protected $casts = [
        'practiced_at' => 'date',
        'next_planned_date' => 'date',
        'recurrence_days_of_week' => 'array',
        'recurrence_ends_at' => 'date',
    ];

    public function isRecurring(): bool
    {
        return ! empty($this->recurrence_frequency);
    }

    public function recurrenceParent()
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    public function recurrenceInstances()
    {
        return $this->hasMany(self::class, 'recurrence_parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

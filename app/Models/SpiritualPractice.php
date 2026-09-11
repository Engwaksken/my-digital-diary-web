<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpiritualPractice extends Model
{
    use HasFactory;
    use SoftDeletes;

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
        'next_planned_date',
        'reflection',
        'recurrence_frequency',
        'recurrence_days_of_week',
        'recurrence_ends_at',
        'recurrence_parent_id',
        'faith_path',
        'custom_faith_path',
        'practice_title',
        'inspirational_text',
        'source_tradition',
        'gratitude',
        'intention',
        'community_place',
        'mood_before',
        'mood_after',
        'notes',
        'is_archived',
        'archived_at',
    ];

    protected $casts = [
        'practiced_at' => 'datetime',
        'next_planned_date' => 'date',
        'recurrence_ends_at' => 'date',
        'recurrence_days_of_week' => 'array',
        'duration_minutes' => 'integer',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    public function recurrenceInstances(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_parent_id');
    }

    public function isRecurring(): bool
    {
        return filled($this->recurrence_frequency);
    }
}

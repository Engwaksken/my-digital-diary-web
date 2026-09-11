<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'start_at', 'end_at', 'location', 'attendees', 'status', 'notes',
        'external_platform', 'external_id', 'meeting_status', 'is_archived',
        'recurrence_frequency', 'recurrence_days_of_week', 'recurrence_ends_at', 'recurrence_parent_id',
        'calendar_provider', 'external_calendar_id', 'external_event_id', 'external_series_id',
        'calendar_synced_at', 'calendar_sync_from_date', 'calendar_sync_to_date',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'recurrence_days_of_week' => 'array',
        'recurrence_ends_at' => 'date',
        'calendar_synced_at' => 'datetime',
        'calendar_sync_from_date' => 'date',
        'calendar_sync_to_date' => 'date',
    ];

    public function isRecurring(): bool
    {
        return ! empty($this->recurrence_frequency);
    }

    public function recurrenceParent()
    {
        return $this->belongsTo(Meeting::class, 'recurrence_parent_id');
    }

    public function recurrenceInstances()
    {
        return $this->hasMany(Meeting::class, 'recurrence_parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recordings()
    {
        return $this->hasMany(MeetingRecording::class);
    }

    /**
     * "Missed" isn't a status anyone sets manually — it's derived: a
     * meeting that was scheduled, never got marked completed or
     * cancelled, and whose start time has now passed. Computed on read
     * rather than needing a scheduled job to flip a stored value, since
     * "has start_at passed" doesn't need to be pre-computed to be cheap.
     */
    public function displayStatus(): string
    {
        if (in_array($this->meeting_status, ['completed', 'cancelled'], true)) {
            return $this->meeting_status;
        }

        if ($this->start_at && $this->start_at->isPast()) {
            return 'missed';
        }

        return 'scheduled';
    }

    public function getStartDateAttribute(): ?string
    {
        return $this->start_at?->format('Y-m-d');
    }

    public function getStartTimeAttribute(): ?string
    {
        return $this->start_at?->format('H:i');
    }

    public function getEndDateAttribute(): ?string
    {
        return $this->end_at?->format('Y-m-d');
    }

    public function getEndTimeAttribute(): ?string
    {
        return $this->end_at?->format('H:i');
    }

    public function getStartHourAttribute(): ?string
    {
        return $this->start_at?->format('g');
    }

    public function getStartMinuteAttribute(): ?string
    {
        return $this->start_at?->format('i');
    }

    public function getStartMeridiemAttribute(): ?string
    {
        return $this->start_at?->format('A');
    }

    public function getEndHourAttribute(): ?string
    {
        return $this->end_at?->format('g');
    }

    public function getEndMinuteAttribute(): ?string
    {
        return $this->end_at?->format('i');
    }

    public function getEndMeridiemAttribute(): ?string
    {
        return $this->end_at?->format('A');
    }
}

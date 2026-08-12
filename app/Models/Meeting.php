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
        'recurrence_frequency', 'recurrence_days_of_week', 'recurrence_ends_at', 'recurrence_parent_id',];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'recurrence_days_of_week' => 'array',
        'recurrence_ends_at' => 'date',
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
}

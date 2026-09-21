<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MeetingRecording extends Model
{
    protected $fillable = [
        'meeting_id', 'recorded_by_user_id', 'status', 'consent_given_at',
        'audio_path', 'duration_seconds',
        'transcript', 'transcript_segments', 'transcription_status', 'transcription_error',
        'summary', 'summary_status', 'summary_error',
    ];

    protected $casts = [
        'consent_given_at' => 'datetime',
        'transcript_segments' => 'array',
        'summary' => 'array',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function audioUrl(): ?string
    {
        return $this->audio_path ? Storage::disk('public')->url($this->audio_path) : null;
    }

    public function formattedDuration(): string
    {
        $minutes = intdiv($this->duration_seconds, 60);
        $seconds = $this->duration_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function segments()
    {
        return $this->hasMany(MeetingRecordingSegment::class, 'meeting_recording_id');
    }
}

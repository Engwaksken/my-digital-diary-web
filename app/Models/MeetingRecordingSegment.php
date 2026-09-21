<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MeetingRecordingSegment extends Model
{
    protected $fillable = [
        'meeting_recording_id',
        'created_by_user_id',
        'audio_path',
        'start_seconds',
        'end_seconds',
        'duration_seconds',
        'title',
        'notes',
        'transcript',
        'transcript_segments',
        'transcription_status',
        'transcription_error',
        'summary',
        'summary_status',
        'summary_error',
    ];

    protected $casts = [
        'transcript_segments' => 'array',
        'summary' => 'array',
    ];

    public function recording()
    {
        return $this->belongsTo(MeetingRecording::class, 'meeting_recording_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
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

    public function formattedStartTime(): string
    {
        $minutes = intdiv($this->start_seconds, 60);
        $seconds = $this->start_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function formattedEndTime(): string
    {
        $minutes = intdiv($this->end_seconds, 60);
        $seconds = $this->end_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['meeting_id', 'user_id', 'action', 'details'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(int $meetingId, int $userId, string $action, ?string $details = null): void
    {
        static::create([
            'meeting_id' => $meetingId,
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}

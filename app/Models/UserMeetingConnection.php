<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMeetingConnection extends Model
{
    protected $fillable = [
        'user_id', 'platform', 'access_token', 'refresh_token',
        'token_expires_at', 'connected_email', 'last_synced_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }
}

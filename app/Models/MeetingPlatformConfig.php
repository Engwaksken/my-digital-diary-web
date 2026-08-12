<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingPlatformConfig extends Model
{
    protected $fillable = ['platform', 'name', 'client_id', 'client_secret', 'is_enabled'];

    protected $casts = [
        'client_id' => 'encrypted',
        'client_secret' => 'encrypted',
        'is_enabled' => 'boolean',
    ];

    public function isConfigured(): bool
    {
        return ! empty($this->client_id) && ! empty($this->client_secret);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginActivity extends Model
{
    protected $fillable = [
        'user_id',
        'guard',
        'ip_address',
        'user_agent',
        'session_id',
        'logged_in_at',
    ];

    protected $casts = [
        'logged_in_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Human-friendly device/browser label without adding a third-party
     * user-agent parsing dependency.
     */
    public function deviceLabel(): string
    {
        $ua = strtolower((string) $this->user_agent);

        $browser = match (true) {
            str_contains($ua, 'edg/') => 'Microsoft Edge',
            str_contains($ua, 'opr/') || str_contains($ua, 'opera') => 'Opera',
            str_contains($ua, 'chrome/') => 'Chrome',
            str_contains($ua, 'firefox/') => 'Firefox',
            str_contains($ua, 'safari/') => 'Safari',
            default => 'Browser',
        };

        $device = match (true) {
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'iphone') || str_contains($ua, 'ipad') => 'iPhone/iPad',
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'macintosh') || str_contains($ua, 'mac os') => 'Mac',
            str_contains($ua, 'linux') => 'Linux',
            default => 'Unknown device',
        };

        return $browser . ' on ' . $device;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyStep extends Model
{
    protected $fillable = [
        'user_id',
        'tracking_date',
        'steps',
        'daily_goal',
        'is_tracking',
        'tracking_started_at',
        'tracking_stopped_at',
        'last_synced_at',
        'device_baseline_steps',
        'distance_m',
    ];

    protected $casts = [
        'tracking_date' => 'date',
        'steps' => 'integer',
        'daily_goal' => 'integer',
        'is_tracking' => 'boolean',
        'tracking_started_at' => 'datetime',
        'tracking_stopped_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'device_baseline_steps' => 'integer',
        'distance_m' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function progressPercent(): int
    {
        $goal = max(1, (int) $this->daily_goal);
        return min(100, (int) round(((int) $this->steps / $goal) * 100));
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WellbeingNudgeLog extends Model
{
    protected $fillable = [
        'user_id',
        'metric',
        'last_notified_at',
    ];

    protected $casts = [
        'last_notified_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SleepLog extends Model
{
    use HasFactory;

    protected $table = 'sleep_logs';

    protected $fillable = ['user_id', 'sleep_date', 'bed_time', 'wake_time', 'duration_minutes', 'quality', 'notes', 'is_archived',];

    protected $casts = [
        'sleep_date' => 'date',
    ];

    protected static function booted()
    {
        static::saving(function (SleepLog $log) {
            if ($log->bed_time && $log->wake_time && ! $log->duration_minutes) {
                $bed = \Carbon\Carbon::parse($log->bed_time);
                $wake = \Carbon\Carbon::parse($log->wake_time);
                if ($wake->lessThanOrEqualTo($bed)) {
                    $wake->addDay();
                }
                $log->duration_minutes = $bed->diffInMinutes($wake);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

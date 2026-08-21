<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupSetting extends Model
{
    protected $fillable = [
        'enabled','frequency','day_of_week','day_of_month','run_time','timezone','backup_type','provider',
        's3_key','s3_secret','s3_region','s3_bucket','s3_endpoint','s3_path_style','retention_days','last_run_at','next_run_at',
    ];

    protected $casts = [
        'enabled' => 'boolean', 's3_path_style' => 'boolean', 's3_secret' => 'encrypted',
        'last_run_at' => 'datetime', 'next_run_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'enabled' => false, 'frequency' => 'weekly', 'day_of_week' => 0,
            'run_time' => '02:00:00', 'timezone' => 'Africa/Kampala', 'backup_type' => 'both',
            'provider' => 'local', 'retention_days' => 90,
        ]);
    }
}

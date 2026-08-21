<?php

namespace App\Console\Commands;

use App\Models\BackupSetting;
use App\Services\BackupService;
use Illuminate\Console\Command;

class RunScheduledBackup extends Command
{
    protected $signature = 'backups:run-scheduled';
    protected $description = 'Run the configured automatic backup when it is due';
    public function handle(BackupService $service): int
    {
        $settings = BackupSetting::current();
        if (! $service->isDue($settings)) return self::SUCCESS;
        $history = $service->run($settings, 'scheduled');
        if ($history->status !== 'completed') { $this->error($history->error_message ?: 'Backup failed.'); return self::FAILURE; }
        $this->info('Backup completed.');
        return self::SUCCESS;
    }
}

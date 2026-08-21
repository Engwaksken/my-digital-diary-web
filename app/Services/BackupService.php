<?php

namespace App\Services;

use App\Models\BackupHistory;
use App\Models\BackupSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class BackupService
{
    public function run(BackupSetting $settings, string $trigger = 'scheduled', ?int $createdBy = null, ?string $type = null): BackupHistory
    {
        $type = $type ?: $settings->backup_type;
        $history = BackupHistory::create([
            'created_by' => $createdBy, 'trigger' => $trigger, 'backup_type' => $type,
            'provider' => $settings->provider, 'status' => 'running', 'started_at' => now(),
        ]);

        $workDir = storage_path('app/backup-temp/'.$history->id);
        File::ensureDirectoryExists($workDir);

        try {
            if (! class_exists(ZipArchive::class)) throw new RuntimeException('PHP Zip extension is required for backups.');
            if (in_array($type, ['database','both'], true)) $this->exportDatabase($workDir.'/database');
            if (in_array($type, ['files','both'], true)) $this->copyUserFiles($workDir.'/files');

            $filename = 'my-digital-diary-'.now()->format('Ymd-His').'-'.$history->id.'.zip';
            $localZip = storage_path('app/backup-temp/'.$filename);
            $this->zipDirectory($workDir, $localZip);
            $targetPath = 'backups/'.$filename;
            $disk = $this->disk($settings);
            $disk->put($targetPath, fopen($localZip, 'rb'));
            $size = filesize($localZip) ?: null;

            $history->update(['status' => 'completed', 'path' => $targetPath, 'size_bytes' => $size, 'completed_at' => now()]);
            $settings->forceFill(['last_run_at' => now(), 'next_run_at' => $this->nextRun($settings)])->save();
            $this->cleanup($settings, $disk);
            @unlink($localZip);
            File::deleteDirectory($workDir);
        } catch (\Throwable $e) {
            report($e);
            $history->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => now()]);
            File::deleteDirectory($workDir);
        }

        return $history->fresh();
    }

    public function isDue(BackupSetting $settings): bool
    {
        if (! $settings->enabled) return false;
        if (! $settings->next_run_at) {
            $settings->next_run_at = $this->nextRun($settings, true);
            $settings->save();
        }
        return $settings->next_run_at && now()->gte($settings->next_run_at);
    }

    public function nextRun(BackupSetting $settings, bool $fromNow = false): Carbon
    {
        $tz = $settings->timezone ?: 'Africa/Kampala';
        $now = Carbon::now($tz);
        [$h,$m] = array_map('intval', explode(':', substr((string)$settings->run_time, 0, 5)));
        $next = $now->copy()->setTime($h, $m, 0);
        if ($settings->frequency === 'daily') {
            if ($next->lte($now)) $next->addDay();
        } elseif ($settings->frequency === 'monthly') {
            $day = max(1, min(28, (int)$settings->day_of_month));
            $next->day($day);
            if ($next->lte($now)) $next->addMonth()->day($day);
        } else {
            $target = (int)$settings->day_of_week;
            while ($next->dayOfWeek !== $target || $next->lte($now)) $next->addDay();
        }
        return $next->utc();
    }

    private function exportDatabase(string $dir): void
    {
        File::ensureDirectoryExists($dir);
        $driver = DB::getDriverName();
        $tables = $driver === 'mysql'
            ? collect(DB::select('SHOW TABLES'))->map(fn($r) => array_values((array)$r)[0])->all()
            : collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))->pluck('name')->all();
        foreach ($tables as $table) {
            $fh = fopen($dir.'/'.$table.'.jsonl', 'wb');
            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use ($fh) {
                foreach ($rows as $row) fwrite($fh, json_encode((array)$row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
            });
            fclose($fh);
        }
    }

    private function copyUserFiles(string $dest): void
    {
        File::ensureDirectoryExists($dest);
        foreach ([storage_path('app/public')] as $source) {
            if (is_dir($source)) File::copyDirectory($source, $dest.'/public');
        }
    }

    private function zipDirectory(string $source, string $target): void
    {
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE|ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create backup archive.');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile()) $zip->addFile($file->getRealPath(), substr($file->getRealPath(), strlen($source)+1));
        }
        $zip->close();
    }

    private function disk(BackupSetting $settings)
    {
        if ($settings->provider === 's3') {
            if (! $settings->s3_bucket || ! $settings->s3_key || ! $settings->s3_secret) throw new RuntimeException('Complete the S3 cloud backup credentials first.');
            return Storage::build([
                'driver' => 's3','key' => $settings->s3_key,'secret' => $settings->s3_secret,'region' => $settings->s3_region ?: 'us-east-1',
                'bucket' => $settings->s3_bucket,'endpoint' => $settings->s3_endpoint ?: null,'use_path_style_endpoint' => (bool)$settings->s3_path_style,
                'throw' => true,
            ]);
        }
        return Storage::disk('local');
    }

    private function cleanup(BackupSetting $settings, $disk): void
    {
        if (! $settings->retention_days) return;
        $cutoff = now()->subDays((int)$settings->retention_days);
        foreach (BackupHistory::where('status','completed')->where('completed_at','<',$cutoff)->get() as $old) {
            if ($old->path) try { $disk->delete($old->path); } catch (\Throwable $e) { report($e); }
            $old->delete();
        }
    }
}

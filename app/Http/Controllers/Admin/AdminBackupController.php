<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupSetting;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminBackupController extends Controller
{
    public function update(Request $request, BackupService $service): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable','boolean'], 'frequency' => ['required','in:daily,weekly,monthly'],
            'day_of_week' => ['required','integer','between:0,6'], 'day_of_month' => ['required','integer','between:1,28'],
            'run_time' => ['required','date_format:H:i'], 'timezone' => ['required','timezone'],
            'backup_type' => ['required','in:database,files,both'], 'provider' => ['required','in:local,s3'],
            's3_key' => ['nullable','string','max:255'], 's3_secret' => ['nullable','string','max:500'],
            's3_region' => ['nullable','string','max:100'], 's3_bucket' => ['nullable','string','max:255'],
            's3_endpoint' => ['nullable','url','max:500'], 's3_path_style' => ['nullable','boolean'],
            'retention_days' => ['nullable','integer','min:1','max:3650'],
        ]);
        $setting = BackupSetting::current();
        $secret = $data['s3_secret'] ?? null;
        unset($data['s3_secret']);
        $setting->fill($data);
        $setting->enabled = $request->boolean('enabled');
        $setting->s3_path_style = $request->boolean('s3_path_style');
        if (filled($secret)) $setting->s3_secret = $secret;
        $setting->next_run_at = $setting->enabled ? $service->nextRun($setting, true) : null;
        $setting->save();
        return back()->with('success', 'Backup settings updated.');
    }

    public function run(Request $request, BackupService $service): RedirectResponse
    {
        $data = $request->validate(['backup_type' => ['required','in:database,files,both']]);
        $history = $service->run(BackupSetting::current(), 'manual', $request->user()->id, $data['backup_type']);
        return back()->with($history->status === 'completed' ? 'success' : 'error', $history->status === 'completed' ? 'Backup completed successfully.' : 'Backup failed: '.$history->error_message);
    }
}

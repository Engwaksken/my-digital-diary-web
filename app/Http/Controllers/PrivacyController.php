<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\DietLog;
use App\Models\EducationPlan;
use App\Models\Expense;
use App\Models\HealthCheckup;
use App\Models\Income;
use App\Models\NetworkContact;
use App\Models\PersonalRelationship;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Reminder;
use App\Models\SleepLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Privacy & Data" account page: shows the user's on-record consent, lets
 * them export everything they've stored (right to data portability), and
 * permanently delete their account (right to erasure). Deliberately kept
 * OUTSIDE the 'subscribed' middleware gate in routes/web.php — these are
 * rights a user should be able to exercise even if their trial has lapsed.
 */
class PrivacyController extends Controller
{
    public function show(Request $request)
    {
        $pendingExport = $request->session()->get('pending_export');
        $exportReady = $pendingExport && file_exists($pendingExport['path'] ?? '');

        return view('privacy.show', [
            'user' => $request->user(),
            'exportReady' => $exportReady,
            'exportEncrypted' => $pendingExport['encrypted'] ?? false,
        ]);
    }

    /**
     * Step 1 of 2 — generates the export as a compressed, password-
     * protected zip (not a bare JSON download) and shows the password
     * via a flash message. The password can't be shown AND the file
     * downloaded in the same response, so this redirects back with the
     * password displayed, and downloadExport() below handles the
     * actual file transfer as a separate step once the user has seen
     * (and hopefully saved) that password.
     */
    public function requestExport(Request $request)
    {
        $userId = $request->user()->id;

        $data = [
            'exported_at' => now()->toIso8601String(),
            'account' => $request->user()->only([
                'name', 'email', 'created_at', 'data_consent_at', 'data_consent_version',
            ]),
            'plans' => Plan::where('user_id', $userId)->get(),
            'incomes' => Income::where('user_id', $userId)->get(),
            'budgets' => Budget::where('user_id', $userId)->get(),
            'expenses' => Expense::where('user_id', $userId)->get(),
            'diet_logs' => DietLog::where('user_id', $userId)->get(),
            'sleep_logs' => SleepLog::where('user_id', $userId)->get(),
            'health_checkups' => HealthCheckup::where('user_id', $userId)->get(),
            'projects' => Project::where('user_id', $userId)->get(),
            'project_tasks' => ProjectTask::where('user_id', $userId)->get(),
            'reminders' => Reminder::where('user_id', $userId)->get(),
            'education_plans' => EducationPlan::where('user_id', $userId)->get(),
            'network_contacts' => NetworkContact::where('user_id', $userId)->get(),
            'relationships' => PersonalRelationship::where('user_id', $userId)->get(),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $password = \Illuminate\Support\Str::random(12);

        $tempDir = storage_path('app/private-exports');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0700, true);
        }

        // Scoped to this user specifically and randomized, so even
        // someone else on the same server guessing at filenames can't
        // land on another user's pending export.
        $zipFilename = 'export-' . $userId . '-' . \Illuminate\Support\Str::random(16) . '.zip';
        $zipPath = $tempDir . '/' . $zipFilename;

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('personal-monitor-data-export.json', $json);

        $encrypted = false;
        try {
            // Not every PHP/libzip build supports this — if it's
            // unavailable, the export still succeeds as a plain
            // (compressed but unencrypted) zip rather than failing
            // outright, since a working export is better than none.
            $zip->setEncryptionName('personal-monitor-data-export.json', \ZipArchive::EM_AES_256, $password);
            $encrypted = true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Zip encryption unavailable, exporting uncompressed password protection.', ['error' => $e->getMessage()]);
        }
        $zip->close();

        // Path stays in a persistent session key (the download button
        // needs it to keep working even across a page refresh); the
        // password is flashed instead — shown exactly once, on the
        // very next page load, then gone. Nothing this sensitive
        // should linger in the session longer than it has to.
        $request->session()->put('pending_export', [
            'path' => $zipPath,
            'encrypted' => $encrypted,
        ]);

        return redirect()->route('privacy.show')->with('export_password', $encrypted ? $password : null);
    }

    public function downloadExport(Request $request)
    {
        $pending = $request->session()->get('pending_export');

        abort_unless($pending && file_exists($pending['path']), 404);

        return response()->download($pending['path'], 'personal-monitor-data-export.zip')
            ->deleteFileAfterSend(true);
    }

    public function destroyAccount(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        // Every module's migration declared user_id as
        // ->constrained()->cascadeOnDelete(), so this one call removes
        // every plan, expense, health record, relationship note, etc.
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Your account and all associated data have been permanently deleted.');
    }
}

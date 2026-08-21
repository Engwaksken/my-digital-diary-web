<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Reminder Scheduling
|--------------------------------------------------------------------------
|
| Checks every minute for reminders that are due.
|
*/
Schedule::command('reminders:send')
    ->everyMinute()
    ->withoutOverlapping(2);

/*
|--------------------------------------------------------------------------
| Health Checkup Reminders
|--------------------------------------------------------------------------
|
| Creates reminder records for upcoming health checkups.
|
*/
Schedule::command('checkups:create-reminders')
    ->dailyAt('06:00')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Recurring Meetings
|--------------------------------------------------------------------------
|
| Generates future occurrences of recurring meetings.
|
*/
Schedule::command('meetings:generate-recurring')
    ->dailyAt('06:30')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Daily Planner / Due Item Notifications
|--------------------------------------------------------------------------
|
| These commands may internally decide when an individual user should
| actually receive a notification.
|
*/
Schedule::command('digest:daily-top-tasks')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('push:daily-due-items')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Subscription Expiry Reminders
|--------------------------------------------------------------------------
*/
Schedule::command('subscriptions:send-expiry-reminders')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| End-of-Day Digest
|--------------------------------------------------------------------------
*/
Schedule::command('digest:end-of-day')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Engagement
|--------------------------------------------------------------------------
*/
Schedule::command('engagement:daily-tip')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('engagement:send-smart-nudges')
    ->hourly()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Account Cleanup
|--------------------------------------------------------------------------
*/
Schedule::command('accounts:purge-scheduled-deletions')
    ->hourly()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Scheduled Backups
|--------------------------------------------------------------------------
*/
Schedule::command('backups:run-scheduled')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Chat / Support Cleanup
|--------------------------------------------------------------------------
*/
Schedule::command('support:release-idle-assignments')
    ->everyMinute()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Mobile Sync Cleanup
|--------------------------------------------------------------------------
|
| Remove old idempotency/synchronisation records after 30 days.
|
*/
Schedule::call(function () {
    if (! Schema::hasTable('mobile_sync_requests')) {
        return;
    }

    DB::table('mobile_sync_requests')
        ->where('created_at', '<', now()->subDays(30))
        ->delete();
})
    ->dailyAt('02:20')
    ->name('cleanup-mobile-sync-requests')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Social Media Planner
|--------------------------------------------------------------------------
|
| IMPORTANT:
| The currently installed social-media:process-scheduled command does NOT
| support a --limit option.
|
| Run this every minute so that:
| - upcoming social posts can trigger reminders;
| - due manual posts can become Ready to Post;
| - due automatic posts can be sent to connected publishing APIs/providers;
| - retryable publishing failures can be retried by the command.
|
| We intentionally do NOT use ->onOneServer() here because this installation
| is running from a single cPanel Laravel application. Using onOneServer()
| previously caused the command to be skipped because of the scheduler mutex.
|
*/
Schedule::command('social-media:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping(5);

/*
|--------------------------------------------------------------------------
| Social Media Analytics
|--------------------------------------------------------------------------
|
| Refresh available post performance metrics from connected social platforms.
|
| Keep --limit=250 only if:
|
|   php artisan help social-media:sync-metrics
|
| shows that --limit is supported.
|
*/
Schedule::command('social-media:sync-metrics --limit=250')
    ->everyThirtyMinutes()
    ->withoutOverlapping(10);

/*
|--------------------------------------------------------------------------
| Recurring Spiritual Growth
|--------------------------------------------------------------------------
|
| Generates upcoming Daily / Weekly / Monthly recurring Spiritual Growth
| sessions.
|
*/
Schedule::command('spiritual-growth:generate-recurring')
    ->dailyAt('06:40')
    ->withoutOverlapping();
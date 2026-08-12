<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Reminder Scheduling
|--------------------------------------------------------------------------
|
| Checks every MINUTE for reminders that are due and sends them (mail or
| in-app/database notification, per each reminder's "channel"). This used
| to run ->hourly(), which meant an "every 15 minutes" or "hourly"
| reminder could sit for up to an hour before actually going out — this
| command itself only sends things whose next_run_at has already passed,
| so checking more often just means better precision, not duplicate sends.
| Make sure your server has a cron entry running `php artisan schedule:run`
| every minute — see README.md.
|
*/
Schedule::command('reminders:send')->everyMinute()->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Auto-generate reminders from upcoming health checkups
|--------------------------------------------------------------------------
|
| Every morning, scans health_checkups.next_due_date and creates a Reminder
| 7 days out (configurable via --days) for anything that doesn't already
| have one. That reminder then gets picked up and sent by reminders:send
| above like any other reminder.
|
*/
Schedule::command('checkups:create-reminders')->dailyAt('06:00');
Schedule::command('meetings:generate-recurring')->dailyAt('06:30');

/*
|--------------------------------------------------------------------------
| Daily Top 3 Tasks Digest
|--------------------------------------------------------------------------
|
| Emails every opted-in user (users.daily_digest_enabled, default true —
| opt-OUT, not opt-in) their top 3 open items for the day at 6am. Skips
| a user entirely if they have nothing open that day, rather than sending
| an empty "here's your top 0 tasks" email.
|
*/
Schedule::command('digest:daily-top-tasks')->dailyAt('08:00');
Schedule::command('push:daily-due-items')->dailyAt('08:00');

/*
|--------------------------------------------------------------------------
| Subscription Expiry Reminders
|--------------------------------------------------------------------------
|
| Emails users on a fixed schedule as their trial/subscription approaches
| expiry: 14, 12, 10, 8, 6, 4, 2 days out, and on the expiry date itself.
| Each milestone only ever sends once (see the command's own docblock).
|
*/
Schedule::command('subscriptions:send-expiry-reminders')->dailyAt('07:00');

<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Notifications\ReminderNotification;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Runs on a schedule (see routes/console.php) and fires every reminder
 * whose next_run_at has passed. Recurring reminders are automatically
 * rolled forward (daily/weekly/monthly/annually); one-off reminders are
 * deactivated after firing.
 */
class SendReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Send all due reminders and reschedule recurring ones';

    public function handle(FcmService $fcm): int
    {
        $due = Reminder::with('user')
            ->where('is_active', true)
            ->where('next_run_at', '<=', Carbon::now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No reminders due.');
            return self::SUCCESS;
        }

        foreach ($due as $reminder) {
            if (! $reminder->user) {
                continue;
            }

            // Per the subscription-expiry restrictions: an expired user
            // doesn't get reminder emails/alarms sent on their behalf
            // until they renew. Still advances the schedule (rather than
            // leaving it due) so a lapsed subscriber doesn't get hit with
            // a backlog of every missed occurrence firing at once the
            // moment they renew — reminders just resume normally from
            // whatever's next after that point.
            if (! $reminder->user->hasActiveAccess()) {
                $reminder->scheduleNext();
                continue;
            }

            // This runs every minute (see routes/console.php). Without
            // this try/catch, one reminder's email getting rejected by the
            // mail server (a 550 "classified as spam" response is a real,
            // observed failure mode) would throw and abort the ENTIRE
            // foreach — every other due reminder in this run, for
            // potentially many different users, would silently never get
            // sent or rescheduled that cycle. Each reminder is now
            // independent: one failure is logged and skipped, the rest of
            // the batch still goes out. A reminder that failed to send
            // deliberately does NOT call scheduleNext() — its next_run_at
            // stays in the past, so it's picked up again (and retried) on
            // the next run, rather than silently skipping an occurrence
            // that was never actually delivered.
            try {
                NotificationFacade::send($reminder->user, new ReminderNotification($reminder));
            } catch (Throwable $e) {
                Log::warning('Could not send a reminder notification.', [
                    'reminder_id' => $reminder->id,
                    'user_id' => $reminder->user->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Failed to send reminder #{$reminder->id} \"{$reminder->title}\" to {$reminder->user->email} — will retry next run.");

                continue;
            }

            // Mobile push, in ADDITION to email — deliberately does not
            // gate scheduleNext()/the email path above at all. Email is
            // the long-established, reliable channel (with its own
            // hardening, above); push is a bonus for anyone with the
            // Flutter app installed and no different in spirit from the
            // in-app browser alarm — if it's not configured
            // (FIREBASE_CREDENTIALS_PATH missing) or a user has no
            // registered device, FcmService::sendToUser() silently does
            // nothing rather than failing the reminder.
            try {
                $fcm->sendToUser($reminder->user, $reminder->title, $reminder->message ?: 'This is your scheduled reminder.', [
                    'reminder_id' => (string) $reminder->id,
                    'type' => 'reminder',
                ]);
            } catch (Throwable $e) {
                Log::warning('FCM push attempt threw unexpectedly.', [
                    'reminder_id' => $reminder->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $reminder->scheduleNext();

            $this->info("Sent reminder #{$reminder->id} \"{$reminder->title}\" to {$reminder->user->email}");
        }

        return self::SUCCESS;
    }
}

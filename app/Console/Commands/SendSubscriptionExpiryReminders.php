<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SubscriptionExpiryReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs daily — for every user with a relevant expiry date (trialing or
 * paid-but-not-lifetime), computes days remaining and sends a reminder
 * on the schedule: 14, 12, 10, 8, 6, 4, 2, 0 days out. Each milestone is
 * only ever sent once (tracked via users.last_expiry_reminder_days) —
 * running this command twice in one day, or any day the days-remaining
 * count doesn't land exactly on a milestone, sends nothing extra.
 *
 * Deliberately stops emailing after the expiry-day (0) reminder — the
 * persistent "subscription expired" banner (see layouts/app.blade.php)
 * keeps showing on every page load for as long as they're expired, which
 * covers the "continue displaying... until renewed" requirement without
 * emailing someone daily for months if they haven't come back.
 */
class SendSubscriptionExpiryReminders extends Command
{
    protected $signature = 'subscriptions:send-expiry-reminders';

    protected $description = 'Email users approaching or at their subscription/trial expiry date, on a fixed schedule';

    private const SCHEDULE_DAYS = [14, 12, 10, 8, 6, 4, 2, 0];

    public function handle(): int
    {
        $users = User::whereIn('subscription_status', ['trialing', 'active'])
            ->get()
            ->filter(fn ($user) => $user->relevantExpiryDate() !== null);

        foreach ($users as $user) {
            $expiryDate = $user->relevantExpiryDate();
            $daysRemaining = (int) now()->startOfDay()->diffInDays($expiryDate->copy()->startOfDay(), false);

            if (! in_array($daysRemaining, self::SCHEDULE_DAYS, true)) {
                continue;
            }

            if ($user->last_expiry_reminder_days === $daysRemaining) {
                continue; // already sent today's milestone
            }

            try {
                $user->notify(new SubscriptionExpiryReminderNotification($expiryDate, $daysRemaining));
                $user->update(['last_expiry_reminder_days' => $daysRemaining]);
                \App\Models\BillingEventLog::record('reminder_sent', $user->id, ['recipient_email' => $user->email, 'details' => "{$daysRemaining} days before expiry"]);
                $this->info("Sent {$daysRemaining}-day expiry reminder to {$user->email}");
            } catch (Throwable $e) {
                \App\Models\BillingEventLog::record('reminder_sent', $user->id, [
                    'recipient_email' => $user->email, 'status' => 'failed', 'details' => $e->getMessage(),
                ]);
                Log::warning('Could not send subscription expiry reminder.', [
                    'user_id' => $user->id,
                    'days_remaining' => $daysRemaining,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}

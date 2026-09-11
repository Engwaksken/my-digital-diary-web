<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SubscriptionReminderService;
use Illuminate\Console\Command;

final class SendSubscriptionExpiryReminders extends Command
{
    protected $signature = 'subscriptions:send-expiry-reminders';

    protected $description =
        'Send trial expiry reminders twice per week during the final 14 days.';

    public function handle(SubscriptionReminderService $reminders): int
    {
        $sent = $reminders->sendDueReminders();

        $this->info($sent.' subscription reminder notification(s) sent.');

        return self::SUCCESS;
    }
}

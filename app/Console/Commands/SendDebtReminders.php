<?php

namespace App\Console\Commands;

use App\Models\Debt;
use App\Services\DebtReminderService;
use Illuminate\Console\Command;

class SendDebtReminders extends Command
{
    protected $signature = 'debts:send-reminders {--limit=200}';
    protected $description = 'Send due automatic debt reminders';

    public function handle(DebtReminderService $service): int
    {
        Debt::query()
            ->with('user')
            ->where('status', 'outstanding')
            ->where('reminder_enabled', true)
            ->whereNotNull('next_reminder_at')
            ->where('next_reminder_at', '<=', now())
            ->orderBy('next_reminder_at')
            ->limit((int) $this->option('limit'))
            ->get()
            ->each(function (Debt $debt) use ($service): void {
                if (! $debt->user) return;
                $service->send(
                    $debt,
                    $debt->user,
                    $debt->reminder_channel ?: 'email',
                    'counterparty'
                );
            });

        return self::SUCCESS;
    }
}

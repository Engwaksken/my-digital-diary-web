<?php

namespace App\Console\Commands;

use App\Models\SavingsGoal;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendSavingsGoalReminders extends Command
{
    protected $signature = 'savings:send-reminders {--limit=200}';
    protected $description = 'Send due savings contribution reminders';

    public function handle(SmsService $sms): int
    {
        SavingsGoal::query()
            ->with('user')
            ->where('status', 'in_progress')
            ->where('reminder_enabled', true)
            ->whereNotNull('next_reminder_at')
            ->where('next_reminder_at', '<=', now())
            ->orderBy('next_reminder_at')
            ->limit((int) $this->option('limit'))
            ->get()
            ->each(function (SavingsGoal $goal) use ($sms): void {
                $user = $goal->user;
                if (! $user) return;

                $message = "Savings reminder: {$goal->name}. Saved "
                    . number_format($goal->totalContributed(), 0)
                    . " of " . number_format((float) $goal->target_amount, 0)
                    . ". A small contribution today can keep the goal moving.";

                if (in_array($goal->reminder_channel, ['email','both'], true) && $user->email) {
                    try {
                        Mail::raw($message, fn ($mail) => $mail->to($user->email)->subject('Savings goal reminder'));
                    } catch (Throwable $e) { report($e); }
                }

                if (in_array($goal->reminder_channel, ['sms','both'], true) && filled($user->phone ?? null)) {
                    try { $sms->send($user->phone, $message); } catch (Throwable $e) { report($e); }
                }

                $goal->forceFill([
                    'last_reminder_at' => now(),
                    'next_reminder_at' => match ($goal->reminder_frequency) {
                        'daily' => now()->addDay(),
                        'fortnightly' => now()->addWeeks(2),
                        'monthly' => now()->addMonthNoOverflow(),
                        default => now()->addWeek(),
                    },
                ])->save();
            });

        return self::SUCCESS;
    }
}

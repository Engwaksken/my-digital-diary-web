<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\DebtReminderLog;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DebtReminderService
{
    public function __construct(private readonly SmsService $sms) {}

    public function send(
        Debt $debt,
        User $owner,
        string $channel = 'both',
        string $recipientScope = 'counterparty',
        ?string $customMessage = null
    ): array {
        $channel = in_array($channel, ['email','sms','both'], true) ? $channel : 'email';
        $recipientScope = in_array($recipientScope, ['counterparty','me','both'], true)
            ? $recipientScope : 'counterparty';

        $message = trim((string) $customMessage);
        if ($message === '') {
            $message = $this->message($debt, $owner);
        }

        $recipients = [];
        if (in_array($recipientScope, ['counterparty','both'], true)) {
            $recipients[] = [
                'scope' => 'counterparty',
                'name' => $debt->person_name,
                'email' => $debt->contact_email,
                'phone' => $debt->contact_phone,
            ];
        }
        if (in_array($recipientScope, ['me','both'], true)) {
            $recipients[] = [
                'scope' => 'me',
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone ?? null,
            ];
        }

        $results = [];
        foreach ($recipients as $recipient) {
            if (in_array($channel, ['email','both'], true)) {
                $results[] = $this->email($debt, $owner, $recipient, $message);
            }
            if (in_array($channel, ['sms','both'], true)) {
                $results[] = $this->sms($debt, $owner, $recipient, $message);
            }
        }

        $debt->forceFill([
            'last_reminder_at' => now(),
            'next_reminder_at' => $debt->reminder_enabled
                ? $this->nextDate($debt->reminder_frequency)
                : $debt->next_reminder_at,
        ])->save();

        return $results;
    }

    private function email(Debt $debt, User $owner, array $recipient, string $message): array
    {
        $address = trim((string) ($recipient['email'] ?? ''));
        if ($address === '') {
            return $this->log($debt, $owner, $recipient, 'email', 'skipped', $message, 'No email address.');
        }

        try {
            Mail::raw($message, function ($mail) use ($address, $debt): void {
                $mail->to($address)->subject(
                    $debt->type === 'lent' ? 'Debt payment reminder' : 'Debt reminder'
                );
            });
            return $this->log($debt, $owner, $recipient, 'email', 'sent', $message, 'Mail accepted for delivery.');
        } catch (Throwable $e) {
            report($e);
            return $this->log($debt, $owner, $recipient, 'email', 'failed', $message, $e->getMessage());
        }
    }

    private function sms(Debt $debt, User $owner, array $recipient, string $message): array
    {
        $address = trim((string) ($recipient['phone'] ?? ''));
        if ($address === '') {
            return $this->log($debt, $owner, $recipient, 'sms', 'skipped', $message, 'No phone number.');
        }

        try {
            $result = $this->sms->send($address, $message);
            return $this->log(
                $debt,
                $owner,
                $recipient,
                'sms',
                $result['ok'] ? 'sent' : 'failed',
                $message,
                json_encode($result, JSON_UNESCAPED_SLASHES)
            );
        } catch (Throwable $e) {
            report($e);
            return $this->log($debt, $owner, $recipient, 'sms', 'failed', $message, $e->getMessage());
        }
    }

    private function log(
        Debt $debt,
        User $owner,
        array $recipient,
        string $channel,
        string $status,
        string $message,
        string $response
    ): array {
        $address = $channel === 'email'
            ? ($recipient['email'] ?? null)
            : ($recipient['phone'] ?? null);

        $log = DebtReminderLog::create([
            'user_id' => $owner->id,
            'debt_id' => $debt->id,
            'recipient_scope' => $recipient['scope'],
            'recipient_name' => $recipient['name'] ?? null,
            'recipient_address' => $address,
            'channel' => $channel,
            'status' => $status,
            'message' => $message,
            'provider_response' => $response,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);

        return $log->toArray();
    }

    private function message(Debt $debt, User $owner): string
    {
        $amount = function_exists('format_money')
            ? format_money((float) $debt->amount)
            : number_format((float) $debt->amount, 2);

        if ($debt->type === 'lent') {
            return "Hello {$debt->person_name}, this is a reminder regarding the outstanding amount of {$amount}"
                . ($debt->due_date ? " due on {$debt->due_date->format('d M Y')}" : '')
                . ". Please contact {$owner->name} to confirm payment or discuss an arrangement. Thank you.";
        }

        return "Reminder: the outstanding amount of {$amount} owed to {$debt->person_name}"
            . ($debt->due_date ? " is/was due on {$debt->due_date->format('d M Y')}" : '')
            . ". Please review the commitment and arrange payment or follow-up.";
    }

    public function nextDate(?string $frequency)
    {
        return match ($frequency) {
            'daily' => now()->addDay(),
            'every_3_days' => now()->addDays(3),
            'weekly' => now()->addWeek(),
            'fortnightly' => now()->addWeeks(2),
            'monthly' => now()->addMonthNoOverflow(),
            default => null,
        };
    }
}

<?php
declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SubscriptionTrialReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $daysRemaining,
        private readonly string $planName = 'Monthly'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your My Digital Diary trial ends in '.$this->daysRemaining.' day'.($this->daysRemaining === 1 ? '' : 's'))
            ->greeting('Hello '.$notifiable->name.',')
            ->line(
                'Your '.$this->planName.' trial has '.$this->daysRemaining.' day'
                .($this->daysRemaining === 1 ? '' : 's').' remaining.'
            )
            ->line('Subscribe before the trial ends to keep uninterrupted access to My Digital Diary.')
            ->action('View Subscription', route('subscription.show'))
            ->line('These reminders stop as soon as your paid subscription becomes active.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_trial_reminder',
            'title' => 'Trial ending soon',
            'message' => 'Your '.$this->planName.' trial ends in '.$this->daysRemaining.' day'.($this->daysRemaining === 1 ? '' : 's').'.',
            'days_remaining' => $this->daysRemaining,
            'url' => route('subscription.show'),
        ];
    }
}

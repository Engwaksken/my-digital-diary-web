<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionActivatedNotification extends Notification
{
    use Queueable;

    public function __construct(public User $subscriber)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Subscription activated',
            'body' => sprintf(
                '%s (%s) activated or renewed their subscription.',
                $this->subscriber->name,
                $this->subscriber->email
            ),
            'type' => 'subscription_activated',
            'subscriber_id' => $this->subscriber->id,
        ];
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your login verification code')
            ->greeting('Hi ' . $notifiable->name . ',')
            ->line('Your one-time login code is:')
            ->line('**' . $this->code . '**')
            ->line('This code expires in 10 minutes.')
            ->line('If you did not try to log in, you can safely ignore this email.');
    }
}

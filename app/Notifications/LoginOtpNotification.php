<?php

namespace App\Notifications;

use App\Models\SiteSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification
{
    public function __construct(public string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = SiteSetting::current();
        $siteName = trim((string) ($site->site_name ?: 'My Digital Diary'));
        $primaryColor = method_exists($notifiable, 'themeColor')
            ? (string) $notifiable->themeColor()
            : '#00897B';

        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
            $primaryColor = '#00897B';
        }

        return (new MailMessage)
            ->subject("Your {$siteName} verification code")
            ->view('emails.login-verification-code', [
                'siteName' => $siteName,
                'logoUrl' => $site->logoUrl(),
                'primaryColor' => $primaryColor,
                'recipientName' => trim((string) ($notifiable->name ?? '')),
                'code' => $this->code,
            ]);
    }
}

<?php

namespace App\Notifications;

use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Reminder $reminder)
    {
    }

    public function via(object $notifiable): array
    {
        // 'database' is always included so every reminder shows up in
        // the in-app notification list (mobile's Notifications tab,
        // and any web equivalent) regardless of which delivery channel
        // the user actually chose — otherwise a user who picked "Email"
        // would never see their reminder history in-app at all, since
        // nothing would ever get recorded to the notifications table.
        return array_unique(['database', $this->reminder->channel]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reminder: ' . $this->reminder->title)
            ->greeting('Hi ' . $notifiable->name . ',')
            ->line($this->reminder->message ?: 'This is your scheduled reminder.')
            ->action('Open Personal Monitor', url('/dashboard'))
            ->line('You are receiving this because you set up a ' . $this->reminder->frequency . ' reminder.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'reminder_id' => $this->reminder->id,
            'title' => $this->reminder->title,
            'module' => $this->reminder->module,
            'message' => $this->reminder->message,
        ];
    }
}

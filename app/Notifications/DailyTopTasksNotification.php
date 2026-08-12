<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent daily at 8am (see SendDailyTopTasksDigest) with up to 3 of a user's
 * highest-priority open items for the day, pulled from Plans and Project
 * Tasks combined.
 */
class DailyTopTasksNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<int, array{title: string, source: string}> $tasks
     */
    public function __construct(public array $tasks)
    {
    }

    public function via(object $notifiable): array
    {
        // 'database' added alongside 'mail' for the same reason as
        // ReminderNotification::via() — otherwise this never shows up
        // in the mobile app's Notifications tab (or any future web
        // equivalent) at all, only in an email that's easy to miss.
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your top ' . count($this->tasks) . ' for today')
            ->greeting('Good morning, ' . $notifiable->name . '!')
            ->line("Here's what's most worth your attention today:");

        foreach ($this->tasks as $i => $task) {
            $mail->line(($i + 1) . '. ' . $task['title'] . ' (' . $task['source'] . ')');
        }

        return $mail
            ->action('Open Dashboard', url('/dashboard'))
            ->line("You're receiving this because daily task digests are on — see Reminders to adjust.");
    }

    public function toArray(object $notifiable): array
    {
        return ['tasks' => $this->tasks];
    }
}

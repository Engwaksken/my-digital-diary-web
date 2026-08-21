<?php

namespace App\Notifications;

use App\Models\SocialMediaPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SocialMediaPostingNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly SocialMediaPost $post,
        public readonly string $event,
        public readonly string $title,
        public readonly string $message
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'social_media_posting',
            'event' => $this->event,
            'title' => $this->title,
            'message' => $this->message,
            'post_id' => $this->post->id,
            'status' => $this->post->status,
            'posting_mode' => $this->post->posting_mode ?? 'manual',
            'platforms' => $this->post->platforms ?? [],
            'scheduled_at' => optional($this->post->scheduled_at)
                ->toIso8601String(),
            'url' => route('social-media-planner.index'),
            'action_required' => in_array(
                $this->event,
                ['ready_to_post', 'partial_failure', 'failed'],
                true
            ),
            'icon' => match ($this->event) {
                'published' => 'fa-circle-check',
                'failed', 'partial_failure' => 'fa-triangle-exclamation',
                'posting' => 'fa-paper-plane',
                default => 'fa-clock',
            },
            'tone' => match ($this->event) {
                'published' => 'emerald',
                'failed', 'partial_failure' => 'rose',
                'posting' => 'sky',
                default => 'amber',
            },
        ];
    }
}

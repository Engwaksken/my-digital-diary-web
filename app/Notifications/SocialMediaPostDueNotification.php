<?php

namespace App\Notifications;

use App\Models\SocialMediaPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SocialMediaPostDueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly SocialMediaPost $post
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'social_media_post_due',
            'title' => 'Social media post ready',
            'message' => "“{$this->post->title}” is ready to post now.",
            'post_id' => $this->post->id,
            'scheduled_at' => optional($this->post->scheduled_at)->toIso8601String(),
            'platforms' => $this->post->platforms ?? [],
            'url' => route('social-media-planner.index'),
            'action_required' => true,
            'tone' => 'sky',
            'icon' => 'fa-bullhorn',
        ];
    }
}

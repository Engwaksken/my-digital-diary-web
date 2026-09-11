<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TeamChatConversation;
use App\Models\TeamChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class TeamChatNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly TeamChatConversation $conversation,
        private readonly TeamChatMessage $message,
        private readonly string $kind = 'message'
    ) {}

    public function via(object $notifiable): array
    {
        // Database notification is always available to the existing in-app
        // notification centre. Existing FCM/push dispatchers can mirror these
        // database notifications without changing the team chat module.
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'team_chat',
            'kind' => $this->kind,
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'title' => $this->message->is_announcement
                ? 'Team announcement'
                : ($this->conversation->name ?: 'New team message'),
            'message' => mb_strimwidth(
                trim((string) $this->message->body),
                0,
                160,
                '…'
            ),
            'url' => route(
                'team-chat.show',
                $this->conversation
            ).'#message-'.$this->message->id,
        ];
    }
}

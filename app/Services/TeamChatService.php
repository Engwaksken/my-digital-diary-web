<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\TeamChatAttachment;
use App\Models\TeamChatConversation;
use App\Models\TeamChatConversationMember;
use App\Models\TeamChatMention;
use App\Models\TeamChatMessage;
use App\Models\User;
use App\Notifications\TeamChatNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class TeamChatService
{
    public function __construct(
        private readonly TeamChatAccessService $access
    ) {}

    public function createConversation(
        Organization $organization,
        User $actor,
        array $data
    ): TeamChatConversation {
        if (! $this->access->canManage($organization, $actor)
            && $data['type'] === 'channel') {
            throw new RuntimeException('Only owners and administrators can create channels.');
        }

        return DB::transaction(function () use ($organization, $actor, $data) {
            $conversation = TeamChatConversation::create([
                'organization_id' => $organization->id,
                'created_by' => $actor->id,
                'type' => $data['type'],
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'is_general' => false,
                'is_announcement_only' => (bool) ($data['is_announcement_only'] ?? false),
            ]);

            $userIds = collect($data['member_user_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->push((int) $actor->id)
                ->unique();

            if ($conversation->type !== 'channel') {
                $allowed = collect($this->access->organizationUserIds($organization));

                $userIds = $userIds
                    ->filter(fn ($id) => $allowed->contains($id))
                    ->values();

                foreach ($userIds as $userId) {
                    TeamChatConversationMember::firstOrCreate([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                    ]);
                }
            }

            return $conversation;
        });
    }

    public function createMessage(
        TeamChatConversation $conversation,
        User $actor,
        array $data,
        array $files = []
    ): TeamChatMessage {
        $organization = $this->access->assertConversationAccess(
            $actor,
            $conversation
        );

        if (
            $conversation->is_announcement_only
            && ! $this->access->canManage($organization, $actor)
        ) {
            throw new RuntimeException(
                'Only owners and administrators can post in this announcement channel.'
            );
        }

        if (
            ! empty($data['is_announcement'])
            && ! $this->access->canManage($organization, $actor)
        ) {
            throw new RuntimeException(
                'Only owners and administrators can send announcements.'
            );
        }

        if (empty(trim((string) ($data['body'] ?? ''))) && empty($files)) {
            throw new RuntimeException(
                'Enter a message or attach at least one file.'
            );
        }

        return DB::transaction(function () use (
            $conversation,
            $organization,
            $actor,
            $data,
            $files
        ) {
            $message = TeamChatMessage::create([
                'conversation_id' => $conversation->id,
                'user_id' => $actor->id,
                'reply_to_id' => $data['reply_to_id'] ?? null,
                'body' => trim((string) ($data['body'] ?? '')),
                'is_announcement' => (bool) ($data['is_announcement'] ?? false),
                'notify_all' => (bool) ($data['notify_all'] ?? false),
            ]);

            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $path = $file->store(
                    'team-chat/'.$organization->id.'/'.$conversation->id,
                    'local'
                );

                TeamChatAttachment::create([
                    'message_id' => $message->id,
                    'uploaded_by' => $actor->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize() ?: 0,
                ]);
            }

            $mentionedUserIds = collect(
                $data['mention_user_ids'] ?? []
            )
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique();

            $allowedIds = collect(
                $this->access->organizationUserIds($organization)
            );

            $mentionedUserIds = $mentionedUserIds
                ->filter(fn ($id) => $allowedIds->contains($id));

            foreach ($mentionedUserIds as $userId) {
                TeamChatMention::firstOrCreate([
                    'message_id' => $message->id,
                    'user_id' => $userId,
                ]);
            }

            $recipientIds = $this->recipientUserIds(
                $conversation,
                $organization,
                $actor,
                $message,
                $mentionedUserIds
            );

            $message->load(['attachments', 'replyTo', 'user']);

            User::query()
                ->whereIn('id', $recipientIds)
                ->get()
                ->each(function (User $user) use ($conversation, $message, $mentionedUserIds) {
                    $kind = $message->is_announcement
                        ? 'announcement'
                        : ($mentionedUserIds->contains($user->id) ? 'mention' : 'message');

                    $user->notify(
                        new TeamChatNotification(
                            $conversation,
                            $message,
                            $kind
                        )
                    );
                });

            return $message;
        });
    }

    public function editMessage(
        TeamChatMessage $message,
        User $actor,
        string $body
    ): TeamChatMessage {
        $organization = $this->access->assertConversationAccess(
            $actor,
            $message->conversation
        );

        $isManager = $this->access->canManage($organization, $actor);
        $isOwner = (int) $message->user_id === (int) $actor->id;
        $withinWindow = $message->created_at?->gt(now()->subMinutes(30)) ?? false;

        if (! $isManager && (! $isOwner || ! $withinWindow)) {
            throw new RuntimeException(
                'You can edit only your own recent messages.'
            );
        }

        $message->update([
            'body' => trim($body),
            'edited_at' => now(),
        ]);

        return $message->fresh(['user', 'attachments', 'reactions', 'replyTo']);
    }

    public function deleteMessage(
        TeamChatMessage $message,
        User $actor
    ): void {
        $organization = $this->access->assertConversationAccess(
            $actor,
            $message->conversation
        );

        $isManager = $this->access->canManage($organization, $actor);
        $isOwner = (int) $message->user_id === (int) $actor->id;
        $withinWindow = $message->created_at?->gt(now()->subMinutes(30)) ?? false;

        if (! $isManager && (! $isOwner || ! $withinWindow)) {
            throw new RuntimeException(
                'You can delete only your own recent messages.'
            );
        }

        $message->load('attachments');

        foreach ($message->attachments as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $message->delete();
    }

    private function recipientUserIds(
        TeamChatConversation $conversation,
        Organization $organization,
        User $actor,
        TeamChatMessage $message,
        Collection $mentionedUserIds
    ): array {
        if ($message->notify_all || $message->is_announcement) {
            $ids = collect($this->access->organizationUserIds($organization));
        } elseif ($conversation->type === 'channel') {
            $ids = collect($this->access->organizationUserIds($organization));
        } else {
            $ids = TeamChatConversationMember::query()
                ->where('conversation_id', $conversation->id)
                ->pluck('user_id');
        }

        return $ids
            ->merge($mentionedUserIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $actor->id)
            ->unique()
            ->values()
            ->all();
    }
}

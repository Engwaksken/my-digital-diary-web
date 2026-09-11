<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\TeamChatConversation;
use App\Models\TeamChatConversationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class TeamChatAccessService
{
    public function managedOrganization(User $user): Organization
    {
        $owned = Organization::query()
            ->where('owner_user_id', $user->id)
            ->first();

        if ($owned) {
            return $owned;
        }

        $membership = OrganizationMember::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            throw new RuntimeException(
                'You do not have access to an active organisation workspace.'
            );
        }

        return Organization::findOrFail($membership->organization_id);
    }

    public function membership(
        Organization $organization,
        User $user
    ): ?OrganizationMember {
        if ((int) $organization->owner_user_id === (int) $user->id) {
            return null;
        }

        return OrganizationMember::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    public function role(
        Organization $organization,
        User $user
    ): string {
        if ((int) $organization->owner_user_id === (int) $user->id) {
            return 'owner';
        }

        return strtolower(
            (string) ($this->membership($organization, $user)?->role ?? 'member')
        );
    }

    public function canManage(
        Organization $organization,
        User $user
    ): bool {
        return in_array(
            $this->role($organization, $user),
            ['owner', 'admin'],
            true
        );
    }

    public function assertConversationAccess(
        User $user,
        TeamChatConversation $conversation
    ): Organization {
        $organization = $conversation->organization()->firstOrFail();

        if ((int) $organization->owner_user_id === (int) $user->id) {
            return $organization;
        }

        $membership = $this->membership($organization, $user);

        if (! $membership) {
            throw new RuntimeException(
                'You no longer have access to this organisation conversation.'
            );
        }

        if ($conversation->type === 'channel') {
            return $organization;
        }

        $included = TeamChatConversationMember::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $included) {
            throw new RuntimeException(
                'You are not a member of this conversation.'
            );
        }

        return $organization;
    }

    public function organizationUserIds(
        Organization $organization
    ): array {
        $memberIds = OrganizationMember::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $memberIds[] = (int) $organization->owner_user_id;

        return array_values(array_unique($memberIds));
    }

    public function ensureGeneralChannel(
        Organization $organization,
        User $actor
    ): TeamChatConversation {
        return DB::transaction(function () use ($organization, $actor) {
            $channel = TeamChatConversation::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'is_general' => true,
                ],
                [
                    'created_by' => $actor->id,
                    'type' => 'channel',
                    'name' => 'General',
                    'description' => 'Organisation-wide conversation and announcements.',
                ]
            );

            return $channel;
        });
    }

    public function visibleConversations(
        User $user,
        Organization $organization
    ): Builder {
        return TeamChatConversation::query()
            ->where('organization_id', $organization->id)
            ->where('is_archived', false)
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->where('type', 'channel')
                    ->orWhereHas(
                        'members',
                        fn (Builder $memberQuery) =>
                            $memberQuery->where('user_id', $user->id)
                    );
            });
    }
}

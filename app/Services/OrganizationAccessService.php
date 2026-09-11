<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OrganizationAccessService
{
    public function ownedOrganization(User $user): ?Organization
    {
        return Organization::query()
            ->where('owner_user_id', $user->id)
            ->first();
    }

    public function activeMembership(User $user): ?OrganizationMember
    {
        return OrganizationMember::query()
            ->with(['organization.plan'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    public function organizationFor(User $user): ?Organization
    {
        $owned = $this->ownedOrganization($user);

        if ($owned) {
            return $owned;
        }

        return $this->activeMembership($user)?->organization;
    }

    public function ownerFor(User $user): ?User
    {
        $organization = $this->organizationFor($user);

        if (! $organization) {
            return null;
        }

        return User::find($organization->owner_user_id);
    }

    public function isOwner(User $user): bool
    {
        return $this->ownedOrganization($user) !== null;
    }

    public function isManagedMember(User $user): bool
    {
        return ! $this->isOwner($user)
            && $this->activeMembership($user) !== null;
    }

    public function effectiveRole(User $user): ?string
    {
        if ($this->isOwner($user)) {
            return 'owner';
        }

        return strtolower(
            (string) ($this->activeMembership($user)?->role ?? '')
        ) ?: null;
    }

    public function ownerHasActiveAccess(User $owner): bool
    {
        if (method_exists($owner, 'hasActiveAccess')) {
            return (bool) $owner->hasActiveAccess();
        }

        $status = strtolower(
            (string) ($owner->subscription_status ?? '')
        );

        if (! in_array(
            $status,
            ['active', 'trial', 'trialing'],
            true
        )) {
            return false;
        }

        $expiresAt =
            $owner->subscription_expires_at
            ?? $owner->subscription_ends_at
            ?? $owner->trial_ends_at
            ?? null;

        if ($expiresAt) {
            try {
                return Carbon::parse($expiresAt)->isFuture();
            } catch (\Throwable) {
                // Keep status-based access if legacy expiry is malformed.
            }
        }

        return true;
    }

    public function hasEffectiveAccess(User $user): bool
    {
        if ($this->isManagedMember($user)) {
            $owner = $this->ownerFor($user);

            return $owner
                ? $this->ownerHasActiveAccess($owner)
                : false;
        }

        return $this->ownerHasActiveAccess($user);
    }

    /**
     * Apply the owner's subscription to the current member IN MEMORY only.
     *
     * This deliberately does not create a member subscription and does not
     * write the owner's subscription ID into the member's billing fields.
     */
    public function applyInheritedSubscriptionContext(
        User $user
    ): User {
        $membership = $this->activeMembership($user);

        if (! $membership) {
            $this->syncOwnerIdentity($user);
            return $user;
        }

        $organization = $membership->organization;
        $owner = User::find($organization->owner_user_id);

        if (! $owner) {
            return $user;
        }

        $user->setAttribute(
            'organization_id',
            $organization->id
        );

        $user->setAttribute(
            'organization_role',
            $membership->role
        );

        foreach ([
            'subscription_plan_id',
            'subscription_status',
            'subscription_started_at',
            'subscription_expires_at',
            'subscription_ends_at',
            'trial_ends_at',
        ] as $field) {
            if (array_key_exists($field, $owner->getAttributes())) {
                $user->setAttribute(
                    $field,
                    $owner->getAttribute($field)
                );
            }
        }

        if ($owner->relationLoaded('subscriptionPlan')) {
            $user->setRelation(
                'subscriptionPlan',
                $owner->getRelation('subscriptionPlan')
            );
        } elseif (method_exists($owner, 'subscriptionPlan')) {
            $user->setRelation(
                'subscriptionPlan',
                $owner->subscriptionPlan
            );
        }

        $user->setRelation('organizationOwner', $owner);

        return $user;
    }

    /**
     * Keep users.organization_id / organization_role aligned with the
     * authoritative Organization + OrganizationMember records.
     */
    public function syncUserHierarchy(User $user): void
    {
        $owned = $this->ownedOrganization($user);

        if ($owned) {
            $update = [];

            if (Schema::hasColumn('users', 'organization_id')) {
                $update['organization_id'] = $owned->id;
            }

            if (Schema::hasColumn('users', 'organization_role')) {
                $update['organization_role'] = 'owner';
            }

            if ($update !== []) {
                $user->forceFill($update)->save();
            }

            return;
        }

        $membership = $this->activeMembership($user);

        if (! $membership) {
            return;
        }

        $update = [];

        if (Schema::hasColumn('users', 'organization_id')) {
            $update['organization_id'] =
                $membership->organization_id;
        }

        if (Schema::hasColumn('users', 'organization_role')) {
            $update['organization_role'] =
                $membership->role;
        }

        if ($update !== []) {
            $user->forceFill($update)->save();
        }
    }

    public function syncOrganization(
        Organization $organization
    ): void {
        DB::transaction(function () use ($organization): void {
            $owner = User::find($organization->owner_user_id);

            if ($owner) {
                $this->syncUserHierarchy($owner);
            }

            OrganizationMember::query()
                ->where(
                    'organization_id',
                    $organization->id
                )
                ->whereNotNull('user_id')
                ->get()
                ->each(function (OrganizationMember $membership): void {
                    $user = User::find($membership->user_id);

                    if (! $user) {
                        return;
                    }

                    $update = [];

                    if (
                        Schema::hasColumn(
                            'users',
                            'organization_id'
                        )
                    ) {
                        $update['organization_id'] =
                            $membership->organization_id;
                    }

                    if (
                        Schema::hasColumn(
                            'users',
                            'organization_role'
                        )
                    ) {
                        $update['organization_role'] =
                            $membership->role;
                    }

                    if ($update !== []) {
                        $user->forceFill($update)->save();
                    }
                });
        });
    }

    private function syncOwnerIdentity(User $user): void
    {
        $owned = $this->ownedOrganization($user);

        if (! $owned) {
            return;
        }

        $user->setAttribute(
            'organization_id',
            $owned->id
        );

        $user->setAttribute(
            'organization_role',
            'owner'
        );
    }
}

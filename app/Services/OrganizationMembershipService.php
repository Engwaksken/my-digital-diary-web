<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class OrganizationMembershipService
{
    public function canUseTeamWorkspace(User $user): bool
    {
        $plan = $user->subscriptionPlan;

        if (! $plan) {
            return false;
        }

        $includedSeats = (int) ($plan->included_seats ?? 0);
        $category = strtolower((string) ($plan->category ?? ''));
        $name = strtolower((string) ($plan->name ?? ''));

        $multiMemberPlan =
            $includedSeats > 1
            || in_array($category, [
                'family',
                'team',
                'small_team',
                'organization',
                'organisation',
                'enterprise',
            ], true)
            || str_contains($name, 'family')
            || str_contains($name, 'team')
            || str_contains($name, 'organization')
            || str_contains($name, 'organisation')
            || str_contains($name, 'enterprise');

        if (! $multiMemberPlan) {
            return false;
        }

        if (method_exists($user, 'hasActiveAccess')) {
            return (bool) $user->hasActiveAccess();
        }

        return in_array(
            strtolower((string) $user->subscription_status),
            ['active', 'trial', 'trialing'],
            true
        );
    }

    public function ensureManagedOrganization(User $user): ?Organization
    {
        if (! Schema::hasTable('organizations')) {
            return null;
        }

        $owned = Organization::query()
            ->where('owner_user_id', $user->id)
            ->first();

        if ($owned) {
            $this->syncPlan($owned, $user);
            return $owned->fresh('plan');
        }

        if (
            isset($user->organization_id)
            && $user->organization_id
            && Schema::hasColumn('users', 'organization_id')
        ) {
            $organization = Organization::find($user->organization_id);

            if (
                $organization
                && (
                    (int) $organization->owner_user_id === (int) $user->id
                    || strtolower((string) ($user->organization_role ?? '')) === 'admin'
                )
            ) {
                $this->syncPlan($organization, $user);
                return $organization->fresh('plan');
            }
        }

        if (! $this->canUseTeamWorkspace($user)) {
            return null;
        }

        $plan = $user->subscriptionPlan;

        return DB::transaction(function () use ($user, $plan): Organization {
            $organization = Organization::query()
                ->firstOrCreate(
                    ['owner_user_id' => $user->id],
                    [
                        'name' => $this->defaultOrganizationName($user, $plan?->name),
                        'subscription_plan_id' => $plan?->id,
                    ]
                );

            $this->syncPlan($organization, $user);

            return $organization->fresh('plan');
        });
    }

    public function seatLimit(Organization $organization): int
    {
        $organization->loadMissing('plan');

        return max(0, (int) ($organization->plan?->included_seats ?? 0));
    }

    public function seatsUsed(Organization $organization): int
    {
        return $organization->members()
            ->whereIn('status', ['invited', 'active'])
            ->count();
    }

    public function remainingSeats(Organization $organization): int
    {
        return max(
            0,
            $this->seatLimit($organization) - $this->seatsUsed($organization)
        );
    }

    public function assertSeatAvailable(Organization $organization): void
    {
        $limit = $this->seatLimit($organization);

        if ($limit <= 0) {
            throw new RuntimeException(
                'Your current subscription does not include member seats.'
            );
        }

        if ($this->seatsUsed($organization) >= $limit) {
            throw new RuntimeException(
                'All member seats on this subscription are currently in use.'
            );
        }
    }

    public function createAndAddNewUser(
        Organization $organization,
        string $name,
        string $email,
        string $temporaryPassword,
        string $role
    ): OrganizationMember {
        $email = strtolower(trim($email));

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw new RuntimeException(
                'That email already belongs to a My Digital Diary user. Add the existing account instead; its password must not be overwritten.'
            );
        }

        $this->assertSeatAvailable($organization);

        return DB::transaction(function () use (
            $organization,
            $name,
            $email,
            $temporaryPassword,
            $role
        ): OrganizationMember {
            $userData = [
                'name' => trim($name),
                'email' => $email,
                'password' => Hash::make($temporaryPassword),
            ];

            if (Schema::hasColumn('users', 'organization_id')) {
                $userData['organization_id'] = $organization->id;
            }

            if (Schema::hasColumn('users', 'organization_role')) {
                $userData['organization_role'] = $role;
            }

            /*
             * Do not mark the email verified automatically. The account can
             * log in immediately, but normal Laravel email-verification rules
             * still apply to verified-only areas.
             */
            $user = User::query()->create($userData);

            $member = OrganizationMember::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'invited_email' => $email,
                'role' => $role,
                'status' => 'active',
                'invite_token' => null,
                'invited_at' => now(),
                'activated_at' => now(),
                'deactivated_at' => null,
            ]);

            return $member->fresh('user');
        });
    }

    public function addExistingUser(
        Organization $organization,
        User $memberUser,
        string $role
    ): OrganizationMember {
        if ((int) $organization->owner_user_id === (int) $memberUser->id) {
            throw new RuntimeException(
                'The workspace owner is already part of this organization.'
            );
        }

        $member = OrganizationMember::query()
            ->where('organization_id', $organization->id)
            ->where(function ($query) use ($memberUser): void {
                $query
                    ->where('user_id', $memberUser->id)
                    ->orWhereRaw(
                        'LOWER(invited_email) = ?',
                        [strtolower((string) $memberUser->email)]
                    );
            })
            ->first();

        if (! $member || ! in_array($member->status, ['active', 'invited'], true)) {
            $this->assertSeatAvailable($organization);
        }

        $member ??= new OrganizationMember([
            'organization_id' => $organization->id,
        ]);

        $member->fill([
            'user_id' => $memberUser->id,
            'invited_email' => strtolower((string) $memberUser->email),
            'role' => $role,
            'status' => 'active',
            'invite_token' => null,
            'activated_at' => now(),
            'deactivated_at' => null,
        ]);

        if (! $member->invited_at) {
            $member->invited_at = now();
        }

        $member->save();

        $userUpdate = [];

        if (Schema::hasColumn('users', 'organization_id')) {
            $userUpdate['organization_id'] = $organization->id;
        }

        if (Schema::hasColumn('users', 'organization_role')) {
            $userUpdate['organization_role'] = $role;
        }

        if ($userUpdate !== []) {
            $memberUser->forceFill($userUpdate)->save();
        }

        return $member->fresh('user');
    }

    public function editMember(
        Organization $organization,
        OrganizationMember $member,
        array $data
    ): void {
        $this->assertBelongsTo($organization, $member);

        $role = $data['role'] ?? $member->role;
        $email = isset($data['email'])
            ? strtolower(trim((string) $data['email']))
            : null;

        $update = [
            'role' => $role,
        ];

        /*
         * Owners may edit the invitation email only while the member is still
         * pending. Once a real user account is linked, their account email is
         * personal profile data and is not changed from workspace management.
         */
        if (
            $member->status === 'invited'
            && $member->user_id === null
            && $email
        ) {
            $update['invited_email'] = $email;
        }

        $member->update($update);

        if (
            $member->user
            && Schema::hasColumn('users', 'organization_role')
        ) {
            $member->user->forceFill([
                'organization_role' => $role,
            ])->save();
        }
    }

    public function updateRole(
        Organization $organization,
        OrganizationMember $member,
        string $role
    ): void {
        $this->assertBelongsTo($organization, $member);

        $member->update(['role' => $role]);

        if (
            $member->user
            && Schema::hasColumn('users', 'organization_role')
        ) {
            $member->user->forceFill([
                'organization_role' => $role,
            ])->save();
        }
    }

    public function setActive(
        Organization $organization,
        OrganizationMember $member,
        bool $active
    ): void {
        $this->assertBelongsTo($organization, $member);

        $member->update([
            'status' => $active ? 'active' : 'inactive',
            'activated_at' => $active ? now() : $member->activated_at,
            'deactivated_at' => $active ? null : now(),
        ]);
    }

    public function remove(
        Organization $organization,
        OrganizationMember $member
    ): void {
        $this->assertBelongsTo($organization, $member);

        $memberUser = $member->user;

        $member->delete();

        if ($memberUser) {
            $update = [];

            if (Schema::hasColumn('users', 'organization_id')) {
                $update['organization_id'] = null;
            }

            if (Schema::hasColumn('users', 'organization_role')) {
                $update['organization_role'] = null;
            }

            if ($update !== []) {
                $memberUser->forceFill($update)->save();
            }
        }
    }

    public function assertBelongsTo(
        Organization $organization,
        OrganizationMember $member
    ): void {
        if ((int) $member->organization_id !== (int) $organization->id) {
            throw new RuntimeException(
                'That member does not belong to this organization.'
            );
        }
    }

    private function syncPlan(Organization $organization, User $user): void
    {
        $planId = $user->subscription_plan_id;

        if (
            $planId
            && (int) $organization->subscription_plan_id !== (int) $planId
        ) {
            $organization->forceFill([
                'subscription_plan_id' => $planId,
            ])->save();
        }
    }

    private function defaultOrganizationName(
        User $user,
        ?string $planName
    ): string {
        $name = trim((string) $user->name);

        if ($name !== '') {
            return $name.' Workspace';
        }

        return $planName
            ? $planName.' Workspace'
            : 'My Digital Diary Workspace';
    }
}

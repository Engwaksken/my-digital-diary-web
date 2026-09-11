<?php

namespace App\Services;

use App\Models\OrganizationWorkspaceActivity;
use App\Models\OrganizationWorkspaceInvitation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class OrganizationWorkspaceService
{
    public function organizationForUser(Authenticatable $user): ?object
    {
        $userId = (int) $user->getAuthIdentifier();

        if (Schema::hasTable('organizations')) {
            foreach (['owner_id', 'owner_user_id', 'user_id'] as $column) {
                if (! Schema::hasColumn('organizations', $column)) {
                    continue;
                }

                $organization = DB::table('organizations')
                    ->where($column, $userId)
                    ->first();

                if ($organization) {
                    return $organization;
                }
            }
        }

        $table = $this->memberTable();

        if (
            $table
            && Schema::hasColumn($table, 'organization_id')
            && Schema::hasColumn($table, 'user_id')
        ) {
            $query = DB::table($table)
                ->where('user_id', $userId);

            if (Schema::hasColumn($table, 'status')) {
                $query->whereIn('status', ['active', 'accepted']);
            }

            if (Schema::hasColumn($table, 'is_active')) {
                $query->where('is_active', true);
            }

            $membership = $query->first();

            if ($membership) {
                return DB::table('organizations')
                    ->where('id', $membership->organization_id)
                    ->first();
            }
        }

        return null;
    }

    public function memberTable(): ?string
    {
        foreach ([
            'organization_members',
            'organization_user',
            'organization_users',
        ] as $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        return null;
    }

    public function members(int $organizationId): Collection
    {
        $table = $this->memberTable();

        if (
            ! $table
            || ! Schema::hasColumn($table, 'organization_id')
            || ! Schema::hasColumn($table, 'user_id')
        ) {
            return collect();
        }

        $columns = [
            'm.id as membership_id',
            'm.organization_id',
            'm.user_id',
            'u.name',
            'u.email',
        ];

        foreach ([
            'role',
            'status',
            'is_active',
            'created_at',
            'joined_at',
        ] as $column) {
            if (Schema::hasColumn($table, $column)) {
                $columns[] = 'm.'.$column;
            }
        }

        return DB::table($table.' as m')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.organization_id', $organizationId)
            ->select($columns)
            ->orderBy('u.name')
            ->get();
    }

    public function pendingInvitations(int $organizationId): Collection
    {
        return OrganizationWorkspaceInvitation::query()
            ->with('inviter:id,name,email')
            ->where('organization_id', $organizationId)
            ->where('status', 'pending')
            ->whereNull('cancelled_at')
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->get();
    }

    public function roleForUser(int $organizationId, int $userId): string
    {
        $organization = Schema::hasTable('organizations')
            ? DB::table('organizations')->where('id', $organizationId)->first()
            : null;

        foreach (['owner_id', 'owner_user_id', 'user_id'] as $column) {
            if (
                $organization
                && property_exists($organization, $column)
                && (int) $organization->{$column} === $userId
            ) {
                return 'owner';
            }
        }

        $table = $this->memberTable();

        if (
            $table
            && Schema::hasColumn($table, 'organization_id')
            && Schema::hasColumn($table, 'user_id')
        ) {
            $row = DB::table($table)
                ->where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->first();

            if ($row) {
                return strtolower((string) ($row->role ?? 'member'));
            }
        }

        return 'none';
    }

    public function canManage(int $organizationId, int $userId): bool
    {
        return in_array(
            $this->roleForUser($organizationId, $userId),
            ['owner', 'admin', 'administrator', 'manager'],
            true
        );
    }

    public function assertMember(int $organizationId, int $userId): void
    {
        if ($this->roleForUser($organizationId, $userId) === 'none') {
            throw new RuntimeException(
                'You do not have access to this workspace.'
            );
        }
    }

    public function assertManager(int $organizationId, int $userId): void
    {
        if (! $this->canManage($organizationId, $userId)) {
            throw new RuntimeException(
                'You do not have permission to manage workspace members.'
            );
        }
    }

    public function seatLimit(
        object $organization,
        ?Authenticatable $owner = null
    ): ?int {
        foreach ([
            'seat_limit',
            'seats',
            'max_members',
            'member_limit',
        ] as $property) {
            if (
                isset($organization->{$property})
                && (int) $organization->{$property} > 0
            ) {
                return (int) $organization->{$property};
            }
        }

        if ($owner && method_exists($owner, 'subscriptionPlan')) {
            $plan = $owner->subscriptionPlan;

            if ($plan) {
                foreach ([
                    'max_workspace_members',
                    'seat_limit',
                    'seats',
                    'max_users',
                    'user_limit',
                ] as $property) {
                    if (
                        isset($plan->{$property})
                        && (int) $plan->{$property} > 0
                    ) {
                        return (int) $plan->{$property};
                    }
                }
            }
        }

        return null;
    }

    public function seatsUsed(int $organizationId): int
    {
        $members = $this->members($organizationId)
            ->filter(function ($member): bool {
                if (isset($member->status)) {
                    return ! in_array(
                        strtolower((string) $member->status),
                        ['removed', 'cancelled'],
                        true
                    );
                }

                if (isset($member->is_active)) {
                    return (bool) $member->is_active;
                }

                return true;
            })
            ->count();

        return $members + $this->pendingInvitations($organizationId)->count();
    }

    public function addOrReactivateMember(
        int $organizationId,
        int $userId,
        string $role
    ): void {
        $table = $this->memberTable();

        if (! $table) {
            throw new RuntimeException(
                'The existing organisation membership table was not found.'
            );
        }

        $existing = DB::table($table)
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->first();

        $values = [];

        if (Schema::hasColumn($table, 'role')) {
            $values['role'] = $role;
        }

        if (Schema::hasColumn($table, 'status')) {
            $values['status'] = 'active';
        }

        if (Schema::hasColumn($table, 'is_active')) {
            $values['is_active'] = true;
        }

        if (Schema::hasColumn($table, 'joined_at')) {
            $values['joined_at'] = now();
        }

        if (Schema::hasColumn($table, 'left_at')) {
            $values['left_at'] = null;
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $values['updated_at'] = now();
        }

        if ($existing) {
            DB::table($table)
                ->where('id', $existing->id)
                ->update($values);

            return;
        }

        $values['organization_id'] = $organizationId;
        $values['user_id'] = $userId;

        if (Schema::hasColumn($table, 'created_at')) {
            $values['created_at'] = now();
        }

        DB::table($table)->insert($values);
    }

    public function updateMemberRole(
        int $organizationId,
        int $membershipId,
        string $role
    ): void {
        $table = $this->memberTable();

        if (! $table || ! Schema::hasColumn($table, 'role')) {
            throw new RuntimeException(
                'The organisation membership role field is unavailable.'
            );
        }

        DB::table($table)
            ->where('organization_id', $organizationId)
            ->where('id', $membershipId)
            ->update([
                'role' => $role,
                ...(
                    Schema::hasColumn($table, 'updated_at')
                    ? ['updated_at' => now()]
                    : []
                ),
            ]);
    }

    public function setMemberActive(
        int $organizationId,
        int $membershipId,
        bool $active
    ): void {
        $table = $this->memberTable();

        if (! $table) {
            throw new RuntimeException(
                'The organisation membership table is unavailable.'
            );
        }

        $values = [];

        if (Schema::hasColumn($table, 'status')) {
            $values['status'] = $active ? 'active' : 'suspended';
        }

        if (Schema::hasColumn($table, 'is_active')) {
            $values['is_active'] = $active;
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $values['updated_at'] = now();
        }

        if (! $values) {
            throw new RuntimeException(
                'The organisation membership status fields are unavailable.'
            );
        }

        DB::table($table)
            ->where('organization_id', $organizationId)
            ->where('id', $membershipId)
            ->update($values);
    }

    public function removeMember(
        int $organizationId,
        int $membershipId
    ): void {
        $table = $this->memberTable();

        if (! $table) {
            throw new RuntimeException(
                'The organisation membership table is unavailable.'
            );
        }

        $values = [];

        if (Schema::hasColumn($table, 'status')) {
            $values['status'] = 'removed';
        }

        if (Schema::hasColumn($table, 'is_active')) {
            $values['is_active'] = false;
        }

        if (Schema::hasColumn($table, 'left_at')) {
            $values['left_at'] = now();
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $values['updated_at'] = now();
        }

        if ($values) {
            DB::table($table)
                ->where('organization_id', $organizationId)
                ->where('id', $membershipId)
                ->update($values);

            return;
        }

        DB::table($table)
            ->where('organization_id', $organizationId)
            ->where('id', $membershipId)
            ->delete();
    }

    public function log(
        int $organizationId,
        ?int $actorUserId,
        string $event,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = []
    ): void {
        try {
            OrganizationWorkspaceActivity::create([
                'organization_id' => $organizationId,
                'actor_user_id' => $actorUserId,
                'event' => $event,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'metadata' => $metadata ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Member management must not fail if audit storage is unavailable.
        }
    }
}

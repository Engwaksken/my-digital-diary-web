<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OrganizationMembershipLifecycleService
{
    public function removeMember(User $user, int $organizationId): void
    {
        DB::transaction(function () use ($user, $organizationId): void {
            if (Schema::hasTable('organization_members')) {
                DB::table('organization_members')
                    ->where('organization_id', $organizationId)
                    ->where('user_id', $user->id)
                    ->delete();
            }

            $updates = [];

            if (Schema::hasColumn('users', 'organization_id')) {
                $updates['organization_id'] = null;
            }
            if (Schema::hasColumn('users', 'account_mode')) {
                $updates['account_mode'] = 'unassigned';
            }
            if (Schema::hasColumn('users', 'workspace_access_status')) {
                $updates['workspace_access_status'] = 'removed_from_workspace';
            }
            if (Schema::hasColumn('users', 'subscription_plan_id')) {
                $updates['subscription_plan_id'] = null;
            }

            if ($updates !== []) {
                DB::table('users')->where('id', $user->id)->update($updates);
            }
        });
    }

    public function attachToOrganization(User $user, int $organizationId, string $role = 'member'): void
    {
        DB::transaction(function () use ($user, $organizationId, $role): void {
            if (Schema::hasTable('organization_members')) {
                DB::table('organization_members')->updateOrInsert(
                    ['organization_id' => $organizationId, 'user_id' => $user->id],
                    ['role' => $role, 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]
                );
            }

            $updates = [];
            if (Schema::hasColumn('users', 'organization_id')) {
                $updates['organization_id'] = $organizationId;
            }
            if (Schema::hasColumn('users', 'account_mode')) {
                $updates['account_mode'] = 'managed';
            }
            if (Schema::hasColumn('users', 'workspace_access_status')) {
                $updates['workspace_access_status'] = 'active';
            }
            if (Schema::hasColumn('users', 'subscription_plan_id')) {
                $updates['subscription_plan_id'] = null;
            }

            if ($updates !== []) {
                DB::table('users')->where('id', $user->id)->update($updates);
            }
        });
    }

    public function activateIndependent(User $user): void
    {
        $updates = [];

        if (Schema::hasColumn('users', 'organization_id')) {
            $updates['organization_id'] = null;
        }
        if (Schema::hasColumn('users', 'account_mode')) {
            $updates['account_mode'] = 'independent';
        }
        if (Schema::hasColumn('users', 'workspace_access_status')) {
            $updates['workspace_access_status'] = 'independent';
        }

        if ($updates !== []) {
            DB::table('users')->where('id', $user->id)->update($updates);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop-in helper used by OrganizationMembershipService.
 *
 * The key rule is:
 * - owner owns the paid plan;
 * - member gets organization_id + organization_role;
 * - member does NOT receive a separate subscription_plan_id.
 */
final class OrganizationMemberHierarchyWriter
{
    public function attach(
        Organization $organization,
        OrganizationMember $membership,
        User $user
    ): void {
        DB::transaction(function () use (
            $organization,
            $membership,
            $user
        ): void {
            $membership->forceFill([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ])->save();

            $values = [];

            if (Schema::hasColumn('users', 'organization_id')) {
                $values['organization_id'] =
                    $organization->id;
            }

            if (Schema::hasColumn('users', 'organization_role')) {
                $values['organization_role'] =
                    $membership->role ?: 'member';
            }

            /*
             * Do NOT copy subscription_plan_id / subscription_status from
             * the owner to the database row. Subscription ownership remains
             * on the owner only.
             */
            if ($values !== []) {
                $user->forceFill($values)->save();
            }

            $owner = User::find($organization->owner_user_id);

            if ($owner) {
                $ownerValues = [];

                if (Schema::hasColumn('users', 'organization_id')) {
                    $ownerValues['organization_id'] =
                        $organization->id;
                }

                if (Schema::hasColumn('users', 'organization_role')) {
                    $ownerValues['organization_role'] = 'owner';
                }

                if ($ownerValues !== []) {
                    $owner->forceFill($ownerValues)->save();
                }
            }
        });
    }
}

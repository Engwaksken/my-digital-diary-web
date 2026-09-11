<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrganizationAccessController extends Controller
{
    public function __invoke(
        Request $request,
        OrganizationAccessService $access
    ): JsonResponse {
        $user = $request->user();

        $access->syncUserHierarchy($user);

        $organization = $access->organizationFor($user);
        $owner = $access->ownerFor($user);
        $managed = $access->isManagedMember($user);

        return response()->json([
            'data' => [
                'managed_by_owner' => $managed,
                'is_workspace_owner' => $access->isOwner($user),
                'has_active_access' =>
                    $access->hasEffectiveAccess($user),
                'organization_id' => $organization?->id,
                'organization_name' => $organization?->name,
                'organization_role' =>
                    $access->effectiveRole($user),
                'owner_user_id' => $owner?->id,
                'owner_name' => $owner?->name,
                'subscription_owner_user_id' =>
                    $managed ? $owner?->id : $user->id,
            ],
        ]);
    }
}

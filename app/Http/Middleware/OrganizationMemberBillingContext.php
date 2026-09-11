<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\OrganizationAccessService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OrganizationMemberBillingContext
{
    public function __construct(
        private readonly OrganizationAccessService $access
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $this->access->syncUserHierarchy($user);

        if (! $this->access->isManagedMember($user)) {
            return $next($request);
        }

        $owner = $this->access->ownerFor($user);
        $organization = $this->access->organizationFor($user);

        if (
            $request->isMethod('GET')
            && ! $request->is('api/*')
        ) {
            return redirect()
                ->route('dashboard')
                ->with(
                    'info',
                    'Your subscription is managed by the workspace owner. You do not need a separate subscription.'
                );
        }

        /*
         * Prevent organisation members from starting/cancelling/retrying
         * subscription payments against their own account.
         */
        if (! $request->isMethod('GET')) {
            if (
                $request->expectsJson()
                || $request->is('api/*')
            ) {
                return response()->json([
                    'message' =>
                        'Your subscription is managed by the workspace owner.',
                    'managed_by_owner' => true,
                    'organization' => $organization?->name,
                    'owner_name' => $owner?->name,
                ], 403);
            }

            return redirect()
                ->route('dashboard')
                ->with(
                    'error',
                    'Billing is managed by the workspace owner.'
                );
        }

        $response = $next($request);

        /*
         * Mobile GET /api/subscription/status can still render status, but
         * it receives explicit flags telling it NOT to show purchase UI.
         */
        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);

            if (! is_array($payload)) {
                $payload = [];
            }

            $payload['managed_by_owner'] = true;
            $payload['subscription_required'] = false;
            $payload['organization'] = $organization?->name;
            $payload['organization_id'] = $organization?->id;
            $payload['organization_role'] =
                $this->access->effectiveRole($user);
            $payload['owner_name'] = $owner?->name;
            $payload['owner_user_id'] = $owner?->id;
            $payload['has_active_access'] =
                $owner
                    ? $this->access->ownerHasActiveAccess($owner)
                    : false;

            $response->setData($payload);
        }

        return $response;
    }
}

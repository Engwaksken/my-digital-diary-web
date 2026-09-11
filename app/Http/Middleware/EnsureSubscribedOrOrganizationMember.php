<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\OrganizationAccessService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSubscribedOrOrganizationMember
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
            abort(401);
        }

        /*
         * Authoritative hierarchy is Organization/OrganizationMember.
         * Repair the convenience columns if an older member account still
         * has organization_id / organization_role as NULL.
         */
        $this->access->syncUserHierarchy($user);

        if (! $this->access->hasEffectiveAccess($user)) {
            $message = $this->access->isManagedMember($user)
                ? 'Your workspace owner’s subscription is not currently active. Please contact the workspace owner.'
                : 'An active subscription is required to continue.';

            if (
                $request->expectsJson()
                || $request->is('api/*')
            ) {
                return response()->json([
                    'message' => $message,
                    'subscription_required' =>
                        ! $this->access->isManagedMember($user),
                    'managed_by_owner' =>
                        $this->access->isManagedMember($user),
                ], 403);
            }

            if ($this->access->isManagedMember($user)) {
                return redirect()
                    ->route('dashboard')
                    ->with('error', $message);
            }

            return redirect()
                ->route('subscription.show')
                ->with('error', $message);
        }

        /*
         * Controllers that still read the logged-in user's legacy
         * subscription fields will see the owner's plan for this request.
         * No billing record is created for the member.
         */
        $this->access->applyInheritedSubscriptionContext(
            $user
        );

        return $next($request);
    }
}

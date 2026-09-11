<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\OrganizationMember;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class LockOrganizationMemberEmail
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $hasManagedMembership = OrganizationMember::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'invited'])
            ->exists();

        if (! $hasManagedMembership) {
            return $next($request);
        }

        if (
            $request->exists('email')
            && strtolower(trim((string) $request->input('email')))
                !== strtolower((string) $user->email)
        ) {
            throw ValidationException::withMessages([
                'email' =>
                    'Your workspace email is locked while you belong to an organisation. Contact the workspace owner if the membership email needs to be corrected.',
            ]);
        }

        /*
         * Make the existing address authoritative even if a generic profile
         * request sends a stale or manipulated email value.
         */
        if ($request->exists('email')) {
            $request->merge([
                'email' => $user->email,
            ]);
        }

        return $next($request);
    }
}

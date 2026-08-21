<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSupportStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! in_array((string) $user->role, ['admin', 'super_admin', 'support'], true)) {
            abort(403);
        }

        return $next($request);
    }
}

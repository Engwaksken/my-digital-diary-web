<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ForceCanonicalDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $canonicalHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $canonicalScheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        if (! $canonicalHost) {
            return $next($request);
        }

        if (
            strcasecmp($request->getHost(), $canonicalHost) !== 0
            || ($canonicalScheme === 'https' && ! $request->isSecure())
        ) {
            $target = $canonicalScheme.'://'.$canonicalHost.$request->getRequestUri();

            // 302 is intentional. Do not replay a POST body across hosts; forms
            // should always be rendered on the canonical host before submit.
            return redirect()->away($target, 302);
        }

        return $next($request);
    }
}

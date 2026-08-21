<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InjectSupportWidget
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        // Only alter successful HTML view responses for signed-in users.
        // Redirects, JSON/API responses, downloads, PDFs and streams are
        // intentionally left untouched.
        if (! auth()->check()
            || ! $response instanceof Response
            || $response->getStatusCode() !== 200
            || ! str_contains(strtolower((string) $response->headers->get('content-type')), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content)
            || ! str_contains(strtolower($content), '</body>')
            || str_contains($content, 'id="pm-support-widget"')) {
            return $response;
        }

        try {
            $widget = view('partials.support-chat-widget')->render();
            $response->setContent(preg_replace('/<\/body>/i', $widget . "\n</body>", $content, 1) ?? $content);
        } catch (\Throwable $e) {
            // A support widget must never take down the application.
            report($e);
        }

        return $response;
    }
}

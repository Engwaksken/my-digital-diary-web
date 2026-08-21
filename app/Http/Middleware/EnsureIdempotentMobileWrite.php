<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotentMobileWrite
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $user = $request->user();
        $key = trim((string) $request->header('X-Idempotency-Key', ''));

        // Old clients do not send a key. Keep them fully compatible.
        if (! $user || $key === '' || ! Schema::hasTable('mobile_sync_requests')) {
            return $next($request);
        }

        if (strlen($key) > 100) {
            return response()->json(['message' => 'Invalid idempotency key.'], 422);
        }

        $existing = DB::table('mobile_sync_requests')
            ->where('user_id', $user->id)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing && $existing->completed_at) {
            $payload = json_decode((string) $existing->response_body, true);
            return response()->json(
                is_array($payload) ? $payload : ['data' => $payload],
                (int) $existing->response_status,
                ['X-Idempotency-Replayed' => '1']
            );
        }

        $requestId = null;
        if (! $existing) {
            try {
                $requestId = DB::table('mobile_sync_requests')->insertGetId([
                    'user_id' => $user->id,
                    'idempotency_key' => $key,
                    'method' => strtoupper($request->method()),
                    'path' => '/'.$request->path(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // A concurrent retry may have won the unique-key race.
                $existing = DB::table('mobile_sync_requests')
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $key)
                    ->first();
                if ($existing && $existing->completed_at) {
                    $payload = json_decode((string) $existing->response_body, true);
                    return response()->json(
                        is_array($payload) ? $payload : ['data' => $payload],
                        (int) $existing->response_status,
                        ['X-Idempotency-Replayed' => '1']
                    );
                }
            }
        } else {
            $requestId = $existing->id;
        }

        $response = $next($request);

        // Cache only successful application responses. Validation/auth failures
        // should be re-evaluated after the user corrects the request/session.
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 400) {
            DB::table('mobile_sync_requests')
                ->where('id', $requestId)
                ->update([
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $response->getContent(),
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
        } elseif ($requestId) {
            DB::table('mobile_sync_requests')->where('id', $requestId)->delete();
        }

        return $response;
    }
}

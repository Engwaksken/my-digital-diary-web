<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Conservative conflict detection for mobile edits replayed after a period
 * offline. Online requests do not send X-Offline-Base-Updated-At and are
 * therefore unaffected. If the server copy changed after the timestamp the
 * mobile device last saw, the replay is stopped with HTTP 409 instead of
 * silently overwriting newer data from another device/web session.
 */
class OfflineConflictGuard
{
    public static function check(Request $request, Model $model): ?JsonResponse
    {
        $raw = trim((string) $request->header('X-Offline-Base-Updated-At', ''));
        if ($raw === '' || ! $model->usesTimestamps() || ! $model->updated_at) {
            return null;
        }

        try {
            $base = CarbonImmutable::parse($raw)->utc();
            $server = CarbonImmutable::parse($model->updated_at)->utc();
        } catch (\Throwable) {
            return null;
        }

        // Database timestamp precision can be lower than Dart's ISO string;
        // a one-second tolerance prevents a false conflict on the same write.
        if ($server->greaterThan($base->addSecond())) {
            return response()->json([
                'message' => 'This record changed on another device while you were offline. Review the latest version before retrying your change.',
                'conflict' => true,
                'server' => $model->fresh(),
            ], 409);
        }

        return null;
    }
}

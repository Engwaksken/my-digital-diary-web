<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncStatusController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $timezone = $user->timezone ?: 'Africa/Kampala';

        $lastWrite = Schema::hasTable('mobile_sync_requests')
            ? DB::table('mobile_sync_requests')
                ->where('user_id', $user->id)
                ->whereNotNull('completed_at')
                ->max('completed_at')
            : null;

        return response()->json([
            'online' => true,
            'server_time' => now($timezone)->toIso8601String(),
            'timezone' => $timezone,
            'last_mobile_write_at' => $lastWrite,
            'offline_sync' => [
                'modules' => ['daily-planner', 'notes', 'project-tasks', 'expenses'],
                'conflict_policy' => 'server-newer-requires-review',
                'idempotent_replay' => true,
            ],
        ]);
    }
}

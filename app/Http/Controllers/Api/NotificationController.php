<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reads from Laravel's built-in notifications table (User already uses
 * the Notifiable trait) — the same notifications already being created
 * elsewhere (subscription expiry reminders, etc.) show up here, rather
 * than needing a second, separate mobile-only notification concept.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->limit(30)->get();

        return response()->json(['data' => $notifications->map(fn ($n) => [
            'id' => $n->id,
            'type' => class_basename($n->type),
            'data' => $n->data,
            'read' => ! is_null($n->read_at),
            'created_at' => $n->created_at->toIso8601String(),
        ])]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        $notification?->markAsRead();

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}

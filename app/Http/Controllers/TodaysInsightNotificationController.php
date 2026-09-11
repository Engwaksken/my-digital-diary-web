<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Notifications\TodaysInsightNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TodaysInsightNotificationController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $notification = $request->user()
            ->unreadNotifications()
            ->where('type', TodaysInsightNotification::class)
            ->latest()
            ->first();

        return response()->json([
            'data' => $notification ? [
                'notification_id' => $notification->id,
                'title' => $notification->data['title'] ?? "Today's Insight",
                'message' => $notification->data['message'] ?? '',
                'url' => $notification->data['url'] ?? route('dashboard'),
                'show_popup' => true,
                'created_at' => $notification->created_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $row = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $row->markAsRead();

        return response()->json([
            'message' => 'Insight notification marked as read.',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\TodaysInsightNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TodaysInsightNotificationService
{
    public function notifyIfChanged(User $user, object|array $insight): bool
    {
        $id = is_array($insight) ? ($insight['id'] ?? null) : ($insight->id ?? null);
        $title = trim((string) (is_array($insight) ? ($insight['title'] ?? $insight['heading'] ?? "Today's Insight") : ($insight->title ?? $insight->heading ?? "Today's Insight")));
        $message = trim((string) (is_array($insight) ? ($insight['message'] ?? $insight['insight'] ?? $insight['content'] ?? $insight['body'] ?? '') : ($insight->message ?? $insight->insight ?? $insight->content ?? $insight->body ?? '')));

        if ($message === '') {
            return false;
        }

        $fingerprint = hash('sha256', mb_strtolower($title).'|'.mb_strtolower($message));

        if (Schema::hasTable('todays_insight_notification_states')) {
            $same = DB::table('todays_insight_notification_states')
                ->where('user_id', $user->id)
                ->where('fingerprint', $fingerprint)
                ->exists();

            if ($same) {
                return false;
            }
        }

        $user->notify(new TodaysInsightNotification(
            insightId: $id,
            title: $title,
            message: $message,
            fingerprint: $fingerprint,
            actionUrl: route('dashboard').'#todays-insight',
        ));

        if (Schema::hasTable('todays_insight_notification_states')) {
            DB::table('todays_insight_notification_states')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'insight_id' => $id !== null ? (string) $id : null,
                    'fingerprint' => $fingerprint,
                    'notified_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return true;
    }

    public function notify(User $user, object|array $insight): bool
    {
        return $this->notifyIfChanged($user, $insight);
    }
}

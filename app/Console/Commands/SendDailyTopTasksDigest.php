<?php

namespace App\Console\Commands;

use App\Models\DailyPlan;
use App\Models\ProjectTask;
use App\Models\User;
use App\Notifications\DailyTopTasksNotification;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends each eligible user's Top 3 for TODAY every day at 08:00.
 * The scheduler entry already lives in routes/console.php:
 *   Schedule::command('digest:daily-top-tasks')->dailyAt('08:00');
 *
 * Today's Daily Planner is the primary source. If it has fewer than three
 * open items, open Project Tasks due today are used to fill the remaining
 * places. Delivery is synchronous so this job does not depend on a queue
 * worker, and the same Top 3 is also sent through FCM for installed apps.
 */
class SendDailyTopTasksDigest extends Command
{
    protected $signature = 'digest:daily-top-tasks';

    protected $description = 'Send each opted-in user their top 3 items for today at 8am';

    public function handle(FcmService $fcm): int
    {
        $users = User::where('daily_digest_enabled', true)->get();

        foreach ($users as $user) {
            if (! $user->hasActiveAccess()) { continue; }
            $tz = $user->timezone ?: 'Africa/Kampala';
            $localNow = now($tz);
            if ((int) $localNow->format('G') !== 8 || (int) $localNow->format('i') >= 30) { continue; }
            $date = $localNow->toDateString();
            $cacheKey = "daily-top3:{$user->id}:{$date}";
            if (Cache::has($cacheKey)) { continue; }

            $tasks = $this->topTasksFor($user, $date);
            if ($tasks === []) {
                continue;
            }

            try {
                // sendNow bypasses ShouldQueue on the notification and makes
                // the 08:00 scheduler the actual delivery point.
                Notification::sendNow($user, new DailyTopTasksNotification($tasks));
            } catch (Throwable $e) {
                Log::warning('Could not send daily Top 3 email/database notification.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $body = collect($tasks)
                    ->values()
                    ->map(fn (array $task, int $index) => ($index + 1).'. '.$task['title'])
                    ->implode(' • ');

                $fcm->sendToUser(
                    $user,
                    'Your Top '.count($tasks).' for today',
                    $body,
                    ['type' => 'daily_top_tasks', 'date' => $date]
                );
            } catch (Throwable $e) {
                Log::warning('Could not send daily Top 3 push notification.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            Cache::put($cacheKey, true, now()->addDays(2));
            $this->info("Sent daily Top 3 to {$user->email}");
        }

        return self::SUCCESS;
    }

    /** @return array<int, array{title:string,source:string}> */
    private function topTasksFor(User $user, string $date): array
    {
        $items = collect();

        // 1) Today's Daily Planner items first.
        $plan = DailyPlan::query()
            ->where('user_id', $user->id)
            ->whereDate('plan_date', $date)
            ->with(['items' => function ($query) {
                $query->where('is_completed', false)
                    ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                    ->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('start_time')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->first();

        if ($plan) {
            foreach ($plan->items->take(3) as $item) {
                $items->push(['title' => $item->title, 'source' => 'Daily Planner']);
            }
        }

        // 2) Fill any remaining slots with Project Tasks explicitly due today.
        if ($items->count() < 3) {
            ProjectTask::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['todo', 'in_progress'])
                ->whereDate('due_date', $date)
                ->orderBy('due_date')
                ->orderBy('id')
                ->limit(3 - $items->count())
                ->get(['title'])
                ->each(fn ($task) => $items->push([
                    'title' => $task->title,
                    'source' => 'Task',
                ]));
        }

        return $items->take(3)->values()->all();
    }
}

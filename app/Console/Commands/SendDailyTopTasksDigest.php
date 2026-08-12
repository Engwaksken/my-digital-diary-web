<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\ProjectTask;
use App\Models\User;
use App\Notifications\DailyTopTasksNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Runs daily at 8am (see routes/console.php) — emails every eligible user
 * their top 3 open items for the day, combining Plans (daily-period,
 * pending/in_progress) and open Project Tasks. "Top" just means earliest
 * due first; no more elaborate priority scoring than that for now.
 */
class SendDailyTopTasksDigest extends Command
{
    protected $signature = 'digest:daily-top-tasks';

    protected $description = "Email each opted-in user their top 3 tasks for the day";

    public function handle(): int
    {
        $users = User::where('daily_digest_enabled', true)->get();

        foreach ($users as $user) {
            if (! $user->hasActiveAccess()) {
                continue;
            }

            $tasks = $this->topTasksFor($user);

            if (empty($tasks)) {
                continue; // nothing to say today — skip rather than sending an empty digest
            }

            // Same per-user try/catch pattern as SendReminders — one
            // user's email failing (e.g. mail server rejection) shouldn't
            // abort the digest for everyone else in this run.
            try {
                Notification::send($user, new DailyTopTasksNotification($tasks));
                $this->info("Sent daily digest to {$user->email}");
            } catch (Throwable $e) {
                Log::warning('Could not send daily top-tasks digest.', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{title: string, source: string}>
     */
    private function topTasksFor(User $user): array
    {
        $plans = Plan::where('user_id', $user->id)
            ->where('period', 'daily')
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('target_date')
            ->limit(3)
            ->get()
            ->map(fn ($p) => ['title' => $p->title, 'source' => 'Plan', 'sort' => $p->target_date]);

        $tasks = ProjectTask::where('user_id', $user->id)
            ->whereIn('status', ['todo', 'in_progress'])
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(fn ($t) => ['title' => $t->title, 'source' => 'Task', 'sort' => $t->created_at]);

        return $plans->concat($tasks)
            ->sortBy('sort')
            ->take(3)
            ->map(fn ($item) => ['title' => $item['title'], 'source' => $item['source']])
            ->values()
            ->all();
    }
}

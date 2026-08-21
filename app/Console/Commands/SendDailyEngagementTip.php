<?php
namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SimpleDatabaseNotification;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendDailyEngagementTip extends Command
{
    protected $signature = 'engagement:daily-tip';
    protected $description = 'Send one rotating, non-repetitive daily wellbeing/productivity tip in the user timezone.';

    private array $tips = [
        ['Budgeting tip', 'Review one expense today and ask whether it supported something you value.', 'expenses'],
        ['Savings tip', 'A small contribution still moves a goal forward. Record what you saved today.', 'savings'],
        ['Health tip', 'Make space for water, movement and enough rest today; consistency matters more than intensity.', 'health'],
        ['Productivity tip', 'Choose one important task, finish it, then record what helped you make progress.', 'productivity'],
        ['Financial planning tip', 'Check one upcoming obligation now so it does not become tomorrow’s surprise.', 'financial_planning'],
        ['Spiritual growth', 'Take a few quiet minutes to reflect on today’s lesson, scripture or gratitude.', 'spiritual_growth'],
        ['Diet tip', 'Plan your next meal before you are very hungry; intentional choices are easier than rushed ones.', 'diet'],
    ];

    public function handle(FcmService $fcm): int
    {
        User::query()->whereNull('suspended_at')->chunkById(100, function ($users) use ($fcm) {
            foreach ($users as $user) {
                if (! $user->hasActiveAccess()) continue;
                $tz = $user->timezone ?: 'Africa/Kampala';
                $now = now($tz);
                // One controlled engagement touch at 1 PM local time.
                if ((int) $now->format('G') !== 13 || (int) $now->format('i') >= 30) continue;
                $date = $now->toDateString();
                $key = "daily-tip:{$user->id}:{$date}";
                if (Cache::has($key)) continue;
                $index = ((int) $now->format('z') + (int) $user->id) % count($this->tips);
                [$title, $body, $type] = $this->tips[$index];
                $user->notify(new SimpleDatabaseNotification($title, $body, "daily_tip_{$type}"));
                $fcm->sendToUser($user, $title, $body, ['type' => "daily_tip_{$type}"]);
                Cache::put($key, true, now()->addDays(2));
            }
        });
        return self::SUCCESS;
    }
}

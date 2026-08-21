<?php
namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SimpleDatabaseNotification;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendEndOfDayDigest extends Command
{
    protected $signature = 'digest:end-of-day';
    protected $description = 'Send each user an end-of-day activity summary at 8:30 PM in their configured timezone.';

    public function handle(FcmService $fcm): int
    {
        User::query()->whereNull('suspended_at')->chunkById(100, function ($users) use ($fcm) {
            foreach ($users as $user) {
                if (! $user->hasActiveAccess()) continue;
                $tz = $user->timezone ?: 'Africa/Kampala';
                $localNow = now($tz);
                if ((int) $localNow->format('G') !== 20 || (int) $localNow->format('i') < 30) continue;
                $date = $localNow->toDateString();
                $key = "end-of-day:{$user->id}:{$date}";
                if (Cache::has($key)) continue;

                $plan = \App\Models\DailyPlan::where('user_id', $user->id)->whereDate('plan_date', $date)->with('items')->first();
                $completed = $plan?->items->where('is_completed', true)->count() ?? 0;
                $pending = $plan?->items->where('is_completed', false)->count() ?? 0;
                $expenses = (float) \App\Models\Expense::where('user_id', $user->id)->whereDate('spent_at', $date)->sum('amount');
                $savings = (float) \App\Models\SavingsContribution::where('user_id', $user->id)->whereDate('contributed_at', $date)->sum('amount');
                $exercise = \App\Models\ExerciseLog::where('user_id', $user->id)->whereDate('performed_at', $date)->count();
                $spiritual = \App\Models\SpiritualPractice::where('user_id', $user->id)->whereDate('practiced_at', $date)->count();

                $body = "Completed {$completed}; pending {$pending}; expenses ".format_money($expenses)."; savings ".format_money($savings)."; exercise {$exercise}; spiritual entries {$spiritual}.";
                $user->notify(new SimpleDatabaseNotification('Your end-of-day summary', $body, 'end_of_day_summary'));
                $fcm->sendToUser($user, 'Your end-of-day summary', $body, ['type' => 'end_of_day_summary', 'date' => $date]);
                Cache::put($key, true, now()->addDays(2));
            }
        });
        return self::SUCCESS;
    }
}

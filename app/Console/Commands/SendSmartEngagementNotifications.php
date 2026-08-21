<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SimpleDatabaseNotification;
use App\Services\GoalIntelligenceService;
use App\Services\MonthlyReviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendSmartEngagementNotifications extends Command
{
    protected $signature = 'engagement:send-smart-nudges';
    protected $description = 'Send rate-limited goal, monthly-review and next-action nudges using user preferences';

    public function handle(GoalIntelligenceService $goals, MonthlyReviewService $monthly): int
    {
        User::query()->whereNull('suspended_at')->chunkById(100, function ($users) use ($goals, $monthly) {
            foreach ($users as $user) {
                $tz = $user->timezone ?: 'Africa/Kampala';
                $now = now($tz);

                // Monthly review: once, on days 1-3 of the month, during the evening window.
                if ($user->engagementNotificationEnabled('monthly_review') && $now->day <= 3 && $now->hour === 19) {
                    $key = 'monthly-review-'.$now->format('Y-m');
                    if (! $this->recentlySent($user, $key, 31)) {
                        $review = $monthly->forUser($user);
                        $user->notify(new SimpleDatabaseNotification('Your Month in Review is ready', 'See how your money, tasks and progress changed last month, then choose your next three actions.', $key));
                    }
                }

                // Missed milestones and weekly goal check-ins.
                if ($user->engagementNotificationEnabled('goal_progress') && $now->hour === 18) {
                    $activeGoals = \App\Models\PersonalGoal::where('user_id',$user->id)->where('is_archived',false)->whereIn('status',['not_started','in_progress'])->with('milestones')->get();
                    $progressService = app(\App\Services\GoalProgressService::class);
                    foreach ($activeGoals as $goal) {
                        $acc = $progressService->accountability($goal,$user);
                        if (($acc['overdue_count'] ?? 0) > 0) {
                            $key = 'missed-milestone-'.$goal->id.'-'.$now->format('Y-m-d');
                            if (! $this->recentlySent($user,$key,1)) {
                                $user->notify(new SimpleDatabaseNotification('A milestone needs rescheduling', $goal->title.' has '.($acc['overdue_count']).' missed checkpoint'.(($acc['overdue_count'])===1?'':'s').'. Choose a realistic new date instead of letting the goal drift.', $key));
                            }
                        }
                        if ((int)$now->dayOfWeek === 0 && empty($acc['checkin'])) {
                            $key = 'goal-checkin-'.$goal->id.'-'.$now->copy()->startOfWeek()->format('Y-m-d');
                            if (! $this->recentlySent($user,$key,7)) {
                                $user->notify(new SimpleDatabaseNotification('Weekly goal check-in', 'Review “'.$goal->title.'” and choose one action you will complete this week.', $key));
                            }
                        }
                    }

                    // Goal nudge: at most once every 24 hours and only when there is an actual priority item.
                    $data = $goals->build($user);
                    $next = collect($data['next_actions'] ?? [])->first();
                    if ($next && ! $this->recentlySent($user, 'goal-progress', 1)) {
                        $title = data_get($next, 'title', 'A goal needs attention');
                        $body = data_get($next, 'message', 'Open Goals & Next Actions to review your next step.');
                        $user->notify(new SimpleDatabaseNotification($title, $body, 'goal-progress'));
                    }
                }
            }
        });

        return self::SUCCESS;
    }

    private function recentlySent(User $user, string $type, int $days): bool
    {
        return $user->notifications()
            ->where('created_at', '>=', now()->subDays($days))
            ->where('data->type', $type)
            ->exists();
    }
}

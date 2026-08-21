<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PersonalGoal;
use App\Models\DailyWellbeingLog;
use App\Models\ProjectTask;
use App\Models\Reminder;
use App\Models\SavingsContribution;
use App\Models\SpiritualPractice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DailyInsightService
{
    public function current(User $user, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $timezone = $user->timezone ?: config('app.timezone', 'Africa/Kampala');
        $local = $now->copy()->setTimezone($timezone);
        $bucketHour = intdiv((int) $local->format('H'), 2) * 2;
        $window = $local->format('Y-m-d') . '-' . str_pad((string) $bucketHour, 2, '0', STR_PAD_LEFT);
        $key = "daily-insight:{$user->id}:{$window}";

        return Cache::remember($key, now()->addHours(2)->addMinutes(5), function () use ($user, $local) {
            return $this->build($user, $local);
        });
    }

    private function build(User $user, Carbon $now): array
    {
        $userId = $user->id;
        $hour = (int) $now->format('H');
        $daypart = match (true) {
            $hour < 5 => 'night',
            $hour < 10 => 'morning',
            $hour < 13 => 'late_morning',
            $hour < 17 => 'afternoon',
            $hour < 20 => 'evening',
            default => 'night',
        };

        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $income = (float) Income::where('user_id', $userId)->whereBetween('received_at', [$monthStart, $monthEnd])->sum('amount');
        $expenses = (float) Expense::where('user_id', $userId)->whereBetween('spent_at', [$monthStart, $monthEnd])->sum('amount');
        $budget = (float) Budget::where('user_id', $userId)->where('period', 'monthly')->sum('amount');
        $saved = (float) SavingsContribution::where('user_id', $userId)->whereBetween('contributed_at', [$monthStart, $monthEnd])->sum('amount');
        $overdueTasks = ProjectTask::where('user_id', $userId)
            ->whereIn('status', ['todo', 'in_progress'])
            ->whereDate('due_date', '<', $now->toDateString())
            ->count();
        $todayReminders = Reminder::where('user_id', $userId)
            ->where('is_active', true)
            ->whereDate('next_run_at', $now->toDateString())
            ->count();

        $candidates = [];

        $todayWellbeing = DailyWellbeingLog::where('user_id', $userId)
            ->whereDate('log_date', $now->toDateString())->first();
        if ($todayWellbeing) {
            $target = max(1, (int) $todayWellbeing->water_target_ml);
            $waterPercent = (int) min(100, round(((int) $todayWellbeing->water_ml / $target) * 100));
            if ($waterPercent < 60 && (int) $now->format('H') >= 12) {
                $candidates[] = $this->item('Self-care', 'fa-droplet', 'sky', 'Give your body a simple reset.',
                    "You are at {$waterPercent}% of the water target you set for today. Have a glass of water now, then keep the rest of the target realistic for your day.",
                    'Open Wellbeing', 'wellbeing.index');
            }
            if (! $todayWellbeing->self_care_done && (int) $now->format('H') >= 15) {
                $candidates[] = $this->item('Self-care', 'fa-spa', 'rose', 'Make room for one small act of care.',
                    'Self-care does not need to be complicated. Choose one restorative thing you can genuinely do today — rest, stretch, walk, read, pray, or step away from screens.',
                    'Open Wellbeing', 'wellbeing.index');
            }
        }

        $priorityGoal = PersonalGoal::where('user_id', $userId)->where('is_archived', false)
            ->whereIn('status', ['not_started','in_progress'])
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderBy('target_date')->first();
        if ($priorityGoal) {
            $candidates[] = $this->item('Daily Affirmation', 'fa-sparkles', 'violet', 'Small actions are still progress.',
                "Your goal “{$priorityGoal->title}” does not need one perfect day. One meaningful step today is enough to keep momentum moving.",
                'Review Goals', 'personal-goals.index');
        }

        if ($income > 0 && $expenses > $income * .8) {
            $candidates[] = $this->item('Financial Wellness', 'fa-wallet', 'emerald', 'Protect what is left this month.',
                'Your recorded expenses are using a large share of this month’s income. Review non-essential spending before adding another purchase.',
                'Review Expenses', 'expenses.index');
        }
        if ($budget > 0 && $expenses > $budget) {
            $candidates[] = $this->item('Budget', 'fa-chart-pie', 'amber', 'Your spending needs a quick budget check.',
                'Recorded expenses are above your monthly budget. Review the largest categories and decide what can be reduced for the rest of the month.',
                'Review Budget', 'budgets.index');
        }
        if ($saved <= 0 && $income > 0) {
            $candidates[] = $this->item('Savings', 'fa-piggy-bank', 'sky', 'Give a small amount to your future self.',
                'You have recorded income this month but no savings contribution yet. Even a small planned transfer can keep your savings habit active.',
                'Open Savings', 'savings-goals.index');
        }
        if ($overdueTasks > 0) {
            $candidates[] = $this->item('Productivity', 'fa-list-check', 'amber', 'Clear one overdue item before adding more.',
                "You have {$overdueTasks} overdue task" . ($overdueTasks === 1 ? '' : 's') . '. Pick the most important one and give it a realistic time slot today.',
                'Open Tasks', 'project-tasks.index');
        }
        if ($todayReminders > 0) {
            $candidates[] = $this->item('Planning', 'fa-bell', 'violet', 'Use your reminders as today’s checkpoints.',
                "You have {$todayReminders} active reminder" . ($todayReminders === 1 ? '' : 's') . ' scheduled for today. Review them early so they support your day instead of interrupting it.',
                'Review Reminders', 'reminders.index');
        }

        // Spiritual Growth belongs inside Today's Insight rather than occupying
        // a separate dashboard card. When the user has a recent reflection,
        // turn that real entry into a useful, gentle prompt; otherwise keep a
        // generic reflection option in the rotation.
        $latestSpiritual = SpiritualPractice::where('user_id', $userId)
            ->where('is_archived', false)
            ->latest('practiced_at')
            ->latest('id')
            ->first();

        if ($latestSpiritual) {
            $topic = trim((string) ($latestSpiritual->theme_topic ?: $latestSpiritual->title ?: $latestSpiritual->practice_type ?: 'your recent reflection'));
            $scripture = trim((string) ($latestSpiritual->scriptures ?? ''));
            $lesson = trim((string) ($latestSpiritual->lessons_learnt ?: $latestSpiritual->reflection ?: ''));
            $messageParts = ["Your recent spiritual reflection focused on {$topic}."];
            if ($scripture !== '') {
                $messageParts[] = 'Revisit ' . \Illuminate\Support\Str::limit($scripture, 80, '…') . '.';
            }
            if ($lesson !== '') {
                $messageParts[] = 'Carry one lesson into today: ' . \Illuminate\Support\Str::limit($lesson, 115, '…');
            } else {
                $messageParts[] = 'Choose one practical way to carry that reflection into today.';
            }

            $candidates[] = $this->item('Spiritual Growth', 'fa-book-bible', 'fuchsia', 'Carry your latest reflection into today.',
                implode(' ', $messageParts), 'Open Spiritual Growth', 'spiritual-practices.index');
        }

        // Generic insights are deliberately time-aware. Personalised candidates
        // above remain eligible, but the fallback pool should match what the
        // user is likely doing now rather than showing sleep advice at 9 AM or
        // a planning prompt close to bedtime.
        $timeCandidates = match ($daypart) {
            'morning' => [
                $this->item('Morning Planning', 'fa-sun', 'amber', 'Choose the few things that deserve your best energy.',
                    'Start with your most important outcome, then place the next two priorities around it. A focused morning makes the rest of the day easier to manage.',
                    'Plan My Day', 'daily-planner.index'),
                $this->item('Daily Affirmation', 'fa-sparkles', 'violet', 'You can begin today without having everything figured out.',
                    'Give your attention to the next useful step. Progress is built from clear, repeatable actions — not from trying to solve the whole week at once.',
                    'Open Planner', 'daily-planner.index'),
                $this->item('Health & Energy', 'fa-glass-water', 'sky', 'Start your day with a simple health check-in.',
                    'Have some water, notice your energy, and decide when you will move your body today. Small choices made early are easier to keep.',
                    'Open Wellbeing', 'wellbeing.index'),
            ],
            'late_morning' => [
                $this->item('Productivity', 'fa-bullseye', 'amber', 'Protect your most productive hours.',
                    'Before switching to smaller requests, check whether your most important task has moved forward. Give it one uninterrupted block if it has not.',
                    'Plan My Day', 'daily-planner.index'),
                $this->item('Digital Wellbeing', 'fa-laptop', 'indigo', 'Reset your posture before the next work block.',
                    'Relax your shoulders, bring the screen closer to eye level, and look away for a moment before continuing. Short resets reduce the strain of long screen sessions.',
                    'Open Daily Planner', 'daily-planner.index'),
                $this->item('Daily Affirmation', 'fa-heart', 'rose', 'Steady effort is enough for this part of the day.',
                    'You do not need to rush to prove that today is productive. Finish the next meaningful thing well, then decide what deserves your attention after that.',
                    'View Goals', 'personal-goals.index'),
            ],
            'afternoon' => [
                $this->item('Self-care', 'fa-droplet', 'sky', 'Give your body an afternoon reset.',
                    'Pause for water, movement, and a quick energy check. If concentration has dropped, a short reset may help more than pushing through without a break.',
                    'Open Wellbeing', 'wellbeing.index'),
                $this->item('Financial Wellness', 'fa-wallet', 'emerald', 'Keep today’s money picture current.',
                    'If you have spent or received money today, record it while the details are still fresh. Accurate small entries make your financial insights much more useful.',
                    'Review Expenses', 'expenses.index'),
                $this->item('Productivity', 'fa-list-check', 'amber', 'Use the afternoon to close one open loop.',
                    'Choose one unfinished task, message, or follow-up that has been occupying mental space and move it to a clear next state before the day gets busier.',
                    'Open Tasks', 'project-tasks.index'),
            ],
            'evening' => [
                $this->item('Evening Review', 'fa-check-double', 'emerald', 'Notice what moved forward today.',
                    'Review what you completed, what needs to move, and one thing you handled well. A short review makes tomorrow easier to start.',
                    'Open Daily Planner', 'daily-planner.index'),
                $this->item('Spiritual Growth', 'fa-seedling', 'fuchsia', 'Create a quiet moment before the day ends.',
                    'Take a few minutes for reflection, prayer, gratitude, or reading. Let the day settle before deciding what you want to carry into tomorrow.',
                    'Open Spiritual Growth', 'spiritual-practices.index'),
                $this->item('Relationships', 'fa-people-group', 'sky', 'Close the day with one meaningful connection.',
                    'If someone is waiting for a reply, encouragement, thank-you, or check-in, a small thoughtful message can be a good way to close an active day.',
                    'Open Planner', 'daily-planner.index'),
            ],
            default => [
                $this->item('Sleep & Devices', 'fa-moon', 'violet', 'Protect the last part of your evening.',
                    'Reduce unnecessary phone use, lower stimulation, and give your mind time to settle. Tomorrow is easier when tonight includes a real stopping point.',
                    'Review Sleep', 'sleep-logs.index'),
                $this->item('Reflection', 'fa-cloud-moon', 'fuchsia', 'You can let today be complete.',
                    'Notice one thing you are grateful for, one thing you learned, and one thing that can wait until tomorrow. Rest is also part of progress.',
                    'Open Spiritual Growth', 'spiritual-practices.index'),
                $this->item('Daily Affirmation', 'fa-sparkles', 'violet', 'You do not have to carry the whole day into the night.',
                    'What is unfinished can be planned for later. Give yourself permission to stop, recover, and begin again with clearer energy tomorrow.',
                    'Open Planner', 'daily-planner.index'),
            ],
        };

        $candidates = array_merge($candidates, $timeCandidates);

        // Keep the final pool sensible for the current time of day. This is
        // intentionally broader during daytime, but late-night insights avoid
        // prompting the user to start heavy financial/productivity work.
        if ($daypart === 'night') {
            $allowed = ['Sleep & Devices', 'Reflection', 'Spiritual Growth', 'Daily Affirmation'];
            $candidates = array_values(array_filter($candidates, fn ($item) => in_array($item['category'] ?? '', $allowed, true)));
        } elseif ($daypart === 'evening') {
            $blocked = ['Morning Planning'];
            $candidates = array_values(array_filter($candidates, fn ($item) => ! in_array($item['category'] ?? '', $blocked, true)));
        } elseif ($daypart === 'morning') {
            $blocked = ['Sleep & Devices', 'Evening Review', 'Reflection'];
            $candidates = array_values(array_filter($candidates, fn ($item) => ! in_array($item['category'] ?? '', $blocked, true)));
        }

        if (! $user->engagementNotificationEnabled('daily_affirmations')) {
            $candidates = array_values(array_filter($candidates, fn ($item) => ($item['category'] ?? '') !== 'Daily Affirmation'));
        }
        if (! $user->engagementNotificationEnabled('spiritual_insights')) {
            $candidates = array_values(array_filter($candidates, fn ($item) => ($item['category'] ?? '') !== 'Spiritual Growth'));
        }

        if ($candidates === []) {
            $candidates = $timeCandidates;
        }

        $historyKey = "daily-insight-history:{$userId}";
        $history = Cache::get($historyKey, []);
        $eligible = array_values(array_filter($candidates, fn ($item) => ! in_array($item['key'], array_slice($history, -4), true)));
        if ($eligible === []) {
            $eligible = $candidates;
        }
        $slot = intdiv((int) $now->format('H'), 2) + (int) $now->format('z');
        $chosen = $eligible[$slot % count($eligible)];
        $history[] = $chosen['key'];
        Cache::put($historyKey, array_slice($history, -12), now()->addDays(7));

        return $chosen + [
            'daypart' => $daypart,
            'local_time' => $now->format('H:i'),
            'refresh_after' => $now->copy()->startOfHour()->addHours(2 - ((int) $now->format('H') % 2))->toIso8601String(),
        ];
    }

    private function item(string $category, string $icon, string $tone, string $title, string $message, string $action, string $route): array
    {
        return [
            'key' => md5($category . '|' . $title),
            'category' => $category,
            'icon' => $icon,
            'tone' => $tone,
            'title' => $title,
            'message' => $message,
            'action' => $action,
            'route_name' => $route,
        ];
    }
}

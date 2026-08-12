<?php

namespace App\Console\Commands;

use App\Models\Debt;
use App\Models\EducationPlan;
use App\Models\HealthCheckup;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Reminder;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Console\Command;

/**
 * Runs daily (see routes/console.php) — a real push notification (not
 * an in-app/email one) summarizing everything due TODAY across
 * reminders and every module with a meaningful due-date-like field:
 * Plans (target_date), Debts (due_date), Health Checkups
 * (next_due_date), Education Plans (target_completion_date), Projects
 * (deadline), Project Tasks (due_date), Savings Goals (target_date).
 * Same "SafeBoda/Jumia-style daily nudge" idea the person asked for —
 * one combined notification per user per day, not one per item.
 *
 * Reuses the existing FcmService (already proven via SendReminders —
 * see that class/DeviceTokenController for the full setup this
 * depends on) rather than adding a second, separate push mechanism.
 */
class SendDailyDueItemsPush extends Command
{
    protected $signature = 'push:daily-due-items';

    protected $description = 'Send each user with a registered device a push notification summarizing everything due today';

    public function handle(FcmService $fcm): int
    {
        $users = User::has('deviceTokens')->get();

        foreach ($users as $user) {
            if (! $user->hasActiveAccess()) {
                continue;
            }

            $items = $this->dueTodayFor($user);

            if (empty($items)) {
                continue; // nothing due today — skip rather than sending an empty nudge
            }

            $title = count($items) === 1 ? '1 item due today' : count($items) . ' items due today';
            $body = collect($items)->take(3)->pluck('label')->implode(' • ');
            if (count($items) > 3) {
                $body .= ' • +' . (count($items) - 3) . ' more';
            }

            $fcm->sendToUser($user, $title, $body);
            $this->info("Sent daily due-items push to {$user->email}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{label: string}>
     */
    private function dueTodayFor(User $user): array
    {
        $userId = $user->id;
        $items = collect();

        Reminder::where('user_id', $userId)
            ->where('is_active', true)
            ->whereDate('next_run_at', today())
            ->get(['title'])
            ->each(fn ($r) => $items->push(['label' => "Reminder: {$r->title}"]));

        Plan::where('user_id', $userId)->where('is_archived', false)
            ->where('status', '!=', 'completed')
            ->whereDate('target_date', today())
            ->get(['title'])
            ->each(fn ($p) => $items->push(['label' => "Plan: {$p->title}"]));

        Debt::where('user_id', $userId)->where('is_archived', false)
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', today())
            ->get(['person_name'])
            ->each(fn ($d) => $items->push(['label' => "Debt due: {$d->person_name}"]));

        HealthCheckup::where('user_id', $userId)->where('is_archived', false)
            ->whereDate('next_due_date', today())
            ->get(['checkup_type'])
            ->each(fn ($h) => $items->push(['label' => "Checkup: {$h->checkup_type}"]));

        EducationPlan::where('user_id', $userId)->where('is_archived', false)
            ->where('status', '!=', 'completed')
            ->whereDate('target_completion_date', today())
            ->get(['title'])
            ->each(fn ($e) => $items->push(['label' => "Education: {$e->title}"]));

        Project::where('user_id', $userId)->where('is_archived', false)
            ->where('status', '!=', 'completed')
            ->whereDate('deadline', today())
            ->get(['name'])
            ->each(fn ($p) => $items->push(['label' => "Project deadline: {$p->name}"]));

        ProjectTask::where('user_id', $userId)->where('is_archived', false)
            ->where('status', '!=', 'done')
            ->whereDate('due_date', today())
            ->get(['title'])
            ->each(fn ($t) => $items->push(['label' => "Task: {$t->title}"]));

        SavingsGoal::where('user_id', $userId)->where('is_archived', false)
            ->where('status', '!=', 'completed')
            ->whereDate('target_date', today())
            ->get(['name'])
            ->each(fn ($s) => $items->push(['label' => "Savings goal target: {$s->name}"]));

        return $items->all();
    }
}

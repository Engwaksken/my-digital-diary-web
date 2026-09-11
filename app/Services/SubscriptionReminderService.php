<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\SubscriptionTrialReminderNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SubscriptionReminderService
{
    private const REMINDER_WEEKDAYS = [
        Carbon::MONDAY,
        Carbon::THURSDAY,
    ];

    public function sendDueReminders(): int
    {
        $today = now();

        if (! in_array($today->dayOfWeek, self::REMINDER_WEEKDAYS, true)) {
            return 0;
        }

        if (
            ! Schema::hasColumn('users', 'trial_ends_at')
            || ! Schema::hasColumn('users', 'subscription_status')
        ) {
            return 0;
        }

        $sent = 0;

        User::query()
            ->whereNotNull('trial_ends_at')
            ->whereDate('trial_ends_at', '>=', $today->toDateString())
            ->whereDate('trial_ends_at', '<=', $today->copy()->addDays(14)->toDateString())
            ->where(function ($query): void {
                $query->whereNull('subscription_status')
                    ->orWhereRaw("LOWER(TRIM(subscription_status)) IN ('trial', 'trialing')");
            })
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$sent, $today): void {
                foreach ($users as $user) {
                    if ($this->isManagedOrganizationMember($user)) {
                        continue;
                    }

                    if ($this->hasPaidSubscription($user)) {
                        continue;
                    }

                    $trialEnd = Carbon::parse($user->trial_ends_at)->startOfDay();
                    $daysRemaining = (int) $today->copy()->startOfDay()->diffInDays($trialEnd, false);

                    if ($daysRemaining < 0 || $daysRemaining > 14) {
                        continue;
                    }

                    if ($this->alreadySentToday($user->id)) {
                        continue;
                    }

                    $user->notify(
                        new SubscriptionTrialReminderNotification(
                            $daysRemaining,
                            $this->planName($user)
                        )
                    );

                    $sent++;
                }
            });

        return $sent;
    }

    private function hasPaidSubscription(User $user): bool
    {
        $status = strtolower(trim((string) ($user->subscription_status ?? '')));

        if (in_array($status, ['active', 'paid'], true)) {
            return true;
        }

        return false;
    }

    private function alreadySentToday(int $userId): bool
    {
        if (! Schema::hasTable('notifications')) {
            return false;
        }

        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->whereDate('created_at', now()->toDateString())
            ->where('type', SubscriptionTrialReminderNotification::class)
            ->exists();
    }

    private function isManagedOrganizationMember(User $user): bool
    {
        if (
            Schema::hasColumn('users', 'organization_id')
            && ! empty($user->organization_id)
        ) {
            if (
                Schema::hasTable('organizations')
                && DB::table('organizations')
                    ->where('owner_user_id', $user->id)
                    ->exists()
            ) {
                return false;
            }

            return true;
        }

        if (! Schema::hasTable('organization_members')) {
            return false;
        }

        return DB::table('organization_members')
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'invited'])
            ->exists();
    }

    private function planName(User $user): string
    {
        try {
            if (
                method_exists($user, 'subscriptionPlan')
                && $user->subscriptionPlan
            ) {
                return (string) (
                    $user->subscriptionPlan->name
                    ?? $user->subscriptionPlan->title
                    ?? 'Monthly'
                );
            }
        } catch (\Throwable) {
            //
        }

        return 'Monthly';
    }
}

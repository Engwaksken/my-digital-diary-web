<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class DiagnoseUserSubscription extends Command
{
    protected $signature = 'subscriptions:diagnose-user {user : User ID or email}';

    protected $description = 'Show the exact stored subscription/trial state for a user.';

    public function handle(): int
    {
        $value = (string) $this->argument('user');

        $user = ctype_digit($value)
            ? User::query()->find((int) $value)
            : User::query()->where('email', $value)->first();

        if (! $user) {
            $this->error('User not found.');
            return self::FAILURE;
        }

        $this->table(
            ['Field', 'Stored value'],
            [
                ['id', $user->id],
                ['email', $user->email],
                ['subscription_status', $user->subscription_status],
                ['subscription_plan_id', $user->subscription_plan_id],
                ['subscription_started_at', $user->subscription_started_at],
                ['subscription_expires_at', $user->subscription_expires_at],
                ['trial_ends_at', $user->trial_ends_at],
                ['onTrial()', method_exists($user, 'onTrial') ? ($user->onTrial() ? 'TRUE' : 'FALSE') : 'method missing'],
                ['trialDaysLeft()', method_exists($user, 'trialDaysLeft') ? $user->trialDaysLeft() : 'method missing'],
            ]
        );

        return self::SUCCESS;
    }
}

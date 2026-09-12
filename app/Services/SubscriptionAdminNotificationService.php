<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SubscriptionActivatedNotification;

class SubscriptionAdminNotificationService
{
    public function notify(User $subscriber): void
    {
        User::query()
            ->whereIn('role', ['admin', 'super_admin'])
            ->eachById(fn (User $admin) => $admin->notify(
                new SubscriptionActivatedNotification($subscriber)
            ));
    }
}

<?php

namespace App\Models\Concerns;

use App\Services\SubscriptionStateService;

trait HasReliableSubscriptionState
{
    public function hasActiveSubscription(): bool
    {
        return app(SubscriptionStateService::class)->isActive($this);
    }

    public function onTrial(): bool
    {
        return app(SubscriptionStateService::class)->onTrial($this);
    }

    public function trialDaysLeft(): int
    {
        return app(SubscriptionStateService::class)->trialDaysLeft($this);
    }
}

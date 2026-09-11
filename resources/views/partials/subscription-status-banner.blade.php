@auth
    @php
        $subscriptionUser = auth()->user();

        $subscriptionStatus = strtolower(
            trim((string) ($subscriptionUser->subscription_status ?? ''))
        );

        $hasActiveSubscription = $subscriptionStatus === 'active';

        $subscriptionExpiryRaw =
            data_get($subscriptionUser, 'subscription_expires_at')
            ?: data_get($subscriptionUser, 'subscription_ends_at')
            ?: data_get($subscriptionUser, 'subscription_end_date')
            ?: data_get($subscriptionUser, 'subscription_expiry_date')
            ?: null;

        $subscriptionExpiry = null;
        $subscriptionDaysLeft = null;
        $subscriptionExpiresSoon = false;

        if ($subscriptionExpiryRaw) {
            try {
                $timezone = $subscriptionUser->timezone
                    ?: config('app.timezone', 'Africa/Kampala');

                $nowForSubscription = now($timezone);
                $subscriptionExpiry = \Illuminate\Support\Carbon::parse(
                    $subscriptionExpiryRaw,
                    $timezone
                )->endOfDay();

                if ($subscriptionExpiry->greaterThanOrEqualTo($nowForSubscription)) {
                    $subscriptionDaysLeft = $nowForSubscription
                        ->copy()
                        ->startOfDay()
                        ->diffInDays(
                            $subscriptionExpiry->copy()->startOfDay(),
                            false
                        );

                    $subscriptionExpiresSoon =
                        $subscriptionDaysLeft >= 0
                        && $subscriptionDaysLeft <= 14;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    @endphp

    @if ($subscriptionUser->isSuspended())
        <div role="alert"
             class="bg-rose-100 text-rose-800 text-sm text-center py-2 px-3 flex flex-wrap items-center justify-center gap-2">
            <i class="fa-solid fa-ban" aria-hidden="true"></i>
            <span>Your account has been suspended.</span>
            <a href="{{ route('subscription.show') }}"
               class="underline font-medium">
                View details
            </a>
        </div>

    @elseif ($hasActiveSubscription)
        @if ($subscriptionExpiresSoon)
            <div role="status"
                 class="bg-amber-100 text-amber-900 text-sm text-center py-2 px-3 flex flex-wrap items-center justify-center gap-2">
                <i class="fa-solid fa-clock" aria-hidden="true"></i>

                @if ($subscriptionDaysLeft === 0)
                    <span>Your subscription expires today.</span>
                @elseif ($subscriptionDaysLeft === 1)
                    <span>Your subscription expires tomorrow.</span>
                @else
                    <span>
                        Your subscription expires in
                        <strong>{{ $subscriptionDaysLeft }} days</strong>.
                    </span>
                @endif

                <a href="{{ route('subscription.show') }}"
                   class="underline font-semibold">
                    Renew now
                </a>
                <span>to keep uninterrupted access.</span>
            </div>
        @endif

    @elseif ($subscriptionUser->onTrial())
        <div role="status"
             class="bg-amber-100 text-amber-800 text-sm text-center py-2 px-3 flex flex-wrap items-center justify-center gap-2">
            <i class="fa-solid fa-clock" aria-hidden="true"></i>
            <span>
                {{ $subscriptionUser->trialDaysLeft() }}
                day(s) left in your free trial.
            </span>
            <a href="{{ route('subscription.show') }}"
               class="underline font-medium">
                Subscribe
            </a>
            <span>to keep uninterrupted access.</span>
        </div>

    @elseif (! $subscriptionUser->isAdmin())
        <div role="alert"
             class="bg-rose-100 text-rose-800 text-sm text-center py-2 px-3 flex flex-wrap items-center justify-center gap-2">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>Your subscription has expired or is inactive.</span>
            <a href="{{ route('subscription.show') }}"
               class="underline font-medium">
                Renew your subscription
            </a>
            <span>to continue using premium features.</span>
        </div>
    @endif
@endauth

<?php

namespace App\Services;

use App\Models\IoTecSubscriptionTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SubscriptionPaymentActivationService
{
    public function activateFromIoTec(
        IoTecSubscriptionTransaction $transaction
    ): User {
        if (strtolower((string) $transaction->status) !== 'success') {
            throw new RuntimeException(
                'Subscription cannot be activated before payment is successful.'
            );
        }

        return DB::transaction(function () use ($transaction) {
            /** @var User $user */
            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($transaction->user_id);

            $expiresAt = $this->resolveExpiry(
                $user,
                $transaction->subscription_plan_id
            );

            $updates = [
                'subscription_status' => 'active',
                'trial_ends_at' => null,
                'subscription_started_at' => now(),
                'subscription_expires_at' => $expiresAt,
            ];

            if (
                $transaction->subscription_plan_id
                && Schema::hasColumn('users', 'subscription_plan_id')
            ) {
                $updates['subscription_plan_id'] =
                    $transaction->subscription_plan_id;
            }

            $user->forceFill($updates)->save();

            $transaction->forceFill([
                'activated_at' => now(),
            ])->save();

            return $user->fresh();
        });
    }

    private function resolveExpiry(
        User $user,
        ?int $planId
    ): Carbon {
        $base = now();

        /*
         * If the user already has a paid subscription extending into the
         * future, renew from the current paid expiry rather than losing days.
         */
        if ($user->subscription_expires_at) {
            $existing = Carbon::parse($user->subscription_expires_at);

            if ($existing->isFuture()) {
                $base = $existing;
            }
        }

        $months = 1;

        if (
            $planId
            && Schema::hasTable('subscription_plans')
        ) {
            $plan = DB::table('subscription_plans')
                ->where('id', $planId)
                ->first();

            if ($plan) {
                foreach ([
                    'duration_months',
                    'billing_months',
                    'months',
                ] as $column) {
                    if (
                        Schema::hasColumn('subscription_plans', $column)
                        && isset($plan->{$column})
                        && (int) $plan->{$column} > 0
                    ) {
                        $months = (int) $plan->{$column};
                        break;
                    }
                }

                if (
                    $months === 1
                    && Schema::hasColumn('subscription_plans', 'billing_cycle')
                    && isset($plan->billing_cycle)
                ) {
                    $cycle = strtolower((string) $plan->billing_cycle);

                    $months = match ($cycle) {
                        'annual', 'yearly', 'year' => 12,
                        'quarterly', 'quarter' => 3,
                        'semiannual', 'semi-annual', 'half-year' => 6,
                        default => 1,
                    };
                }
            }
        }

        return $base->copy()->addMonthsNoOverflow($months)->endOfDay();
    }
}

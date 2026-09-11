<?php

namespace App\Services;

use App\Models\IoTecSubscriptionTransaction;
use App\Models\BillingEventLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
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

            $payment = $this->syncPaymentRecord(
                $transaction,
                $user,
                $expiresAt
            );

            $transactionUpdates = ['activated_at' => now()];

            if (Schema::hasColumn('iotec_subscription_transactions', 'payment_id')) {
                $transactionUpdates['payment_id'] = $payment?->id;
            }

            $transaction->forceFill($transactionUpdates)->save();

            return $user->fresh();
        });
    }

    private function syncPaymentRecord(
        IoTecSubscriptionTransaction $transaction,
        User $user,
        ?Carbon $expiresAt
    ): ?Payment {
        if (! Schema::hasTable('payments')) {
            return null;
        }

        $reference = $this->paymentReference($transaction);
        $payment = null;

        if (
            Schema::hasColumn('iotec_subscription_transactions', 'payment_id')
            && $transaction->payment_id
        ) {
            $payment = Payment::query()->find($transaction->payment_id);
        }

        if (! $payment && $reference !== '') {
            $payment = Payment::query()
                ->where('user_id', $user->id)
                ->where('reference', $reference)
                ->first();
        }

        $payment ??= new Payment();

        $paymentData = [
            'user_id' => $user->id,
            'payment_gateway_id' => $this->iotecPaymentGatewayId(),
            'subscription_plan_id' => $transaction->subscription_plan_id,
            'method' => $transaction->payment_channel === 'card'
                ? 'card'
                : 'mobile_money',
            'amount' => $transaction->amount,
            'currency' => $transaction->currency ?: 'UGX',
            'status' => 'completed',
            'reference' => $reference,
            'notes' => 'Confirmed automatically from ioTec gateway status.',
        ];

        if (Schema::hasColumn('payments', 'gateway_transaction_id')) {
            $paymentData['gateway_transaction_id'] = $reference;
        }

        $payment->forceFill($paymentData)->save();

        $payment->assignReceiptNumber();
        $invoice = $this->syncInvoice($payment, $transaction, $user, $expiresAt);

        if (Schema::hasTable('billing_event_logs')) {
            BillingEventLog::record('payment_status_changed', $user->id, [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice?->id,
                'details' => 'completed automatically from ioTec gateway status',
            ]);
        }

        return $payment->fresh();
    }

    private function syncInvoice(
        Payment $payment,
        IoTecSubscriptionTransaction $transaction,
        User $user,
        ?Carbon $expiresAt
    ): ?Invoice {
        if (! Schema::hasTable('invoices')) {
            return null;
        }

        $invoice = Invoice::query()
            ->where('payment_id', $payment->id)
            ->first();

        if (! $invoice) {
            $invoice = new Invoice();
            $invoice->invoice_number = Invoice::generateInvoiceNumber();
            $invoice->payment_id = $payment->id;
        }

        $invoice->forceFill([
            'user_id' => $user->id,
            'subscription_plan_id' => $transaction->subscription_plan_id,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency ?: 'UGX',
            'status' => 'paid',
            'billing_period_start' => now()->toDateString(),
            'billing_period_end' => $expiresAt?->toDateString(),
            'due_date' => now()->toDateString(),
        ])->save();

        return $invoice->fresh();
    }

    private function paymentReference(IoTecSubscriptionTransaction $transaction): string
    {
        return trim((string) (
            $transaction->iotec_request_id
            ?: $transaction->external_id
            ?: ('iotec-'.$transaction->id)
        ));
    }

    private function iotecPaymentGatewayId(): ?int
    {
        if (! Schema::hasTable('payment_gateways')) {
            return null;
        }

        $gateway = PaymentGateway::query()
            ->where('gateway_code', 'iotec')
            ->orWhere(function ($query) {
                $query->whereIn('type', ['aggregator', 'mobile_money'])
                    ->where('is_default', true);
            })
            ->orderByDesc('is_default')
            ->first();

        return $gateway?->id;
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

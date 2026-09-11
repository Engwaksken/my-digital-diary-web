<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingEventLog;
use App\Models\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SubscriptionAutoRenewController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! Schema::hasColumn('users', 'auto_renew_subscription')) {
            return response()->json([
                'success' => false,
                'message' => 'Auto renewal is not installed yet.',
                'data' => [
                    'available' => false,
                    'enabled' => false,
                ],
            ], 503);
        }

        $gateway = $this->automaticGateway();

        $expiresAt = $user->subscription_expires_at;

        $isLifetime = $user->subscriptionPlan
            ? $user->subscriptionPlan->isLifetime()
            : $expiresAt === null && $user->subscription_plan_id !== null;

        return response()->json([
            'success' => true,
            'data' => [
                'available' => $gateway !== null
                    && $user->subscription_plan_id !== null
                    && $expiresAt !== null
                    && ! $isLifetime,

                'enabled' => (bool) $user->getAttribute(
                    'auto_renew_subscription'
                ),

                'phone_number' =>
                    $user->getAttribute('auto_renew_phone')
                    ?: $user->phone_number,

                'network' =>
                    $user->getAttribute('auto_renew_network')
                    ?: 'mtn',

                'next_renewal_date' =>
                    $expiresAt?->toDateString(),

                'subscription_plan_id' =>
                    $user->subscription_plan_id,

                'subscription_plan' =>
                    $user->subscriptionPlan?->name,

                'gateway' => $gateway ? [
                    'id' => $gateway->id,
                    'name' =>
                        $gateway->display_name
                        ?: $gateway->name,
                    'supports_mtn' =>
                        (bool) $gateway->supports_mtn,
                    'supports_airtel' =>
                        (bool) $gateway->supports_airtel,
                ] : null,

                'message' => $gateway
                    ? null
                    : 'Automatic Mobile Money collection is not configured yet.',
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! Schema::hasColumn('users', 'auto_renew_subscription')) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Auto renewal is not installed yet. Run the latest migration.',
            ], 503);
        }

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'phone_number' => [
                'nullable',
                'string',
                'max:30',
            ],
            'network' => [
                'nullable',
                'in:mtn,airtel',
            ],
        ]);

        $enabled = (bool) $data['enabled'];

        if (! $enabled) {
            $user->forceFill([
                'auto_renew_subscription' => false,
                'auto_renew_disabled_at' => now(),
            ])->save();

            BillingEventLog::record(
                'subscription_auto_renew_disabled',
                $user->id,
                ['source' => 'mobile']
            );

            return response()->json([
                'success' => true,
                'message' => 'Auto renewal disabled.',
                'data' => [
                    'enabled' => false,
                ],
            ]);
        }

        if (! $user->subscription_plan_id) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Choose and activate a subscription plan before enabling auto renewal.',
            ], 422);
        }

        if (! $user->subscription_expires_at) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Lifetime subscriptions do not need auto renewal.',
            ], 422);
        }

        $phone = trim(
            (string) ($data['phone_number'] ?? '')
        );

        if ($phone === '') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Enter the Mobile Money number to use for renewal prompts.',
            ], 422);
        }

        $network = strtolower(
            (string) ($data['network'] ?? 'mtn')
        );

        $gateway = $this->automaticGateway();

        if (! $gateway) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Automatic Mobile Money collection is not configured yet.',
            ], 422);
        }

        if (
            $network === 'mtn'
            && ! $gateway->supports_mtn
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'MTN Mobile Money is not enabled on the automatic payment gateway.',
            ], 422);
        }

        if (
            $network === 'airtel'
            && ! $gateway->supports_airtel
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Airtel Money is not enabled on the automatic payment gateway.',
            ], 422);
        }

        $user->forceFill([
            'auto_renew_subscription' => true,
            'auto_renew_phone' => $phone,
            'auto_renew_network' => $network,
            'auto_renew_payment_gateway_id' =>
                $gateway->id,
            'auto_renew_disabled_at' => null,
        ])->save();

        if (Schema::hasColumn('users', 'phone_number')) {
            $user->forceFill([
                'phone_number' => $phone,
            ])->save();
        }

        BillingEventLog::record(
            'subscription_auto_renew_enabled',
            $user->id,
            [
                'source' => 'mobile',
                'payment_gateway_id' =>
                    $gateway->id,
                'network' => $network,
                'phone_number' => $phone,
                'subscription_plan_id' =>
                    $user->subscription_plan_id,
                'expires_at' =>
                    $user->subscription_expires_at
                        ?->toDateTimeString(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Auto renewal enabled. A Mobile Money prompt will be sent on your expiry date.',
            'data' => [
                'enabled' => true,
                'phone_number' => $phone,
                'network' => $network,
                'next_renewal_date' =>
                    $user->subscription_expires_at
                        ?->toDateString(),
                'gateway' => [
                    'id' => $gateway->id,
                    'name' =>
                        $gateway->display_name
                        ?: $gateway->name,
                ],
            ],
        ]);
    }

    private function automaticGateway(): ?PaymentGateway
    {
        return PaymentGateway::query()
            ->where('is_enabled', true)
            ->whereIn(
                'type',
                ['mobile_money', 'aggregator']
            )
            ->get()
            ->first(
                fn (PaymentGateway $gateway) =>
                    $gateway->collectsAutomatically()
            );
    }
}

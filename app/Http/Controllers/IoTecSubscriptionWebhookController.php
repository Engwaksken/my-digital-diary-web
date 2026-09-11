<?php

namespace App\Http\Controllers;

use App\Models\IoTecSubscriptionTransaction;
use App\Services\SubscriptionPaymentActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IoTecSubscriptionWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionPaymentActivationService $activation
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /*
         * ioTec allows custom security headers on callbacks.
         * Configure the same X-IoTec-Webhook-Secret value in ioTec Pay.
         *
         * This check FAILS CLOSED: if no callback secret is configured the
         * endpoint refuses every request, and a mismatched header is
         * rejected outright. An unauthenticated callback must never be
         * able to activate a subscription.
         */
        $configuredSecret = (string) config(
            'services.iotec.callback_secret',
            ''
        );

        abort_unless($configuredSecret !== '', 401);

        $received = (string) $request->header(
            'X-IoTec-Webhook-Secret',
            ''
        );

        abort_unless(
            hash_equals($configuredSecret, $received),
            401
        );

        $payload = $request->all();

        $externalId = (string) (
            $payload['externalId']
            ?? $payload['external_id']
            ?? ''
        );

        $requestId = (string) (
            $payload['id']
            ?? $payload['requestId']
            ?? ''
        );

        $transaction = IoTecSubscriptionTransaction::query()
            ->when(
                $externalId !== '',
                fn ($query) => $query->where(
                    'external_id',
                    $externalId
                )
            )
            ->when(
                $externalId === '' && $requestId !== '',
                fn ($query) => $query->where(
                    'iotec_request_id',
                    $requestId
                )
            )
            ->first();

        if (! $transaction) {
            Log::warning(
                'Unknown ioTec subscription callback.',
                [
                    'external_id' => $externalId,
                    'request_id' => $requestId,
                ]
            );

            return response()->json([
                'received' => true,
            ]);
        }

        $status = strtolower(
            (string) ($payload['status'] ?? 'pending')
        );

        $transaction->forceFill([
            'iotec_request_id' => $requestId
                ?: $transaction->iotec_request_id,
            'status' => $status,
            'status_code' => $payload['statusCode'] ?? null,
            'status_message' => $payload['statusMessage'] ?? null,
            'gateway_response' => $payload,
            'paid_at' => $status === 'success'
                ? ($transaction->paid_at ?: now())
                : $transaction->paid_at,
        ])->save();

        /*
         * This is the critical subscription rule:
         * only ioTec Success activates the user.
         */
        if (
            $status === 'success'
            && ! $transaction->activated_at
        ) {
            $this->activation->activateFromIoTec(
                $transaction->fresh()
            );
        }

        return response()->json([
            'received' => true,
            'status' => $status,
        ]);
    }
}

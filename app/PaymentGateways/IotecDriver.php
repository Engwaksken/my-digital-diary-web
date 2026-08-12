<?php

namespace App\PaymentGateways;

use App\Models\PaymentGateway;
use App\Models\PaymentTransactionLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * IoTec Collections integration — OAuth2 client_credentials token, then a
 * mobile money collection (charge) request, with status checked either
 * by polling or via their webhook callback.
 *
 * Honest flag: this is built from general knowledge of how IoTec's
 * Collections API is structured (token → collect → status/callback),
 * not from their live API docs in front of me right now. The OAuth2
 * token step is standardized and should just work. The collection
 * request's exact field names (walletId, externalId, payer, channel,
 * etc.) are my best understanding and are the part most likely to need
 * a small adjustment once tested against a real sandbox — that's
 * exactly why every raw request/response gets written to
 * payment_transaction_logs.
 */
class IotecDriver implements PaymentGatewayDriverInterface
{
    public function __construct(protected PaymentGateway $gateway)
    {
    }

    public function getAccessToken(): string
    {
        $cacheKey = 'iotec_token_' . $this->gateway->id;

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $response = Http::asForm()->post($this->gateway->token_url, [
                'client_id' => $this->gateway->api_key,
                'client_secret' => $this->gateway->api_secret,
                'grant_type' => 'client_credentials',
            ]);

            if ($response->failed()) {
                throw new RuntimeException('IoTec token request failed: ' . ($response->json('error_description') ?? $response->json('error') ?? $response->body()));
            }

            $token = $response->json('access_token');
            if (! $token) {
                throw new RuntimeException('IoTec token response did not include an access_token — check token_url and credentials.');
            }

            return $token;
        });
    }

    public function testConnection(): array
    {
        try {
            $this->getAccessToken();

            return ['success' => true, 'message' => 'Connected — a valid access token was retrieved.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function initiateCollection(
        string $externalReference,
        float $amount,
        string $currency,
        ?string $phoneNumber,
        ?string $network,
        ?int $userId,
        ?int $paymentId
    ): PaymentTransactionLog {
        if (! $phoneNumber) {
            throw new RuntimeException('IoTec collection requires a phone number.');
        }

        Cache::forget('iotec_token_' . $this->gateway->id);
        $token = $this->getAccessToken();

        // Fetched fresh here (only $userId is passed into this method)
        // purely for payerName below — IoTec's own docs example
        // includes it, and this app doesn't otherwise ask for a
        // separate "payer name" during checkout.
        $payerName = $userId ? (\App\Models\User::find($userId)?->name) : null;

        $payload = [
            'category' => 'MobileMoney',
            'currency' => $currency,
            'walletId' => $this->gateway->merchant_id,
            'externalId' => $externalReference,
            'payer' => $phoneNumber,
            'payerName' => $payerName,
            'amount' => $amount,
            'payerNote' => 'Subscription payment',
            'payeeNote' => 'Subscription payment',
            // IoTec's own /collections/collect documentation example
            // shows this as null, not a network-specific string like
            // 'MTN-UG'/'AIRTEL-UG' — they appear to auto-detect the
            // network from the phone number's own prefix instead.
            // Sending an unrecognized channel value here is the most
            // likely explanation for MTN requests being silently
            // accepted (status: pending, vendor: Mtn correctly
            // identified) but never actually reaching the customer's
            // phone, while Airtel — whichever value happened to still
            // work or get ignored — did.
            'channel' => null,
            'transactionChargesCategory' => 'ChargeWallet',
            'redirectUrl' => null,
            'callbackUrl' => $this->gateway->callback_url,
        ];

        $log = PaymentTransactionLog::create([
            'payment_gateway_id' => $this->gateway->id,
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'status' => 'initiated',
            'amount' => $amount,
            'currency' => $currency,
            'phone_number' => $phoneNumber,
            'network' => $network,
            'request_payload' => json_encode($payload),
        ]);

        try {
            $response = Http::withToken($token)->post($this->gateway->collect_url, $payload);

            $log->update([
                'response_payload' => $response->body(),
                'external_reference' => $response->json('id') ?? $response->json('transactionId'),
                'status' => $response->successful() ? 'pending' : 'failed',
                'error_message' => $response->failed() ? ($response->json('message') ?? $response->body()) : null,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('IoTec collection request failed: ' . ($response->json('message') ?? $response->body()));
            }
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }

        return $log->fresh();
    }

    public function verifyTransaction(PaymentTransactionLog $log): array
    {
        if (! $log->external_reference) {
            throw new RuntimeException('No external reference to check yet — the collection request may not have completed.');
        }

        $token = $this->getAccessToken();
        $response = Http::withToken($token)->get(rtrim($this->gateway->status_url, '/') . '/' . $log->external_reference);

        if ($response->failed()) {
            throw new RuntimeException('IoTec status check failed: ' . $response->body());
        }

        $status = strtolower((string) ($response->json('status') ?? 'pending'));
        $mappedStatus = $this->mapStatus($status);

        $log->update(['status' => $mappedStatus, 'response_payload' => $response->body()]);

        return ['status' => $mappedStatus, 'raw' => (array) $response->json()];
    }

    public function parseWebhookPayload(array $payload): array
    {
        $externalReference = $payload['id'] ?? $payload['transactionId'] ?? null;
        $status = strtolower((string) ($payload['status'] ?? ''));

        return ['external_reference' => $externalReference, 'status' => $this->mapStatus($status)];
    }

    private function mapStatus(string $status): string
    {
        return match (true) {
            in_array($status, ['success', 'successful', 'completed'], true) => 'completed',
            in_array($status, ['failed', 'error', 'declined'], true) => 'failed',
            default => 'pending',
        };
    }
}

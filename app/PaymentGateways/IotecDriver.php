<?php

declare(strict_types=1);

namespace App\PaymentGateways;

use App\Models\PaymentGateway;
use App\Models\PaymentTransactionLog;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * ioTec Pay collection driver.
 *
 * payment_gateways columns used:
 * - token_url
 * - collect_url
 * - status_url
 * - client_id      (encrypted)
 * - client_secret  (encrypted)
 * - wallet_guid    (encrypted)
 * - callback_url
 *
 * IMPORTANT:
 * The old driver used api_key/api_secret and merchant_id. Those columns are
 * not the credential source for this project's payment_gateways schema.
 */
final class IotecDriver implements PaymentGatewayDriverInterface
{
    private const TOKEN_CACHE_PREFIX = 'iotec_oauth_token_v2_';

    public function __construct(protected PaymentGateway $gateway)
    {
    }

    public function getAccessToken(bool $forceRefresh = false): string
    {
        $cacheKey = self::TOKEN_CACHE_PREFIX.$this->gateway->id;

        // Remove the key used by the old driver, whose token was incorrectly
        // cached for 50 minutes despite ioTec tokens expiring in about 5 min.
        Cache::forget('iotec_token_'.$this->gateway->id);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && trim($cached) !== '') {
            return $cached;
        }

        $tokenUrl = GatewayValue::required($this->gateway, 'token_url');
        $clientId = GatewayValue::requiredSecret($this->gateway, 'client_id');
        $clientSecret = GatewayValue::requiredSecret($this->gateway, 'client_secret');

        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30)
            ->post($tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'ioTec authentication failed (HTTP '.$response->status().'): '.
                $this->safeProviderMessage($response)
            );
        }

        $token = trim((string) $response->json('access_token'));

        if ($token === '') {
            throw new RuntimeException(
                'ioTec authentication succeeded but no access_token was returned.'
            );
        }

        $expiresIn = max(1, (int) ($response->json('expires_in') ?? 300));

        // Keep the cached token shorter than its actual provider lifetime.
        $ttlSeconds = $expiresIn > 45
            ? $expiresIn - 30
            : max(1, $expiresIn - 5);

        Cache::put($cacheKey, $token, now()->addSeconds($ttlSeconds));

        return $token;
    }

    public function testConnection(): array
    {
        try {
            $this->getAccessToken(forceRefresh: true);

            return [
                'success' => true,
                'message' => 'Connected to ioTec successfully.',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
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
        $phoneNumber = $this->normalizePhoneNumber($phoneNumber);

        if ($phoneNumber === '') {
            throw new RuntimeException('ioTec collection requires a valid phone number.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('ioTec collection amount must be greater than zero.');
        }

        $currency = strtoupper(trim($currency));

        if ($currency === '') {
            throw new RuntimeException('ioTec collection currency is required.');
        }

        $collectUrl = GatewayValue::required($this->gateway, 'collect_url');
        $walletGuid = GatewayValue::requiredSecret($this->gateway, 'wallet_guid');

        $payerName = $userId !== null
            ? trim((string) (User::query()->find($userId)?->name ?? ''))
            : '';

        $callbackUrl = GatewayValue::plain($this->gateway, 'callback_url');

        $payload = [
            'category' => 'MobileMoney',
            'currency' => $currency,
            'walletId' => $walletGuid,
            'externalId' => $externalReference,
            'payer' => $phoneNumber,
            'payerName' => $payerName !== '' ? $payerName : null,
            'amount' => $amount,
            'payerNote' => 'Subscription payment',
            'payeeNote' => 'Subscription payment',
            // ioTec can identify the mobile-money operator from the number.
            // Keep null unless ioTec explicitly requires a channel value.
            'channel' => null,
            'transactionChargesCategory' => 'ChargeWallet',
            'redirectUrl' => null,
            'callbackUrl' => $callbackUrl !== '' ? $callbackUrl : null,
        ];

        $log = PaymentTransactionLog::query()->create([
            'payment_gateway_id' => $this->gateway->id,
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'status' => 'initiated',
            'amount' => $amount,
            'currency' => $currency,
            'phone_number' => $phoneNumber,
            'network' => $network,
            'request_payload' => json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
        ]);

        try {
            $response = $this->sendAuthenticatedCollection($collectUrl, $payload);

            // If a cached token was rejected, refresh once and retry. This
            // handles provider-side token revocation without looping.
            if ($response->status() === 401) {
                $response = $this->sendAuthenticatedCollection(
                    $collectUrl,
                    $payload,
                    forceFreshToken: true
                );
            }

            $providerReference = $this->providerReference($response);
            $successful = $response->successful();

            $log->update([
                'response_payload' => $response->body(),
                'external_reference' => $providerReference,
                'status' => $successful ? 'pending' : 'failed',
                'error_message' => $successful
                    ? null
                    : $this->safeProviderMessage($response),
            ]);

            if (! $successful) {
                throw new RuntimeException(
                    'ioTec collection request failed (HTTP '.$response->status().'): '.
                    $this->safeProviderMessage($response)
                );
            }
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $log->fresh();
    }

    public function verifyTransaction(PaymentTransactionLog $log): array
    {
        $providerReference = trim((string) $log->external_reference);

        if ($providerReference === '') {
            throw new RuntimeException(
                'The ioTec transaction has no provider reference to verify.'
            );
        }

        $statusUrl = $this->buildStatusUrl($providerReference);

        $response = $this->authenticatedGet($statusUrl);

        if ($response->status() === 401) {
            $response = $this->authenticatedGet($statusUrl, forceFreshToken: true);
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'ioTec status check failed (HTTP '.$response->status().'): '.
                $this->safeProviderMessage($response)
            );
        }

        $providerStatus = strtolower(trim((string) (
            $response->json('status')
            ?? $response->json('transactionStatus')
            ?? $response->json('state')
            ?? 'pending'
        )));

        $mappedStatus = $this->mapStatus($providerStatus);

        $log->update([
            'status' => $mappedStatus,
            'response_payload' => $response->body(),
            'error_message' => $mappedStatus === 'failed'
                ? $this->safeProviderMessage($response)
                : null,
        ]);

        $raw = $response->json();

        return [
            'status' => $mappedStatus,
            'raw' => is_array($raw) ? $raw : [],
        ];
    }

    public function parseWebhookPayload(array $payload): array
    {
        $externalReference = $payload['id']
            ?? $payload['transactionId']
            ?? $payload['transaction_id']
            ?? $payload['reference']
            ?? $payload['externalId']
            ?? null;

        $status = strtolower(trim((string) (
            $payload['status']
            ?? $payload['transactionStatus']
            ?? $payload['state']
            ?? ''
        )));

        return [
            'external_reference' => is_scalar($externalReference)
                ? (string) $externalReference
                : null,
            'status' => $this->mapStatus($status),
        ];
    }

    private function sendAuthenticatedCollection(
        string $url,
        array $payload,
        bool $forceFreshToken = false
    ): Response {
        $token = $this->getAccessToken($forceFreshToken);

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(45)
            ->post($url, $payload);
    }

    private function authenticatedGet(
        string $url,
        bool $forceFreshToken = false
    ): Response {
        $token = $this->getAccessToken($forceFreshToken);

        return Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30)
            ->get($url);
    }

    private function buildStatusUrl(string $providerReference): string
    {
        $statusUrl = GatewayValue::required($this->gateway, 'status_url');
        $encodedReference = rawurlencode($providerReference);

        foreach (['{id}', '{reference}', '{transactionId}'] as $placeholder) {
            if (str_contains($statusUrl, $placeholder)) {
                return str_replace($placeholder, $encodedReference, $statusUrl);
            }
        }

        return rtrim($statusUrl, '/').'/'.$encodedReference;
    }

    private function providerReference(Response $response): ?string
    {
        $reference = $response->json('id')
            ?? $response->json('transactionId')
            ?? $response->json('transaction_id')
            ?? $response->json('reference')
            ?? null;

        return is_scalar($reference) && trim((string) $reference) !== ''
            ? trim((string) $reference)
            : null;
    }

    private function safeProviderMessage(Response $response): string
    {
        $message = $response->json('error_description')
            ?? $response->json('message')
            ?? $response->json('error')
            ?? $response->json('detail')
            ?? null;

        if (is_scalar($message) && trim((string) $message) !== '') {
            return trim((string) $message);
        }

        $body = trim($response->body());

        if ($body === '') {
            return 'The provider returned an empty response.';
        }

        // Keep errors useful without dumping an unexpectedly large provider
        // payload into the UI/log message.
        return mb_substr($body, 0, 1000);
    }

    private function normalizePhoneNumber(?string $phoneNumber): string
    {
        $value = trim((string) $phoneNumber);

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '+')) {
            return '+'.preg_replace('/\D+/', '', substr($value, 1));
        }

        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function mapStatus(string $status): string
    {
        return match (true) {
            in_array($status, [
                'success',
                'successful',
                'completed',
                'complete',
                'paid',
                'approved',
            ], true) => 'completed',

            in_array($status, [
                'failed',
                'failure',
                'error',
                'declined',
                'rejected',
                'cancelled',
                'canceled',
                'expired',
            ], true) => 'failed',

            default => 'pending',
        };
    }
}

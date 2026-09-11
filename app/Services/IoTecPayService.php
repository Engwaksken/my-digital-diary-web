<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentGateway;
use App\PaymentGateways\IotecDriver;
use App\PaymentGateways\PaymentGatewayDriverFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

final class IoTecPayService
{
    /**
     * Return the enabled ioTec gateway.
     */
    public function gateway(): PaymentGateway
    {
        $gateway = PaymentGateway::query()
            ->whereRaw('LOWER(TRIM(gateway_code)) = ?', ['iotec'])
            ->when(
                $this->hasColumn('is_enabled'),
                fn ($query) => $query->where('is_enabled', true)
            )
            ->first();

        if (! $gateway) {
            throw new RuntimeException(
                'ioTec payment gateway is not configured or is disabled.'
            );
        }

        return $gateway;
    }

    public function configured(): bool
    {
        try {
            $gateway = $this->gateway();
            $driver = $this->driver($gateway);

            return $driver->testConnection()['success'] === true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Start an ioTec Mobile Money collection.
     *
     * Public method signature intentionally remains unchanged because the
     * existing web and mobile controllers already call this method.
     *
     * @return array<string, mixed>
     */
    public function collectMobileMoney(array $data): array
    {
        $gateway = $this->gateway();

        $url = $this->value($gateway, 'collect_url');

        if (! $url) {
            $url = rtrim($this->baseUrl($gateway), '/').'/collections/collect';
        }

        $payload = [
            'category' => 'MobileMoney',
            'currency' => strtoupper((string) ($data['currency'] ?? 'UGX')),
            'walletId' => $this->walletId($gateway),
            'externalId' => (string) $data['external_id'],
            'payer' => (string) $data['payer'],
            'payerName' => $data['payer_name'] ?? null,
            'payerNote' => $data['payer_note'] ?? null,
            'amount' => (float) $data['amount'],
            'payeeNote' => $data['payee_note'] ?? null,
            'transactionChargesCategory' =>
                $this->transactionChargesCategory($gateway),
            'redirectUrl' =>
                $data['redirect_url']
                ?? $this->value($gateway, 'return_url')
                ?? null,
        ];

        $response = $this->postWithFreshTokenRetry(
            $gateway,
            $url,
            $payload
        );

        $this->throwProviderError(
            $response,
            'ioTec Mobile Money collection'
        );

        return $this->jsonResponse(
            $response,
            'ioTec Mobile Money collection'
        );
    }

    /**
     * Start ioTec hosted card checkout.
     *
     * @return array<string, mixed>
     */
    public function collectCard(array $data): array
    {
        $gateway = $this->gateway();

        $cardUrl = $this->cardCollectionUrl();

        $payload = [
            'category' => 'Card',
            'currency' => strtoupper((string) ($data['currency'] ?? 'UGX')),
            'walletId' => $this->walletId($gateway),
            'externalId' => (string) $data['external_id'],
            'payer' => (string) $data['payer'],
            'payerName' => $data['payer_name'] ?? null,
            'payerNote' => $data['payer_note'] ?? null,
            'amount' => (float) $data['amount'],
            'payeeNote' => $data['payee_note'] ?? null,
            'channel' => $data['channel'] ?? 'Card',
            'transactionChargesCategory' =>
                $this->transactionChargesCategory($gateway),
            'redirectUrl' =>
                $data['redirect_url']
                ?? $this->value($gateway, 'return_url')
                ?? null,
        ];

        $response = $this->postWithFreshTokenRetry(
            $gateway,
            $cardUrl,
            $payload
        );

        $this->throwProviderError(
            $response,
            'ioTec card checkout'
        );

        return $this->jsonResponse(
            $response,
            'ioTec card checkout'
        );
    }

    /**
     * Return the card-collection endpoint without exposing credentials.
     */
    public function cardCollectionUrl(): string
    {
        $gateway = $this->gateway();

        /*
         * Optional explicit override in config remains supported.
         */
        $configured = $this->configValue(
            $gateway,
            'card_collect_url'
        );

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        /*
         * Best source of truth: derive the card endpoint from the working
         * Mobile Money collect_url.
         *
         * Example:
         * https://pay.iotec.io/api/collections/collect
         * becomes:
         * https://pay.iotec.io/api/collections/collect/card
         */
        $collectUrl = $this->value($gateway, 'collect_url');

        if (is_string($collectUrl) && trim($collectUrl) !== '') {
            $collectUrl = rtrim(trim($collectUrl), '/');

            if (str_ends_with($collectUrl, '/collect/card')) {
                return $collectUrl;
            }

            if (str_ends_with($collectUrl, '/collect')) {
                return $collectUrl.'/card';
            }

            if (str_contains($collectUrl, '/api/collections')) {
                return preg_replace(
                    '#/api/collections(?:/.*)?$#',
                    '/api/collections/collect/card',
                    $collectUrl
                ) ?: $collectUrl.'/card';
            }
        }

        /*
         * Final fallback: ioTec Pay v1 exposes the card collection endpoint
         * under /api/collections/collect/card.
         */
        $baseUrl = rtrim($this->baseUrl($gateway), '/');

        if (str_ends_with($baseUrl, '/api')) {
            return $baseUrl.'/collections/collect/card';
        }

        return $baseUrl.'/api/collections/collect/card';
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $requestId): array
    {
        $gateway = $this->gateway();

        $statusUrl = $this->value($gateway, 'status_url');

        if ($statusUrl) {
            $encodedRequestId = rawurlencode($requestId);

            $url = str_contains($statusUrl, '{requestId}')
                ? str_replace(
                    '{requestId}',
                    $encodedRequestId,
                    $statusUrl
                )
                : rtrim($statusUrl, '/').'/'.$encodedRequestId;
        } else {
            $url = rtrim(
                $this->baseUrl($gateway),
                '/'
            ).'/collections/status/'.rawurlencode($requestId);
        }

        $response = $this->getWithFreshTokenRetry(
            $gateway,
            $url
        );

        $this->throwProviderError(
            $response,
            'ioTec payment status check'
        );

        return $this->jsonResponse(
            $response,
            'ioTec payment status check'
        );
    }

    public function webhookSecret(): ?string
    {
        $gateway = $this->gateway();

        $value = $this->configValue(
            $gateway,
            'callback_secret'
        );

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : null;
    }

    public function callbackUrl(): ?string
    {
        $gateway = $this->gateway();

        return $this->value($gateway, 'callback_url')
            ?? $this->value($gateway, 'webhook_url');
    }

    public function supportsCard(): bool
    {
        $gateway = $this->gateway();

        $supported = $this->jsonArray(
            $this->value(
                $gateway,
                'supported_payment_methods'
            )
        );

        if ($supported === []) {
            return (bool) $this->configValue(
                $gateway,
                'supports_card',
                false
            );
        }

        $supported = array_map(
            static fn ($value): string =>
                strtolower(trim((string) $value)),
            $supported
        );

        return in_array('card', $supported, true)
            || in_array('visa', $supported, true)
            || in_array('mastercard', $supported, true)
            || in_array('visa_mastercard', $supported, true);
    }

    /**
     * One source of truth for ioTec OAuth.
     *
     * The service no longer performs its own client_credentials request or
     * maintains a second token cache. The corrected IotecDriver owns OAuth.
     */
    private function driver(PaymentGateway $gateway): IotecDriver
    {
        $driver = PaymentGatewayDriverFactory::make($gateway);

        if (! $driver instanceof IotecDriver) {
            throw new RuntimeException(
                'The configured ioTec gateway did not resolve to IotecDriver.'
            );
        }

        return $driver;
    }

    private function request(
        PaymentGateway $gateway,
        bool $forceFreshToken = false
    ): PendingRequest {
        $token = $this->driver($gateway)
            ->getAccessToken($forceFreshToken);

        return \Illuminate\Support\Facades\Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(45);
    }

    private function postWithFreshTokenRetry(
        PaymentGateway $gateway,
        string $url,
        array $payload
    ): Response {
        $response = $this->request($gateway)
            ->post($url, $payload);

        if ($response->status() === 401) {
            $response = $this->request(
                $gateway,
                forceFreshToken: true
            )->post($url, $payload);
        }

        return $response;
    }

    private function getWithFreshTokenRetry(
        PaymentGateway $gateway,
        string $url
    ): Response {
        $response = $this->request($gateway)
            ->get($url);

        if ($response->status() === 401) {
            $response = $this->request(
                $gateway,
                forceFreshToken: true
            )->get($url);
        }

        return $response;
    }

    private function baseUrl(PaymentGateway $gateway): string
    {
        $baseUrl = $this->value($gateway, 'base_url');

        if (! $baseUrl) {
            throw new RuntimeException(
                'ioTec base_url is missing in payment_gateways.'
            );
        }

        return $baseUrl;
    }

    private function walletId(PaymentGateway $gateway): string
    {
        /*
         * wallet_guid is encrypted in payment_gateways. The PaymentGateway
         * model may already decrypt it; GatewayValue handles either case.
         */
        $wallet = \App\PaymentGateways\GatewayValue::secret(
            $gateway,
            'wallet_guid'
        );

        if ($wallet === '') {
            throw new RuntimeException(
                'ioTec wallet_guid is missing in payment_gateways.'
            );
        }

        return $wallet;
    }

    private function transactionChargesCategory(
        PaymentGateway $gateway
    ): string {
        $value = $this->configValue(
            $gateway,
            'transaction_charges_category',
            'ChargeWallet'
        );

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : 'ChargeWallet';
    }

    private function value(
        PaymentGateway $gateway,
        string $column
    ): ?string {
        $value = $gateway->getAttribute($column);

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(PaymentGateway $gateway): array
    {
        $config = $gateway->getAttribute('config');

        if (is_array($config)) {
            return $config;
        }

        if (! is_string($config) || trim($config) === '') {
            return [];
        }

        $decoded = json_decode($config, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function configValue(
        PaymentGateway $gateway,
        string $key,
        mixed $default = null
    ): mixed {
        return data_get(
            $this->config($gateway),
            $key,
            $default
        );
    }

    /**
     * @return array<int, mixed>
     */
    private function jsonArray(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return array_values($decoded);
        }

        return array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(',', $value)
                ),
                static fn ($item): bool => $item !== ''
            )
        );
    }

    private function throwProviderError(
        Response $response,
        string $operation
    ): void {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('error_description')
            ?? $response->json('message')
            ?? $response->json('error')
            ?? $response->json('detail')
            ?? null;

        if (! is_scalar($message) || trim((string) $message) === '') {
            $message = trim($response->body());

            if ($message === '') {
                $message = 'The provider returned an empty response.';
            }
        }

        throw new RuntimeException(
            $operation.' failed with HTTP '
            .$response->status().': '
            .mb_substr(trim((string) $message), 0, 1000)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonResponse(
        Response $response,
        string $operation
    ): array {
        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException(
                $operation.' returned an invalid JSON response.'
            );
        }

        return $json;
    }

    /**
     * Avoid a hard dependency on Schema in the hot payment path while
     * preserving compatibility with installations where is_enabled may not
     * yet exist.
     */
    private function hasColumn(string $column): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn(
                'payment_gateways',
                $column
            );
        } catch (Throwable) {
            return false;
        }
    }
}

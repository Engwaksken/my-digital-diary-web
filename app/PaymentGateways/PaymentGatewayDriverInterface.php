<?php

namespace App\PaymentGateways;

use App\Models\PaymentTransactionLog;

/**
 * Every aggregator driver implements this — the subscription checkout
 * and webhook handling code only ever talk to THIS interface, never to
 * a specific provider's API directly. Adding a new aggregator (Pesapal,
 * Flutterwave, PayPal, a bank, etc.) means writing one new class that
 * implements this and registering it in PaymentGatewayDriverFactory —
 * nothing in SubscriptionController or the webhook controller needs to
 * change.
 */
interface PaymentGatewayDriverInterface
{
    /**
     * A lightweight, no-money-moved connectivity check for the admin's
     * "Test Connection" button.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;

    /**
     * Starts a real collection (charge) attempt and returns the log row
     * created for it. $network is nullable — only mobile-money-style
     * gateways need it; a card/bank driver can ignore it.
     */
    public function initiateCollection(
        string $externalReference,
        float $amount,
        string $currency,
        ?string $phoneNumber,
        ?string $network,
        ?int $userId,
        ?int $paymentId
    ): PaymentTransactionLog;

    /**
     * Actively polls the provider for a transaction's current status —
     * used when a webhook hasn't arrived yet (or during testing before
     * a real public callback URL exists).
     *
     * @return array{status: string, raw: array}
     */
    public function verifyTransaction(PaymentTransactionLog $log): array;

    /**
     * Normalizes a provider's webhook payload into a common shape, so
     * the webhook controller doesn't need to know each provider's own
     * field names.
     *
     * @return array{external_reference: ?string, status: string}
     */
    public function parseWebhookPayload(array $payload): array;
}

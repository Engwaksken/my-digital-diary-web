<?php

namespace App\PaymentGateways;

use App\Models\PaymentGateway;
use App\Models\PaymentTransactionLog;
use RuntimeException;

/**
 * Structural placeholder — the admin form, database schema, encryption,
 * and factory routing for Flutterwave all work today; only the actual API
 * calls (what an OAuth/API-key exchange with Flutterwave looks like, their
 * specific collection/charge request shape, and their webhook payload
 * format) are not implemented, since that needs their real API docs and
 * live sandbox credentials to get right — same situation as IoTec was
 * before it got built out. Filling in the method bodies below with
 * Flutterwave's actual API is a self-contained task that doesn't touch
 * anything else in the app.
 */
class FlutterwaveDriver implements PaymentGatewayDriverInterface
{
    public function __construct(protected PaymentGateway $gateway)
    {
    }

    public function testConnection(): array
    {
        return ['success' => false, 'message' => 'Flutterwave is not implemented yet — this gateway type is a placeholder.'];
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
        throw new RuntimeException('Flutterwave is not implemented yet — choose a different active gateway, or ask your developer to complete FlutterwaveDriver.');
    }

    public function verifyTransaction(PaymentTransactionLog $log): array
    {
        throw new RuntimeException('Flutterwave is not implemented yet.');
    }

    public function parseWebhookPayload(array $payload): array
    {
        return ['external_reference' => null, 'status' => 'pending'];
    }
}

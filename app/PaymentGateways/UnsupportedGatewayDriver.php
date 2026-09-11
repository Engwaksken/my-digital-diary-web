<?php

declare(strict_types=1);

namespace App\PaymentGateways;

use App\Models\PaymentGateway;
use App\Models\PaymentTransactionLog;
use RuntimeException;

/**
 * Shared base for gateway rows that are configurable in Admin but whose
 * provider API integration has not been implemented yet.
 */
abstract class UnsupportedGatewayDriver implements PaymentGatewayDriverInterface
{
    public function __construct(protected PaymentGateway $gateway)
    {
    }

    abstract protected function providerName(): string;

    public function testConnection(): array
    {
        return [
            'success' => false,
            'message' => $this->providerName().' integration is not implemented yet.',
        ];
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
        throw new RuntimeException(
            $this->providerName().' collection integration is not implemented yet.'
        );
    }

    public function verifyTransaction(PaymentTransactionLog $log): array
    {
        throw new RuntimeException(
            $this->providerName().' transaction verification is not implemented yet.'
        );
    }

    public function parseWebhookPayload(array $payload): array
    {
        return [
            'external_reference' => null,
            'status' => 'pending',
        ];
    }
}

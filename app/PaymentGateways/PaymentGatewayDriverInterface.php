<?php

declare(strict_types=1);

namespace App\PaymentGateways;

use App\Models\PaymentTransactionLog;

interface PaymentGatewayDriverInterface
{
    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;

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
     * @return array{status: string, raw: array<string, mixed>}
     */
    public function verifyTransaction(PaymentTransactionLog $log): array;

    /**
     * @param array<string, mixed> $payload
     * @return array{external_reference: ?string, status: string}
     */
    public function parseWebhookPayload(array $payload): array;
}

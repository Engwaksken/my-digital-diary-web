<?php

declare(strict_types=1);

namespace App\PaymentGateways;

use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Throwable;

/**
 * Reads normal and encrypted values from payment_gateways consistently.
 *
 * client_id, client_secret and wallet_guid are encrypted in this project.
 * If the model already decrypts a value through an accessor/cast, the plain
 * value is returned unchanged.
 */
final class GatewayValue
{
    public static function plain(PaymentGateway $gateway, string $field): string
    {
        return trim((string) ($gateway->getAttribute($field) ?? ''));
    }

    public static function secret(PaymentGateway $gateway, string $field): string
    {
        $value = self::plain($gateway, $field);

        if ($value === '') {
            return '';
        }

        try {
            return trim(Crypt::decryptString($value));
        } catch (Throwable) {
            // The PaymentGateway model may already decrypt the field.
            return $value;
        }
    }

    public static function required(PaymentGateway $gateway, string $field): string
    {
        $value = self::plain($gateway, $field);

        if ($value === '') {
            throw new RuntimeException(
                "Payment gateway '{$gateway->gateway_code}' is missing {$field}."
            );
        }

        return $value;
    }

    public static function requiredSecret(PaymentGateway $gateway, string $field): string
    {
        $value = self::secret($gateway, $field);

        if ($value === '') {
            throw new RuntimeException(
                "Payment gateway '{$gateway->gateway_code}' is missing {$field}."
            );
        }

        return $value;
    }
}

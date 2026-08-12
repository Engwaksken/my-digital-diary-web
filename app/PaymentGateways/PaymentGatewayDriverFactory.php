<?php

namespace App\PaymentGateways;

use App\Models\PaymentGateway;
use RuntimeException;

/**
 * Resolves a PaymentGateway row to the driver class that actually knows
 * how to talk to that provider — the ONE place that needs a new line
 * when a new aggregator gets built out. Everything else (subscription
 * checkout, the webhook controller, admin forms) works against
 * PaymentGatewayDriverInterface and never needs to change.
 */
class PaymentGatewayDriverFactory
{
    private const DRIVERS = [
        'iotec' => IotecDriver::class,
        'pesapal' => PesapalDriver::class,
        'flutterwave' => FlutterwaveDriver::class,
        'paypal' => PayPalDriver::class,
    ];

    public static function make(PaymentGateway $gateway): PaymentGatewayDriverInterface
    {
        $driverClass = self::DRIVERS[$gateway->gateway_code] ?? null;

        if (! $driverClass) {
            throw new RuntimeException(
                "No driver registered for gateway_code '{$gateway->gateway_code}' — add one to PaymentGatewayDriverFactory::DRIVERS."
            );
        }

        return new $driverClass($gateway);
    }

    /** @return string[] gateway_code values that have a real (non-placeholder) driver */
    public static function availableCodes(): array
    {
        return array_keys(self::DRIVERS);
    }
}

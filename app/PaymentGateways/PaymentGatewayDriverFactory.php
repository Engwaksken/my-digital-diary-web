<?php

declare(strict_types=1);

namespace App\PaymentGateways;

use App\Models\PaymentGateway;
use RuntimeException;

final class PaymentGatewayDriverFactory
{
    /**
     * Registered gateway codes.
     *
     * Some registered drivers are intentional placeholders so existing Admin
     * configuration remains compatible. Use implementedCodes() when callers
     * need providers that can currently process live collections.
     *
     * @var array<string, class-string<PaymentGatewayDriverInterface>>
     */
    private const DRIVERS = [
        'iotec' => IotecDriver::class,
        'pesapal' => PesapalDriver::class,
        'flutterwave' => FlutterwaveDriver::class,
        'paypal' => PayPalDriver::class,
    ];

    /**
     * @var string[]
     */
    private const IMPLEMENTED = [
        'iotec',
    ];

    public static function make(PaymentGateway $gateway): PaymentGatewayDriverInterface
    {
        $code = strtolower(trim((string) $gateway->gateway_code));
        $driverClass = self::DRIVERS[$code] ?? null;

        if ($driverClass === null) {
            throw new RuntimeException(
                "No payment driver is registered for gateway_code '{$gateway->gateway_code}'."
            );
        }

        return new $driverClass($gateway);
    }

    /**
     * Backwards-compatible list of every registered gateway code.
     *
     * @return string[]
     */
    public static function availableCodes(): array
    {
        return array_keys(self::DRIVERS);
    }

    /**
     * Providers whose API integration is currently implemented.
     *
     * @return string[]
     */
    public static function implementedCodes(): array
    {
        return self::IMPLEMENTED;
    }

    public static function isImplemented(string $gatewayCode): bool
    {
        return in_array(strtolower(trim($gatewayCode)), self::IMPLEMENTED, true);
    }
}

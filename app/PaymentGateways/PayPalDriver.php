<?php

declare(strict_types=1);

namespace App\PaymentGateways;

final class PayPalDriver extends UnsupportedGatewayDriver
{
    protected function providerName(): string
    {
        return 'PayPal';
    }
}

<?php

declare(strict_types=1);

namespace App\PaymentGateways;

final class FlutterwaveDriver extends UnsupportedGatewayDriver
{
    protected function providerName(): string
    {
        return 'Flutterwave';
    }
}

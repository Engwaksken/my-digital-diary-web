<?php

declare(strict_types=1);

namespace App\PaymentGateways;

final class PesapalDriver extends UnsupportedGatewayDriver
{
    protected function providerName(): string
    {
        return 'Pesapal';
    }
}

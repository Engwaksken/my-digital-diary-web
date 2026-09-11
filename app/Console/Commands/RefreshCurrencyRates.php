<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Command;

final class RefreshCurrencyRates extends Command
{
    protected $signature = 'currency:refresh-rates';
    protected $description = 'Refresh exchange rates for the configured base currency.';

    public function handle(CurrencyService $currency): int
    {
        $currency->refresh($currency->settings()->base_currency);
        $this->info('Currency rates refreshed.');
        return self::SUCCESS;
    }
}

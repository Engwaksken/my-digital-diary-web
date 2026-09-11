<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\CurrencyRate;
use App\Models\CurrencySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class CurrencyService
{
    public function settings(): CurrencySetting
    {
        return CurrencySetting::current();
    }

    public function supported(): array
    {
        return config('currency.supported', []);
    }

    public function refresh(?string $base = null): array
    {
        $base = strtoupper($base ?: $this->settings()->base_currency);
        $url = rtrim((string) config('currency.provider_url'), '/').'/'.$base;

        $response = Http::acceptJson()->timeout(15)->retry(2, 500)->get($url);
        $response->throw();

        $rates = $response->json('rates', []);
        if (! is_array($rates) || $rates === []) {
            throw new \RuntimeException('Currency provider returned no rates.');
        }

        foreach ($rates as $quote => $rate) {
            if (! is_numeric($rate)) {
                continue;
            }

            CurrencyRate::query()->updateOrCreate(
                ['base_currency' => $base, 'quote_currency' => strtoupper((string) $quote)],
                ['rate' => (float) $rate, 'fetched_at' => now()]
            );
        }

        CurrencyRate::query()->updateOrCreate(
            ['base_currency' => $base, 'quote_currency' => $base],
            ['rate' => 1, 'fetched_at' => now()]
        );

        return $rates;
    }

    public function rate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $settings = $this->settings();
        $row = CurrencyRate::query()
            ->where('base_currency', $from)
            ->where('quote_currency', $to)
            ->first();

        if (! $row || ! $row->fetched_at || $row->fetched_at->lt(now()->subMinutes(max(5, (int) $settings->refresh_minutes)))) {
            try {
                $this->refresh($from);
                $row = CurrencyRate::query()
                    ->where('base_currency', $from)
                    ->where('quote_currency', $to)
                    ->first();
            } catch (\Throwable $e) {
                Log::warning('Currency refresh failed; using cached rate when available.', [
                    'from' => $from,
                    'to' => $to,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $row) {
            throw new \RuntimeException("No exchange rate is available for {$from}/{$to}.");
        }

        return (float) $row->rate;
    }

    public function convert(float $amount, string $from, string $to): float
    {
        return $amount * $this->rate($from, $to);
    }
}

<?php

use App\Models\SiteSetting;

if (! function_exists('format_money')) {
    /**
     * Formats an amount using the site's configured base currency (UGX by
     * default — see SiteSetting::formatMoney()) — the replacement for
     * every hardcoded "${{ number_format($x, 2) }}" that used to assume
     * USD throughout the app.
     */
    function format_money(float|int|string $amount): string
    {
        return SiteSetting::current()->formatMoney((float) $amount);
    }
}

if (! function_exists('format_money_in')) {
    /**
     * For a SPECIFIC record's own stored amount+currency (a Payment,
     * Invoice, etc.) — use this instead of format_money() whenever
     * you're displaying something that has its own currency field.
     * format_money() always assumes the site's CURRENT default
     * currency, which silently mislabels an old record made in a
     * currency the site has since switched away from.
     */
    function format_money_in(float|int|string $amount, ?string $currencyCode): string
    {
        return SiteSetting::current()->formatMoneyInCurrency((float) $amount, $currencyCode);
    }
}

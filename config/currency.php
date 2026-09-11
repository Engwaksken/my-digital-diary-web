<?php
declare(strict_types=1);

return [
    'provider_url' => env('CURRENCY_RATE_URL', 'https://open.er-api.com/v6/latest'),
    'base_currency' => env('CURRENCY_BASE', 'UGX'),
    'display_currency' => env('CURRENCY_DISPLAY', 'UGX'),
    'refresh_minutes' => (int) env('CURRENCY_REFRESH_MINUTES', 60),
    'supported' => [
        'UGX' => ['name' => 'Ugandan Shilling', 'symbol' => 'UGX', 'decimals' => 0],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'decimals' => 2],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'decimals' => 2],
        'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KES', 'decimals' => 2],
        'TZS' => ['name' => 'Tanzanian Shilling', 'symbol' => 'TZS', 'decimals' => 0],
        'RWF' => ['name' => 'Rwandan Franc', 'symbol' => 'RWF', 'decimals' => 0],
    ],
];

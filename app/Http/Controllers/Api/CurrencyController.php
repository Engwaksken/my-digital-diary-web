<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CurrencyController extends Controller
{
    public function show(Request $request, CurrencyService $currency): JsonResponse
    {
        $settings = $currency->settings();
        $selected = strtoupper((string) $request->query('currency', $settings->display_currency));
        $supported = $currency->supported();

        if (! isset($supported[$selected])) {
            $selected = $settings->display_currency;
        }

        return response()->json([
            'data' => [
                'base_currency' => $settings->base_currency,
                'display_currency' => $selected,
                'rate' => $currency->rate($settings->base_currency, $selected),
                'allow_user_selection' => $settings->allow_user_selection,
                'currencies' => $supported,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}

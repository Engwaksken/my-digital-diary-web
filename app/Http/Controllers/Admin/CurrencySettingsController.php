<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurrencySetting;
use App\Services\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CurrencySettingsController extends Controller
{
    public function edit(CurrencyService $currency): View
    {
        return view('admin.settings.currency', [
            'settings' => $currency->settings(),
            'currencies' => $currency->supported(),
        ]);
    }

    public function update(Request $request, CurrencyService $currency): RedirectResponse
    {
        $codes = array_keys($currency->supported());

        $data = $request->validate([
            'base_currency' => ['required', 'in:'.implode(',', $codes)],
            'display_currency' => ['required', 'in:'.implode(',', $codes)],
            'refresh_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'allow_user_selection' => ['nullable', 'boolean'],
        ]);

        $data['allow_user_selection'] = $request->boolean('allow_user_selection');
        CurrencySetting::current()->update($data);

        try {
            $currency->refresh($data['base_currency']);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('warning', 'Currency settings saved, but the latest rates could not be refreshed.');
        }

        return back()->with('success', 'Currency settings updated.');
    }
}

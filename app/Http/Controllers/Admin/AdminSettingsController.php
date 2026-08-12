<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Whole-app branding: system name, logo, favicon. A singleton row
 * (SiteSetting::current()) shared into every view via AppServiceProvider,
 * so it's visible on both authenticated pages and the guest/login layout.
 */
class AdminSettingsController extends Controller
{
    public function edit(): View
    {
        $aiProviders = \App\Models\AiProvider::orderBy('sort_order')->get();
        $meetingPlatforms = \App\Models\MeetingPlatformConfig::orderBy('platform')->get();

        return view('admin.settings.edit', [
            'settings' => SiteSetting::current(),
            'aiProviders' => $aiProviders,
            'meetingPlatforms' => $meetingPlatforms,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'default_currency_code' => ['required', 'string', 'max:3'],
            'default_currency_symbol' => ['required', 'string', 'max:10'],
            'default_currency_decimals' => ['required', 'integer', 'min:0', 'max:4'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'currency_codes' => ['nullable', 'array'],
            'currency_codes.*' => ['nullable', 'string', 'max:3'],
            'currency_symbols' => ['nullable', 'array'],
            'currency_symbols.*' => ['nullable', 'string', 'max:5'],
            'currency_rates' => ['nullable', 'array'],
            'currency_rates.*' => ['nullable', 'numeric', 'min:0'],
            'logo' => ['nullable', 'image', 'max:1024'],
            'favicon' => ['nullable', 'image', 'max:512'],
            'default_ai_provider' => ['nullable', 'exists:ai_providers,key'],
            'default_ai_api_key' => ['nullable', 'string'],
            'default_ai_free_limit_per_month' => ['required', 'integer', 'min:0', 'max:1000'],
            'privacy_policy_content' => ['nullable', 'string'],
            'privacy_policy_version' => ['nullable', 'string', 'max:20'],
        ]);

        // $request->validate()'s 'integer' rule checks the FORMAT but
        // doesn't cast the value — form fields always arrive as strings,
        // and Carbon::addDays() below requires a real int, not a numeric
        // string ("45"), or it throws a TypeError. Casting once here
        // keeps every use below (the comparison, the assignment, and the
        // addDays() call) consistently a real int.
        $data['trial_days'] = (int) $data['trial_days'];

        $settings = SiteSetting::current();
        $oldTrialDays = $settings->trial_days;
        $settings->site_name = $data['site_name'];
        $settings->monthly_price = $data['monthly_price'];
        $settings->default_currency_code = strtoupper($data['default_currency_code']);
        $settings->default_currency_symbol = $data['default_currency_symbol'];
        $settings->default_currency_decimals = $data['default_currency_decimals'];
        $settings->trial_days = $data['trial_days'];
        $settings->default_ai_provider = filled($data['default_ai_provider'] ?? null)
            ? $data['default_ai_provider']
            : $settings->default_ai_provider;
        $settings->default_ai_free_limit_per_month = $data['default_ai_free_limit_per_month'];
        if (array_key_exists('privacy_policy_content', $data)) {
            $settings->privacy_policy_content = filled($data['privacy_policy_content'])
                ? $data['privacy_policy_content']
                : null;
        }
        $settings->privacy_policy_version = filled($data['privacy_policy_version'] ?? null)
            ? $data['privacy_policy_version']
            : ($settings->privacy_policy_version ?: '1.0');

        // Builds [{code, symbol, rate}, ...] from three parallel arrays
        // (one row per currency in the form) rather than asking an admin
        // to hand-write JSON — empty rows (no code entered) are dropped.
        $currencies = [];
        $codes = $data['currency_codes'] ?? [];
        $symbols = $data['currency_symbols'] ?? [];
        $rates = $data['currency_rates'] ?? [];
        foreach ($codes as $i => $code) {
            if (! empty($code) && ! empty($rates[$i])) {
                $currencies[] = [
                    'code' => strtoupper($code),
                    'symbol' => $symbols[$i] ?? $code,
                    'rate' => (float) $rates[$i],
                ];
            }
        }
        $settings->supported_currencies = $currencies;

        if (! empty($data['default_ai_api_key'])) {
            $settings->default_ai_api_key = $data['default_ai_api_key'];
        }

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $settings->logo_path = $request->file('logo')->store('branding', 'public');
        }

        if ($request->hasFile('favicon')) {
            if ($settings->favicon_path) {
                Storage::disk('public')->delete($settings->favicon_path);
            }
            $settings->favicon_path = $request->file('favicon')->store('branding', 'public');
        }

        $settings->save();

        $updatedTrialCount = 0;

        if ($oldTrialDays !== $data['trial_days']) {
            // Retroactively re-applies the new trial length to everyone
            // STILL on trial (subscription_status = 'trialing' — anyone
            // who has already paid/gone active is untouched, since their
            // trial length is irrelevant to them now). Each user's trial
            // is recalculated from THEIR OWN join date, not from today —
            // someone who joined 20 days ago with a 30-day trial and now
            // gets a 45-day trial should have 25 days left, not a fresh
            // 45 from right now.
            \App\Models\User::where('subscription_status', 'trialing')->chunkById(100, function ($users) use ($data, &$updatedTrialCount) {
                foreach ($users as $user) {
                    $user->trial_ends_at = $user->created_at->copy()->addDays($data['trial_days']);
                    $user->save();
                    $updatedTrialCount++;
                }
            });
        }

        $message = 'Site settings updated.';
        if ($updatedTrialCount > 0) {
            $message .= " Trial period updated for {$updatedTrialCount} user(s) currently on trial.";
        }

        return back()->with('success', $message);
    }
}

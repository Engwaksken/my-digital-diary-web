<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Singleton settings row (always id=1) for whole-app branding: system
 * name, logo, favicon. Managed by admins at /admin/settings, read by
 * every view via $siteSettings (shared globally in AppServiceProvider).
 */
class SiteSetting extends Model
{
    protected $table = 'site_settings';

    protected $fillable = [
        'site_name', 'monthly_price', 'trial_days', 'logo_path', 'favicon_path',
        'default_currency_code', 'default_currency_symbol', 'default_currency_decimals',
        'default_ai_provider', 'default_ai_api_key', 'default_ai_free_limit_per_month',
        'privacy_policy_content', 'privacy_policy_version', 'supported_currencies',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'default_ai_api_key' => 'encrypted',
        'trial_days' => 'integer',
        'default_ai_free_limit_per_month' => 'integer',
        'supported_currencies' => 'array',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], ['site_name' => 'Personal Monitor']);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * DomPDF blocks loading images from remote HTTP URLs unless
     * `isRemoteEnabled` is explicitly turned on in its own package
     * config — a setting this app doesn't control from application code.
     * Embedding the logo as a base64 data URI sidesteps that entirely:
     * DomPDF just reads the bytes straight out of the HTML, no fetch of
     * any kind involved. Used by PDF templates specifically; email
     * templates should keep using logoUrl() instead, since most email
     * clients strip data URIs for security.
     */
    public function logoDataUri(): ?string
    {
        if (! $this->logo_path || ! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($this->logo_path);
        $mimeType = Storage::disk('public')->mimeType($this->logo_path) ?: 'image/png';

        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }

    public function faviconUrl(): ?string
    {
        return $this->favicon_path ? Storage::disk('public')->url($this->favicon_path) : null;
    }

    public function hasDefaultAiKey(): bool
    {
        return ! empty($this->default_ai_provider) && ! empty($this->default_ai_api_key);
    }

    /**
     * Formats an amount using the site's BASE currency — "UGX 50,000" by
     * default (0 decimals, since shilling-scale amounts rarely need
     * them), or whatever an admin has configured. Used everywhere a
     * dollar sign used to be hardcoded — see the `format_money()` global
     * helper in app/helpers.php, which just calls this on the current
     * settings so views don't need `SiteSetting::current()->formatMoney(...)`
     * spelled out everywhere.
     */
    public function formatMoney(float $amount): string
    {
        $formatted = number_format($amount, $this->default_currency_decimals);

        return trim($this->default_currency_symbol . ' ' . $formatted);
    }

    /**
     * For displaying a SPECIFIC record's own stored amount+currency
     * (a Payment, Invoice, etc.) — NOT the same as formatMoney(), which
     * always uses the site's CURRENT default currency regardless of
     * what currency that record actually recorded. An old payment made
     * back when the site's default was USD would be silently and
     * incorrectly relabeled as UGX by formatMoney() if the site has
     * since switched — this looks up the right symbol for whatever
     * currency the record itself says it's in, falling back to just
     * showing the currency code when it's not the site's own default or
     * one of the additional supported ones with a configured symbol.
     */
    public function formatMoneyInCurrency(float $amount, ?string $currencyCode): string
    {
        if (! $currencyCode || strcasecmp($currencyCode, $this->default_currency_code) === 0) {
            return $this->formatMoney($amount);
        }

        foreach ($this->supported_currencies ?? [] as $currency) {
            if (strcasecmp($currency['code'] ?? '', $currencyCode) === 0) {
                return trim(($currency['symbol'] ?? $currencyCode) . ' ' . number_format($amount, $this->default_currency_decimals));
            }
        }

        return trim($currencyCode . ' ' . number_format($amount, 2));
    }

    /**
     * Returns the admin-edited content if one has been saved, otherwise a
     * sensible starting default — so the /privacy-policy page never shows
     * blank just because an admin hasn't touched Settings yet. Stored and
     * rendered as PLAIN TEXT (not raw HTML) deliberately — an admin-editable
     * textarea that got rendered as unescaped HTML would be a stored-XSS
     * risk on every visitor's browser. The page renders this with
     * `white-space: pre-line` so blank lines between sections still read
     * as paragraph breaks.
     */
    public function privacyPolicyContent(): string
    {
        return $this->privacy_policy_content ?: <<<'TEXT'
What we store

Whatever you choose to enter into any module of this application: plans, income, budgets, expenses, diet and sleep logs, health checkup records, projects, education plans, network contacts, and personal relationship notes. This can include sensitive information such as your health history and financial details.

How we use it

Your data is used only to power the features you use directly: displaying your dashboard, sending the reminders you configure, and generating summary charts. We do not sell your data or use it for advertising.

AI Planner & third parties

If you add your own Anthropic or OpenAI API key and use the AI Planner, a summary of your tracked data is sent to that provider — using your own key, at your own cost — to generate a plan. This only happens when you click "Generate New Plan"; it never runs automatically or in the background.

Your rights

- Access and export all of your data at any time from your account's Privacy & Data page.
- Permanently delete your account and every record at any time — no waiting period.
- Withdraw consent by deleting your account, since active consent is required to keep using the service.

Contact

Replace this section with your own organization's contact details and any additional disclosures required in your jurisdiction (e.g. GDPR, CCPA) before going live — this text is a starting point, not legal advice.
TEXT;
    }
}

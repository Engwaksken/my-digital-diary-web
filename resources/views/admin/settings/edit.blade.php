@extends('layouts.app')

@section('title', 'Site Settings')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-gear text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Site Settings</h1>
    </div>

    <div role="tablist" aria-label="Settings sections" class="flex gap-1 border-b border-slate-200 mb-6">
        <button type="button" role="tab" id="pm-settings-tab-branding" aria-controls="pm-settings-panel-branding"
                aria-selected="true" tabindex="0" data-tab="branding"
                onclick="pmSelectSettingsTab('branding')" onkeydown="pmSettingsTabKeydown(event, 'branding')"
                class="pm-settings-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-[var(--brand-1)] text-[var(--brand-1)]">
            <i class="fa-solid fa-image" aria-hidden="true"></i>
            <span>Branding</span>
        </button>
        <button type="button" role="tab" id="pm-settings-tab-pricing" aria-controls="pm-settings-panel-pricing"
                aria-selected="false" tabindex="-1" data-tab="pricing"
                onclick="pmSelectSettingsTab('pricing')" onkeydown="pmSettingsTabKeydown(event, 'pricing')"
                class="pm-settings-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-dollar-sign" aria-hidden="true"></i>
            <span>Pricing &amp; Trial</span>
        </button>
        <button type="button" role="tab" id="pm-settings-tab-ai" aria-controls="pm-settings-panel-ai"
                aria-selected="false" tabindex="-1" data-tab="ai"
                onclick="pmSelectSettingsTab('ai')" onkeydown="pmSettingsTabKeydown(event, 'ai')"
                class="pm-settings-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-robot" aria-hidden="true"></i>
            <span>AI Configuration</span>
        </button>
        <button type="button" role="tab" id="pm-settings-tab-privacy" aria-controls="pm-settings-panel-privacy"
                aria-selected="false" tabindex="-1" data-tab="privacy"
                onclick="pmSelectSettingsTab('privacy')" onkeydown="pmSettingsTabKeydown(event, 'privacy')"
                class="pm-settings-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-file-shield" aria-hidden="true"></i>
            <span>Privacy Policy</span>
        </button>
        <button type="button" role="tab" id="pm-settings-tab-terms" aria-controls="pm-settings-panel-terms"
                aria-selected="false" tabindex="-1" data-tab="terms"
                onclick="pmSelectSettingsTab('terms')" onkeydown="pmSettingsTabKeydown(event, 'terms')"
                class="pm-settings-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-file-contract" aria-hidden="true"></i>
            <span>Terms of Use</span>
        </button>
        <button type="button" role="tab" id="pm-settings-tab-meetings" aria-controls="pm-settings-panel-meetings"
                aria-selected="false" tabindex="-1" data-tab="meetings"
                onclick="pmSelectSettingsTab('meetings')" onkeydown="pmSettingsTabKeydown(event, 'meetings')"
                class="pm-settings-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-video" aria-hidden="true"></i>
            <span>Meeting Platforms</span>
        </button>
        <button type="button" role="tab" id="pm-settings-tab-backups" aria-controls="pm-settings-panel-backups"
                aria-selected="false" tabindex="-1" data-tab="backups"
                onclick="pmSelectSettingsTab('backups')" onkeydown="pmSettingsTabKeydown(event, 'backups')"
                class="pm-settings-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
            <span>Backups</span>
        </button>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="max-w-xl">
        @csrf

        <div role="tabpanel" id="pm-settings-panel-branding" aria-labelledby="pm-settings-tab-branding" tabindex="0"
             class="pm-settings-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 space-y-6">
            <div>
                <label for="site_name" class="block text-sm font-medium text-slate-700 mb-1">System Name</label>
                <input type="text" id="site_name" name="site_name" value="{{ old('site_name', $settings->site_name) }}"
                       required aria-required="true"
                       @error('site_name') aria-invalid="true" aria-describedby="site_name-error" @enderror
                       class="pm-input">
                <p class="text-xs text-slate-400 mt-1">Shown in the sidebar and browser tab title across the whole app.</p>
                @error('site_name')
                    <p id="site_name-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <span class="block text-sm font-medium text-slate-700 mb-1">Logo</span>
                <div class="flex items-center gap-4 mb-2">
                    @if ($settings->logoUrl())
                        <img src="{{ $settings->logoUrl() }}" alt="Current logo"
                             class="h-12 w-auto border border-slate-200 rounded p-1"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                        <span class="text-xs text-rose-500" style="display: none;">
                            Logo file not found — has <code>php artisan storage:link</code> been run on this server?
                        </span>
                    @else
                        <span class="text-xs text-slate-400">No logo uploaded — the system name is shown as text instead.</span>
                    @endif
                </div>
                <label for="logo" class="sr-only">Upload logo</label>
                <input type="file" id="logo" name="logo" accept="image/*"
                       aria-describedby="logo-hint @error('logo') logo-error @enderror"
                       @error('logo') aria-invalid="true" @enderror
                       class="block w-full text-sm">
                <p id="logo-hint" class="text-xs text-slate-400 mt-1">PNG or SVG with a transparent background works best. Max 1MB.</p>
                @error('logo')
                    <p id="logo-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <span class="block text-sm font-medium text-slate-700 mb-1">Favicon</span>
                <div class="flex items-center gap-4 mb-2">
                    @if ($settings->faviconUrl())
                        <img src="{{ $settings->faviconUrl() }}" alt="Current favicon"
                             class="h-8 w-8 border border-slate-200 rounded p-1"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                        <span class="text-xs text-rose-500" style="display: none;">
                            Favicon file not found — has <code>php artisan storage:link</code> been run on this server?
                        </span>
                    @else
                        <span class="text-xs text-slate-400">No favicon uploaded — browsers will show their default icon.</span>
                    @endif
                </div>
                <label for="favicon" class="sr-only">Upload favicon</label>
                <input type="file" id="favicon" name="favicon" accept="image/*"
                       aria-describedby="favicon-hint @error('favicon') favicon-error @enderror"
                       @error('favicon') aria-invalid="true" @enderror
                       class="block w-full text-sm">
                <p id="favicon-hint" class="text-xs text-slate-400 mt-1">Square image (e.g. 32&times;32 or 64&times;64). Max 512KB.</p>
                @error('favicon')
                    <p id="favicon-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div role="tabpanel" id="pm-settings-panel-pricing" aria-labelledby="pm-settings-tab-pricing" tabindex="0"
             class="pm-settings-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 space-y-6" hidden>
            <div>
                <span class="block text-sm font-medium text-slate-700 mb-1">Base Currency</span>
                <p class="text-xs text-slate-500 mb-3">
                    What every price in the app (subscription, expenses, income, etc.) is actually
                    denominated in. Changing this doesn't convert any existing numbers — it only
                    changes how they're displayed and labeled. To let users VIEW prices converted into
                    other currencies, use "Additional Currencies" below instead — this is the one base
                    everything else is computed from.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="default_currency_code" class="block text-xs text-slate-500 mb-1">Code</label>
                        <input type="text" id="default_currency_code" name="default_currency_code" maxlength="3"
                               value="{{ old('default_currency_code', $settings->default_currency_code) }}"
                               required aria-required="true" placeholder="UGX" class="pm-input uppercase">
                    </div>
                    <div>
                        <label for="default_currency_symbol" class="block text-xs text-slate-500 mb-1">Symbol / Label</label>
                        <input type="text" id="default_currency_symbol" name="default_currency_symbol" maxlength="10"
                               value="{{ old('default_currency_symbol', $settings->default_currency_symbol) }}"
                               required aria-required="true" placeholder="UGX" class="pm-input">
                    </div>
                    <div>
                        <label for="default_currency_decimals" class="block text-xs text-slate-500 mb-1">Decimal Places</label>
                        <input type="number" id="default_currency_decimals" name="default_currency_decimals" min="0" max="4"
                               value="{{ old('default_currency_decimals', $settings->default_currency_decimals) }}"
                               required aria-required="true" class="pm-input">
                    </div>
                </div>
                @error('default_currency_code')
                    <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="monthly_price" class="block text-sm font-medium text-slate-700 mb-1">Monthly Subscription Price</label>
                <input type="number" id="monthly_price" name="monthly_price" step="0.01" min="0"
                       value="{{ old('monthly_price', $settings->monthly_price) }}"
                       required aria-required="true"
                       @error('monthly_price') aria-invalid="true" aria-describedby="monthly_price-error" @enderror
                       class="pm-input">
                <p class="text-xs text-slate-400 mt-1">
                    In the base currency above (e.g. 50000 for "UGX 50,000") — shown on the subscribe
                    page and used to charge card payments. Every subscription tier's price at
                    Admin &rarr; Subscription Plans is computed from this base rate.
                </p>
                @error('monthly_price')
                    <p id="monthly_price-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="trial_days" class="block text-sm font-medium text-slate-700 mb-1">Free Trial Period (days)</label>
                <input type="number" id="trial_days" name="trial_days" step="1" min="0" max="365"
                       value="{{ old('trial_days', $settings->trial_days) }}"
                       required aria-required="true"
                       @error('trial_days') aria-invalid="true" aria-describedby="trial_days-error" @enderror
                       class="pm-input">
                <p class="text-xs text-slate-400 mt-1">
                    How long a new signup gets free access before needing to subscribe. Only applies to
                    accounts created AFTER you change this — existing users' trial end dates don't move.
                </p>
                @error('trial_days')
                    <p id="trial_days-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="border-t border-slate-100 pt-6">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="font-semibold text-slate-800">Additional Currencies</h2>
                    <button type="button" onclick="pmAddCurrencyRow()" class="text-sm text-[var(--brand-1)] hover:underline">
                        <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i> Add Currency
                    </button>
                </div>
                <p class="text-xs text-slate-500 mb-4">
                    Lets users VIEW the subscription price converted into another currency — a display
                    convenience only. Actual charges (card, mobile money) still process in the base price
                    above; this doesn't set up multi-currency billing with any payment gateway.
                    Enter how many {{ $settings->default_currency_code }} equal 1 unit of the additional currency.
                    Example: if 1 USD = UGX 3,700, enter 3700. Display conversion is calculated as
                    {{ $settings->default_currency_code }} amount ÷ rate.
                </p>

                <div id="pm-currency-rows" class="space-y-2 mb-3">
                    @php $currencies = old('currency_codes') ? collect(old('currency_codes'))->map(fn($c, $i) => ['code' => $c, 'symbol' => old('currency_symbols')[$i] ?? '', 'rate' => old('currency_rates')[$i] ?? '']) : collect($settings->supported_currencies ?? []) @endphp
                    @foreach ($currencies as $currency)
                        <div class="grid grid-cols-12 gap-2 pm-currency-row">
                            <input type="text" name="currency_codes[]" value="{{ $currency['code'] }}" placeholder="Code (e.g. KES)" maxlength="3" class="col-span-4 pm-input text-sm uppercase">
                            <input type="text" name="currency_symbols[]" value="{{ $currency['symbol'] }}" placeholder="Symbol (e.g. KSh)" maxlength="5" class="col-span-3 pm-input text-sm">
                            <input type="number" name="currency_rates[]" value="{{ $currency['rate'] }}" placeholder="{{ $settings->default_currency_code . ' per 1 ' . ($currency['code'] ?: 'currency') }}" min="0" step="0.0001" class="col-span-4 pm-input text-sm">
                            <button type="button" onclick="this.closest('.pm-currency-row').remove()" class="col-span-1 text-rose-500 hover:text-rose-700" aria-label="Remove currency">
                                <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <script>
    window.pmInitialSettingsTab = @json(request('tab', old('privacy_policy_version') !== null || old('privacy_policy_content') !== null ? 'privacy' : (old('terms_of_use_version') !== null || old('terms_of_use_content') !== null ? 'terms' : 'branding')));
</script>

<script>
            function pmAddCurrencyRow() {
                var container = document.getElementById('pm-currency-rows');
                var row = document.createElement('div');
                row.className = 'grid grid-cols-12 gap-2 pm-currency-row';
                row.innerHTML =
                    '<input type="text" name="currency_codes[]" placeholder="Code (e.g. KES)" maxlength="3" class="col-span-4 pm-input text-sm uppercase">' +
                    '<input type="text" name="currency_symbols[]" placeholder="Symbol (e.g. KSh)" maxlength="5" class="col-span-3 pm-input text-sm">' +
                    '<input type="number" name="currency_rates[]" placeholder="{{ $settings->default_currency_code }} per 1 currency" min="0" step="0.0001" class="col-span-4 pm-input text-sm">' +
                    '<button type="button" onclick="this.closest(\'.pm-currency-row\').remove()" class="col-span-1 text-rose-500 hover:text-rose-700" aria-label="Remove currency">' +
                        '<i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>' +
                    '</button>';
                container.appendChild(row);
            }
        </script>

        <div role="tabpanel" id="pm-settings-panel-ai" aria-labelledby="pm-settings-tab-ai" tabindex="0"
             class="pm-settings-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 space-y-4" hidden>
            <h2 class="font-semibold text-slate-800 mb-1">Default Free AI Plan</h2>
            <p class="text-xs text-slate-500 mb-4">
                Optional — supply your own API key here to let EVERY user generate AI plans for free
                (up to the monthly limit below) without adding their own key first. A user's own key,
                if they add one at "API Keys", always takes priority over this shared one and has no
                monthly limit. Real API usage cost for shared-key generations is billed to whichever
                account this key belongs to, not to individual users — that's the whole point of the
                per-user monthly cap.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="default_ai_provider" class="block text-sm font-medium text-slate-700 mb-1">Provider</label>
                    <select id="default_ai_provider" name="default_ai_provider" class="pm-input">
                        <option value="" @selected(!old('default_ai_provider', $settings->default_ai_provider))>Not configured</option>
                        @foreach ($aiProviders->where('is_enabled', true) as $provider)
                            <option value="{{ $provider->key }}" @selected(old('default_ai_provider', $settings->default_ai_provider) === $provider->key)>{{ $provider->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="default_ai_free_limit_per_month" class="block text-sm font-medium text-slate-700 mb-1">Free plans per user / month</label>
                    <input type="number" id="default_ai_free_limit_per_month" name="default_ai_free_limit_per_month" min="0" max="1000"
                           value="{{ old('default_ai_free_limit_per_month', $settings->default_ai_free_limit_per_month) }}"
                           class="pm-input">
                </div>
            </div>

            <div>
                <label for="default_ai_api_key" class="block text-sm font-medium text-slate-700 mb-1">API Key</label>
                <input type="password" id="default_ai_api_key" name="default_ai_api_key" autocomplete="off"
                       placeholder="{{ $settings->hasDefaultAiKey() ? '•••••••• (saved — leave blank to keep it)' : 'sk-... or sk-ant-...' }}"
                       class="pm-input">
                <p class="text-xs text-slate-400 mt-1">Stored encrypted. Leave blank when editing to keep the current key.</p>
            </div>

            <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                Save Settings
            </button>

            <div class="border-t border-slate-100 pt-6 mt-6">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold text-slate-800">AI Providers</h2>
                    <button type="button" onclick="document.getElementById('ai-provider-create-modal').showModal()"
                            class="text-sm text-[var(--brand-1)] hover:underline">
                        <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i> Add Provider
                    </button>
                </div>
                <p class="text-xs text-slate-500 mb-4">
                    Claude and ChatGPT are built in. Add any other OpenAI-compatible provider (Groq, Mistral,
                    Together.ai, Perplexity, a self-hosted Ollama server, etc.) by its API base URL and model
                    name — most providers that offer a "chat completions" endpoint work this way.
                </p>

                <div class="space-y-2">
                    @foreach ($aiProviders as $provider)
                        <div class="flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <div>
                                <span class="font-medium text-slate-700">{{ $provider->name }}</span>
                                @if ($provider->isBuiltIn())
                                    <span class="text-xs text-slate-400 ml-1">(built-in)</span>
                                @else
                                    <span class="text-xs text-slate-400 ml-1 font-mono">{{ $provider->default_model }}</span>
                                @endif
                                @unless ($provider->is_enabled)
                                    <span class="text-xs text-slate-400 ml-1">— disabled</span>
                                @endunless
                            </div>
                            @unless ($provider->isBuiltIn())
                                <div class="flex items-center gap-3">
                                    <form action="{{ route('admin.ai-providers.toggle', $provider->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="text-[var(--brand-1)] hover:underline">
                                            {{ $provider->is_enabled ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                    <button type="button" onclick="document.getElementById('ai-provider-edit-modal-{{ $provider->id }}').showModal()" class="text-[var(--brand-1)] hover:underline">Edit</button>
                                    <form action="{{ route('admin.ai-providers.destroy', $provider->id) }}" method="POST"
                                          data-confirm="Remove {{ $provider->name }}? Any user using it as their active key will need to pick a new one." data-confirm-title="Remove AI provider?" data-confirm-text="Remove">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:underline">Delete</button>
                                    </form>
                                </div>
                            @endunless
                        </div>
                    @endforeach
                </div>
            </div>
        </div>


    </form>


    <form method="POST" action="{{ route('admin.settings.privacy.update') }}" class="max-w-4xl">
        @csrf
        <div role="tabpanel" id="pm-settings-panel-privacy" aria-labelledby="pm-settings-tab-privacy" tabindex="0"
             class="pm-settings-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 space-y-4" hidden>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">Privacy Policy</h2>
                    <p class="text-xs text-slate-500 mt-1">Edit the live Privacy Policy shown to users and visitors.</p>
                </div>
                <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener noreferrer"
                   class="text-xs font-semibold text-[var(--brand-1)] hover:underline whitespace-nowrap">View live page</a>
            </div>

            @if ($errors->has('privacy_policy_version') || $errors->has('privacy_policy_content'))
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    Please correct the Privacy Policy fields below and save again.
                </div>
            @endif

            <p class="text-xs text-slate-500">
                Shown publicly at <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener noreferrer" class="text-[var(--brand-1)] hover:underline">/privacy-policy<span class="sr-only"> (opens in a new tab)</span></a>
                and referenced from every user's Privacy &amp; Data page. Changes here update the live page
                immediately for everyone — there's no separate "publish" step.
            </p>

            <div>
                <label for="privacy_policy_version" class="block text-sm font-medium text-slate-700 mb-1">Version</label>
                <input type="text" id="privacy_policy_version" name="privacy_policy_version"
                       value="{{ old('privacy_policy_version', $settings->privacy_policy_version) }}"
                       required aria-required="true" @error('privacy_policy_version') aria-invalid="true" @enderror class="pm-input max-w-xs">
                @error('privacy_policy_version')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
                <p class="text-xs text-slate-400 mt-1">
                    A plain label shown at the top of the page (e.g. "1.0", "2.1", "2026-08-20") — bump this
                    whenever you make a meaningful change, so it's visibly a new version to anyone re-reading it.
                </p>
            </div>

            <div>
                <label for="privacy_policy_content" class="block text-sm font-medium text-slate-700 mb-1">Content</label>
                <textarea id="privacy_policy_content" name="privacy_policy_content" rows="16"
                          @error('privacy_policy_content') aria-invalid="true" @enderror
                          class="pm-input font-mono text-sm">{{ old('privacy_policy_content', $settings->privacy_policy_content ?: $settings->privacyPolicyContent()) }}</textarea>
                @error('privacy_policy_content')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
                <p class="text-xs text-slate-400 mt-1">
                    Plain text only — no HTML tags (they won't render as HTML; they'll show up literally as
                    text on the page, since raw HTML input here isn't executed, for every visitor's security).
                    Leave a blank line between sections to create a paragraph break. Leave this entirely
                    blank to fall back to the built-in starting template.
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener noreferrer"
                   class="px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">
                    Preview
                </a>
                <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i> Save Privacy Policy
                </button>
            </div>
        </div>

    </form>

    <form method="POST" action="{{ route('admin.settings.terms.update') }}" class="max-w-xl">
        @csrf
        <div role="tabpanel" id="pm-settings-panel-terms" aria-labelledby="pm-settings-tab-terms" tabindex="0"
             class="pm-settings-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 space-y-4" hidden>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">Terms of Use</h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Edit the live Terms of Use shown to users. Saving here updates the public page immediately.
                    </p>
                </div>
                <a href="{{ Route::has('terms-of-use') ? route('terms-of-use') : url('/terms-of-use') }}" target="_blank" rel="noopener noreferrer"
                   class="text-xs font-semibold text-[var(--brand-1)] hover:underline whitespace-nowrap">
                    View live page
                </a>
            </div>

            @if ($errors->has('terms_of_use_version') || $errors->has('terms_of_use_content'))
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    Please correct the Terms of Use fields below and save again.
                </div>
            @endif

            <div>
                <label for="terms_of_use_version" class="block text-sm font-medium text-slate-700 mb-1">Version</label>
                <input type="text" id="terms_of_use_version" name="terms_of_use_version"
                       value="{{ old('terms_of_use_version', $settings->terms_of_use_version ?: '1.0') }}"
                       required aria-required="true" class="pm-input max-w-xs">
                @error('terms_of_use_version')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="terms_of_use_content" class="block text-sm font-medium text-slate-700 mb-1">Content</label>
                <textarea id="terms_of_use_content" name="terms_of_use_content" rows="22"
                          class="pm-input font-mono text-sm">{{ old('terms_of_use_content', $settings->terms_of_use_content ?: $settings->termsOfUseContent()) }}</textarea>
                <p class="text-xs text-slate-400 mt-1">
                    Use plain text with blank lines between sections. Leave the content blank only if you want to use the built-in default terms.
                </p>
                @error('terms_of_use_content')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                <a href="{{ Route::has('terms-of-use') ? route('terms-of-use') : url('/terms-of-use') }}" target="_blank" rel="noopener noreferrer"
                   class="px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">
                    Preview
                </a>
                <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i> Save Terms of Use
                </button>
            </div>
        </div>
    </form>

    <div role="tabpanel" id="pm-settings-panel-meetings" aria-labelledby="pm-settings-tab-meetings" tabindex="0"
         class="pm-settings-panel space-y-4" hidden>
        <p class="text-sm text-slate-500 pm-card-bg shadow-sm border border-slate-100 rounded-xl p-4">
            Register this app as an "OAuth app" on each platform's own developer console first (each
            gives you a Client ID and Client Secret), then paste them here and activate. This alone
            doesn't fetch anyone's meetings — it's what lets a USER then connect their own account
            from the Meetings page to fetch theirs.
        </p>

        @foreach ($meetingPlatforms as $platform)
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <form method="POST" action="{{ route('admin.meeting-platforms.update', $platform->id) }}">
                    @csrf
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                        <div>
                            <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                                <i class="fa-solid fa-video text-slate-400" aria-hidden="true"></i>
                                {{ $platform->name }}
                            </h3>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                @if ($platform->isConfigured())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 font-medium text-emerald-700 border border-emerald-200">
                                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Credentials configured
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-1 font-medium text-amber-700 border border-amber-200">
                                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Credentials incomplete
                                    </span>
                                @endif
                                <span class="{{ $platform->is_enabled ? 'text-emerald-700' : 'text-slate-500' }}">
                                    {{ $platform->is_enabled ? 'Active for users' : 'Inactive for users' }}
                                </span>
                            </div>
                        </div>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <span class="text-xs text-slate-500">Enable</span>
                            <input type="checkbox" name="is_enabled" value="1" @checked($platform->is_enabled)
                                   class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="client_id_{{ $platform->id }}" class="block text-xs text-slate-500 mb-1">Client ID</label>
                            <input type="text" id="client_id_{{ $platform->id }}" name="client_id"
                                   value="{{ old('client_id', $platform->client_id) }}"
                                   autocomplete="off"
                                   placeholder="Enter Client ID"
                                   class="pm-input text-sm font-mono">
                            <p class="text-[11px] text-slate-400 mt-1">Client ID is safe to display here and remains editable.</p>
                        </div>
                        <div>
                            <label for="client_secret_{{ $platform->id }}" class="block text-xs text-slate-500 mb-1">Client Secret</label>
                            <div class="relative">
                                <input type="password" id="client_secret_{{ $platform->id }}" name="client_secret"
                                       value="" autocomplete="new-password"
                                       placeholder="{{ $platform->client_secret ? 'Configured — enter a new secret only to replace it' : 'Enter Client Secret' }}"
                                       class="pm-input text-sm pr-10">
                                <button type="button"
                                        onclick="pmToggleMeetingSecret('client_secret_{{ $platform->id }}', this)"
                                        class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-600"
                                        aria-label="Show or hide the Client Secret being entered">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            @if ($platform->client_secret)
                                <p class="text-[11px] text-emerald-600 mt-1">
                                    <i class="fa-solid fa-lock" aria-hidden="true"></i> Saved securely. Leave blank to keep the existing secret.
                                </p>
                            @else
                                <p class="text-[11px] text-slate-400 mt-1">No Client Secret saved yet.</p>
                            @endif
                        </div>
                    </div>

                    <p class="text-xs text-slate-400 mt-2">
                        Redirect URI to register on {{ $platform->name }}'s developer console:
                        <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">{{ url('/meetings/connect/' . $platform->platform . '/callback') }}</code>
                    </p>

                    <button type="submit" class="mt-3 btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                        Save
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    <div role="tabpanel" id="pm-settings-panel-backups" aria-labelledby="pm-settings-tab-backups" tabindex="0"
         class="pm-settings-panel space-y-5" hidden>
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
                <div>
                    <h3 class="font-semibold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-cloud-arrow-up text-[var(--brand-1)]"></i> Automatic User Data Backups</h3>
                    <p class="text-xs text-slate-500 mt-1">Create scheduled database and uploaded-file backups. Default scheduling uses Africa/Kampala time.</p>
                </div>
                @if($backupSetting->last_run_at)
                    <span class="text-xs text-slate-500">Last backup: {{ $backupSetting->last_run_at->timezone($backupSetting->timezone ?: 'Africa/Kampala')->format('d M Y, H:i') }}</span>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.backups.update') }}" class="space-y-4">
                @csrf
                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="enabled" value="1" @checked($backupSetting->enabled) class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                    Enable automatic backups
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div><label class="block text-xs font-medium text-slate-600 mb-1">Frequency</label><select name="frequency" class="pm-input"><option value="daily" @selected($backupSetting->frequency==='daily')>Daily</option><option value="weekly" @selected($backupSetting->frequency==='weekly')>Weekly</option><option value="monthly" @selected($backupSetting->frequency==='monthly')>Monthly</option></select></div>
                    <div><label class="block text-xs font-medium text-slate-600 mb-1">Weekly day</label><select name="day_of_week" class="pm-input">@foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $i=>$day)<option value="{{ $i }}" @selected((int)$backupSetting->day_of_week===$i)>{{ $day }}</option>@endforeach</select></div>
                    <div><label class="block text-xs font-medium text-slate-600 mb-1">Monthly day</label><input type="number" min="1" max="28" name="day_of_month" value="{{ $backupSetting->day_of_month ?: 1 }}" class="pm-input"></div>
                    <div><label class="block text-xs font-medium text-slate-600 mb-1">Backup time</label><input type="time" name="run_time" value="{{ substr((string)$backupSetting->run_time,0,5) }}" class="pm-input"></div>
                    <div><label class="block text-xs font-medium text-slate-600 mb-1">Timezone</label><input type="text" name="timezone" value="{{ $backupSetting->timezone ?: 'Africa/Kampala' }}" class="pm-input" placeholder="Africa/Kampala"></div>
                    <div><label class="block text-xs font-medium text-slate-600 mb-1">What to back up</label><select name="backup_type" class="pm-input"><option value="both" @selected($backupSetting->backup_type==='both')>Database + files</option><option value="database" @selected($backupSetting->backup_type==='database')>Database only</option><option value="files" @selected($backupSetting->backup_type==='files')>Files only</option></select></div>
                    <div><label class="block text-xs font-medium text-slate-600 mb-1">Storage</label><select name="provider" id="backup-provider" class="pm-input" onchange="pmToggleBackupCloudFields()"><option value="local" @selected($backupSetting->provider==='local')>Local server</option><option value="s3" @selected($backupSetting->provider==='s3')>S3 / S3-compatible cloud</option></select></div>
                    <div><label class="block text-xs font-medium text-slate-600 mb-1">Retention</label><select name="retention_days" class="pm-input">@foreach([7,30,60,90,180,365,730] as $days)<option value="{{ $days }}" @selected((int)$backupSetting->retention_days===$days)>{{ $days }} days</option>@endforeach</select></div>
                </div>

                <div id="backup-s3-fields" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div><label class="block text-xs font-medium text-slate-600 mb-1">Access Key</label><input type="text" name="s3_key" value="{{ $backupSetting->s3_key }}" autocomplete="off" class="pm-input"></div>
                        <div><label class="block text-xs font-medium text-slate-600 mb-1">Secret Key</label><input type="password" name="s3_secret" value="" autocomplete="new-password" placeholder="{{ $backupSetting->s3_secret ? 'Saved securely — enter only to replace' : 'Secret key' }}" class="pm-input"></div>
                        <div><label class="block text-xs font-medium text-slate-600 mb-1">Bucket</label><input type="text" name="s3_bucket" value="{{ $backupSetting->s3_bucket }}" class="pm-input"></div>
                        <div><label class="block text-xs font-medium text-slate-600 mb-1">Region</label><input type="text" name="s3_region" value="{{ $backupSetting->s3_region }}" placeholder="us-east-1" class="pm-input"></div>
                        <div class="sm:col-span-2"><label class="block text-xs font-medium text-slate-600 mb-1">Endpoint (optional for S3-compatible providers)</label><input type="url" name="s3_endpoint" value="{{ $backupSetting->s3_endpoint }}" placeholder="https://..." class="pm-input"></div>
                        <label class="sm:col-span-2 inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="s3_path_style" value="1" @checked($backupSetting->s3_path_style) class="rounded border-slate-300"> Use path-style endpoint</label>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold">Save Backup Settings</button>
                </div>
            </form>
        </div>

        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div><h3 class="font-semibold text-slate-800">Backup Now</h3><p class="text-xs text-slate-500 mt-1">Create an immediate backup using the configured storage destination.</p></div>
                <form id="pm-manual-backup-form" method="POST" action="{{ route('admin.backups.run') }}" data-confirm="Create a backup now? Depending on the amount of data, this may take a few minutes." data-confirm-title="Create backup now?" data-confirm-text="Create backup" data-confirm-danger="false" class="flex gap-2">
                    @csrf
                    <select name="backup_type" class="pm-input text-sm"><option value="both">Database + files</option><option value="database">Database only</option><option value="files">Files only</option></select>
                    <button class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-semibold"><i class="fa-solid fa-database mr-1"></i> Backup Now</button>
                </form>
            </div>

            <div class="overflow-x-auto pm-admin-table-scroll">
                <table class="min-w-full text-sm pm-admin-horizontal-table">
                    <thead><tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500"><th class="py-2 pr-4">Date</th><th class="py-2 pr-4">Type</th><th class="py-2 pr-4">Storage</th><th class="py-2 pr-4">Size</th><th class="py-2 pr-4">Status</th><th class="py-2">Triggered by</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($backupHistory as $backup)
                        <tr><td class="py-3 pr-4 whitespace-nowrap">{{ optional($backup->started_at)->format('d M Y H:i') }}</td><td class="py-3 pr-4">{{ ucfirst($backup->backup_type) }}</td><td class="py-3 pr-4">{{ strtoupper($backup->provider) }}</td><td class="py-3 pr-4">{{ $backup->size_bytes ? number_format($backup->size_bytes/1024/1024,2).' MB' : '—' }}</td><td class="py-3 pr-4"><span class="font-medium {{ $backup->status==='completed' ? 'text-emerald-600' : ($backup->status==='failed' ? 'text-rose-600' : 'text-amber-600') }}">{{ ucfirst($backup->status) }}</span>@if($backup->error_message)<div class="text-xs text-rose-500 max-w-xs truncate" title="{{ $backup->error_message }}">{{ $backup->error_message }}</div>@endif</td><td class="py-3">{{ $backup->trigger==='manual' ? ($backup->creator->name ?? 'Admin') : 'Scheduler' }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-slate-400">No backups created yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.settings._ai-provider-modal', ['provider' => new \App\Models\AiProvider, 'modalId' => 'ai-provider-create-modal', 'action' => route('admin.ai-providers.store'), 'method' => null])
    @foreach ($aiProviders->reject->isBuiltIn() as $provider)
            @include('admin.settings._ai-provider-modal', ['provider' => $provider, 'modalId' => 'ai-provider-edit-modal-' . $provider->id, 'action' => route('admin.ai-providers.update', $provider->id), 'method' => 'PUT'])
    @endforeach

    <script>
        function pmToggleMeetingSecret(inputId, button) {
            var input = document.getElementById(inputId);
            if (!input) return;
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            var icon = button ? button.querySelector('i') : null;
            if (icon) {
                icon.classList.toggle('fa-eye', showing);
                icon.classList.toggle('fa-eye-slash', !showing);
            }
        }

        function pmSelectSettingsTab(key) {
            document.querySelectorAll('.pm-settings-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected);
                btn.classList.toggle('border-transparent', !isSelected);
                btn.classList.toggle('text-slate-500', !isSelected);
                if (isSelected) { btn.focus(); }
            });
            document.querySelectorAll('.pm-settings-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-settings-panel-' + key;
            });
        }

        function pmSettingsTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-settings-tab'), function (t) { return t.dataset.tab; });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else if (event.key === 'Home') { nextIndex = 0; }
            else if (event.key === 'End') { nextIndex = tabs.length - 1; }
            else { return; }

            event.preventDefault();
            pmSelectSettingsTab(tabs[nextIndex]);
        }

        function pmToggleBackupCloudFields() {
            var provider = document.getElementById('backup-provider');
            var fields = document.getElementById('backup-s3-fields');
            if (provider && fields) fields.style.display = provider.value === 's3' ? 'block' : 'none';
        }

        // If a field in a hidden tab has a validation error, switch to
        // that tab automatically so the error isn't invisible.
        document.addEventListener('DOMContentLoaded', function () {
            pmToggleBackupCloudFields();
            var firstErrorField = document.querySelector('[aria-invalid="true"]');
            if (firstErrorField) {
                var panel = firstErrorField.closest('.pm-settings-panel');
                if (panel) {
                    var key = panel.id.replace('pm-settings-panel-', '');
                    pmSelectSettingsTab(key);
                    return;
                }
            }
            pmSelectSettingsTab(window.pmInitialSettingsTab || 'branding');
        });
    </script>
@endsection

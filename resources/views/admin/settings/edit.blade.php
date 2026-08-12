@extends('layouts.app')

@section('title', 'Site Settings')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-gear text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Site Settings</h1>
    </div>

    <div class="w-full overflow-x-auto overscroll-x-contain mb-6 -mx-1 px-1 pb-1" style="-webkit-overflow-scrolling: touch;">
        <div role="tablist" aria-label="Settings sections" class="inline-flex min-w-max gap-1 border-b border-slate-200">
        <button type="button" role="tab" id="pm-settings-tab-branding" aria-controls="pm-settings-panel-branding"
                aria-selected="true" tabindex="0" data-tab="branding"
                onclick="pmSelectSettingsTab('branding')" onkeydown="pmSettingsTabKeydown(event, 'branding')"
                class="pm-settings-tab flex shrink-0 items-center gap-2 px-3 sm:px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-[var(--brand-1)] text-[var(--brand-1)]">
            <i class="fa-solid fa-image" aria-hidden="true"></i>
            <span>Branding</span>
        </button>
        <button type="button" role="tab" id="pm-settings-tab-pricing" aria-controls="pm-settings-panel-pricing"
                aria-selected="false" tabindex="-1" data-tab="pricing"
                onclick="pmSelectSettingsTab('pricing')" onkeydown="pmSettingsTabKeydown(event, 'pricing')"
                class="pm-settings-tab flex shrink-0 items-center gap-2 px-3 sm:px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-dollar-sign" aria-hidden="true"></i>
            <span>Pricing &amp; Trial</span>
        </button>
        <button type="button" role="tab" id="pm-settings-tab-ai" aria-controls="pm-settings-panel-ai"
                aria-selected="false" tabindex="-1" data-tab="ai"
                onclick="pmSelectSettingsTab('ai')" onkeydown="pmSettingsTabKeydown(event, 'ai')"
                class="pm-settings-tab flex shrink-0 items-center gap-2 px-3 sm:px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-robot" aria-hidden="true"></i>
            <span>AI Configuration</span>
        </button>
        <button type="button" role="tab" id="pm-settings-tab-privacy" aria-controls="pm-settings-panel-privacy"
                aria-selected="false" tabindex="-1" data-tab="privacy"
                onclick="pmSelectSettingsTab('privacy')" onkeydown="pmSettingsTabKeydown(event, 'privacy')"
                class="pm-settings-tab flex shrink-0 items-center gap-2 px-3 sm:px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-file-shield" aria-hidden="true"></i>
            <span>Privacy Policy</span>
        </button>
        <button type="button" role="tab" id="pm-settings-tab-meetings" aria-controls="pm-settings-panel-meetings"
                aria-selected="false" tabindex="-1" data-tab="meetings"
                onclick="pmSelectSettingsTab('meetings')" onkeydown="pmSettingsTabKeydown(event, 'meetings')"
                class="pm-settings-tab flex shrink-0 items-center gap-2 px-3 sm:px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-video" aria-hidden="true"></i>
            <span>Meeting Platforms</span>
        </button>
        </div>
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
                    "Rate" is how many units of that currency equal 1 {{ $settings->default_currency_code }}.
                </p>

                <div id="pm-currency-rows" class="space-y-2 mb-3">
                    @php $currencies = old('currency_codes') ? collect(old('currency_codes'))->map(fn($c, $i) => ['code' => $c, 'symbol' => old('currency_symbols')[$i] ?? '', 'rate' => old('currency_rates')[$i] ?? '']) : collect($settings->supported_currencies ?? []) @endphp
                    @foreach ($currencies as $currency)
                        <div class="grid grid-cols-12 gap-2 pm-currency-row">
                            <input type="text" name="currency_codes[]" value="{{ $currency['code'] }}" placeholder="Code (e.g. KES)" maxlength="3" class="col-span-4 pm-input text-sm uppercase">
                            <input type="text" name="currency_symbols[]" value="{{ $currency['symbol'] }}" placeholder="Symbol (e.g. KSh)" maxlength="5" class="col-span-3 pm-input text-sm">
                            <input type="number" name="currency_rates[]" value="{{ $currency['rate'] }}" placeholder="{{ 'Rate per 1 ' . $settings->default_currency_code }}" min="0" step="0.0001" class="col-span-4 pm-input text-sm">
                            <button type="button" onclick="this.closest('.pm-currency-row').remove()" class="col-span-1 text-rose-500 hover:text-rose-700" aria-label="Remove currency">
                                <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <script>
            function pmAddCurrencyRow() {
                var container = document.getElementById('pm-currency-rows');
                var row = document.createElement('div');
                row.className = 'grid grid-cols-12 gap-2 pm-currency-row';
                row.innerHTML =
                    '<input type="text" name="currency_codes[]" placeholder="Code (e.g. KES)" maxlength="3" class="col-span-4 pm-input text-sm uppercase">' +
                    '<input type="text" name="currency_symbols[]" placeholder="Symbol (e.g. KSh)" maxlength="5" class="col-span-3 pm-input text-sm">' +
                    '<input type="number" name="currency_rates[]" placeholder="{{ 'Rate per 1 ' . $settings->default_currency_code }}" min="0" step="0.0001" class="col-span-4 pm-input text-sm">' +
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
                                          onsubmit="return confirm('Remove {{ $provider->name }}? Any user using it as their active key will need to pick a new one.');">
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

        <div role="tabpanel" id="pm-settings-panel-privacy" aria-labelledby="pm-settings-tab-privacy" tabindex="0"
             class="pm-settings-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 space-y-4" hidden>
            <p class="text-xs text-slate-500">
                Shown publicly at <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener noreferrer" class="text-[var(--brand-1)] hover:underline">/privacy-policy<span class="sr-only"> (opens in a new tab)</span></a>
                and referenced from every user's Privacy &amp; Data page. Changes here update the live page
                immediately for everyone — there's no separate "publish" step.
            </p>

            <div>
                <label for="privacy_policy_version" class="block text-sm font-medium text-slate-700 mb-1">Version</label>
                <input type="text" id="privacy_policy_version" name="privacy_policy_version"
                       value="{{ old('privacy_policy_version', $settings->privacy_policy_version) }}"
                       class="pm-input w-full sm:max-w-xs">
                <p class="text-xs text-slate-400 mt-1">
                    Optional version label shown at the top of the page (e.g. "1.0", "2.1", "2026-08-20").
                    Leave it blank to keep the current version.
                </p>
            </div>

            <div>
                <label for="privacy_policy_content" class="block text-sm font-medium text-slate-700 mb-1">Content</label>
                <textarea id="privacy_policy_content" name="privacy_policy_content" rows="16"
                          class="pm-input font-mono text-sm">{{ old('privacy_policy_content', $settings->privacy_policy_content) }}</textarea>
                <p class="text-xs text-slate-400 mt-1">
                    Plain text only — no HTML tags (they won't render as HTML; they'll show up literally as
                    text on the page, since raw HTML input here isn't executed, for every visitor's security).
                    Leave a blank line between sections to create a paragraph break. Leave this entirely
                    blank to fall back to the built-in starting template.
                </p>
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
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-video text-slate-400" aria-hidden="true"></i>
                            {{ $platform->name }}
                        </h3>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <span class="text-xs text-slate-500">{{ $platform->is_enabled ? 'Active' : 'Inactive' }}</span>
                            <input type="checkbox" name="is_enabled" value="1" @checked($platform->is_enabled)
                                   class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="client_id_{{ $platform->id }}" class="block text-xs text-slate-500 mb-1">Client ID</label>
                            <input type="text" id="client_id_{{ $platform->id }}" name="client_id"
                                   placeholder="{{ $platform->client_id ? 'Saved — leave blank to keep it' : 'Not set' }}"
                                   class="pm-input text-sm">
                        </div>
                        <div>
                            <label for="client_secret_{{ $platform->id }}" class="block text-xs text-slate-500 mb-1">Client Secret</label>
                            <input type="password" id="client_secret_{{ $platform->id }}" name="client_secret"
                                   placeholder="{{ $platform->client_secret ? 'Saved — leave blank to keep it' : 'Not set' }}"
                                   class="pm-input text-sm">
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

    @include('admin.settings._ai-provider-modal', ['provider' => new \App\Models\AiProvider, 'modalId' => 'ai-provider-create-modal', 'action' => route('admin.ai-providers.store'), 'method' => null])
    @foreach ($aiProviders->reject->isBuiltIn() as $provider)
        @include('admin.settings._ai-provider-modal', ['provider' => $provider, 'modalId' => 'ai-provider-edit-modal-' . $provider->id, 'action' => route('admin.ai-providers.update', $provider->id), 'method' => 'PUT'])
    @endforeach

    <script>
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

        // If a field in a hidden tab has a validation error, switch to
        // that tab automatically so the error isn't invisible.
        document.addEventListener('DOMContentLoaded', function () {
            var firstErrorField = document.querySelector('[aria-invalid="true"]');
            if (firstErrorField) {
                var panel = firstErrorField.closest('.pm-settings-panel');
                if (panel) {
                    var key = panel.id.replace('pm-settings-panel-', '');
                    pmSelectSettingsTab(key);
                }
            }
        });
    </script>
@endsection

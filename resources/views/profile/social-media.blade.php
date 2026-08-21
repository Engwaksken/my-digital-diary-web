@extends('layouts.app')

@section('title', 'Social Media Accounts')

@section('content')
<style>
@media (max-width: 640px) {
    .social-settings-page { width:100%; max-width:100%; overflow-x:hidden; }
    .social-settings-grid { grid-template-columns:minmax(0,1fr)!important; }
    .social-account-row { display:grid!important; grid-template-columns:minmax(0,1fr) auto; align-items:center; width:100%; }
    .social-account-main { min-width:0; }
    .social-account-main > div { overflow-wrap:anywhere; word-break:break-word; }
}
</style>
<div class="space-y-4 max-w-5xl social-settings-page">
    <div class="apple-surface rounded-2xl p-5">
        <h1 class="text-xl font-black">Social Media Accounts</h1>
        <p class="mt-1 text-sm text-slate-500">
            Manage the accounts and WhatsApp details used by your Social Media Planner.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2 social-settings-grid">
        <section class="apple-surface rounded-2xl p-5">
            <h2 class="font-black">WhatsApp</h2>
            <p class="mt-1 text-xs text-slate-500">
                Set the number used for Status sharing and your WhatsApp Channel details.
            </p>

            <form method="POST" action="{{ route('profile.social-media.whatsapp') }}" class="mt-4 space-y-3">
                @csrf
                @method('PUT')

                <div>
                    <label class="text-xs font-bold">WhatsApp number</label>
                    <input name="whatsapp_number"
                           value="{{ old('whatsapp_number', auth()->user()->whatsapp_number) }}"
                           placeholder="+2567XXXXXXXX"
                           class="pm-input mt-1 w-full">
                </div>

                <div>
                    <label class="text-xs font-bold">WhatsApp Channel name</label>
                    <input name="whatsapp_channel_name"
                           value="{{ old('whatsapp_channel_name', auth()->user()->whatsapp_channel_name) }}"
                           class="pm-input mt-1 w-full">
                </div>

                <div>
                    <label class="text-xs font-bold">WhatsApp Channel link</label>
                    <input name="whatsapp_channel_url"
                           value="{{ old('whatsapp_channel_url', auth()->user()->whatsapp_channel_url) }}"
                           placeholder="https://whatsapp.com/channel/..."
                           class="pm-input mt-1 w-full">
                </div>

                <button class="btn-primary rounded-xl px-4 py-2.5 text-sm font-bold text-white">
                    Save WhatsApp Settings
                </button>
            </form>
        </section>

        <section class="apple-surface rounded-2xl p-5">
            <h2 class="font-black">Add Social Account</h2>
            <p class="mt-1 text-xs text-slate-500">
                Save the public identity now. Official API authorisation can be connected separately.
            </p>

            <form method="POST" action="{{ route('profile.social-media.accounts.store') }}" class="mt-4 space-y-3">
                @csrf

                <div>
                    <label class="text-xs font-bold">Platform</label>
                    <select name="platform" class="pm-input mt-1 w-full" required>
                        <option value="instagram">Instagram</option>
                        <option value="facebook">Facebook</option>
                        <option value="x">X (Twitter)</option>
                        <option value="tiktok">TikTok</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="whatsapp_status">WhatsApp Status</option>
                        <option value="whatsapp_channel">WhatsApp Channel</option>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-bold">Account name</label>
                    <input name="account_name" required class="pm-input mt-1 w-full">
                </div>

                <div>
                    <label class="text-xs font-bold">Username / handle</label>
                    <input name="username" placeholder="@username" class="pm-input mt-1 w-full">
                </div>

                <button class="btn-primary rounded-xl px-4 py-2.5 text-sm font-bold text-white">
                    Add Account
                </button>
            </form>
        </section>
    </div>

    <section class="apple-surface rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-100">
            <h2 class="font-black">Connected / Saved Accounts</h2>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($accounts as $account)
                <div class="p-4 space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="social-account-main min-w-0">
                            <div class="font-bold">{{ match($account->platform) {
                                'x' => 'X (Twitter)',
                                'whatsapp_status' => 'WhatsApp Status',
                                'whatsapp_channel' => 'WhatsApp Channel',
                                default => ucfirst($account->platform),
                            } }} · {{ $account->account_name }}</div>
                            <div class="text-xs text-slate-500">{{ $account->username ?: 'No username saved' }}</div>
                            <div class="mt-2 flex flex-wrap gap-2 text-[11px] font-bold">
                                <span class="rounded-full px-2 py-1 {{ $account->is_connected ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                    {{ $account->is_connected ? 'API connected' : 'API token missing' }}
                                </span>
                                <span class="rounded-full px-2 py-1 {{ $account->auto_publish_enabled ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $account->auto_publish_enabled ? 'Automatic posting ON' : 'Automatic posting OFF' }}
                                </span>
                            </div>
                        </div>

                        <form method="POST"
                              action="{{ route('profile.social-media.accounts.destroy', $account->id) }}">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs font-bold text-rose-600">Remove</button>
                        </form>
                    </div>

                    <form method="POST"
                          action="{{ route('profile.social-media.accounts.automatic-publishing', $account->id) }}"
                          class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 space-y-3">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-bold">External Page / User / Author ID</label>
                                <input name="external_account_id"
                                       value="{{ old('external_account_id', $account->external_account_id) }}"
                                       placeholder="Required by Facebook, Instagram or LinkedIn"
                                       class="pm-input mt-1 w-full">
                            </div>
                            <div>
                                <label class="text-xs font-bold">Official API access token</label>
                                <input type="password"
                                       name="access_token"
                                       autocomplete="new-password"
                                       placeholder="Leave blank to keep current token"
                                       class="pm-input mt-1 w-full">
                            </div>
                        </div>

                        @if(in_array($account->platform, ['whatsapp_status','whatsapp_channel'], true))
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-3 space-y-3">
                                <div>
                                    <div class="text-xs font-black text-emerald-800">WhatsApp automatic provider</div>
                                    <p class="mt-1 text-[11px] leading-5 text-emerald-700">
                                        Manual posting always remains available. Automatic Status/Channel publishing requires a provider/webhook that explicitly supports this target.
                                    </p>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="text-xs font-bold">Provider name</label>
                                        <input name="automation_provider"
                                               value="{{ old('automation_provider', $account->automation_provider) }}"
                                               placeholder="e.g. Custom provider"
                                               class="pm-input mt-1 w-full">
                                    </div>
                                    <div>
                                        <label class="text-xs font-bold">Provider webhook / API endpoint</label>
                                        <input type="url"
                                               name="automation_endpoint"
                                               value="{{ old('automation_endpoint', $account->automation_endpoint) }}"
                                               placeholder="https://provider.example.com/publish"
                                               class="pm-input mt-1 w-full">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-bold">Webhook signing secret (optional)</label>
                                    <input type="password"
                                           name="automation_secret"
                                           autocomplete="new-password"
                                           placeholder="Leave blank to keep the current secret"
                                           class="pm-input mt-1 w-full">
                                </div>
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <label class="flex items-center gap-2 text-xs font-bold">
                                <input type="hidden" name="enabled" value="0">
                                <input type="checkbox" name="enabled" value="1" {{ $account->auto_publish_enabled ? 'checked' : '' }}>
                                Enable automatic posting
                            </label>
                            <button class="btn-primary rounded-xl px-3 py-2 text-xs font-bold text-white">
                                Save Automatic Posting
                            </button>
                        </div>

                        <p class="text-[11px] leading-5 text-slate-500">
                            For Facebook, Instagram, X, TikTok and LinkedIn, automatic posting requires authorised platform API access. WhatsApp Status/Channel automatic posting requires a connected provider/webhook that supports that publishing target. Manual posting remains available for every platform.
                        </p>
                    </form>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-slate-400">
                    No social media accounts saved yet.
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection

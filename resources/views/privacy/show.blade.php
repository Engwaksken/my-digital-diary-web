@extends('layouts.app')

@section('title', 'Privacy & Data')

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-11 h-11 rounded-xl bg-[var(--brand-1-tint-10)] text-[var(--brand-1)] flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-shield-halved text-lg" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Privacy &amp; Data</h1>
        </div>

        {{-- Consent status is always visible, never tabbed a quick-reference
             summary shouldn't be hidden behind a click. --}}
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 mb-6">
            <h2 class="font-semibold mb-1">Your consent</h2>
            @if ($user->hasGivenDataConsent())
                <p class="text-sm text-slate-600">
                    You agreed to our
                    <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener noreferrer" class="text-[var(--brand-1)] underline">
                        Privacy Policy
                        <span class="sr-only">(opens in a new tab)</span>
                    </a>
                    (version {{ $user->data_consent_version }}) on {{ $user->data_consent_at->format('Y-m-d') }}.
                </p>
            @else
                <p class="text-sm text-rose-600">No consent currently on record.</p>
            @endif
        </div>

        <div role="tablist" aria-label="Privacy actions" class="flex gap-1 border-b border-slate-200 mb-6">
            <button type="button" role="tab" id="pm-privacy-tab-export" aria-controls="pm-privacy-panel-export"
                    aria-selected="true" tabindex="0" data-tab="export"
                    onclick="pmSelectPrivacyTab('export')" onkeydown="pmPrivacyTabKeydown(event, 'export')"
                    class="pm-privacy-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-[var(--brand-1)] text-[var(--brand-1)]">
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                <span>Export Data</span>
            </button>
            <button type="button" role="tab" id="pm-privacy-tab-delete" aria-controls="pm-privacy-panel-delete"
                    aria-selected="false" tabindex="-1" data-tab="delete"
                    onclick="pmSelectPrivacyTab('delete')" onkeydown="pmPrivacyTabKeydown(event, 'delete')"
                    class="pm-privacy-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-rose-500 hover:text-rose-700 hover:border-rose-300">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <span>Delete Account</span>
            </button>
        </div>

        <div role="tabpanel" id="pm-privacy-panel-export" aria-labelledby="pm-privacy-tab-export" tabindex="0" class="pm-privacy-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6">
            <h2 class="font-semibold mb-2">Export your data</h2>
            <p class="text-sm text-slate-600 mb-4">
                Download everything you've stored across every module as a single, compressed and
                password-protected zip file (complete, machine-readable), or a formatted PDF report
                (human-readable summary).
            </p>

            @if (session('export_password'))
                <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm mb-4">
                    <p class="font-medium mb-1">
                        <i class="fa-solid fa-key" aria-hidden="true"></i>
                        Your export password save this now, it won't be shown again
                    </p>
                    <div class="flex items-center gap-2 mt-2">
                        <code id="pm-export-password" class="bg-white border border-amber-300 rounded px-3 py-1.5 font-mono text-base tracking-wide">{{ session('export_password') }}</code>
                        <button type="button" onclick="pmCopyExportPassword()" class="text-xs text-amber-700 hover:underline">
                            <i class="fa-solid fa-copy" aria-hidden="true"></i> Copy
                        </button>
                    </div>
                    <p class="text-xs text-amber-700 mt-2">You'll need this password to open the downloaded zip file.</p>
                </div>
            @elseif ($exportReady && ! $exportEncrypted)
                <div class="rounded-lg bg-blue-50 border border-blue-200 text-blue-900 px-4 py-3 text-sm mb-4">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    Your export is compressed but not password-protected encryption wasn't available on
                    this server for this file. The zip itself doesn't need a password to open.
                </div>
            @endif

            <div class="flex flex-wrap gap-3">
                @if ($exportReady)
                    <a href="{{ route('privacy.export.download') }}"
                       class="inline-block btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--brand-2)]">
                        <i class="fa-solid fa-download" aria-hidden="true"></i> Download my data (zip)
                    </a>
                    <form method="POST" action="{{ route('privacy.export.request') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-sm text-slate-500 hover:underline">Generate a new export instead</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('privacy.export.request') }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-block btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--brand-2)]">
                            <i class="fa-solid fa-box-archive" aria-hidden="true"></i> Prepare my data export
                        </button>
                    </form>
                @endif
                <a href="{{ route('report.download') }}"
                   class="inline-block bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-md text-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--brand-2)]">
                    Download Personal Report (PDF)
                </a>
            </div>
        </div>

        <script>
            function pmCopyExportPassword() {
                var el = document.getElementById('pm-export-password');
                if (el) { navigator.clipboard?.writeText(el.textContent.trim()); }
            }
        </script>

        <div role="tabpanel" id="pm-privacy-panel-delete" aria-labelledby="pm-privacy-tab-delete" tabindex="0" class="pm-privacy-panel bg-white shadow-sm border border-rose-200 rounded-xl p-6" hidden>
            <h2 class="font-semibold mb-2 text-rose-700">Delete your account</h2>
            <p class="text-sm text-slate-600 mb-4">
                This permanently deletes your account and every record across every module plans,
                income, budgets, expenses, diet, sleep, health checkups, projects, education, network
                contacts, relationships, reminders, AI plans, and API keys. This cannot be undone.
            </p>

            <form method="POST" action="{{ route('privacy.destroy-account') }}"
                  onsubmit="return confirm('This will permanently delete your account and all data. This cannot be undone. Continue?');">
                @csrf
                @method('DELETE')

                <div class="mb-4 max-w-xs">
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
                        Confirm your password
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        aria-required="true"
                        @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                        class="pm-input"
                    >
                    @error('password')
                        <p id="password-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="bg-rose-600 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md hover:bg-rose-700 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    Permanently delete my account
                </button>
            </form>
        </div>
    </div>

    <script>
        function pmSelectPrivacyTab(key) {
            document.querySelectorAll('.pm-privacy-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                var isDanger = btn.dataset.tab === 'delete';
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected && !isDanger);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected && !isDanger);
                btn.classList.toggle('border-rose-500', isSelected && isDanger);
                btn.classList.toggle('text-rose-700', isSelected && isDanger);
                btn.classList.toggle('border-transparent', !isSelected);
                if (isSelected) { btn.focus(); }
            });
            document.querySelectorAll('.pm-privacy-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-privacy-panel-' + key;
            });
        }

        function pmPrivacyTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-privacy-tab'), function (t) { return t.dataset.tab; });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else if (event.key === 'Home') { nextIndex = 0; }
            else if (event.key === 'End') { nextIndex = tabs.length - 1; }
            else { return; }

            event.preventDefault();
            pmSelectPrivacyTab(tabs[nextIndex]);
        }
    </script>
@endsection

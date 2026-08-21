@extends('layouts.app')
@section('title','Privacy & Data')
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-[var(--brand-1-tint-10)] text-[var(--brand-1)] flex items-center justify-center"><i class="fa-solid fa-shield-halved"></i></div>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Privacy & Data</h1>
            <p class="text-sm text-slate-500">Understand how your diary data is handled, control AI access, export your information and manage your account.</p>
        </div>
    </div>

    <div class="border-b border-slate-200 overflow-x-auto">
        <div class="flex min-w-max gap-1" role="tablist" aria-label="Privacy sections">
            <button type="button" class="privacy-tab border-b-2 border-[var(--brand-1)] px-4 py-3 text-sm font-semibold text-[var(--brand-1)]" data-tab="trust">Privacy & Trust</button>
            <button type="button" class="privacy-tab border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-slate-500" data-tab="report">Data Report</button>
            <button type="button" class="privacy-tab border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-slate-500" data-tab="history">Report History <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $reportRequests->count() }}</span></button>
            <button type="button" class="privacy-tab border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-slate-500" data-tab="delete">Delete Account</button>
        </div>
    </div>

    <section class="privacy-panel" data-panel="trust">
        <div class="grid lg:grid-cols-2 gap-4">
            <article class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-5">
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-xl bg-white text-emerald-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-lock"></i></span>
                    <div>
                        <h2 class="font-bold text-slate-900">Your diary is private by default</h2>
                        <p class="text-sm text-slate-600 mt-1 leading-6">Your diary entries are not published or shared with other users by default. My Digital Diary does not sell your diary data or use your private records for third-party advertising.</p>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-sky-100 bg-sky-50/70 p-5">
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-xl bg-white text-sky-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-user-shield"></i></span>
                    <div>
                        <h2 class="font-bold text-slate-900">Who can access stored data?</h2>
                        <p class="text-sm text-slate-600 mt-1 leading-6">Your account is protected by authentication and ownership checks. Authorised system administrators may technically access stored information only when needed for support, security, maintenance, fraud prevention or legal obligations. Sensitive access should be limited to people who genuinely need it.</p>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-violet-100 bg-violet-50/70 p-5">
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-xl bg-white text-violet-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-robot"></i></span>
                    <div>
                        <h2 class="font-bold text-slate-900">You control what AI may use</h2>
                        <p class="text-sm text-slate-600 mt-1 leading-6">AI features use selected summaries from the areas you allow. You can turn access to Finance, Health, Notes, Spiritual Growth and other life areas on or off from your Personalisation & AI settings.</p>
                        @if(Route::has('profile.edit'))
                            <a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-2 mt-3 text-sm font-semibold text-violet-700 hover:text-violet-900"><i class="fa-solid fa-sliders"></i> Review AI privacy choices</a>
                        @endif
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-amber-100 bg-amber-50/70 p-5">
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-xl bg-white text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-export"></i></span>
                    <div>
                        <h2 class="font-bold text-slate-900">You remain in control</h2>
                        <p class="text-sm text-slate-600 mt-1 leading-6">You can request a copy of your data, review recent account sign-ins and schedule account deletion. These controls are available here so privacy does not depend on trusting a hidden process.</p>
                        <div class="flex flex-wrap gap-3 mt-3">
                            <button type="button" class="text-sm font-semibold text-amber-800" onclick="document.querySelector('[data-tab=report]')?.click()"><i class="fa-solid fa-download mr-1"></i> Export my data</button>
                            @if(Route::has('login-activity.index'))
                                <a href="{{ route('login-activity.index') }}" class="text-sm font-semibold text-amber-800"><i class="fa-solid fa-clock-rotate-left mr-1"></i> Recent sign-ins</a>
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        </div>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex items-start gap-3">
                <span class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-circle-info"></i></span>
                <div>
                    <h3 class="font-bold text-slate-900">A realistic privacy promise</h3>
                    <p class="text-sm text-slate-600 mt-1 leading-6">A cloud diary must store information on servers in order to synchronise it between devices and provide features such as reminders, reports and backups. No online service can promise that data is impossible for every authorised operator to access. Our goal is to minimise access, protect it with technical and organisational controls, be transparent about AI use, and give you meaningful control over your information.</p>
                    <div class="flex flex-wrap gap-3 mt-3 text-sm">
                        @if(Route::has('privacy-policy'))<a href="{{ route('privacy-policy') }}" class="font-semibold text-[var(--brand-1)]">Read Privacy Policy</a>@endif
                        @if(Route::has('terms-of-use'))<a href="{{ route('terms-of-use') }}" class="font-semibold text-[var(--brand-1)]">Read Terms of Use</a>@endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="privacy-panel hidden" data-panel="report">
        <div class="pm-card-bg border border-slate-100 rounded-xl p-4 sm:p-6">
            <h2 class="font-semibold mb-2">Request a personal data report</h2>
            <p class="text-sm text-slate-600 mb-4">Choose exactly what you need and the period to cover. A customised PDF will be generated and emailed to your registered address.</p>
            <form method="POST" action="{{ route('privacy.export.request') }}" class="space-y-4">@csrf
                <div><label class="block text-sm font-medium mb-1">Why do you need this report?</label><textarea name="reason" rows="3" required class="pm-input">{{ old('reason') }}</textarea></div>
                <div><p class="text-sm font-medium mb-2">Information to include</p><div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">@foreach(['plans'=>'Annual Plans','daily_plans'=>'Daily Planner','incomes'=>'Income','budgets'=>'Budgets','expenses'=>'Expenses','debts'=>'Debts','savings_goals'=>'Financial Savings Goals','savings_contributions'=>'Savings Contributions','diet_logs'=>'Diet','exercise_logs'=>'Exercise','sleep_logs'=>'Sleep','health_checkups'=>'Health','projects'=>'Projects','project_tasks'=>'Tasks','reminders'=>'Reminders','spiritual_practices'=>'Spiritual Growth','notes'=>'Notes','ai_plans'=>'AI Planner'] as $k=>$v)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="modules[]" value="{{ $k }}"> {{ $v }}</label>@endforeach</div></div>
                <div class="grid sm:grid-cols-2 gap-3"><div><label class="block text-sm font-medium mb-1">From</label><input type="date" name="date_from" class="pm-input"></div><div><label class="block text-sm font-medium mb-1">To</label><input type="date" name="date_to" class="pm-input"></div></div>
                <button class="btn-primary text-white px-4 py-2 rounded-lg"><i class="fa-solid fa-file-pdf mr-1"></i> Generate & email PDF</button>
            </form>
        </div>
    </section>

    <section class="privacy-panel hidden" data-panel="history">
        <div class="pm-card-bg border border-slate-100 rounded-xl p-4 sm:p-6">
            <h2 class="font-semibold mb-3">Report history</h2>
            @if($reportRequests->isNotEmpty())
                <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="text-left"><th class="py-2 pr-4">Requested</th><th class="pr-4">Status</th><th class="pr-4">Period</th><th></th></tr></thead><tbody>@foreach($reportRequests as $r)<tr class="border-t"><td class="py-3 pr-4 whitespace-nowrap">{{ $r->created_at->format('Y-m-d H:i') }}</td><td class="pr-4">{{ ucfirst($r->status) }}</td><td class="pr-4 whitespace-nowrap">{{ $r->date_from?->format('Y-m-d') ?? 'All' }} - {{ $r->date_to?->format('Y-m-d') ?? 'Present' }}</td><td>@if($r->status==='completed')<a class="text-[var(--brand-1)] font-medium" href="{{ route('privacy.report.download',$r) }}">Download</a>@endif</td></tr>@endforeach</tbody></table></div>
            @else
                <p class="text-sm text-slate-500">No personal data reports have been requested yet.</p>
            @endif
        </div>
    </section>

    <section class="privacy-panel hidden" data-panel="delete">
        <div class="bg-white border border-rose-200 rounded-xl p-4 sm:p-6">
            <h2 class="font-semibold text-rose-700 mb-2">Delete my account</h2>
            @if($user->scheduled_deletion_at)
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4 text-sm">Your account is scheduled for permanent deletion on <strong>{{ $user->scheduled_deletion_at->format('Y-m-d H:i') }}</strong>. After permanent deletion your data cannot be restored.<form method="POST" action="{{ route('privacy.cancel-deletion') }}" class="mt-3">@csrf<button class="border border-amber-400 px-3 py-1.5 rounded">Cancel account deletion</button></form></div>
            @else
                <p class="text-sm text-slate-600 mb-4">Before deletion, tell us why you are leaving. Your account will enter a 30-day grace period before permanent deletion. You can cancel during that period.</p>
                <form method="POST" action="{{ route('privacy.destroy-account') }}" class="space-y-3">@csrf @method('DELETE')
                    <textarea name="reason" required rows="3" class="pm-input" placeholder="Why do you want to delete your account?"></textarea>
                    <label class="flex gap-2 text-sm"><input type="checkbox" name="backup" value="1"> Send/prepare a complete data backup before deletion</label>
                    <input type="password" name="password" required class="pm-input" placeholder="Confirm password">
                    <label class="flex gap-2 text-sm text-rose-700"><input type="checkbox" name="confirm_delete" value="1" required> I understand my data will be permanently deleted after 30 days and cannot be restored.</label>
                    <button class="bg-rose-600 text-white px-4 py-2 rounded-lg">Schedule account deletion</button>
                </form>
            @endif
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabs = Array.from(document.querySelectorAll('.privacy-tab'));
    const panels = Array.from(document.querySelectorAll('.privacy-panel'));
    function openTab(name) {
        tabs.forEach(tab => {
            const active = tab.dataset.tab === name;
            tab.classList.toggle('border-[var(--brand-1)]', active);
            tab.classList.toggle('text-[var(--brand-1)]', active);
            tab.classList.toggle('border-transparent', !active);
            tab.classList.toggle('text-slate-500', !active);
        });
        panels.forEach(panel => panel.classList.toggle('hidden', panel.dataset.panel !== name));
        if (history.replaceState) history.replaceState(null, '', '#' + name);
    }
    tabs.forEach(tab => tab.addEventListener('click', () => openTab(tab.dataset.tab)));
    const requested = location.hash.replace('#', '');
    if (['trust','report','history','delete'].includes(requested)) openTab(requested);
});
</script>
@endsection

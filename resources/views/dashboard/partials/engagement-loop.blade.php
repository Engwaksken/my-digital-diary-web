@php
    $engagement = $engagement ?? [];
    $streak = data_get($engagement, 'streak.current', 0);
    $bestStreak = data_get($engagement, 'streak.best', 0);
    $startDone = (bool) data_get($engagement, 'start_day.completed', false);
    $closeDone = (bool) data_get($engagement, 'close_day.completed', false);
    $progress = data_get($engagement, 'progress', []);
@endphp

<section class="mb-5 space-y-3" aria-labelledby="engagement-loop-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-slate-400">Daily rhythm</p>
            <h2 id="engagement-loop-title" class="text-lg font-extrabold text-slate-900">
                Build progress worth returning to
            </h2>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-extrabold text-amber-800">
                <span aria-hidden="true">🔥</span> {{ $streak }} day streak
            </span>
            <span class="hidden sm:inline text-xs text-slate-500">
                Best {{ $bestStreak }} days
            </span>
        </div>
    </div>

    <div class="grid gap-3 lg:grid-cols-3">
        <article class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-emerald-700 shadow-sm">
                    <i class="fa-solid fa-sun"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-extrabold uppercase tracking-wide text-emerald-700">
                        Start My Day
                    </p>
                    <h3 class="mt-1 font-extrabold text-slate-900">
                        {{ $startDone ? 'Your day is started' : 'Choose what matters first' }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ data_get($engagement, 'start_day.focus_count', 0) }} focus item(s) ready.
                    </p>
                </div>
            </div>

            <button
                type="button"
                data-engagement-action="start-day"
                class="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800 disabled:opacity-60"
                @disabled($startDone)
            >
                <i class="fa-solid {{ $startDone ? 'fa-check' : 'fa-play' }}"></i>
                {{ $startDone ? 'Started' : 'Start My Day' }}
            </button>
        </article>

        <article class="rounded-2xl border border-violet-100 bg-violet-50/70 p-4">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-violet-700 shadow-sm">
                    <i class="fa-solid fa-chart-line"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-extrabold uppercase tracking-wide text-violet-700">Today’s progress</p>
                    <h3 class="mt-1 font-extrabold text-slate-900">
                        {{ data_get($progress, 'tasks_completed', 0) }}/{{ data_get($progress, 'tasks_total', 0) }}
                        tasks completed
                    </h3>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ data_get($progress, 'meaningful_actions', 0) }} meaningful action(s) recorded today.
                    </p>
                </div>
            </div>

            <div class="mt-4 h-2 overflow-hidden rounded-full bg-violet-100">
                <div
                    class="h-full rounded-full bg-violet-600"
                    style="width: {{ min(100, max(0, (int) data_get($progress, 'completion_percent', 0))) }}%"
                ></div>
            </div>
        </article>

        <article class="rounded-2xl border border-sky-100 bg-sky-50/70 p-4">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-sky-700 shadow-sm">
                    <i class="fa-solid fa-moon"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-extrabold uppercase tracking-wide text-sky-700">Close My Day</p>
                    <h3 class="mt-1 font-extrabold text-slate-900">
                        {{ $closeDone ? 'Today is closed' : 'Finish today with clarity' }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-600">
                        Capture wins, gratitude and tomorrow’s first priority.
                    </p>
                </div>
            </div>

            <button
                type="button"
                data-engagement-action="close-day"
                class="mt-4 inline-flex items-center gap-2 rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60"
                @disabled($closeDone)
            >
                <i class="fa-solid {{ $closeDone ? 'fa-check' : 'fa-moon' }}"></i>
                {{ $closeDone ? 'Completed' : 'Close My Day' }}
            </button>
        </article>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <button type="button"
                data-engagement-review="week"
                class="group rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:border-[var(--brand-1)] hover:shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Weekly reflection</p>
                    <h3 class="mt-1 font-extrabold text-slate-900">My Week in Review</h3>
                    <p class="mt-1 text-sm text-slate-500">See tasks, meaningful days, money and consistency.</p>
                </div>
                <i class="fa-solid fa-arrow-right text-slate-300 group-hover:text-[var(--brand-1)]"></i>
            </div>
        </button>

        <button type="button"
                data-engagement-review="month"
                class="group rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:border-[var(--brand-1)] hover:shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Personal wrapped</p>
                    <h3 class="mt-1 font-extrabold text-slate-900">My Month in Review</h3>
                    <p class="mt-1 text-sm text-slate-500">A shareable snapshot of your progress this month.</p>
                </div>
                <i class="fa-solid fa-share-nodes text-slate-300 group-hover:text-[var(--brand-1)]"></i>
            </div>
        </button>
    </div>

    @if(data_get($engagement, 'celebration'))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <div class="font-extrabold text-amber-900">
                {{ data_get($engagement, 'celebration.title') }}
            </div>
            <p class="mt-1 text-sm text-amber-800">
                {{ data_get($engagement, 'celebration.message') }}
            </p>
        </div>
    @endif
</section>

<dialog id="engagement-review-modal" class="pm-dialog rounded-2xl">
    <div class="pm-modal-content">
        <div class="pm-modal-header">
            <div class="pm-modal-heading">
                <span class="pm-modal-icon"><i class="fa-solid fa-chart-pie"></i></span>
                <div>
                    <h2 class="pm-modal-title" id="engagement-review-title">Your Review</h2>
                    <p class="pm-modal-subtitle">Progress worth noticing and sharing.</p>
                </div>
            </div>
            <button type="button" class="pm-modal-close" data-engagement-close aria-label="Close">&times;</button>
        </div>
        <div class="pm-modal-body">
            <div id="engagement-review-content" class="grid gap-3 sm:grid-cols-2"></div>
        </div>
        <div class="pm-modal-footer">
            <button type="button" class="btn-secondary" data-engagement-close>Close</button>
            <button type="button" class="btn-primary" id="engagement-share-review">
                <i class="fa-solid fa-share-nodes"></i> Share
            </button>
        </div>
    </div>
</dialog>

<script>
(() => {
    const modal = document.getElementById('engagement-review-modal');
    const content = document.getElementById('engagement-review-content');
    const title = document.getElementById('engagement-review-title');
    const share = document.getElementById('engagement-share-review');
    let shareText = '';

    async function postCheckin(type) {
        const body = type === 'close-day'
            ? {
                reflection: prompt('What moved forward today?') || '',
                gratitude: prompt('What are you grateful for today?') || '',
                tomorrow_focus: prompt('What should matter first tomorrow?') || ''
              }
            : {
                reflection: prompt('What matters most today?') || ''
              };

        const response = await fetch(`/api/engagement/checkin/${type}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify(body)
        });

        if (!response.ok) throw new Error('Could not save your daily check-in.');
        window.location.reload();
    }

    async function loadReview(period) {
        const response = await fetch(`/api/engagement/review/${period}`, {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'}
        });
        if (!response.ok) throw new Error('Could not load review.');

        const json = await response.json();
        const data = json.data || {};
        const periodTitle = period === 'week' ? 'My Week in Review' : 'My Month in Review';

        title.textContent = periodTitle;
        const metrics = [
            ['Tasks completed', `${data.tasks_completed || 0}/${data.tasks_total || 0}`],
            ['Completion', `${data.completion_percent || 0}%`],
            ['Meaningful days', data.meaningful_days || 0],
            ['Exercise sessions', data.exercise_sessions || 0],
            ['Income', `UGX ${Number(data.income || 0).toLocaleString()}`],
            ['Expenses', `UGX ${Number(data.expenses || 0).toLocaleString()}`],
            ['Current streak', `${data.streak?.current || 0} days`],
            ['Best streak', `${data.streak?.best || 0} days`],
        ];

        content.innerHTML = metrics.map(([label, value]) => `
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div class="text-xs font-bold uppercase tracking-wide text-slate-400">${label}</div>
                <div class="mt-1 text-lg font-extrabold text-slate-900">${value}</div>
            </div>
        `).join('');

        shareText = `${periodTitle}\n` +
            metrics.slice(0, 4).map(([label, value]) => `${label}: ${value}`).join('\n') +
            `\n\nMy Digital Diary`;

        modal.showModal();
    }

    document.querySelectorAll('[data-engagement-action]').forEach(btn => {
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            try {
                await postCheckin(btn.dataset.engagementAction);
            } catch (e) {
                alert(e.message || 'Could not save your check-in.');
                btn.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-engagement-review]').forEach(btn => {
        btn.addEventListener('click', () => loadReview(btn.dataset.engagementReview));
    });

    document.querySelectorAll('[data-engagement-close]').forEach(btn => {
        btn.addEventListener('click', () => modal.close());
    });

    share?.addEventListener('click', async () => {
        if (navigator.share) {
            await navigator.share({title: title.textContent, text: shareText});
        } else {
            await navigator.clipboard.writeText(shareText);
            share.textContent = 'Copied';
            setTimeout(() => share.innerHTML = '<i class="fa-solid fa-share-nodes"></i> Share', 1500);
        }
    });
})();
</script>

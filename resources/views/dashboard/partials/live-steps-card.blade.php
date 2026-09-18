@php
    $stepData = is_array($stepData ?? null) ? $stepData : [];
    $steps = max(0, (int) ($stepData['steps'] ?? 0));
    $goal = max(1, (int) ($stepData['daily_goal'] ?? 5000));
    $progress = min(100, max(0, (int) ($stepData['progress_percent'] ?? round(($steps / $goal) * 100))));
    $remaining = max(0, (int) ($stepData['remaining_steps'] ?? ($goal - $steps)));
    $distanceKm = max(0, (float) ($stepData['distance_km'] ?? 0));
    $tracking = (bool) ($stepData['is_tracking'] ?? false);
@endphp

<section
    id="md-live-steps-card"
    class="md-dashboard-section md-shell p-5 border border-emerald-100 bg-white shadow-sm"
    data-url="{{ url('/wellbeing/steps/live') }}"
>
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shrink-0">
                <i class="fa-solid fa-shoe-prints text-xl"></i>
            </div>

            <div class="min-w-0">
                <div class="flex items-end gap-2">
                    <span id="md-step-count" class="text-3xl font-black text-slate-900">{{ number_format($steps) }}</span>

                    <span id="md-step-distance"
                          class="text-sm font-black text-emerald-700 mb-1 {{ $steps > 0 && $distanceKm > 0 ? '' : 'hidden' }}">
                        ≈ {{ number_format($distanceKm, 1) }} km
                    </span>

                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Steps today</span>
                </div>

                <div id="md-step-status" class="text-sm font-bold {{ $tracking ? 'text-emerald-700' : 'text-slate-500' }}">
                    {{ $tracking ? 'Tracking your steps' : 'Tracking paused' }}
                </div>
            </div>
        </div>

        <div class="text-right shrink-0">
            <div id="md-step-percent" class="text-lg font-black text-slate-800">{{ $progress }}%</div>
            <div class="text-[10px] text-slate-400">of {{ number_format($goal) }}</div>
        </div>
    </div>

    <div class="mt-4 h-2.5 rounded-full bg-slate-100 overflow-hidden">
        <div
            id="md-step-progress"
            class="h-full rounded-full bg-emerald-500 transition-all duration-300"
            style="width: {{ $progress }}%"
        ></div>
    </div>

    <div class="mt-2 flex items-center justify-between gap-3 text-xs text-slate-500">
        <span id="md-step-remaining">{{ number_format($remaining) }} remaining</span>
        <span id="md-step-updated">
            @if(!empty($stepData['last_synced_at']))
                Last sync {{ \Illuminate\Support\Carbon::parse($stepData['last_synced_at'])->format('g:i A') }}
            @else
                Waiting for phone sync
            @endif
        </span>
    </div>

    <div id="md-step-sync-note" class="mt-3 text-[11px] text-slate-500">
        The mobile app counts your movement and synchronises it to this dashboard automatically.
    </div>

    <div id="md-step-next-goal"
         class="mt-2 text-[11px] font-bold text-emerald-700 {{ !empty($stepData['goal_achieved']) ? '' : 'hidden' }}">
        @if(!empty($stepData['goal_achieved']))
            Goal achieved. Your next daily target is {{ number_format((int) ($stepData['next_daily_goal'] ?? $goal)) }} steps.
        @endif
    </div>
</section>

@once
<script>
(() => {
    const card = document.getElementById('md-live-steps-card');
    if (!card) return;

    const url = card.dataset.url;
    const count = document.getElementById('md-step-count');
    const distance = document.getElementById('md-step-distance');
    const status = document.getElementById('md-step-status');
    const percent = document.getElementById('md-step-percent');
    const progress = document.getElementById('md-step-progress');
    const remaining = document.getElementById('md-step-remaining');
    const updated = document.getElementById('md-step-updated');
    const note = document.getElementById('md-step-sync-note');
    const nextGoal = document.getElementById('md-step-next-goal');

    let requestRunning = false;
    let lastSeenSteps = Number(String(count?.textContent || '0').replace(/,/g, '')) || 0;

    const number = value => new Intl.NumberFormat().format(Number(value || 0));

    async function refresh() {
        if (requestRunning || document.hidden) return;
        requestRunning = true;

        try {
            const response = await fetch(`${url}?_=${Date.now()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) {
                note.textContent = `Live step refresh failed (${response.status}).`;
                return;
            }

            const json = await response.json();
            const data = json?.data || {};

            const steps = Math.max(0, Number(data.steps || 0));
            const goal = Math.max(1, Number(data.daily_goal || 5000));
            const pct = Math.min(100, Math.max(0, Number(
                data.progress_percent ?? Math.round((steps / goal) * 100)
            )));
            const tracking = Boolean(data.is_tracking);

            count.textContent = number(steps);
            percent.textContent = `${pct}%`;
            progress.style.width = `${pct}%`;
            remaining.textContent = `${number(Math.max(0, goal - steps))} remaining`;

            if (distance) {
                const km = Number(data.distance_km || 0);
                distance.textContent = `≈ ${km.toLocaleString(undefined, {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1,
                })} km`;
                distance.classList.toggle('hidden', steps === 0 || km <= 0);
            }

            status.textContent = tracking ? 'Tracking your steps' : 'Tracking paused';
            status.className = `text-sm font-bold ${tracking ? 'text-emerald-700' : 'text-slate-500'}`;

            if (data.last_synced_at) {
                const synced = new Date(data.last_synced_at);
                updated.textContent = `Last sync ${synced.toLocaleTimeString([], {
                    hour: 'numeric',
                    minute: '2-digit',
                })}`;
            } else {
                updated.textContent = 'Waiting for phone sync';
            }

            note.textContent = steps !== lastSeenSteps
                ? 'Updated from your phone just now.'
                : 'Live sync active — waiting for your next phone step update.';

            if (nextGoal) {
                const achieved = Boolean(data.goal_achieved);
                nextGoal.classList.toggle('hidden', !achieved);
                if (achieved) {
                    nextGoal.textContent =
                        `Goal achieved. Your next daily target is ${number(data.next_daily_goal || goal)} steps.`;
                }
            }

            lastSeenSteps = steps;
        } catch (_) {
            note.textContent = 'Live sync is temporarily unavailable. The last saved count is shown.';
        } finally {
            requestRunning = false;
        }
    }

    refresh();
    const timer = setInterval(refresh, 2000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
    window.addEventListener('focus', refresh);
    window.addEventListener('pageshow', refresh);
    window.addEventListener('beforeunload', () => clearInterval(timer), { once: true });
})();
</script>
@endonce

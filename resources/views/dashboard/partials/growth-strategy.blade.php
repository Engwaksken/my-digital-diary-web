@php
    $growth = $growth ?? [];
    $activation = data_get($growth, 'activation', []);
    $challenge = data_get($growth, 'challenge', []);
    $referral = data_get($growth, 'referral', []);
@endphp

@if(!empty($growth))
<section class="md-dashboard-section md-shell" style="padding:14px 15px" id="growth-strategy-card">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="text-[10px] uppercase tracking-[.14em] font-black text-slate-400">Take back your attention</div>
            <h2 class="mt-1 text-base font-black text-slate-900">Build your own progress, not just your feed.</h2>
            <p class="mt-1 text-xs text-slate-500">Use a few intentional minutes to manage your priorities, money and goals.</p>
        </div>
        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[10px] font-black text-emerald-800">Private by design</span>
    </div>

    <div class="mt-3 grid gap-3 md:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="text-[10px] font-black uppercase tracking-wide text-slate-400">First value</div>
            <div class="mt-1 text-sm font-black text-slate-900">{{ data_get($activation,'completed',0) }}/{{ data_get($activation,'total',3) }} setup actions complete</div>
            <div class="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full bg-emerald-600" style="width:{{ min(100,(int)data_get($activation,'percent',0)) }}%"></div></div>
            <div class="mt-3 space-y-1">
                @foreach(data_get($activation,'steps',[]) as $step)
                    <div class="text-[11px] {{ !empty($step['complete']) ? 'text-emerald-700' : 'text-slate-500' }}"><i class="fa-solid {{ !empty($step['complete']) ? 'fa-circle-check' : 'fa-circle' }} mr-1"></i>{{ $step['label'] }}</div>
                @endforeach
            </div>
        </article>

        <article class="rounded-2xl border border-violet-200 bg-violet-50/60 p-4">
            <div class="text-[10px] font-black uppercase tracking-wide text-violet-700">30-Day Challenge</div>
            <div class="mt-1 text-sm font-black text-slate-900">{{ data_get($challenge,'title','30 Days With My Digital Diary') }}</div>
            <p class="mt-1 text-[11px] text-slate-600">Plan, act, record and reflect consistently.</p>
            @if(data_get($challenge,'joined'))
                <div class="mt-3 h-2 rounded-full bg-violet-100 overflow-hidden"><div class="h-full rounded-full bg-violet-600" style="width:{{ min(100,(int)data_get($challenge,'progress_percent',0)) }}%"></div></div>
                <p class="mt-2 text-[10px] font-bold text-violet-700">{{ data_get($challenge,'meaningful_days',0) }} meaningful day(s)</p>
            @else
                <button type="button" data-growth-join class="mt-3 rounded-xl bg-violet-700 px-3 py-2 text-xs font-bold text-white">Join Challenge</button>
            @endif
        </article>

        <article class="rounded-2xl border border-sky-200 bg-sky-50/60 p-4">
            <div class="text-[10px] font-black uppercase tracking-wide text-sky-700">Grow together</div>
            <div class="mt-1 text-sm font-black text-slate-900">Invite someone who wants more intentional days.</div>
            <p class="mt-1 text-[11px] text-slate-600">{{ data_get($referral,'conversions',0) }} friend(s) joined from your invites.</p>
            <button type="button" data-growth-referral class="mt-3 rounded-xl bg-sky-700 px-3 py-2 text-xs font-bold text-white"><i class="fa-solid fa-share-nodes mr-1"></i>Invite a friend</button>
        </article>
    </div>

    <div class="mt-3 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2.5 text-[11px] text-emerald-900"><strong>Privacy promise:</strong> {{ data_get($growth,'trust.message') }}</div>
</section>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    document.querySelector('[data-growth-join]')?.addEventListener('click', async e => {
        const button=e.currentTarget; button.disabled=true;
        try { const r=await fetch('/growth/challenge/join',{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf}}); if(!r.ok) throw new Error('Could not join challenge.'); location.reload(); }
        catch(err){ alert(err.message); button.disabled=false; }
    });
    document.querySelector('[data-growth-referral]')?.addEventListener('click', async () => {
        const r=await fetch('/growth/referral',{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({channel:'web'})});
        if(!r.ok){ alert('Could not create invite.'); return; }
        const j=await r.json(), d=j.data||{}, text=`${d.share_text||'Join me on My Digital Diary'}\n${d.url||''}`;
        if(navigator.share){ try{ await navigator.share({title:'My Digital Diary',text}); return; }catch(_){} }
        try{ await navigator.clipboard.writeText(text); alert('Invite copied. Share it on WhatsApp, Instagram, LinkedIn or email.'); }catch(_){ prompt('Copy invite:',text); }
    });
})();
</script>
@endif

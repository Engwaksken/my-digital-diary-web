@auth
<div id="pm-support-widget" aria-live="polite">
    <style>

        /*
         * Fixed children already sit outside document flow. Avoid a
         * zero-size/contain parent because some phone browsers clip fixed
         * descendants when their ancestor establishes containment.
         */
        #pm-support-widget {
            display: block !important;
            position: static !important;
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            min-height: 0 !important;
            max-width: none !important;
            max-height: none !important;
            overflow: visible !important;
            visibility: visible !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        #pm-support-toggle {
            position: fixed !important;
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            right: max(5.75rem, calc(env(safe-area-inset-right) + 5.75rem));
            bottom: max(1.25rem, env(safe-area-inset-bottom));
            z-index: 70;
            width: 56px;
            height: 56px;
            border: 0;
            border-radius: 9999px;
            background: var(--brand-1, #00897B);
            color: #fff;
            box-shadow: 0 12px 28px rgba(15,23,42,.24);
            display:flex;
            align-items:center;
            justify-content:center;
            cursor:pointer;
        }
        #pm-support-toggle:hover { background: var(--brand-2, #73BEB6); }
        #pm-support-panel {
            position: fixed !important;
            visibility: visible !important;
            opacity: 1 !important;
            right: max(1.25rem, env(safe-area-inset-right));
            bottom: calc(56px + 2rem + env(safe-area-inset-bottom));
            z-index: 70;
            width: min(92vw, 24rem);
            height: min(70vh, 34rem);
            background:#fff;
            border:1px solid #dbe4ea;
            border-radius:1rem;
            box-shadow:0 24px 64px rgba(15,23,42,.25);
            overflow:hidden;
            display:flex;
            flex-direction:column;
        }
        #pm-support-panel[hidden] { display:none !important; }
        .pm-support-head { background:var(--brand-1,#00897B); color:#fff; padding:14px 16px; display:flex; justify-content:space-between; align-items:center; gap:12px; }
        .pm-support-thread { flex:1; overflow:auto; padding:14px; background:#f8fafc; display:flex; flex-direction:column; gap:10px; }
        .pm-support-msg { max-width:84%; padding:10px 12px; border-radius:14px; font-size:14px; line-height:1.4; white-space:pre-wrap; overflow-wrap:anywhere; }
        .pm-support-msg.user { align-self:flex-end; background:var(--brand-1,#00897B); color:#fff; border-bottom-right-radius:4px; }
        .pm-support-msg.other { align-self:flex-start; background:#fff; color:#1e293b; border:1px solid #e2e8f0; border-bottom-left-radius:4px; }
        .pm-support-who { display:block; font-size:10px; opacity:.7; margin-bottom:3px; }
        .pm-support-status { padding:7px 14px; font-size:11px; color:#64748b; border-bottom:1px solid #e2e8f0; background:#fff; }
        .pm-support-form { padding:10px; border-top:1px solid #e2e8f0; background:#fff; display:flex; gap:8px; }
        .pm-support-form textarea { flex:1; resize:none; min-height:44px; max-height:100px; border:1px solid #94a3b8; border-radius:10px; padding:9px 10px; outline:none; }
        .pm-support-form textarea:focus { border-color:var(--brand-1,#00897B); box-shadow:0 0 0 3px rgba(0,137,123,.12); }
        .pm-support-send { width:44px; height:44px; border:0; border-radius:10px; background:var(--brand-1,#00897B); color:#fff; cursor:pointer; flex:none; }
        .pm-support-send:disabled { opacity:.55; cursor:not-allowed; }
        .pm-support-footer { padding:0 12px 10px; background:#fff; font-size:11px; display:flex; justify-content:space-between; gap:10px; }
        .pm-support-footer a { color:var(--brand-1,#00897B); text-decoration:none; font-weight:600; }
        @media (max-width: 767.98px) {
            #pm-support-toggle {
                position: fixed !important;
                right: calc(max(12px, env(safe-area-inset-right)) + 60px) !important;
                bottom: max(12px, env(safe-area-inset-bottom)) !important;
                width: 48px !important;
                height: 48px !important;
                min-width: 48px !important;
                min-height: 48px !important;
                max-width: 48px !important;
                max-height: 48px !important;
                margin: 0 !important;
                z-index: 10001 !important;
            }

            #pm-support-panel {
                position: fixed !important;
                top: auto !important;
                right: 12px !important;
                bottom: calc(72px + env(safe-area-inset-bottom)) !important;
                left: 12px !important;
                width: auto !important;
                min-width: 0 !important;
                max-width: none !important;
                height: min(72dvh, 34rem) !important;
                max-height: calc(100dvh - 92px - env(safe-area-inset-bottom)) !important;
                margin: 0 !important;
                overflow: hidden !important;
                box-sizing: border-box !important;
                z-index: 10001 !important;
            }

            .pm-support-head,
            .pm-support-status,
            .pm-support-form,
            .pm-support-footer {
                min-width: 0 !important;
                max-width: 100% !important;
            }

            .pm-support-thread {
                min-width: 0 !important;
                overflow-x: hidden !important;
                overflow-y: auto !important;
                overscroll-behavior: contain !important;
            }

            .pm-support-form textarea {
                min-width: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .pm-support-footer {
                flex-wrap: wrap !important;
            }
        }
    </style>

    <button id="pm-support-toggle" type="button" aria-label="Open support chat" aria-controls="pm-support-panel" aria-expanded="false">
        <i class="fa-solid fa-comments" aria-hidden="true"></i>
    </button>

    <section id="pm-support-panel" hidden role="dialog" aria-label="My Digital Diary support chat">
        <div class="pm-support-head">
            <div>
                <div style="font-weight:700"><i class="fa-solid fa-headset" style="margin-right:6px"></i>Support</div>
                <div id="pm-support-mode" style="font-size:11px;opacity:.85">AI support until a person is assigned</div>
            </div>
            <button type="button" id="pm-support-close" aria-label="Close support chat" style="border:0;background:transparent;color:#fff;font-size:18px;cursor:pointer"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="pm-support-status" class="pm-support-status">Loading conversation…</div>
        <div id="pm-support-thread" class="pm-support-thread"></div>
        <form id="pm-support-form" class="pm-support-form">
            <textarea id="pm-support-input" maxlength="4000" required placeholder="Ask about My Digital Diary…" aria-label="Support message"></textarea>
            <button id="pm-support-send" class="pm-support-send" type="submit" aria-label="Send message"><i class="fa-solid fa-paper-plane"></i></button>
        </form>
        <div class="pm-support-footer">
            <span>Never share passwords, OTPs or PINs.</span>
            <a href="{{ route('support.index') }}">Full chat</a>
        </div>
    </section>

    <script>
    (() => {
        const toggle = document.getElementById('pm-support-toggle');
        const panel = document.getElementById('pm-support-panel');
        const closeBtn = document.getElementById('pm-support-close');
        const thread = document.getElementById('pm-support-thread');
        const status = document.getElementById('pm-support-status');
        const mode = document.getElementById('pm-support-mode');
        const form = document.getElementById('pm-support-form');
        const input = document.getElementById('pm-support-input');
        const sendBtn = document.getElementById('pm-support-send');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
        let loaded = false;

        function esc(v) {
            const div = document.createElement('div');
            div.textContent = v ?? '';
            return div.innerHTML;
        }
        function addMessage(m) {
            const mine = m.sender_type === 'user';
            const el = document.createElement('div');
            el.className = 'pm-support-msg ' + (mine ? 'user' : 'other');
            el.innerHTML = '<span class="pm-support-who">' + esc(m.sender || (mine ? 'You' : 'Support')) + '</span>' + esc(m.message);
            thread.appendChild(el);
            thread.scrollTop = thread.scrollHeight;
        }
        async function loadHistory() {
            status.textContent = 'Loading conversation…';
            try {
                const r = await fetch('{{ route('support.widget.history') }}', {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'});
                if (!r.ok) throw new Error('Could not load support chat');
                const data = await r.json();
                thread.innerHTML = '';
                (data.messages || []).forEach(addMessage);
                if (!(data.messages || []).length) {
                    thread.innerHTML = '<div style="margin:auto;text-align:center;color:#94a3b8;font-size:13px"><i class="fa-solid fa-comments" style="font-size:26px;margin-bottom:8px"></i><br>Ask a question about My Digital Diary.</div>';
                }
                const human = data.mode === 'human';
                status.textContent = human ? ('Human support' + (data.assignee ? ': ' + data.assignee : '')) : 'AI support is active until an admin assigns a support person.';
                mode.textContent = human ? 'Human support is handling this chat' : 'AI support until a person is assigned';
                loaded = true;
            } catch (e) {
                status.textContent = e.message || 'Could not load support chat.';
            }
        }
        function openPanel() {
            const a11yPanel = document.getElementById('pm-a11y-panel');
            const a11yToggle = document.getElementById('pm-a11y-toggle');

            if (a11yPanel && !a11yPanel.hidden) {
                a11yPanel.hidden = true;
                a11yToggle?.setAttribute('aria-expanded', 'false');
            }

            panel.hidden = false;
            toggle.setAttribute('aria-expanded','true');
            if (!loaded) loadHistory();
            setTimeout(() => input.focus(), 50);
        }
        function closePanel() {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded','false');
        }
        toggle.addEventListener('click', () => panel.hidden ? openPanel() : closePanel());
        closeBtn.addEventListener('click', closePanel);
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden) closePanel(); });
        form.addEventListener('submit', async e => {
            e.preventDefault();
            const message = input.value.trim();
            if (!message || sendBtn.disabled) return;
            if (thread.querySelector('[style*="margin:auto"]')) thread.innerHTML='';
            addMessage({sender_type:'user', sender:'You', message});
            input.value=''; sendBtn.disabled=true; status.textContent='Sending…';
            try {
                const r = await fetch('{{ route('support.widget.send') }}', {
                    method:'POST', credentials:'same-origin',
                    headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
                    body:JSON.stringify({message})
                });
                const data = await r.json();
                if (!r.ok) throw new Error(data.message || 'Could not send message');
                if (data.reply) addMessage(data.reply);
                status.textContent = data.waiting_for_human ? 'Message sent to your assigned support person.' : 'AI support is active until an admin assigns a support person.';
            } catch (e2) {
                status.textContent = e2.message || 'Could not send message.';
            } finally { sendBtn.disabled=false; input.focus(); }
        });
    })();
    </script>
</div>
@endauth

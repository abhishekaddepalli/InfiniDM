// InstaMagic install wizard — client-side step navigation + validation.
// Standalone (loads before the app is installed): depends only on markup +
// instaflow.css. No framework. The final "Install" step auto-runs the install.
function initInstallWizard() {
    const form = document.getElementById('iw-form');
    if (!form) return;

    const q = (sel, root = document) => root.querySelector(sel);
    const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';

    // Base path of the installer, taken from the URL actually loaded — so every
    // endpoint works whether the app sits at the web root (/install) or in a
    // subfolder (/instamagic/public/install). Hardcoding "/install" 404s in a
    // subfolder and the fetch then returns HTML → a useless "Bad response".
    const INSTALL_BASE = window.location.pathname.replace(/\/+$/, '');   // …/install
    const URL_TESTDB   = INSTALL_BASE + '/test-db';
    const URL_LICENSE  = INSTALL_BASE + '/verify-license';
    const URL_STATUS   = INSTALL_BASE + '/status';
    const URL_INSTALL  = INSTALL_BASE;

    const panels = qa('[data-iw-panel]');
    const stepItems = qa('[data-iw-stepitem]');
    const total = panels.length;
    const NAMES = ['Welcome', 'Requirements', 'Database', 'Application', 'Admin', 'Node', 'Install'];
    const MIN_PW = 8;

    const backBtn = q('[data-iw-back]');
    const nextBtn = q('[data-iw-next]');
    const nextText = q('[data-iw-nexttext]');
    let cur = 0;
    let installStarted = false;

    const DOT_DONE = 'bg-ig-pink/15 text-ig-magenta';
    const DOT_ACTIVE = 'ig-grad-btn shadow-card';
    const DOT_IDLE = 'bg-paper-100 border border-paper-200 text-ink-500';

    // ---- password reveal (eye) toggle -------------------------------------
    qa('[data-iw-eye]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = q('input', btn.parentElement);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            q('[data-iw-eye-open]', btn)?.classList.toggle('hidden', show);
            q('[data-iw-eye-off]', btn)?.classList.toggle('hidden', !show);
        });
    });

    // ---- per-field inline error -------------------------------------------
    function setMsg(input, text) {
        const wrap = input.closest('[data-iw-fieldwrap]');
        const msg = wrap && q('[data-iw-fieldmsg]', wrap);
        input.classList.add('border-accent-coral');
        if (msg) { msg.textContent = text; msg.classList.remove('hidden'); }
    }
    function clearMsg(input) {
        const wrap = input.closest('[data-iw-fieldwrap]');
        const msg = wrap && q('[data-iw-fieldmsg]', wrap);
        input.classList.remove('border-accent-coral');
        if (msg) { msg.textContent = ''; msg.classList.add('hidden'); }
    }
    qa('[data-iw-input]').forEach((i) => i.addEventListener('input', () => clearMsg(i)));

    // ---- generate a fresh Node shared token -------------------------------
    q('[data-iw-gentoken]')?.addEventListener('click', () => {
        const input = q('input[name=node_token]');
        if (!input) return;
        const bytes = new Uint8Array(16);
        (window.crypto || window.msCrypto).getRandomValues(bytes);
        input.value = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
        clearMsg(input);
    });

    // ---- timezone searchable combobox (matches WaDesk) --------------------
    const tzWrap = q('[data-iw-tz-wrap]');
    if (tzWrap) {
        const tzSearch = q('[data-iw-tz-search]', tzWrap);
        const tzHidden = q('[data-iw-tz-value]', tzWrap);
        const tzListEl = q('[data-iw-tz-list]', tzWrap);
        const tzAll = (tzListEl.getAttribute('data-iw-tz-all') || '').split(',').filter(Boolean);
        // Default to the visitor's real timezone when none is pre-set.
        try {
            const guess = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (guess && (!tzHidden.value || tzHidden.value === 'UTC') && tzAll.includes(guess)) tzHidden.value = guess;
        } catch (e) { /* Intl unavailable — keep the default */ }
        tzSearch.value = tzHidden.value || 'UTC';
        const tzRender = () => {
            const term = tzSearch.value.toLowerCase().trim();
            const matches = (term ? tzAll.filter((t) => t.toLowerCase().includes(term)) : tzAll).slice(0, 25);
            tzListEl.innerHTML = matches.length
                ? matches.map((t) => `<div data-tz="${t}" class="px-3 py-2 text-[12.5px] font-mono cursor-pointer hover:bg-ig-pink/8 hover:text-ig-magenta">${t}</div>`).join('')
                : '<div class="px-3 py-2 text-[12px] text-ink-500 italic">No timezones match.</div>';
        };
        tzSearch.addEventListener('focus', () => { tzRender(); tzListEl.classList.remove('hidden'); });
        tzSearch.addEventListener('input', () => { tzRender(); tzListEl.classList.remove('hidden'); });
        tzListEl.addEventListener('click', (e) => {
            const opt = e.target.closest('[data-tz]');
            if (!opt) return;
            tzHidden.value = opt.getAttribute('data-tz');
            tzSearch.value = opt.getAttribute('data-tz');
            tzListEl.classList.add('hidden');
        });
        document.addEventListener('click', (e) => { if (!tzWrap.contains(e.target)) tzListEl.classList.add('hidden'); });
    }

    // ---- stepper visuals ---------------------------------------------------
    function paintStepper(active) {
        stepItems.forEach((item, i) => {
            const dot = q('[data-iw-dot]', item);
            const num = q('[data-iw-num]', dot);
            const tick = q('[data-iw-tick]', dot);
            const label = q('[data-iw-label]', item);
            item.classList.toggle('bg-ig-pink/10', i === active);
            dot.className = 'w-7 h-7 rounded-full grid place-items-center shrink-0 font-mono text-[12px] font-semibold transition-all '
                + (i < active ? DOT_DONE : i === active ? DOT_ACTIVE : DOT_IDLE);
            num.classList.toggle('hidden', i < active);
            tick.classList.toggle('hidden', i >= active);
            label.className = 'text-[12px] font-mono uppercase tracking-[0.14em] transition-colors '
                + (i === active ? 'text-ink-900 font-semibold' : i < active ? 'text-ink-700' : 'text-ink-500');
        });
        const bar = q('[data-iw-mobbar]');
        if (bar) bar.style.width = ((active + 1) / total) * 100 + '%';
        const ms = q('[data-iw-mobstep]'); if (ms) ms.textContent = active + 1;
        const mn = q('[data-iw-mobname]'); if (mn) mn.textContent = NAMES[active];
    }

    function goTo(step) {
        cur = Math.max(0, Math.min(total - 1, step));
        panels.forEach((panel, i) => {
            const on = i === cur;
            panel.classList.toggle('hidden', !on);
            if (on) { panel.classList.remove('iw-step-enter'); void panel.offsetWidth; panel.classList.add('iw-step-enter'); }
        });
        paintStepper(cur);

        const last = cur === total - 1;      // Install step (auto-runs)
        const nodeStep = cur === total - 2;  // Node step -> button = "Install now"
        backBtn.disabled = cur === 0;
        backBtn.classList.toggle('hidden', last);
        nextBtn.classList.toggle('hidden', last);
        if (nextText) nextText.textContent = nodeStep ? 'Install now' : 'Continue';

        const scroll = q('.install-scroll');
        if (scroll) scroll.scrollTop = 0;

        if (last) runInstall();
    }

    // ---- validation --------------------------------------------------------
    function validate(step) {
        const panel = panels[step];
        for (const input of qa('[data-iw-input]', panel)) {
            // db_pass may be blank; purchase_code is checked by the async
            // licence verify (which also allows blank when unconfigured).
            if (input.name !== 'db_pass' && input.name !== 'purchase_code' && !input.value.trim()) {
                setMsg(input, 'This field is required.');
                input.focus();
                return false;
            }
        }
        const pw = q('input[name=admin_password]', panel);
        if (pw && pw.value.length < MIN_PW) {
            setMsg(pw, `Password must be at least ${MIN_PW} characters.`);
            pw.focus();
            return false;
        }
        return true;
    }



    nextBtn?.addEventListener('click', () => {
        if (!validate(cur)) return;
        goTo(cur + 1);
    });
    backBtn?.addEventListener('click', () => goTo(cur - 1));

    // ---- test DB connection ------------------------------------------------
    const OK_CLS = 'rounded-xl border px-3 py-2 text-[12px] font-medium border-ig-pink/40 bg-ig-pink/8 text-ig-magenta';
    const ERR_CLS = 'rounded-xl border px-3 py-2 text-[12px] font-medium border-accent-coral/40 bg-accent-coral/10 text-accent-coral';

    q('[data-iw-testdb]')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const out = q('[data-iw-dbresult]');
        const spin = q('[data-iw-dbspin]');
        const text = q('[data-iw-dbtext]');
        const body = new URLSearchParams();
        ['db_host', 'db_port', 'db_name', 'db_user', 'db_pass'].forEach((n) => body.append(n, form[n]?.value || ''));
        btn.disabled = true; spin.classList.remove('hidden'); text.textContent = 'Testing…'; out.classList.add('hidden');
        try {
            const raw = await fetch(URL_TESTDB, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
            });
            const text = await raw.text();
            let res;
            try { res = JSON.parse(text); }
            catch { res = { ok: false, message: 'Server returned ' + raw.status + ' at ' + URL_TESTDB + (raw.status === 404 ? ' — the installer could not reach its own endpoint (check the base path).' : ' — ' + text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220)) }; }
            out.textContent = res.message || (res.ok ? 'Connected' : 'Failed');
            out.className = res.ok ? OK_CLS : ERR_CLS;
            out.classList.remove('hidden');
        } catch (err) {
            out.textContent = err.message; out.className = ERR_CLS; out.classList.remove('hidden');
        } finally {
            btn.disabled = false; spin.classList.add('hidden'); text.textContent = 'Test connection';
        }
    });

    // ---- run install — stepped progress (auto-triggered on final step) -----
    function runInstall() {
        if (installStarted) return;
        installStarted = true;

        const stepEls = qa('[data-iw-step]', q('[data-iw-steps]'));
        const bar = q('[data-iw-prog-bar]');
        const pct = q('[data-iw-prog-pct]');
        const progLabel = q('[data-iw-prog-label]');
        const errBlock = q('[data-iw-err-block]');
        const errText = q('[data-iw-installerr]');
        const retry = q('[data-iw-retry]');
        const doneBlock = q('[data-iw-done-block]');

        const N = stepEls.length;
        const status = new Array(N).fill('pending');
        let elapsedTimer = null, midTimer = null, runningIdx = -1, runStart = 0, failed = false;

        const icon = (el, st) => {
            q('[data-iw-ic-pending]', el).classList.toggle('hidden', st !== 'pending');
            q('[data-iw-ic-run]', el).classList.toggle('hidden', st !== 'running');
            q('[data-iw-ic-done]', el).classList.toggle('hidden', st !== 'done');
            q('[data-iw-ic-fail]', el).classList.toggle('hidden', st !== 'failed');
        };
        const paintStep = (i) => {
            const el = stepEls[i], st = status[i];
            el.className = 'flex items-center gap-3 py-2 px-2.5 rounded-xl transition-colors '
                + (st === 'running' ? 'bg-ig-pink/8 iw-step-enter' : st === 'done' ? 'bg-ig-pink/5' : st === 'failed' ? 'bg-accent-coral/10' : '');
            icon(el, st);
            const txt = q('[data-iw-step-text]', el);
            txt.textContent = st === 'running' ? el.getAttribute('data-iw-active') : el.getAttribute('data-iw-label');
            txt.className = 'text-[12.5px] transition-colors '
                + (st === 'pending' ? 'text-ink-500' : st === 'running' ? 'text-ig-magenta font-semibold' : st === 'done' ? 'text-ink-900' : 'text-accent-coral font-semibold');
        };
        const paintBar = () => {
            const done = status.filter((s) => s === 'done').length;
            const running = status.filter((s) => s === 'running').length;
            const p = ((done + running * 0.5) / N) * 100;
            bar.style.width = p + '%';
            pct.textContent = Math.round(p) + '%';
            if (status.every((s) => s === 'done')) progLabel.textContent = 'Complete';
            else if (failed) progLabel.textContent = 'Failed — see below';
            else { const r = status.indexOf('running'); progLabel.textContent = r >= 0 ? stepEls[r].getAttribute('data-iw-active').replace('…', '') : 'Preparing…'; }
        };
        const setStep = (i, st) => {
            if (runningIdx === i && st !== 'running') {
                clearInterval(elapsedTimer);
                if (runStart) q('[data-iw-step-time]', stepEls[i]).textContent = ((Date.now() - runStart) / 1000).toFixed(1) + 's';
            }
            status[i] = st;
            if (st === 'running') {
                runningIdx = i; runStart = Date.now();
                clearInterval(elapsedTimer);
                elapsedTimer = setInterval(() => {
                    q('[data-iw-step-time]', stepEls[i]).textContent = ((Date.now() - runStart) / 1000).toFixed(1) + 's';
                }, 100);
            }
            paintStep(i); paintBar();
        };

        // reset
        failed = false;
        errBlock.classList.add('hidden'); retry.classList.add('hidden'); doneBlock.classList.add('hidden'); errText.textContent = '';
        for (let i = 0; i < N; i++) { status[i] = 'pending'; q('[data-iw-step-time]', stepEls[i]).textContent = ''; paintStep(i); }
        paintBar();

        const fail = (msg) => {
            installStarted = false; failed = true;
            clearInterval(elapsedTimer); clearInterval(midTimer);
            if (runningIdx >= 0) setStep(runningIdx, 'failed');
            errText.textContent = msg; errBlock.classList.remove('hidden'); retry.classList.remove('hidden');
            paintBar();
        };
        const succeed = (url) => {
            clearInterval(elapsedTimer); clearInterval(midTimer);
            for (let i = 0; i < N; i++) { status[i] = 'done'; paintStep(i); }
            paintBar();
            doneBlock.classList.remove('hidden');
            setTimeout(() => { window.location.href = url; }, 1200);
        };

        // Visually walk the middle steps (migrations -> seed -> admin) while we
        // poll; steps 4-5 only complete when the server actually reports done.
        const advanceMiddle = () => { if (runningIdx >= 1 && runningIdx < 3) { setStep(runningIdx, 'done'); setStep(runningIdx + 1, 'running'); } };

        const poll = () => {
            fetch(URL_STATUS, { headers: { Accept: 'application/json' } })
                .then((r) => r.json()).catch(() => ({}))
                .then((s) => {
                    if (s.done && s.redirect) { succeed(s.redirect); return; }
                    if (s.error) { fail(s.error); return; }
                    setTimeout(poll, 1500);
                });
        };

        // Step 0 — write env + create DB (real POST /install).
        setStep(0, 'running');
        fetch(URL_INSTALL, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: new URLSearchParams(new FormData(form)) })
            .then((r) => r.json()).catch(() => ({ ok: false, message: 'Bad response from server' }))
            .then((res) => {
                if (res.ok && res.redirect) { setStep(0, 'done'); succeed(res.redirect); return; }
                if (!res.ok) { fail(res.message || 'Install failed'); return; }
                if (res.running) {
                    setStep(0, 'done');
                    setStep(1, 'running');
                    midTimer = setInterval(advanceMiddle, 3500);
                    poll();
                    return;
                }
                fail('Unexpected response from server');
            })
            .catch((e) => fail(e.message));
    }

    q('[data-iw-retry]')?.addEventListener('click', runInstall);

    goTo(0);
}

const run = () => { try { initInstallWizard(); } catch (e) { console.error('[instagram] instaflow-install failed', e); } };
document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', run) : run();

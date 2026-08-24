/*
 * IgDesk standalone install wizard.
 *
 * Single-page, JS-driven step flow (Welcome → Requirements → Database →
 * Application → Admin → Install) mirroring the WaDesk installer's card+stepper
 * pattern. Panels live in the DOM; this shows one at a time, drives the left
 * rail stepper, runs the AJAX "Test connection", and posts the whole form to
 * /install (which creates the DB, migrates, seeds the admin) then redirects.
 *
 * Built by vite.instagram.config.mjs into public/extensions/instagram/instaflow-install.js
 * and loaded by resources/views/install.blade.php.
 */
export default function init() {
    const form = document.getElementById('iw-form');
    if (!form) return;

    const $  = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';

    const panels    = $$('[data-iw-panel]');
    const stepItems = $$('[data-iw-stepitem]');
    const total     = panels.length;
    const names     = ['Welcome', 'Requirements', 'Database', 'Application', 'Admin', 'Node', 'Install'];

    const backBtn    = $('[data-iw-back]');
    const nextBtn    = $('[data-iw-next]');
    const installBtn = $('[data-iw-install]');
    let cur = 0;

    const DOT_DONE   = 'bg-wa-mint text-wa-deep';
    const DOT_ACTIVE = 'ig-grad-btn shadow-card';
    const DOT_TODO   = 'bg-paper-100 border border-paper-200 text-ink-500';

    function setStepper(i) {
        stepItems.forEach((item, idx) => {
            const dot   = $('[data-iw-dot]', item);
            const num   = $('[data-iw-num]', dot);
            const tick  = $('[data-iw-tick]', dot);
            const label = $('[data-iw-label]', item);
            item.classList.toggle('bg-ig-pink/10', idx === i);
            dot.className = 'w-7 h-7 rounded-full grid place-items-center shrink-0 font-mono text-[12px] font-semibold transition-all '
                + (idx < i ? DOT_DONE : idx === i ? DOT_ACTIVE : DOT_TODO);
            num.classList.toggle('hidden', idx < i);
            tick.classList.toggle('hidden', idx >= i);
            label.className = 'text-[12px] font-mono uppercase tracking-[0.14em] transition-colors '
                + (idx === i ? 'text-ink-900 font-semibold' : idx < i ? 'text-ink-700' : 'text-ink-500');
        });
        const bar = $('[data-iw-mobbar]');  if (bar)  bar.style.width = (((i + 1) / total) * 100) + '%';
        const st  = $('[data-iw-mobstep]'); if (st)   st.textContent = i + 1;
        const nm  = $('[data-iw-mobname]'); if (nm)   nm.textContent = names[i];
    }

    function show(i) {
        cur = Math.max(0, Math.min(total - 1, i));
        panels.forEach((p, idx) => {
            const on = idx === cur;
            p.classList.toggle('hidden', !on);
            if (on) { p.classList.remove('iw-step-enter'); void p.offsetWidth; p.classList.add('iw-step-enter'); }
        });
        setStepper(cur);
        backBtn.disabled = cur === 0;
        const last = cur === total - 1;
        nextBtn.classList.toggle('hidden', last);
        installBtn.classList.toggle('hidden', !last);
        installBtn.classList.toggle('flex', last);
        const scroller = $('.install-scroll');
        if (scroller) scroller.scrollTop = 0;
    }

    function flash(el) {
        el.classList.add('border-accent-coral');
        setTimeout(() => el.classList.remove('border-accent-coral'), 1200);
    }

    function validatePanel(i) {
        const panel = panels[i];
        for (const inp of $$('[data-iw-input]', panel)) {
            if (inp.name === 'db_pass') continue; // password may be blank
            if (!inp.value.trim()) { inp.focus(); flash(inp); return false; }
        }
        const pw = $('input[name=admin_password]', panel);
        if (pw && pw.value.length < 12) { pw.focus(); flash(pw); return false; }
        return true;
    }

    nextBtn?.addEventListener('click', () => { if (validatePanel(cur)) show(cur + 1); });
    backBtn?.addEventListener('click', () => show(cur - 1));

    // ---- Test connection -------------------------------------------------
    $('[data-iw-testdb]')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const out = $('[data-iw-dbresult]');
        const body = new URLSearchParams();
        ['db_host', 'db_port', 'db_name', 'db_user', 'db_pass'].forEach((n) => body.append(n, form[n]?.value || ''));
        btn.disabled = true;
        out.textContent = 'Testing…';
        out.className = 'text-[12px] font-mono text-ink-500';
        try {
            const r = await fetch('/install/test-db', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
            });
            const d = await r.json().catch(() => ({ ok: false, message: 'Bad response' }));
            out.textContent = d.message || (d.ok ? 'Connected' : 'Failed');
            out.className = 'text-[12px] font-mono ' + (d.ok ? 'text-wa-deep' : 'text-accent-coral');
        } catch (err) {
            out.textContent = err.message;
            out.className = 'text-[12px] font-mono text-accent-coral';
        } finally {
            btn.disabled = false;
        }
    });

    // ---- Run install -----------------------------------------------------
    installBtn?.addEventListener('click', async () => {
        if (!validatePanel(cur)) return;
        const err  = $('[data-iw-installerr]');
        const spin = $('[data-iw-spinner]');
        const txt  = $('[data-iw-installtext]');
        err.classList.add('hidden');
        spin.classList.remove('hidden');
        txt.textContent = 'Installing…';
        installBtn.disabled = true;
        try {
            const r = await fetch('/install', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: new URLSearchParams(new FormData(form)),
            });
            const d = await r.json().catch(() => ({ ok: false, message: 'Bad response from server' }));
            if (d.ok && d.redirect) {
                txt.textContent = 'Done — signing in…';
                window.location.href = d.redirect;
                return;
            }
            throw new Error(d.message || 'Install failed');
        } catch (e) {
            err.textContent = e.message;
            err.classList.remove('hidden');
            spin.classList.add('hidden');
            txt.textContent = 'Install now';
            installBtn.disabled = false;
        }
    });

    show(0);
}

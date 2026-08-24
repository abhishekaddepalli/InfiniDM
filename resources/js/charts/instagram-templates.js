// Instagram reply-templates — two pages share this initializer:
//   • Form page  (#igt-form): type toggle, dynamic quick-reply / button rows,
//                 edit pre-fill (from data-items/data-type), and a live preview.
//   • Index page ([data-igt-state]): type tabs + search filtering of the cards.
// Page key: instagram-templates.
export default function init() {
    initForm();
    initIndex();
}

// ───────────────────────── FORM PAGE ─────────────────────────
function initForm() {
    const form = document.getElementById('igt-form');
    if (!form) return;

    const $  = (id) => document.getElementById(id);
    const qrWrap  = $('igt-qr');
    const btnWrap = $('igt-btn');
    const qrRows  = $('igt-qr-rows');
    const btnRows = $('igt-btn-rows');
    const esc = (s) => String(s == null ? '' : s).replace(/"/g, '&quot;');

    const currentType = () => form.querySelector('input[name="type"]:checked')?.value || 'text';

    function syncType() {
        const t = currentType();
        qrWrap?.classList.toggle('hidden', t !== 'quick_replies');
        btnWrap?.classList.toggle('hidden', t !== 'buttons');
        if (t === 'quick_replies' && qrRows && qrRows.children.length === 0) addQrRow();
        if (t === 'buttons' && btnRows && btnRows.children.length === 0) addBtnRow();
        renderPreview();
    }

    function rowShell(inner) {
        const div = document.createElement('div');
        div.className = 'flex items-center gap-1.5';
        div.innerHTML = inner
            + '<button type="button" data-del class="w-7 h-7 shrink-0 rounded-lg hover:bg-paper-100 grid place-items-center text-ink-400 hover:text-accent-coral">'
            + '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4l8 8M12 4l-8 8"/></svg></button>';
        return div;
    }

    function addQrRow(title = '', payload = '') {
        if (!qrRows || qrRows.children.length >= 13) return;
        qrRows.appendChild(rowShell(
            `<input name="qr_title[]" maxlength="20" placeholder="Button text" value="${esc(title)}"
                class="igt-live flex-1 px-2.5 py-2 border border-paper-200 rounded-lg text-[12.5px] focus:outline-none focus:border-ig-pink">`
          + `<input name="qr_payload[]" placeholder="Payload (optional)" value="${esc(payload)}"
                class="flex-1 px-2.5 py-2 border border-paper-200 rounded-lg text-[12px] text-ink-500 focus:outline-none focus:border-ig-pink">`
        ));
        renderPreview();
    }

    function addBtnRow(type = 'postback', title = '', value = '') {
        if (!btnRows || btnRows.children.length >= 3) return;
        const sel = (v) => type === v ? ' selected' : '';
        btnRows.appendChild(rowShell(
            `<select name="btn_type[]" class="igt-live px-2 py-2 border border-paper-200 rounded-lg text-[12px] bg-paper-0 focus:outline-none focus:border-ig-pink">
                <option value="postback"${sel('postback')}>Reply</option>
                <option value="web_url"${sel('web_url')}>Link</option>
             </select>`
          + `<input name="btn_title[]" maxlength="20" placeholder="Button text" value="${esc(title)}"
                class="igt-live flex-1 px-2.5 py-2 border border-paper-200 rounded-lg text-[12.5px] focus:outline-none focus:border-ig-pink">`
          + `<input name="btn_value[]" placeholder="Payload or https://…" value="${esc(value)}"
                class="flex-1 px-2.5 py-2 border border-paper-200 rounded-lg text-[12px] text-ink-500 focus:outline-none focus:border-ig-pink">`
        ));
        renderPreview();
    }

    // ── Live preview ──
    const pBody = $('igt-preview-body'), pQr = $('igt-preview-qr'), pBtn = $('igt-preview-btn');
    function renderPreview() {
        if (!pBody) return;
        const body = ($('igt-body')?.value || '').trim();
        // escape → highlight {{vars}} → newlines
        let html = body.replace(/[&<>"]/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
        html = html.replace(/\{\{\s*([^{}]+?)\s*\}\}/g, '<span class="inline-block px-1 rounded bg-ig-pink/10 text-ig-pink font-medium">{{$1}}</span>');
        html = html.replace(/\n/g, '<br>');
        pBody.innerHTML = html || '<span class="text-ink-400 italic">Your message will appear here…</span>';

        const t = currentType();
        pQr.innerHTML = ''; pBtn.innerHTML = '';
        if (t === 'quick_replies') {
            form.querySelectorAll('input[name="qr_title[]"]').forEach((inp) => {
                const v = inp.value.trim(); if (!v) return;
                const chip = document.createElement('span');
                chip.className = 'px-2.5 py-1 rounded-full border border-ig-pink/40 text-ig-pink text-[11px] font-medium bg-white';
                chip.textContent = v; pQr.appendChild(chip);
            });
        } else if (t === 'buttons') {
            form.querySelectorAll('input[name="btn_title[]"]').forEach((inp) => {
                const v = inp.value.trim(); if (!v) return;
                const b = document.createElement('div');
                b.className = 'bg-white rounded-xl shadow-sm text-center text-[12px] text-ig-pink font-medium py-1.5';
                b.textContent = v; pBtn.appendChild(b);
            });
        }
    }

    // ── wire ──
    form.querySelectorAll('input[name="type"]').forEach((r) => r.addEventListener('change', syncType));
    form.querySelectorAll('[data-add]').forEach((b) => b.addEventListener('click', () =>
        b.dataset.add === 'qr' ? addQrRow() : addBtnRow()));
    form.addEventListener('click', (e) => {
        const del = e.target.closest('[data-del]');
        if (del) { del.closest('.flex').remove(); renderPreview(); }
    });
    form.addEventListener('input', renderPreview);
    $('igt-body')?.addEventListener('input', renderPreview);

    // Pre-fill existing rows on the EDIT page (items JSON on the form element).
    let items = [];
    try { items = JSON.parse(form.dataset.items || '[]') || []; } catch (e) { items = []; }
    const initialType = form.dataset.type || 'text';
    items.forEach((it) => {
        if (initialType === 'quick_replies') addQrRow(it.title || '', it.payload || '');
        else if (initialType === 'buttons')  addBtnRow(it.type || 'postback', it.title || '', it.value || '');
    });

    syncType();
    renderPreview();
}

// ───────────────────────── INDEX PAGE ─────────────────────────
function initIndex() {
    const state = document.querySelector('[data-igt-state]');
    if (!state) return;
    const grid   = document.getElementById('igt-grid');
    const cards  = Array.from(document.querySelectorAll('.igt-card'));
    const search = document.getElementById('igt-search');
    const empty  = document.getElementById('igt-empty-filter');
    const tabs   = Array.from(document.querySelectorAll('.igt-tab'));
    let activeTab = 'all', term = '';

    const apply = () => {
        let shown = 0;
        cards.forEach((c) => {
            const okType = activeTab === 'all' || c.dataset.igtType === activeTab;
            const okTerm = !term || (c.dataset.igtName || '').includes(term);
            const show = okType && okTerm;
            c.classList.toggle('hidden', !show);
            if (show) shown++;
        });
        empty?.classList.toggle('hidden', shown !== 0 || cards.length === 0);
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => {
        activeTab = tab.dataset.igtTab;
        tabs.forEach((t) => {
            const on = t === tab;
            t.classList.toggle('text-ig-pink', on);
            t.classList.toggle('text-ink-500', !on);
            t.querySelector('.igt-underline')?.classList.toggle('hidden', !on);
            const badge = t.querySelector('span:not(.igt-underline)');
            if (badge) {
                badge.classList.toggle('bg-ig-pink/10', on);
                badge.classList.toggle('text-ig-pink', on);
                badge.classList.toggle('bg-paper-100', !on);
                badge.classList.toggle('text-ink-500', !on);
            }
        });
        apply();
    }));
    search?.addEventListener('input', () => { term = search.value.trim().toLowerCase(); apply(); });
}

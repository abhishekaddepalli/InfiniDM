// IgDesk bulk-DM create form — live DM preview, char count, account/segment
// sync, contact search + select-all, and submit guards. Page key:
// instagram-broadcast-create (registered in app.js).
export default function init() {
    const form = document.getElementById('igBcForm');
    if (!form) return;

    const body = document.getElementById('igBcBody');
    const count = document.getElementById('igBcCount');
    const pvBody = document.getElementById('igPvBody');
    const pvName = document.getElementById('igPvName');
    const pvAvatar = document.getElementById('igPvAvatar');
    const pvCount = document.getElementById('igPvCount');
    const selSummary = document.getElementById('igBcSelSummary');
    const contactsPanel = document.getElementById('igBcContacts');
    const selectAll = document.getElementById('igBcSelectAll');
    const search = document.getElementById('igBcSearch');

    const acctRadios = Array.from(form.querySelectorAll('input[name="instagram_account_id"]'));
    const segRadios = Array.from(form.querySelectorAll('input[name="segment"]'));
    const cbs = () => Array.from(form.querySelectorAll('.igbc-cb'));
    const nf = (n) => Number(n).toLocaleString();

    const currentAccount = () => acctRadios.find((r) => r.checked);
    const reachOf = () => { const a = currentAccount(); return a ? parseInt(a.dataset.reach || '0', 10) : 0; };
    const segment = () => (segRadios.find((r) => r.checked)?.value) || 'all';
    const pickedCount = () => cbs().filter((c) => c.checked).length;

    const syncMsg = () => {
        const v = body?.value || '';
        if (count) count.textContent = `${nf(v.length)} / 1,000`;
        if (pvBody) pvBody.textContent = v || 'Your message preview…';
    };
    const syncCounts = () => {
        const pick = segment() === 'pick';
        const n = pick ? pickedCount() : reachOf();
        if (pvCount) pvCount.textContent = nf(n);
        if (selSummary) selSummary.textContent = pick ? `${nf(n)} selected` : 'Everyone';
    };
    const syncAccount = () => {
        const a = currentAccount();
        if (pvName) pvName.textContent = a?.dataset.username || '@your.account';
        if (pvAvatar) pvAvatar.textContent = a?.dataset.initials || 'IG';
        syncCounts();
    };
    const syncSegment = () => {
        contactsPanel?.classList.toggle('hidden', segment() !== 'pick');
        syncCounts();
    };

    body?.addEventListener('input', syncMsg);
    acctRadios.forEach((r) => r.addEventListener('change', syncAccount));
    segRadios.forEach((r) => r.addEventListener('change', syncSegment));

    selectAll?.addEventListener('change', () => {
        cbs().forEach((cb) => {
            const row = cb.closest('.igbc-row');
            if (row && !row.classList.contains('hidden')) cb.checked = selectAll.checked;
        });
        syncCounts();
    });
    form.addEventListener('change', (e) => { if (e.target.classList?.contains('igbc-cb')) syncCounts(); });

    search?.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        form.querySelectorAll('.igbc-row').forEach((row) => {
            row.classList.toggle('hidden', !!q && !(row.dataset.search || '').includes(q));
        });
    });

    form.addEventListener('submit', (e) => {
        if (!currentAccount()) { e.preventDefault(); window.toast?.('Pick an account to send from.', 'error'); return; }
        if (!(body?.value || '').trim()) { e.preventDefault(); window.toast?.('Write a message first.', 'error'); return; }
        if (segment() === 'pick' && pickedCount() === 0) {
            e.preventDefault();
            window.toast?.('Tick at least one contact, or choose "Everyone in window".', 'error');
        }
    });

    syncMsg();
    syncAccount();
    syncSegment();
}

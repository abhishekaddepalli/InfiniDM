// IgDesk bulk-DM index — live-poll the stats endpoint so the KPI cards +
// per-row Sent / Read / Failed / progress update without a reload (as the
// background sweep drains and read receipts arrive). Page key:
// instagram-broadcast (registered in app.js).
export default function init() {
    const root = document.querySelector('[data-bc-poll]');
    if (!root) return;
    const url = root.dataset.bcStatsUrl;
    if (!url) return;

    const nf = (n) => Number(n || 0).toLocaleString();
    const statusStyle = {
        done:    ['bg-green-100 text-green-700', 'Completed'],
        sending: ['bg-ig-pink/10 text-ig-pink', 'Sending'],
        pending: ['bg-amber-100 text-amber-700', 'Queued'],
        failed:  ['bg-accent-coral/15 text-accent-coral', 'Failed'],
    };

    const apply = (data) => {
        const stats = data.stats || {};
        document.querySelectorAll('[data-bc-stat]').forEach((el) => {
            const k = el.dataset.bcStat;
            if (k in stats) el.textContent = nf(stats[k]);
        });
        const rows = data.rows || {};
        document.querySelectorAll('[data-bc-row]').forEach((tr) => {
            const r = rows[tr.dataset.bcRow];
            if (!r) return;
            const cell = (name) => tr.querySelector(`[data-bc-cell="${name}"]`);
            if (cell('sent')) cell('sent').textContent = nf(r.sent);
            if (cell('read')) cell('read').textContent = nf(r.read);
            if (cell('failed')) cell('failed').textContent = nf(r.failed);
            if (cell('bar')) cell('bar').style.width = `${r.pct || 0}%`;
            const stEl = cell('status');
            if (stEl) {
                const [cls, label] = statusStyle[r.status] || ['bg-paper-100 text-ink-600', r.status];
                stEl.innerHTML = `<span class="text-[10px] px-2 py-0.5 rounded-full ${cls}">${label}</span>`;
            }
        });
    };

    const poll = () => {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : null))
            .then((d) => { if (d) apply(d); })
            .catch(() => {});
    };

    // Poll every 8s while the tab is visible.
    let timer = setInterval(poll, 8000);
    document.addEventListener('visibilitychange', () => {
        clearInterval(timer);
        if (!document.hidden) { poll(); timer = setInterval(poll, 8000); }
    });
}

/*
 * New-message pill + rail dot.
 *
 * Mirrors WaDesk's inbox-bell: a pill bottom-right on every screen EXCEPT the
 * inbox itself (where the list is already live and a notification about the
 * page you are looking at is noise).
 *
 * "Unread" is derived server-side from the highest inbound message id the
 * session has seen — instagram_messages has no read flag — so opening the inbox
 * advances that marker and both the pill and the dot clear on the next poll.
 */

const POLL_MS = 15000;
const MAX_MS = 300000;   // backoff ceiling — matches WaDesk's MAX_INTERVAL
const DISMISS_KEY = 'instaflow.ibx_bell_dismissed';  // survives reloads
let timer = null;
let delay = POLL_MS;
// Highest max_id dismissed with the x. Persisted in localStorage so closing the
// pill actually sticks across page reloads — otherwise the whole unread backlog
// (including hours-old messages) pops straight back on the next navigation.
let dismissedAt = readDismissed();

let lastMaxId = readDismissed();   // last id we've already reacted to (no chime replay)
let audioCtx = null;               // lazily created on first user gesture

function readDismissed() {
    try { return Number(localStorage.getItem(DISMISS_KEY)) || 0; }
    catch { return 0; }
}

// A short two-note chime via WebAudio — no sound file to ship, no Notification
// permission prompt. Mirrors WaDesk's in-app "ping" on a fresh message. Browsers
// block audio until the user has interacted with the page once; we swallow that
// silently rather than nag with a permission dialog.
function chime() {
    try {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return;
        audioCtx = audioCtx || new AC();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const now = audioCtx.currentTime;
        [[880, 0], [1320, 0.12]].forEach(([freq, at]) => {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, now + at);
            gain.gain.exponentialRampToValueAtTime(0.18, now + at + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + at + 0.28);
            osc.connect(gain).connect(audioCtx.destination);
            osc.start(now + at);
            osc.stop(now + at + 0.3);
        });
    } catch { /* audio unavailable — stay silent */ }
}

function writeDismissed(v) {
    dismissedAt = v;
    try { localStorage.setItem(DISMISS_KEY, String(v)); } catch { /* private mode */ }
}

function els() {
    return {
        bell: document.getElementById('ibx-bell'),
        count: document.getElementById('ibx-bell-count'),
        label: document.getElementById('ibx-bell-label'),
        list: document.getElementById('ibx-bell-list'),
        dots: document.querySelectorAll('[data-ibx-dot]'),
    };
}

function paint(data) {
    const { bell, count, label, list, dots } = els();
    const total = Number(data.total) || 0;
    const maxId = Number(data.max_id) || 0;

    // The dot lives in the rail and is independent of the pill — it stays put
    // even after the pill is dismissed, because the messages are still unread.
    dots.forEach((d) => d.classList.toggle('hidden', total === 0));

    if (!bell) return;

    // Keep the max id fresh so dismissing stores the true high-water mark.
    bell.dataset.maxId = String(maxId);

    // Ring once when a genuinely NEW message lands — higher id than anything we've
    // already reacted to, and not one the operator dismissed. Never on the first
    // paint (lastMaxId seeds from the stored dismiss marker), so a page load is silent.
    if (total > 0 && maxId > lastMaxId && maxId > dismissedAt) {
        chime();
    }
    lastMaxId = Math.max(lastMaxId, maxId);

    const hidden = total === 0 || maxId <= dismissedAt;
    bell.classList.toggle('hidden', hidden);
    if (hidden) return;

    if (count) count.textContent = total > 99 ? '99+' : String(total);
    if (label) label.textContent = total === 1 ? '1 new message' : `${total} new messages`;

    if (list) {
        list.innerHTML = (data.items || []).map((m) => `
            <div class="px-3 py-2 border-t border-paper-200 first:border-t-0">
                <div class="text-[12px] font-semibold text-ink-900 truncate">${esc(m.name)}</div>
                <div class="text-[11.5px] text-ink-600 truncate">${esc(m.preview)}</div>
                <div class="text-[10px] font-mono text-ink-500 mt-0.5">${esc(m.at)}</div>
            </div>`).join('');
    }
}

function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}

async function poll() {
    try {
        const r = await fetch('/instagram/inbox/unread-summary', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        // 429 is the one status worth naming: the server is asking us to slow
        // down, so back off rather than retry at the base interval.
        if (r.status === 429) { delay = Math.min(delay * 2, MAX_MS); schedule(); return; }
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        paint(await r.json());
        delay = POLL_MS;                       // healthy response resets backoff
    } catch {
        // Back off rather than hammer a failing endpoint on a 15s timer.
        delay = Math.min(delay * 2, MAX_MS);
    } finally {
        schedule();
    }
}

function schedule() {
    clearTimeout(timer);
    // setTimeout, not setInterval: a slow response must never let two requests
    // overlap.
    if (!document.hidden) timer = setTimeout(poll, delay);
}

export default function init() {
    // The inbox is live already; announcing a message on the page that shows it
    // is noise. Everything else in the shell gets the pill.
    if (document.body?.dataset?.page === 'instagram-inbox') return;

    const { bell } = els();

    document.getElementById('ibx-bell-btn')?.addEventListener('click', () => {
        window.location.href = bell?.dataset?.route || '/instagram/inbox';
    });

    document.getElementById('ibx-bell-x')?.addEventListener('click', (e) => {
        e.stopPropagation();
        // Dismisses everything up to the current max id and REMEMBERS it (survives
        // reloads). The pill only comes back when a message with a higher id than
        // this arrives — so a page refresh no longer resurrects the whole backlog.
        writeDismissed(Number(bell?.dataset?.maxId || 0));
        bell?.classList.add('hidden');
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) { clearTimeout(timer); return; }
        delay = POLL_MS;   // came back to the tab: check immediately
        poll();
    });

    poll();
}

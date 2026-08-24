// IgDesk Direct inbox — thread search filter, message auto-scroll, detail
// panel toggle. Page key: instagram-inbox (registered in app.js).
export default function init() {
    // ── Auto-scroll the open thread to the newest message ──
    const scroll = document.getElementById('ig-message-scroll');
    if (scroll) scroll.scrollTop = scroll.scrollHeight;

    // ── Timestamps in the VIEWER's local timezone ──
    //    The server stores/emits UTC; render each stamp in the browser's own
    //    timezone so it matches the operator's device clock (was showing UTC).
    const fmtLocal = (iso) => {
        if (!iso) return '';
        const d = new Date(iso);
        return isNaN(d.getTime()) ? '' : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
    };
    const localizeTimes = (root) => {
        (root || document).querySelectorAll('.ig-localtime[data-ts]').forEach((el) => {
            const t = fmtLocal(el.dataset.ts);
            if (t) el.textContent = t;
        });
    };
    localizeTimes(document);   // fix the server-rendered bubbles on load

    // Day separators — "13/05/2024, 15:04" in the VIEWER's timezone. The server
    // renders UTC; a separator claiming a different day than the bubbles beneath
    // it is worse than no separator at all.
    const localizeDays = (root) => {
        (root || document).querySelectorAll('.ig-daystamp[data-ts]').forEach((el) => {
            const d = new Date(el.dataset.ts);
            if (isNaN(d)) return;
            const p = (n) => String(n).padStart(2, '0');
            el.textContent = `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()}, ${p(d.getHours())}:${p(d.getMinutes())}`;
        });
    };
    localizeDays(document);

    // In-app reel/video player. Tapping a reel bubble opens the modal and streams
    // it in an <iframe> (a raw inline <video> can't decode Meta's signed CDN url,
    // but a document-level fetch — what the iframe does — plays it). Delegated so
    // live-polled bubbles work too. The href stays a no-JS new-tab fallback.
    const reelModal = document.getElementById('ig-reel-modal');
    const reelVideo = document.getElementById('ig-reel-video');
    const reelFrame = document.getElementById('ig-reel-frame');
    const reelExt   = document.getElementById('ig-reel-ext');
    const reelTpl   = reelModal?.getAttribute('data-media-tpl') || '';
    let   reelLink  = '';
    // A DM-shared reel's url is its public permalink; Instagram's embed player at
    // {permalink}/embed/ IS framable — used as the fallback when we can't stream
    // the real .mp4 inline (private / login-walled reels).
    const toEmbed = (url) => {
        const m = String(url || '').match(/instagram\.com\/(reel|reels|p|tv)\/([A-Za-z0-9_-]+)/i);
        if (!m) return '';
        return `https://www.instagram.com/${m[1] === 'reels' ? 'reel' : m[1]}/${m[2]}/embed/`;
    };
    const showEmbed = () => {
        const embed = toEmbed(reelLink);
        reelVideo?.classList.add('hidden');
        if (reelVideo) { reelVideo.removeAttribute('src'); reelVideo.load(); }
        if (embed && reelFrame) { reelFrame.src = embed; reelFrame.classList.remove('hidden'); }
    };
    const closeReel = () => {
        if (!reelModal) return;
        reelModal.classList.add('hidden');
        reelModal.classList.remove('flex');
        if (reelVideo) { reelVideo.pause?.(); reelVideo.removeAttribute('src'); reelVideo.load(); reelVideo.classList.remove('hidden'); }
        if (reelFrame) { reelFrame.src = 'about:blank'; reelFrame.classList.add('hidden'); }
    };
    if (reelModal && reelVideo) {
        // If the proxied stream can't be resolved (private reel etc.) the <video>
        // fires 'error' → swap to Instagram's embed player.
        reelVideo.addEventListener('error', showEmbed);
        document.addEventListener('click', (e) => {
            const open = e.target.closest('.ig-reel-open');
            if (open) {
                reelLink = open.getAttribute('data-reel') || '';
                const embed = toEmbed(reelLink);
                const id    = open.getAttribute('data-reel-id');
                if (!embed && !(id && reelTpl)) return;   // nothing to show → let the fallback href open a tab
                e.preventDefault();
                if (reelExt) reelExt.href = reelLink || '#';
                if (embed) {
                    // A DM-shared reel is only a permalink, and Instagram gates the
                    // raw video behind a login — so their embed player (shown here)
                    // is the most we can render in-app. Straight to it, no delay.
                    reelVideo.classList.add('hidden'); reelVideo.removeAttribute('src'); reelVideo.load();
                    reelFrame.src = embed; reelFrame.classList.remove('hidden');
                } else {
                    // Rare: a reel that carried a direct CDN video → proxy-stream it.
                    reelFrame.classList.add('hidden'); reelFrame.src = 'about:blank';
                    reelVideo.classList.remove('hidden');
                    reelVideo.src = reelTpl.replace('MID', id) + '?nc=' + Date.now();
                    reelVideo.play?.().catch(() => {});
                }
                reelModal.classList.remove('hidden');
                reelModal.classList.add('flex');
                return;
            }
            if (e.target === reelModal || e.target.closest('#ig-reel-close')) closeReel();
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !reelModal.classList.contains('hidden')) closeReel(); });
    }

    // ── Per-message reaction affordance builders (mirror the Blade markup so
    //    live-polled bubbles are reactable too). Trigger = inline SVG face icon.
    //    Instagram's reaction API accepts ANY emoji — but it must be sent as the
    //    literal emoji, NOT a keyword ('love'/'like'/… returns "Invalid reaction").
    //    So the palette is a quick set + a "+" that opens the full emoji picker,
    //    matching Team Inbox. The stored/returned value IS the emoji itself.
    const QUICK_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
    // Legacy rows saved the old keyword — map those so they still render; new
    // rows already hold the emoji verbatim. Also strips any HTML-unsafe chars.
    const LEGACY_EMOJI = { love: '❤️', like: '👍', haha: '😂', wow: '😮', sad: '😢', angry: '😡' };
    const asEmoji = (v) => (LEGACY_EMOJI[v] || String(v || '')).replace(/[<>&"']/g, '');
    // Instagram-style hover actions: react (smiley) · reply (arrow) · more (3-dot)
    // in a horizontal row beside the bubble (out → left, in → right). Mirrors the
    // Blade $igMsgActions; the classes drive the same delegated handlers below.
    const IG_ACT_BTN = 'w-6 h-6 grid place-items-center text-ink-400 hover:text-ink-700 transition';
    const igSmileyBtn = `<button type="button" class="ig-react-trigger ${IG_ACT_BTN}" title="React" aria-label="React"><svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="1" fill="currentColor" stroke="none"/><path d="M8.5 14a4.5 4.5 0 0 0 7 0"/></svg></button>`;
    const igReplyBtn  = `<button type="button" class="ig-reply-trigger ${IG_ACT_BTN}" title="Reply" aria-label="Reply"><svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 7 4 12l5 5M4 12h9a5 5 0 0 1 5 5v1"/></svg></button>`;
    const igMoreBtn   = `<button type="button" class="ig-msg-menu ${IG_ACT_BTN}" title="More" aria-label="More"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="currentColor"><circle cx="8" cy="3" r="1.35"/><circle cx="8" cy="8" r="1.35"/><circle cx="8" cy="13" r="1.35"/></svg></button>`;
    const msgActionsHtml = (out) =>
        `<div class="ig-msg-actions absolute top-1/2 -translate-y-1/2 ${out ? 'right-full mr-1' : 'left-full ml-1'} flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition">`
        + (out ? igMoreBtn + igReplyBtn + igSmileyBtn : igSmileyBtn + igReplyBtn + igMoreBtn) + '</div>';
    // WhatsApp-style corner pip (out → bottom-left, in → bottom-right) — same
    // markup/positioning as Team Inbox's reactionPip. Hidden when empty.
    const reactPipHtml = (value, out) => {
        const em = asEmoji(value);
        const base = `ig-react-pip absolute -bottom-2 ${out ? 'left-2' : 'right-2'} bg-paper-0 border border-paper-200 rounded-full px-1.5 py-0.5 text-[13px] shadow-sm leading-none`;
        return em
            ? `<span class="${base}" title="Reaction" data-reaction="${em}">${em}</span>`
            : `<span class="${base} hidden" title="Reaction"></span>`;
    };
    // Pin badge (mirror Blade $igPinBadge). The 3-dot "More" now lives inside
    // msgActionsHtml above alongside the react + reply icons.
    const pinBadgeHtml = (out, pinned) =>
        `<span class="ig-pin-badge absolute -top-2.5 ${out ? 'right-2' : 'left-2'} w-4 h-4 rounded-full bg-ig-pink text-white grid place-items-center shadow-sm${pinned ? '' : ' hidden'}" title="Pinned">`
        + '<svg viewBox="0 0 24 24" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2h8M11 2l1 7-3 2v2h6v-2l-3-2 1-7M12 15v7"/></svg></span>';
    // Interactive payload of a sent template (mirrors Blade $igTplBlock) — quick
    // replies / buttons as white pills, carousel as a mini card strip.
    const tplBlockHtml = (tpl) => {
        if (!tpl || typeof tpl !== 'object') return '';
        const t = tpl.tpl || '';
        const e = (s) => { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; };
        if (t === 'quick_replies' || t === 'buttons') {
            const btns = Array.isArray(tpl.buttons) ? tpl.buttons : [];
            if (!btns.length) return '';
            const rows = btns.map((b) => {
                const icon = b.url
                    ? '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6.5 9.5 9.5 6.5M7 4.5 8.5 3a2.5 2.5 0 0 1 3.5 3.5L10.5 8M9 11.5 7.5 13A2.5 2.5 0 0 1 4 9.5L5.5 8"/></svg>'
                    : '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13 8a5 5 0 1 1-1.5-3.5M13 3v2h-2"/></svg>';
                return `<div class="bg-white/95 rounded-xl text-center text-[12px] text-ig-pink font-medium py-2 flex items-center justify-center gap-1.5">${icon}<span class="truncate">${e(b.title || 'Button')}</span></div>`;
            }).join('');
            return `<div class="mt-2 space-y-1.5">${rows}</div>`;
        }
        if (t === 'carousel') {
            const cards = Array.isArray(tpl.cards) ? tpl.cards : [];
            if (!cards.length) return '';
            const slides = cards.map((c) => {
                const thumb = c.image
                    ? `<div class="h-20 bg-center bg-cover" style="background-image:url('${String(c.image).replace(/'/g, '')}')"></div>`
                    : '<div class="h-20 bg-black/10"></div>';
                const sub = c.subtitle ? `<div class="text-[10px] text-[#8e8e8e] truncate">${e(c.subtitle)}</div>` : '';
                return `<div class="w-32 shrink-0 rounded-xl overflow-hidden bg-[#ffffff]">${thumb}<div class="px-2 py-1.5"><div class="text-[11px] font-semibold text-[#262626] truncate">${e(c.title || '')}</div>${sub}</div></div>`;
            }).join('');
            return `<div class="mt-2 flex gap-2 overflow-x-auto pb-1">${slides}</div>`;
        }
        return '';
    };

    // ── Live poll — fetch new messages for the open thread from our DB (cheap;
    //    the webhook keeps the DB live), then append. Same feel as Team Inbox. ──
    if (scroll && scroll.dataset.accountId && scroll.dataset.igsid) {
        let lastId = parseInt(scroll.dataset.lastId || '0', 10);
        let busy = false;
        const esc = (s) => { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; };
        const escAttr = (s) => esc(s).replace(/"/g, '&quot;');
        // Render a live-polled media attachment as its real element (image / reel /
        // video / audio / share), mirroring the Blade renderer.
        const mediaHtml = (m) => {
            const t = m.atype, u = m.aurl;
            if (!t) return '';
            if (u && (t === 'image' || t === 'story_mention'))
                return `<a href="${escAttr(u)}" target="_blank" rel="noopener"><img src="${escAttr(u)}" alt="" loading="lazy" class="rounded-2xl max-w-full max-h-72 object-cover mb-1"></a>`;
            if (u && (t === 'video' || t === 'ig_reel' || t === 'reel')) {
                const reel = (t === 'ig_reel' || t === 'reel');
                // Tapping plays it in the in-app modal (delegated handler below);
                // href is the no-JS fallback. Inline <video> can't decode Meta's
                // signed CDN url, so the modal streams it in an <iframe>.
                return `<a href="${escAttr(u)}" target="_blank" rel="noopener" data-reel="${escAttr(u)}" data-reel-id="${escAttr(m.id || '')}" class="ig-reel-open relative block rounded-2xl overflow-hidden bg-black mb-1 w-[188px] max-w-full cursor-pointer">`
                    + `<video src="${escAttr(u)}" muted playsinline preload="metadata" class="block w-full ${reel ? 'aspect-[9/16]' : 'max-h-[280px]'} object-cover bg-black"></video>`
                    + `<span class="absolute inset-0 grid place-items-center bg-black/15 hover:bg-black/25 transition"><span class="w-10 h-10 rounded-full bg-black/55 grid place-items-center shadow-soft"><svg viewBox="0 0 24 24" class="w-4.5 h-4.5 text-white translate-x-[1px]" fill="currentColor"><path d="M8 5.5v13l11-6.5z"/></svg></span></span>`
                    + `</a>`;
            }
            if (u && t === 'audio')
                return `<audio src="${escAttr(u)}" controls preload="none" class="w-56 max-w-full mb-1"></audio>`;
            // Story reply → thumbnail + "Replied to your story" (mirrors Blade).
            if (t === 'story_reply') {
                const isVid = u && /\.(mp4|mov|webm|m4v)$/i.test(u);
                const thumb = u
                    ? (isVid
                        ? `<video src="${escAttr(u)}" muted preload="metadata" class="w-9 h-14 rounded-lg object-cover bg-black shrink-0"></video>`
                        : `<img src="${escAttr(u)}" alt="" loading="lazy" class="w-9 h-14 rounded-lg object-cover bg-paper-100 shrink-0">`)
                    : '<div class="w-9 h-14 rounded-lg bg-paper-100 grid place-items-center shrink-0"><svg viewBox="0 0 20 20" class="w-4 h-4 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="3" width="12" height="14" rx="3"/></svg></div>';
                const inner = `<div class="flex items-center gap-2 mb-1.5">${thumb}<div class="text-[10.5px] text-ink-400 flex items-center gap-1"><svg viewBox="0 0 16 16" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 4 4 9l5 5M4 9h7a4 4 0 0 1 4 4v1"/></svg>Replied to your story</div></div>`;
                return u ? `<a href="${escAttr(u)}" target="_blank" rel="noopener">${inner}</a>` : inner;
            }
            // Unsent by the customer → "This message was deleted" (mirrors Blade).
            if (t === 'deleted') {
                const delIcon = '<svg viewBox="0 0 24 24" class="w-3.5 h-3.5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M6.4 6.4l11.2 11.2"/></svg>';
                return `<div class="inline-flex items-center gap-1.5 text-[12px] italic opacity-70">${delIcon}<span>This message was deleted</span></div>`;
            }
            // Shared reel/post (or a reel with no playable url) → "Shared a reel"
            // card, mirroring the Blade renderer. Links out when there's a permalink.
            if (t === 'share' || t === 'ig_reel' || t === 'reel') {
                const icon = '<svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 text-ig-pink" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4.5" width="18" height="15" rx="3.5"/><path d="M3 9h18M8.5 4.5 11 9M14 4.5l2.5 4.5"/><path d="M10.5 12.2v4.1l3.6-2.05z" fill="currentColor" stroke="none"/></svg>';
                const sub = u ? 'Tap to view on Instagram' : 'Reels shared in chat open in the Instagram app';
                // Mirror the Blade tile: a shared reel has only a permalink, so build
                // the same black tile the received ones use (minus the <video> source)
                // instead of a grey label row — our shares must look like theirs.
                if (u) {
                    const rcap = String(m.caption || m.body || '').trim();
                    return `<a href="${escAttr(u)}" target="_blank" rel="noopener" data-reel="${escAttr(u)}" data-reel-id="${escAttr(m.id || '')}" data-cap="${escAttr(rcap)}" class="ig-reel-open relative block rounded-2xl overflow-hidden bg-black mb-1 w-[188px] max-w-full aspect-[9/16] cursor-pointer">`
                        + `<span class="absolute inset-0 grid place-items-center bg-black/15 hover:bg-black/25 transition"><span class="w-10 h-10 rounded-full bg-black/55 grid place-items-center shadow-soft"><svg viewBox="0 0 24 24" class="w-4.5 h-4.5 text-white translate-x-[1px]" fill="currentColor"><path d="M8 5.5v13l11-6.5z"/></svg></span></span>`
                        + (rcap ? `<div class="absolute inset-x-0 bottom-0 pt-8 pb-2.5 px-2.5 bg-gradient-to-t from-black/75 to-transparent pointer-events-none"><div class="text-[11.5px] text-white font-medium leading-snug line-clamp-3 drop-shadow">${esc(rcap)}</div></div>` : '')
                        + `<span class="absolute left-2 top-2 w-5 h-5 rounded-full bg-black/55 grid place-items-center pointer-events-none"><svg viewBox="0 0 24 24" class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><path d="M3 8.5h18M9 3l3 5.5M15 3l3 5.5"/><path d="m10.5 12.5 4 2.2-4 2.3z" fill="currentColor" stroke="none"/></svg></span>`
                        + `</a>`;
                }
                return `<div class="flex items-center gap-2.5 rounded-2xl border border-paper-200 bg-paper-50 px-3 py-2.5 mb-1 min-w-[190px] max-w-[240px]">${icon}<div class="min-w-0"><div class="text-[12.5px] font-medium truncate">Shared a reel</div><div class="text-[10.5px] text-ink-400 truncate">${sub}</div></div></div>`;
            }
            if (u)
                return `<a href="${escAttr(u)}" target="_blank" rel="noopener" class="text-[12px] underline mb-1 inline-block">${esc(t)}</a>`;
            return `<div class="text-[11.5px] italic opacity-80 mb-1">${esc(t)}</div>`;
        };
        const poll = async () => {
            if (busy || document.hidden) return;
            busy = true;
            try {
                const url = `/instagram/inbox/poll?account_id=${encodeURIComponent(scroll.dataset.accountId)}`
                    + `&igsid=${encodeURIComponent(scroll.dataset.igsid)}&after=${lastId}`;
                const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) return;
                const j = await r.json();
                const atBottom = scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < 80;
                (j.messages || []).forEach((m) => {
                    const out = m.dir === 'out';
                    const mid = m.mid || '';   // reactable only when the poll supplies a mid
                    const hasBody = m.body && m.body.trim() !== '';
                    // Skip empty, media-less messages (unsent / unsupported) so no
                    // blank bubbles ever appear live — but keep template-only sends.
                    if (!hasBody && !m.atype && !m.tpl) return;
                    const row = document.createElement('div');
                    row.className = 'flex ig-msg-row group ' + (out ? 'justify-end' : 'justify-start');
                    if (mid) { row.dataset.mid = mid; row.dataset.body = m.body || ''; }
                    const extras = mid
                        ? reactPipHtml(m.reaction || '', out) + msgActionsHtml(out) + pinBadgeHtml(out, !!m.pinned)
                        : '';
                    row.innerHTML = `<div class="max-w-[72%] px-3.5 py-2 shadow-sm relative ${out ? 'bubble-out rounded-[22px]' : 'bg-paper-0 border border-paper-200 rounded-[22px]'}">`
                        + mediaHtml(m)
                        + (hasBody ? `<div class="ig-msg-text text-[13.5px] whitespace-pre-wrap break-words">${esc(m.body)}</div>` : '')
                        + tplBlockHtml(m.tpl)
                        + `<div class="text-[9px] mono text-right mt-1.5 ${out ? 'text-white/70' : 'text-ink-500'}">${esc(fmtLocal(m.ts) || m.time)}</div>`
                        + extras
                        + `</div>`;
                    scroll.appendChild(row);
                });
                if (j.last_id && j.last_id > lastId) {
                    lastId = j.last_id;
                    if (atBottom) scroll.scrollTop = scroll.scrollHeight;
                }
            } catch (e) {
                /* transient — ignore */
            } finally {
                busy = false;
            }
        };
        setInterval(poll, 4000);
        // Let the composer trigger an immediate poll right after an AJAX send
        // so the just-sent message shows without the 4s wait (or any reload).
        window.igPollNow = poll;
    }

    // ── Per-message emoji reactions (Team-Inbox parity, Instagram theme) ──
    //    Hover a bubble → chevron trigger → floating 6-emoji row → POST
    //    /instagram/inbox/react. Optimistic corner pip; rollback + toast on
    //    failure. Re-picking the shown emoji (or tapping the pip) sends
    //    remove:true. Stored keyword re-renders the pip on reload + live poll.
    if (scroll) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const accId = scroll.dataset.accountId;
        const igsid = scroll.dataset.igsid;

        let paletteEl = null;
        const closePalette = () => { paletteEl?.remove(); paletteEl = null; };

        // Fire the reaction. remove=true clears it. Optimistic pip, rollback on
        // failure. `emoji` is the literal emoji — Instagram's API wants the emoji,
        // never a keyword — so it's what we send AND what we store in the pip.
        const sendReaction = async (row, emoji, remove) => {
            const mid = row?.dataset.mid;
            if (!mid || !accId || !igsid) return;
            const pip = row.querySelector('.ig-react-pip');
            const prev = pip
                ? { txt: pip.textContent, val: pip.dataset.reaction || '', hidden: pip.classList.contains('hidden') }
                : null;
            if (pip) {
                if (remove) { pip.classList.add('hidden'); pip.textContent = ''; pip.removeAttribute('data-reaction'); }
                else { pip.textContent = emoji; pip.dataset.reaction = emoji; pip.classList.remove('hidden'); }
            }
            const rollback = () => {
                if (!pip || !prev) return;
                pip.textContent = prev.txt;
                if (prev.val) pip.dataset.reaction = prev.val; else pip.removeAttribute('data-reaction');
                pip.classList.toggle('hidden', prev.hidden);
            };
            try {
                const r = await fetch('/instagram/inbox/react', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        instagram_account_id: accId,
                        igsid,
                        message_id: mid,
                        reaction: emoji,
                        remove: !!remove,
                    }),
                });
                const j = await r.json().catch(() => ({}));
                if (!r.ok || j.ok === false) {
                    rollback();
                    window.toast?.(j.message || 'Could not react.', 'error');
                }
            } catch (e) {
                rollback();
                window.toast?.('Could not react — check your connection.', 'error');
            }
        };

        // ── Full emoji picker for reactions — Instagram accepts ANY emoji, so
        //    the "+" opens the same lazy-loaded emoji-picker-element the composer
        //    uses. Pick any emoji → reacts. Theme-aware (light/dark). ──
        let reactPickerEl = null;
        const closeReactPicker = () => { reactPickerEl?.remove(); reactPickerEl = null; };
        const openFullPicker = async (anchor, row) => {
            const a = anchor.getBoundingClientRect();   // capture before we tear the palette down
            closePalette();
            closeReactPicker();
            const holder = document.createElement('div');
            holder.className = 'ig-react-picker fixed z-[70] rounded-2xl overflow-hidden shadow-soft border border-paper-200 bg-paper-0';
            holder.style.width = '340px';
            reactPickerEl = holder;
            document.body.appendChild(holder);
            // Position near the "+" anchor, clamped inside the viewport.
            const W = 340, H = 420;
            let left = Math.min(Math.max(8, a.left + a.width / 2 - W / 2), window.innerWidth - 8 - W);
            let top = a.bottom + 6;
            if (top + H > window.innerHeight - 8) top = Math.max(8, a.top - H - 6);
            holder.style.left = left + 'px';
            holder.style.top = top + 'px';
            try {
                await import('emoji-picker-element');
                if (!reactPickerEl) return;             // closed while loading
                const picker = document.createElement('emoji-picker');
                const dark = document.documentElement.getAttribute('data-theme') === 'dark'
                    || document.body.getAttribute('data-theme') === 'dark';
                picker.classList.add('chat-emoji-picker', dark ? 'dark' : 'light');
                picker.style.width = '100%';
                picker.addEventListener('emoji-click', (ev) => {
                    const native = ev.detail?.unicode || ev.detail?.emoji?.unicode;
                    closeReactPicker();
                    if (native) sendReaction(row, native, false);
                });
                holder.appendChild(picker);
            } catch (e) {
                closeReactPicker();
                window.toast?.('Could not load emojis.', 'error');
            }
        };

        // Build + position the floating quick-emoji row (+ "more") near the
        // clicked trigger (Team-Inbox openMessageMenu style: fixed, clamped).
        const openPalette = (trigger, row) => {
            closePalette();
            const pip = row.querySelector('.ig-react-pip');
            const active = (pip && !pip.classList.contains('hidden')) ? (pip.dataset.reaction || '') : '';
            paletteEl = document.createElement('div');
            paletteEl.dataset.forMid = row.dataset.mid || '';
            paletteEl.className = 'fixed z-[60] flex items-center gap-0.5 px-1.5 py-1 rounded-full bg-paper-0 border border-paper-200 shadow-soft';
            paletteEl.innerHTML = QUICK_REACTIONS.map((em) =>
                `<button type="button" class="ig-react-emoji w-7 h-7 rounded-full grid place-items-center text-[18px] leading-none cursor-pointer hover:bg-paper-100 ${em === active ? 'bg-paper-100' : ''}" data-emoji="${em}" title="React">${em}</button>`
            ).join('')
            + '<button type="button" class="ig-react-more w-7 h-7 rounded-full grid place-items-center text-ink-500 hover:bg-paper-100 cursor-pointer" title="More emojis" aria-label="More emojis">'
            + '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg></button>';
            document.body.appendChild(paletteEl);
            // Position above the trigger, clamped; flip below if there's no room.
            const r = trigger.getBoundingClientRect();
            const w = paletteEl.offsetWidth, h = paletteEl.offsetHeight;
            let left = r.left + r.width / 2 - w / 2;
            left = Math.max(8, Math.min(left, window.innerWidth - 8 - w));
            let top = r.top - h - 6;
            if (top < 8) top = r.bottom + 6;
            paletteEl.style.left = left + 'px';
            paletteEl.style.top = top + 'px';
            paletteEl.querySelectorAll('.ig-react-emoji').forEach((b) => {
                b.addEventListener('click', (ev) => {
                    ev.stopPropagation();
                    const emoji = b.dataset.emoji;
                    const same = emoji === active;   // re-pick the shown emoji → clear
                    closePalette();
                    sendReaction(row, emoji, same);
                });
            });
            paletteEl.querySelector('.ig-react-more')?.addEventListener('click', (ev) => {
                ev.stopPropagation();
                openFullPicker(ev.currentTarget, row);
            });
        };

        // One delegated handler on the scroll container: toggle palette + pip-tap-to-clear.
        scroll.addEventListener('click', (e) => {
            const trigger = e.target.closest('.ig-react-trigger');
            if (trigger) {
                e.stopPropagation();
                const row = trigger.closest('.ig-msg-row');
                const wasOpen = paletteEl && paletteEl.dataset.forMid === (row.dataset.mid || '');
                closePalette();
                closeReactPicker();
                if (!wasOpen) openPalette(trigger, row);
                return;
            }
            const pip = e.target.closest('.ig-react-pip');
            if (pip && !pip.classList.contains('hidden') && pip.dataset.reaction) {
                e.stopPropagation();
                sendReaction(pip.closest('.ig-msg-row'), pip.dataset.reaction, true);
                return;
            }
            // Reply → jump the operator straight to the composer for this thread.
            const reply = e.target.closest('.ig-reply-trigger');
            if (reply) {
                e.stopPropagation();
                const box = document.getElementById('ig-reply-body');
                if (box) { box.focus(); box.scrollIntoView({ block: 'nearest' }); }
            }
        });

        // Close the palette + picker on outside-click / Escape.
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.ig-react-trigger') && !e.target.closest('.ig-react-emoji') && !e.target.closest('.ig-react-more')) closePalette();
            if (!e.target.closest('.ig-react-picker') && !e.target.closest('.ig-react-more')) closeReactPicker();
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closePalette(); closeReactPicker(); } });
    }

    // ── Per-message 3-dot menu (Forward / Copy / Translate / Pin / Report) ──
    if (scroll) {
        const csrf  = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const accId = scroll.dataset.accountId;
        const igsid = scroll.dataset.igsid;
        const mEsc  = (s) => { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; };
        const mAttr = (s) => mEsc(s).replace(/"/g, '&quot;');

        let menuEl = null, fwdEl = null;
        const closeMenu = () => { menuEl?.remove(); menuEl = null; };
        const closeFwd  = () => { fwdEl?.remove(); fwdEl = null; };

        const postJson = async (url, payload) => {
            const r = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload),
            });
            return { ok: r.ok, json: await r.json().catch(() => ({})) };
        };

        const rowText  = (row) => row?.dataset.body || row?.querySelector('.ig-msg-text')?.textContent || '';
        const rowMedia = (row) => {
            const vid = row?.querySelector('video'); if (vid?.src) return { url: vid.src, type: 'video' };
            const img = row?.querySelector('img');   if (img?.src) return { url: img.src, type: 'image' };
            return null;
        };

        const doCopy = async (row) => {
            try { await navigator.clipboard.writeText(rowText(row)); window.toast?.('Copied to clipboard.', 'instagram'); }
            catch (e) { window.toast?.('Could not copy.', 'error'); }
        };

        const doTranslate = async (row) => {
            const t = rowText(row);
            if (!t.trim()) { window.toast?.('Nothing to translate.', 'error'); return; }
            const existing = row.querySelector('.ig-msg-translation');
            if (existing) { existing.remove(); return; }   // toggle off
            const block = document.createElement('div');
            block.className = 'ig-msg-translation text-[12.5px] mt-1.5 pt-1.5 border-t border-current/15 opacity-90 italic';
            block.textContent = '…';
            (row.querySelector('.ig-msg-text') || row.firstElementChild)?.appendChild(block);
            const { ok, json } = await postJson('/instagram/inbox/translate', { body: t });
            if (ok && json.ok) block.textContent = json.text;
            else { block.remove(); window.toast?.(json.message || 'Translation unavailable.', 'error'); }
        };

        const doPin = async (row) => {
            const mid = row?.dataset.mid; if (!mid) return;
            const badge = row.querySelector('.ig-pin-badge');
            const isPinned = badge && !badge.classList.contains('hidden');
            const { ok, json } = await postJson('/instagram/inbox/pin', { instagram_account_id: accId, message_id: mid, pin: !isPinned });
            if (ok && json.ok) { badge?.classList.toggle('hidden', !json.pinned); window.toast?.(json.message, 'instagram'); }
            else window.toast?.(json.message || 'Could not pin.', 'error');
        };

        const doReport = async (row) => {
            const { ok, json } = await postJson('/instagram/inbox/report', { instagram_account_id: accId, igsid, message_id: row?.dataset.mid || '' });
            window.toast?.((ok && json.ok) ? json.message : (json.message || 'Could not report.'), (ok && json.ok) ? 'instagram' : 'error');
        };

        const openForward = async (row) => {
            closeFwd(); closeMenu();
            const t = rowText(row), media = rowMedia(row);
            fwdEl = document.createElement('div');
            fwdEl.className = 'fixed inset-0 z-[80] flex items-center justify-center bg-black/40';
            fwdEl.innerHTML = '<div class="bg-paper-0 rounded-2xl shadow-soft w-[340px] max-w-[92vw] max-h-[70vh] overflow-hidden flex flex-col">'
                + '<div class="px-4 py-3 hairline-b flex items-center justify-between"><span class="text-[13.5px] font-semibold">Forward to</span>'
                + '<button type="button" class="ig-fwd-close text-ink-500 hover:text-ink-900"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4l8 8M12 4l-8 8"/></svg></button></div>'
                + '<div class="ig-fwd-list overflow-y-auto p-2 text-[13px]"><div class="text-center text-ink-400 py-6 text-[12px]">Loading…</div></div></div>';
            document.body.appendChild(fwdEl);
            fwdEl.querySelector('.ig-fwd-close')?.addEventListener('click', closeFwd);
            fwdEl.addEventListener('click', (e) => { if (e.target === fwdEl) closeFwd(); });
            try {
                const r = await fetch(`/instagram/inbox/forward-targets?instagram_account_id=${encodeURIComponent(accId)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const j = await r.json();
                const list = fwdEl.querySelector('.ig-fwd-list');
                const contacts = (j.contacts || []).filter((c) => c.igsid !== igsid);
                if (!contacts.length) { list.innerHTML = '<div class="text-center text-ink-400 py-6 text-[12px]">No recent chats to forward to.</div>'; return; }
                list.innerHTML = contacts.map((c) =>
                    `<button type="button" class="ig-fwd-pick w-full text-left px-3 py-2.5 rounded-xl hover:bg-paper-100 flex items-center gap-2" data-igsid="${mAttr(c.igsid)}"><span class="w-7 h-7 rounded-full ig-grad-soft shrink-0"></span><span class="truncate">${mEsc(c.name)}</span></button>`
                ).join('');
                list.querySelectorAll('.ig-fwd-pick').forEach((b) => b.addEventListener('click', async () => {
                    b.disabled = true;
                    const payload = { instagram_account_id: accId, to_igsid: b.dataset.igsid, body: t };
                    if (media) { payload.media_url = media.url; payload.media_type = media.type; }
                    const { ok, json } = await postJson('/instagram/inbox/forward', payload);
                    window.toast?.((ok && json.ok) ? json.message : (json.message || 'Could not forward.'), (ok && json.ok) ? 'instagram' : 'error');
                    if (ok && json.ok) closeFwd(); else b.disabled = false;
                }));
            } catch (e) {
                const list = fwdEl.querySelector('.ig-fwd-list');
                if (list) list.innerHTML = '<div class="text-center text-red-500 py-6 text-[12px]">Could not load contacts.</div>';
            }
        };

        const openMenu = (trigger, row) => {
            closeMenu();
            const pinned = (() => { const b = row.querySelector('.ig-pin-badge'); return b && !b.classList.contains('hidden'); })();
            const items = [
                { key: 'forward',   label: 'Forward',   icon: '<path d="M3 9l7-5v3c5 0 8 3 8 8-2-3-4-4-8-4v3z"/>' },
                { key: 'copy',      label: 'Copy',      icon: '<rect x="5" y="5" width="9" height="11" rx="1.5"/><path d="M8 5V3.5A1.5 1.5 0 0 1 9.5 2H16A1.5 1.5 0 0 1 17.5 3.5V12A1.5 1.5 0 0 1 16 13.5h-1.5"/>' },
                { key: 'translate', label: 'Translate', icon: '<path d="M3 5h7M6.5 5v-.8M4.2 8s.9 3 2.8 3M8.5 5S8 9.2 4 11M11.5 17l3-7 3 7M12.6 14.5h3.8"/>' },
                { key: 'pin',       label: pinned ? 'Unpin' : 'Pin', icon: '<path d="M8 2h8M11 2l1 7-3 2v2h6v-2l-3-2 1-7M12 15v7"/>' },
                { key: 'report',    label: 'Report', danger: true, icon: '<circle cx="10" cy="10" r="8"/><path d="M10 6v5M10 14h.01"/>' },
            ];
            menuEl = document.createElement('div');
            menuEl.dataset.forMid = row.dataset.mid || '';
            menuEl.className = 'fixed z-[70] w-40 py-1.5 rounded-xl bg-paper-0 border border-paper-200 shadow-soft text-[13px]';
            menuEl.innerHTML = items.map((it) =>
                `<button type="button" class="ig-menu-item w-full flex items-center justify-between gap-2 px-3 py-2 hover:bg-paper-100 ${it.danger ? 'text-red-500' : ''}" data-act="${it.key}"><span>${it.label}</span><svg viewBox="0 0 20 20" class="w-4 h-4 opacity-80" fill="none" stroke="currentColor" stroke-width="1.5">${it.icon}</svg></button>`
            ).join('');
            document.body.appendChild(menuEl);
            const r = trigger.getBoundingClientRect();
            const w = menuEl.offsetWidth, h = menuEl.offsetHeight;
            let left = Math.min(Math.max(8, r.left + r.width / 2 - w / 2), window.innerWidth - 8 - w);
            let top = r.bottom + 6;
            if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - 6);
            menuEl.style.left = left + 'px';
            menuEl.style.top = top + 'px';
            menuEl.querySelectorAll('.ig-menu-item').forEach((b) => b.addEventListener('click', (ev) => {
                ev.stopPropagation();
                const act = b.dataset.act;
                closeMenu();
                if (act === 'copy') doCopy(row);
                else if (act === 'translate') doTranslate(row);
                else if (act === 'pin') doPin(row);
                else if (act === 'report') doReport(row);
                else if (act === 'forward') openForward(row);
            }));
        };

        scroll.addEventListener('click', (e) => {
            const trigger = e.target.closest('.ig-msg-menu');
            if (!trigger) return;
            e.stopPropagation();
            const row = trigger.closest('.ig-msg-row');
            const wasOpen = menuEl && menuEl.dataset.forMid === (row.dataset.mid || '');
            closeMenu();
            if (!wasOpen) openMenu(trigger, row);
        });
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.ig-msg-menu') && !e.target.closest('.ig-menu-item')) closeMenu();
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeMenu(); closeFwd(); } });
    }

    // ── Client-side thread search (filters the rendered list). Re-queries rows
    //    on each keystroke so it keeps working after the list is live-refreshed. ──
    const search = document.getElementById('ig-thread-search');
    const list = document.getElementById('ig-thread-list');
    const empty = document.getElementById('ig-thread-empty');
    const applySearch = () => {
        if (!search || !list) return;
        const q = search.value.trim().toLowerCase();
        const rows = Array.from(list.querySelectorAll('.ig-thread'));
        let shown = 0;
        rows.forEach((r) => {
            const hit = !q || (r.dataset.search || '').includes(q);
            r.classList.toggle('hidden', !hit);
            if (hit) shown += 1;
        });
        if (empty) empty.classList.toggle('hidden', shown !== 0 || rows.length === 0);
    };
    if (search && list) search.addEventListener('input', applySearch);

    // ── Live thread LIST — a new DM (or a bumped preview) pops in without a
    //    reload, exactly like the Team Inbox. Poll a super-cheap "max message id"
    //    endpoint; when it grows, re-fetch the page and swap in the fresh list. ──
    if (list) {
        let maxId = parseInt(list.dataset.maxId || '0', 10);
        const acc = list.dataset.account || '0';
        let busyList = false;
        const refreshList = async () => {
            try {
                const r = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) return;
                const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
                const fresh = doc.getElementById('ig-thread-list');
                if (fresh) {
                    list.innerHTML = fresh.innerHTML;
                    maxId = parseInt(fresh.dataset.maxId || String(maxId), 10);
                    applySearch();               // re-apply the active filter
                }
            } catch (e) { /* transient */ }
        };
        const listPoll = async () => {
            if (busyList || document.hidden) return;
            busyList = true;
            try {
                const r = await fetch(`/instagram/inbox/list-poll?account=${encodeURIComponent(acc)}`,
                    { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (r.ok) {
                    const j = await r.json();
                    if (j.max_id && j.max_id > maxId) await refreshList();
                }
            } catch (e) { /* transient */ } finally { busyList = false; }
        };
        setInterval(listPoll, 5000);

        // ── Background auto-sync — pull fresh DMs from Instagram so new messages
        //    arrive near real-time even without a webhook (Instagram often doesn't
        //    deliver message webhooks until Advanced Access is granted). Fast 12s
        //    cadence + an immediate sync whenever the tab regains focus, so the
        //    inbox pops in new DMs like the Team Inbox. Silent JSON; the list +
        //    thread polls above then render what the sync stored. ──
        let busySync = false;
        const autoSync = async () => {
            if (busySync || document.hidden) return;
            busySync = true;
            try {
                await fetch('/instagram/inbox/sync', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            } catch (e) { /* transient */ } finally { busySync = false; }
        };
        autoSync();                                   // pull immediately on open
        setInterval(autoSync, 12000);                 // then every 12s
        // Re-sync the instant the operator returns to the tab (matches how a
        // real inbox feels — you switch back and the latest DMs are already there).
        document.addEventListener('visibilitychange', () => { if (!document.hidden) autoSync(); });
        window.addEventListener('focus', autoSync);
    }

    // ── Detail panel show/hide ──
    const toggle = document.getElementById('ig-detail-toggle');
    const panel = document.getElementById('ig-detail-panel');
    if (toggle && panel) {
        toggle.addEventListener('click', () => panel.classList.toggle('hidden'));
    }

    // ── Reply composer — Instagram-style (empty → voice/photo/GIF icons; typing → send) ──
    // ── New message (compose) — the rail's single Instagram-style ✎ ──
    // Pick one of the people who have DMed us and jump to their thread. Meta's
    // Messaging API can only reply INTO an existing conversation, so the list is
    // contacts, never an Instagram-wide user search.
    //
    // MUST live at init() top level, NOT inside the `if (form)` block below:
    // #ig-reply-form only exists once a conversation is open, so anything nested
    // there never binds on the empty state — which is exactly where compose is
    // needed most.
    const composeModal = document.getElementById('ig-compose-modal');
    if (composeModal) {
        const rows    = Array.from(composeModal.querySelectorAll('.ig-compose-row'));
        const cSearch = document.getElementById('ig-compose-search');
        const goBtn   = document.getElementById('ig-compose-go');
        const noMatch = document.getElementById('ig-compose-empty');
        const picked  = new Set();   // keys, "accountId:igsid"

        // Repaint every row from `picked` — never a per-row toggle, which can
        // drift out of sync with the state it is meant to mirror.
        const paint = () => {
            rows.forEach((r) => {
                const on   = picked.has(r.dataset.key);
                const ring = r.querySelector('.ig-radio');
                const dot  = r.querySelector('.dot');
                r.setAttribute('aria-checked', on ? 'true' : 'false');
                r.classList.remove('bg-paper-50');
                if (on) r.classList.add('bg-paper-50');
                if (ring) {
                    ring.classList.remove('border-ig-pink', 'border-paper-300');
                    ring.classList.add(on ? 'border-ig-pink' : 'border-paper-300');
                }
                if (dot) {
                    dot.classList.remove('scale-0', 'scale-100');
                    dot.classList.add(on ? 'scale-100' : 'scale-0');
                }
            });
            if (goBtn) {
                const n = picked.size;
                goBtn.disabled = n === 0;
                // One person has a thread to open; several do not — Instagram has
                // no group DM over the API, so they become individual DMs on the
                // Bulk DM page. Say which is about to happen.
                goBtn.textContent = n > 1
                    ? (window.t ? window.t('Message :n people', { n }) : `Message ${n} people`)
                    : (window.t ? window.t('Chat') : 'Chat');
            }
        };
        const closeCompose = () => composeModal.classList.add('hidden');
        const openCompose  = () => {
            composeModal.classList.remove('hidden');
            if (cSearch) { cSearch.value = ''; cSearch.dispatchEvent(new Event('input')); }
            picked.clear(); paint();
            setTimeout(() => cSearch?.focus(), 30);
        };

        document.getElementById('ig-compose-btn')?.addEventListener('click', openCompose);
        composeModal.querySelectorAll('[data-close-compose]').forEach((b) => b.addEventListener('click', closeCompose));
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !composeModal.classList.contains('hidden')) closeCompose();
        });

        rows.forEach((r) => r.addEventListener('click', () => {
            const k = r.dataset.key;
            if (picked.has(k)) picked.delete(k); else picked.add(k);   // tick / untick
            paint();
        }));

        cSearch?.addEventListener('input', () => {
            const q = cSearch.value.trim().toLowerCase();
            let shown = 0;
            rows.forEach((r) => {
                const hit = !q || (r.dataset.search || '').includes(q);
                r.classList.toggle('hidden', !hit);
                if (hit) shown++;
            });
            noMatch?.classList.toggle('hidden', shown > 0 || rows.length === 0);
        });

        goBtn?.addEventListener('click', () => {
            const keys = Array.from(picked);
            if (!keys.length) return;

            // Exactly one → open their thread right here.
            if (keys.length === 1) {
                const u = new URL(window.location.href);
                u.searchParams.set('thread', keys[0]);
                window.location.href = u.toString();
                return;
            }

            // Several → Bulk DM, pre-ticked. Keys are "accountId:igsid"; the Bulk
            // DM form posts igsids[] for ONE account, so send the account every
            // pick shares and drop any stragglers from other accounts rather than
            // silently mailing the wrong list.
            const parsed  = keys.map((k) => { const i = k.indexOf(':'); return { acc: k.slice(0, i), igsid: k.slice(i + 1) }; });
            const account = parsed[0].acc;
            const igsids  = parsed.filter((p) => p.acc === account).map((p) => p.igsid);
            const dropped = parsed.length - igsids.length;
            if (dropped > 0 && window.toast) {
                window.toast(`Messaging the ${igsids.length} from one account — Instagram can't mix accounts in one bulk DM.`, 'info');
            }
            window.location.href = `/instagram/broadcast/create?account=${encodeURIComponent(account)}&to=${encodeURIComponent(igsids.join(','))}`;
        });
    }

    const form = document.getElementById('ig-reply-form');
    if (form) {
        const body = form.querySelector('input[name="body"]');
        const file = document.getElementById('ig-reply-file');
        const fname = document.getElementById('ig-reply-fname');
        const actions  = document.getElementById('ig-compose-actions'); // mic/photo/GIF group
        const sendBtn  = document.getElementById('ig-send-btn');
        const mediaUrlEl  = document.getElementById('ig-reply-media-url');
        const mediaTypeEl = document.getElementById('ig-reply-media-type');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        // Swap the right-hand controls: icons when the field is empty, send button
        // the instant there's text or staged media (file / GIF) — exactly like IG.
        const hasStaged = () =>
            (body && body.value.trim() !== '') ||
            (file && file.files && file.files.length > 0) ||
            (mediaUrlEl && mediaUrlEl.value !== '') ||
            (document.getElementById('ig-tpl-inject')?.children.length > 0);
        const refreshComposer = () => {
            const on = hasStaged();
            actions?.classList.toggle('hidden', on);
            sendBtn?.classList.toggle('hidden', !on);
        };
        body?.addEventListener('input', refreshComposer);
        refreshComposer();

        // Show the chosen attachment's name + flip to send.
        file?.addEventListener('change', () => {
            const f = file.files && file.files[0];
            if (f && fname) { fname.textContent = f.name; fname.classList.remove('hidden'); }
            else if (fname) { fname.classList.add('hidden'); }
            refreshComposer();
        });

        // Photo/video button just opens the (hidden) file picker.
        document.getElementById('ig-photo-btn')?.addEventListener('click', () => file?.click());

        // AJAX send — NO page reload (like the Team Inbox). Posts the form, then
        // triggers an immediate poll so the sent message shows instantly.
        let sending = false;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!hasStaged()) {
                window.toast?.('Type a message or attach something before sending.', 'error');
                body?.focus();
                return;
            }
            if (sending) return;
            sending = true;
            const sendBtns = form.querySelectorAll('button[type="submit"]');
            sendBtns.forEach((b) => (b.disabled = true));
            try {
                const r = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const j = await r.json().catch(() => ({}));
                if (!r.ok || j.ok === false) {
                    window.toast?.(j.message || 'Could not send.', 'error');
                    return;
                }
                // Reset the composer: clear text, staged media, injected fields, template preview.
                if (body) body.value = '';
                if (file) { file.value = ''; }
                if (mediaUrlEl) mediaUrlEl.value = '';
                if (mediaTypeEl) mediaTypeEl.value = '';
                fname?.classList.add('hidden');
                document.getElementById('ig-tpl-inject')?.replaceChildren();
                document.getElementById('ig-tpl-preview')?.classList.add('hidden');
                document.getElementById('ig-composer-row')?.classList.remove('hidden');
                refreshComposer();
                window.igPollNow?.(); // show the sent message right away
                setTimeout(() => window.igPollNow?.(), 1200); // catch the AI/echo follow-up too
            } catch (err) {
                window.toast?.('Could not send — check your connection.', 'error');
            } finally {
                sending = false;
                sendBtns.forEach((b) => (b.disabled = false));
            }
        });

        // ── Emoji picker (emoji-picker-element web component, lazy-loaded) ──
        const emojiBtn  = document.getElementById('ig-emoji-btn');
        const emojiMenu = document.getElementById('ig-emoji-menu');
        if (emojiBtn && emojiMenu) {
            let emojiMounted = false;
            const mountEmoji = async () => {
                if (emojiMounted) return; emojiMounted = true;
                await import('emoji-picker-element');
                const picker = document.createElement('emoji-picker');
                picker.classList.add('chat-emoji-picker', 'light');
                picker.addEventListener('emoji-click', (ev) => {
                    const native = ev.detail?.unicode || ev.detail?.emoji?.unicode;
                    if (!native || !body) return;
                    const s = body.selectionStart ?? body.value.length, en = body.selectionEnd ?? body.value.length;
                    body.value = body.value.slice(0, s) + native + body.value.slice(en);
                    body.focus();
                    const pos = s + native.length; body.setSelectionRange(pos, pos);
                    refreshComposer();
                });
                emojiMenu.appendChild(picker);
            };
            emojiBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                await mountEmoji();
                emojiMenu.classList.toggle('hidden');
            });
            document.addEventListener('click', (e) => { if (!e.target.closest('#ig-emoji-wrap')) emojiMenu.classList.add('hidden'); });
        }

        // ── GIF picker (official GIPHY SDK Grid, fetched via server proxy) ──
        const gifBtn  = document.getElementById('ig-sticker-btn');
        const gifMenu = document.getElementById('ig-gif-menu');
        const gifGrid = document.getElementById('ig-gif-grid');
        const gifSearch = document.getElementById('ig-gif-search');
        if (gifBtn && gifMenu && gifGrid) {
            let gifMounted = false, removeGrid = null, gifTerm = '', gifTimer = null, renderGridFn = null;
            // fetchGifs feeds the GIPHY Grid — our proxy returns GIPHY's native
            // GifsResult verbatim, so the SDK renders it directly (key stays server-side).
            const fetchGifs = async (offset) => {
                const url = new URL(gifMenu.dataset.url, window.location.origin);
                if (gifTerm) url.searchParams.set('q', gifTerm);
                url.searchParams.set('offset', offset || 0);
                url.searchParams.set('limit', 12);
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                return r.json();
            };
            const drawGrid = () => {
                if (!renderGridFn) return;
                if (removeGrid) { try { removeGrid(); } catch (_) {} removeGrid = null; }
                gifGrid.innerHTML = '';
                removeGrid = renderGridFn({
                    fetchGifs,
                    width: 276,
                    columns: 2,
                    gutter: 6,
                    hideAttribution: true,
                    noLink: true,
                    onGifClick: (gif, e) => {
                        e?.preventDefault?.();
                        // GIPHY objects carry an MP4 — Instagram accepts video attachments.
                        const mp4 = gif?.images?.original_mp4?.mp4
                            || gif?.images?.looping?.mp4
                            || gif?.images?.downsized_small?.mp4;
                        const url = mp4 || gif?.images?.original?.url; // fall back to .gif url
                        if (!url) return;
                        if (mediaUrlEl)  mediaUrlEl.value  = url;
                        if (mediaTypeEl) mediaTypeEl.value = mp4 ? 'video' : 'image';
                        gifMenu.classList.add('hidden');
                        form.requestSubmit ? form.requestSubmit() : form.submit();
                    },
                }, gifGrid);
            };
            const mountGif = async () => {
                if (gifMounted) return; gifMounted = true;
                gifGrid.innerHTML = `<div class="text-center text-[11px] text-ink-400 py-4">Loading…</div>`;
                try {
                    const { renderGrid } = await import('@giphy/js-components');
                    renderGridFn = renderGrid;
                    drawGrid();
                } catch (e) { gifGrid.innerHTML = `<div class="text-center text-[11px] text-red-500 py-4">Could not load GIFs.</div>`; }
            };
            gifBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                gifMenu.classList.toggle('hidden');
                if (!gifMenu.classList.contains('hidden')) await mountGif();
            });
            gifSearch?.addEventListener('input', () => {
                clearTimeout(gifTimer);
                gifTimer = setTimeout(() => { gifTerm = gifSearch.value.trim(); drawGrid(); }, 400);
            });
            document.addEventListener('click', (e) => { if (!e.target.closest('#ig-gif-wrap')) gifMenu.classList.add('hidden'); });
        }

        // ── Voice note (MediaRecorder → attaches as audio, then sends) ─────
        const micBtn = document.getElementById('ig-mic-btn');
        if (micBtn && navigator.mediaDevices?.getUserMedia && window.MediaRecorder) {
            let rec = null, chunks = [], stream = null, recording = false;
            const pickMime = () => ['audio/mp4', 'audio/webm;codecs=opus', 'audio/webm', 'audio/ogg']
                .find(m => window.MediaRecorder.isTypeSupported(m)) || '';
            const setRecUI = (on) => {
                recording = on;
                micBtn.classList.toggle('text-red-500', on);
                micBtn.classList.toggle('bg-red-500/10', on);
                micBtn.title = on ? 'Stop & send voice note' : 'Record voice note';
            };
            micBtn.addEventListener('click', async () => {
                if (recording) { rec?.stop(); return; }
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    const mime = pickMime();
                    rec = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined);
                    chunks = [];
                    rec.ondataavailable = (ev) => { if (ev.data.size) chunks.push(ev.data); };
                    rec.onstop = () => {
                        stream?.getTracks().forEach(t => t.stop());
                        setRecUI(false);
                        if (!chunks.length) return;
                        const type = rec.mimeType || 'audio/webm';
                        const ext = type.includes('mp4') ? 'm4a' : (type.includes('ogg') ? 'ogg' : 'webm');
                        const blob = new File(chunks, `voice-note.${ext}`, { type });
                        const dt = new DataTransfer(); dt.items.add(blob);
                        if (file) { file.files = dt.files; }
                        if (fname) { fname.textContent = 'Voice note'; fname.classList.remove('hidden'); }
                        refreshComposer();
                        window.toast?.('Voice note ready — sending…', 'instagram');
                        form.requestSubmit ? form.requestSubmit() : form.submit();
                    };
                    rec.start();
                    setRecUI(true);
                    window.toast?.('Recording… tap the mic again to send.', 'instagram');
                } catch (e) { window.toast?.('Microphone permission denied.', 'error'); }
            });
        } else if (micBtn) {
            micBtn.addEventListener('click', () => window.toast?.('Voice notes are not supported in this browser.', 'error'));
        }

        // ── Per-conversation AI Agent picker (Team-Inbox style) ──
        // Pick a specific agent for this thread, "Turn off AI", or Manage agents.
        const agentWrap  = document.getElementById('ig-agent-wrap');
        const agentBtn   = document.getElementById('ig-agent-btn');
        const agentMenu  = document.getElementById('ig-agent-menu');
        const agentLabel = document.getElementById('ig-agent-label');
        if (agentWrap && agentBtn && agentMenu) {
            agentBtn.addEventListener('click', (e) => { e.stopPropagation(); agentMenu.classList.toggle('hidden'); });
            document.addEventListener('click', (e) => { if (!agentWrap.contains(e.target)) agentMenu.classList.add('hidden'); });

            agentMenu.querySelectorAll('.ig-agent-pick').forEach((row) => {
                row.addEventListener('click', async () => {
                    const agentId = row.dataset.agent || '0';
                    try {
                        const r = await fetch(agentWrap.dataset.url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            body: JSON.stringify({ instagram_account_id: agentWrap.dataset.account, igsid: agentWrap.dataset.igsid, agent_id: agentId }),
                        });
                        const j = await r.json();
                        if (!r.ok || !j.ok) throw new Error();
                        const on = !!j.ai_enabled;
                        if (agentLabel) agentLabel.textContent = on ? (j.agent_name || 'AI Agent') : 'Assign agent';
                        agentBtn.classList.toggle('border-ig-pink/40', on);
                        agentBtn.classList.toggle('bg-ig-pink/5', on);
                        agentBtn.classList.toggle('text-ig-pink', on);
                        agentBtn.classList.toggle('border-paper-200', !on);
                        agentBtn.classList.toggle('text-ink-500', !on);
                        // Move the tick to the chosen row.
                        agentMenu.querySelectorAll('.ig-agent-pick').forEach((r2) => {
                            const chk = r2.querySelector('.ig-agent-check');
                            if (chk) chk.classList.toggle('hidden', r2.dataset.agent !== agentId);
                        });
                        agentMenu.classList.add('hidden');
                        window.toast?.(on ? ('AI agent set: ' + (j.agent_name || 'default')) : 'AI turned off for this chat.', 'instagram');
                    } catch (e) { window.toast?.('Could not update AI agent.', 'error'); }
                });
            });
        }

        // ── AI Agents modal — create/manage agents WITHOUT leaving the inbox ──
        const agentsModal = document.getElementById('ig-agents-modal');
        if (agentsModal) {
            const openModal  = () => agentsModal.classList.remove('hidden');
            const closeModal = () => agentsModal.classList.add('hidden');
            document.querySelectorAll('.ig-agents-open').forEach((b) => b.addEventListener('click', openModal));
            agentsModal.querySelectorAll('[data-close-agents]').forEach((b) => b.addEventListener('click', closeModal));

            // Avatar-colour swatches → set the hidden input + move the ring.
            const colorWrap = document.getElementById('ig-agent-colors');
            colorWrap?.querySelectorAll('.ig-color-swatch').forEach((sw) => {
                sw.addEventListener('click', () => {
                    const hidden = colorWrap.querySelector('input[name=avatar_color]');
                    if (hidden) hidden.value = sw.dataset.color;
                    colorWrap.querySelectorAll('.ig-color-swatch').forEach((s) => s.classList.remove('ring-2', 'ring-offset-2', 'ring-ink-400'));
                    sw.classList.add('ring-2', 'ring-offset-2', 'ring-ink-400');
                });
            });

            const createForm = document.getElementById('ig-agent-create');
            const cb = (n) => createForm?.querySelector('[name=' + n + ']')?.checked ? '1' : '0';
            createForm?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = createForm.querySelector('button[type="submit"]');
                if (btn) { btn.disabled = true; btn.textContent = 'Creating…'; }
                try {
                    const fd = new FormData(createForm);
                    const r = await fetch(agentsModal.dataset.createUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: new URLSearchParams({
                            instagram_account_id: agentsModal.dataset.account,
                            name:              fd.get('name') || '',
                            provider:          fd.get('provider') || 'openai',
                            model:             fd.get('model') || '',
                            tone:              fd.get('tone') || 'professional',
                            avatar_color:      fd.get('avatar_color') || '#6366f1',
                            system_prompt:     fd.get('system_prompt') || '',
                            max_tokens:        fd.get('max_tokens') || '512',
                            temperature:       fd.get('temperature') || '7',
                            auto_respond:      cb('auto_respond'),
                            use_saved_replies: cb('use_saved_replies'),
                            handoff_enabled:   cb('handoff_enabled'),
                        }),
                    });
                    const j = await r.json();
                    if (!r.ok || !j.ok) throw new Error();
                    window.toast?.('AI agent created.', 'instagram');
                    // Reload so the header AI-Agent dropdown + this conversation immediately
                    // pick up the new agent (it becomes assignable right away).
                    setTimeout(() => window.location.reload(), 350);
                } catch (e2) {
                    window.toast?.('Could not create agent.', 'error');
                    if (btn) { btn.disabled = false; btn.textContent = 'Create agent'; }
                }
            });
        }

        // ── Saved-template picker → fills body + injects quick-reply / button fields ──
        const tplBtn = document.getElementById('ig-tpl-btn');
        const tplMenu = document.getElementById('ig-tpl-menu');
        const tplInject = document.getElementById('ig-tpl-inject');
        const tplActive = document.getElementById('ig-tpl-active');
        const tplActiveName = document.getElementById('ig-tpl-active-name');
        const escAttr = (s) => { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML.replace(/"/g, '&quot;'); };
        let tplLoaded = false;

        // Team-Inbox-style preview panel elements.
        const tplPreview = document.getElementById('ig-tpl-preview');
        const tplComposerRow = document.getElementById('ig-composer-row');
        const tpName = document.getElementById('ig-tp-name');
        const tpBody = document.getElementById('ig-tp-body');
        const tpBtns = document.getElementById('ig-tp-buttons');

        const clearTpl = () => {
            if (tplInject) tplInject.innerHTML = '';
            tplActive?.classList.add('hidden');
            tplPreview?.classList.add('hidden');          // hide the preview bubble
            tplComposerRow?.classList.remove('hidden');   // restore the normal composer
            if (body) body.value = '';
            refreshComposer();
        };
        const applyTpl = (t) => {
            if (body) body.value = t.body || '';
            if (tplInject) {
                tplInject.innerHTML = '';
                (t.items || []).forEach((it) => {
                    if (t.type === 'quick_replies') {
                        tplInject.insertAdjacentHTML('beforeend',
                            `<input type="hidden" name="qr_title[]" value="${escAttr(it.title)}">`
                          + `<input type="hidden" name="qr_payload[]" value="${escAttr(it.payload || it.title)}">`);
                    } else if (t.type === 'buttons') {
                        tplInject.insertAdjacentHTML('beforeend',
                            `<input type="hidden" name="qb_type[]" value="${escAttr(it.type || 'postback')}">`
                          + `<input type="hidden" name="qb_title[]" value="${escAttr(it.title)}">`
                          + `<input type="hidden" name="qb_value[]" value="${escAttr(it.value || '')}">`);
                    }
                });
            }
            // Populate the preview bubble (name + body + buttons / quick-replies).
            if (tpName) tpName.textContent = t.name || 'Template';
            if (tpBody) tpBody.textContent = t.body || '';
            if (tpBtns) {
                const items = t.items || [];
                const isBtnLike = (t.type === 'buttons' || t.type === 'quick_replies') && items.length;
                if (isBtnLike) {
                    tpBtns.classList.remove('hidden');
                    // Full-width white action buttons BELOW the bubble (WhatsApp/WaDesk style).
                    tpBtns.innerHTML = items.map((it) => {
                        const isUrl = (it.type === 'web_url') || !!(it.value && /^https?:/i.test(String(it.value)));
                        const icon = isUrl
                            ? '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6.5 9.5 9.5 6.5M7 4.5 8.5 3a2.5 2.5 0 0 1 3.5 3.5L10.5 8M9 11.5 7.5 13A2.5 2.5 0 0 1 4 9.5L5.5 8"/></svg>'
                            : '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13 8a5 5 0 1 1-1.5-3.5M13 3v2h-2"/></svg>';
                        return `<div class="bg-white rounded-xl shadow-sm text-center text-[12.5px] text-ig-pink font-medium py-2 flex items-center justify-center gap-1.5">${icon}<span class="truncate">${escAttr(it.title || 'Button')}</span></div>`;
                    }).join('');
                } else {
                    tpBtns.classList.add('hidden');
                    tpBtns.innerHTML = '';
                }
            }
            if (tplActiveName) tplActiveName.textContent = t.name;   // keep chip label in sync (chip stays hidden)
            tplPreview?.classList.remove('hidden');                  // show the preview
            tplComposerRow?.classList.add('hidden');                 // hide the plain composer row
            tplMenu?.classList.add('hidden');
        };
        const renderTplMenu = (templates) => {
            if (!templates.length) {
                tplMenu.innerHTML = `<div class="text-[11px] text-ink-400 px-2 py-3 text-center">No templates yet. <a href="${tplMenu.dataset.manage}" class="text-ig-pink underline">Create one</a></div>`;
                return;
            }
            tplMenu.innerHTML = templates.map((t, i) =>
                `<button type="button" data-tpl-i="${i}" class="w-full text-left px-2.5 py-2 rounded-lg hover:bg-paper-100">
                    <div class="flex items-center gap-1.5"><span class="text-[12.5px] font-medium truncate">${escAttr(t.name)}</span>
                    <span class="px-1 py-0.5 rounded font-mono text-[9px] uppercase bg-paper-100 text-ink-500">${escAttr(String(t.type || '').replace('_', ' '))}</span></div>
                    <div class="text-[11px] text-ink-500 truncate">${escAttr(t.body || '')}</div>
                 </button>`).join('')
              + `<a href="${tplMenu.dataset.manage}" class="block text-center text-[11px] text-ig-pink py-1.5 hover:underline">Manage templates</a>`;
            tplMenu.querySelectorAll('[data-tpl-i]').forEach((b) => b.addEventListener('click', () => applyTpl(templates[+b.dataset.tplI])));
        };
        tplBtn?.addEventListener('click', async () => {
            if (!tplMenu.classList.contains('hidden')) { tplMenu.classList.add('hidden'); return; }
            tplMenu.classList.remove('hidden');
            if (!tplLoaded) {
                try {
                    const r = await fetch(tplMenu.dataset.url, { headers: { 'Accept': 'application/json' } });
                    const j = await r.json();
                    renderTplMenu(j.templates || []);
                    tplLoaded = true;
                } catch (e) { tplMenu.innerHTML = '<div class="text-[11px] text-red-500 px-2 py-3 text-center">Failed to load</div>'; }
            }
        });
        tplActive?.addEventListener('click', clearTpl);
        document.getElementById('ig-tp-clear')?.addEventListener('click', clearTpl);
        document.addEventListener('click', (e) => {
            if (tplMenu && !tplMenu.classList.contains('hidden') && !tplMenu.contains(e.target) && !tplBtn.contains(e.target)) {
                tplMenu.classList.add('hidden');
            }
        });
    }
}

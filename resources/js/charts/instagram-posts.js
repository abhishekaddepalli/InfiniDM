// My posts — the grid tiles + Instagram's post modal (media, caption, comments,
// threaded replies). Comments are read live from Graph on open; we never cache
// them, because counts and text change on Instagram's side, not ours.

export default function init() {
    const root = document.getElementById('ig-posts');
    if (!root) return;

    const accountId = root.dataset.account || '';
    // Our own handle + avatar. Instagram's comments edge does NOT expose a
    // commenter's picture (there is no such field), so the only avatar we can
    // legitimately show on a comment is our own — matched by handle.
    const myHandle = String(root.dataset.me || '').replace(/^@/, '').toLowerCase();
    const myAvatar = root.dataset.meAvatar || '';
    const csrf   = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const modal  = document.getElementById('ig-post-modal');
    if (!modal) return;

    const elMedia   = document.getElementById('ig-post-media');
    const elThread  = document.getElementById('ig-post-thread');
    const elWhen    = document.getElementById('ig-post-when');
    const elLikes   = document.getElementById('ig-post-likes');
    const elComs    = document.getElementById('ig-post-comments');
    const elLikeLn  = document.getElementById('ig-post-likeline');
    const elLink    = document.getElementById('ig-post-permalink');
    const form      = document.getElementById('ig-post-reply-form');
    const input     = document.getElementById('ig-post-reply-text');
    const sendBtn   = document.getElementById('ig-post-reply-send');
    const replyBar  = document.getElementById('ig-post-replying');
    const replyWho  = document.getElementById('ig-post-replying-to');

    let post = null;          // the open post
    let replyTo = null;       // {id, username} — null = comment on the post itself

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const toast = (m, k = 'success') => (window.toast ? window.toast(m, k) : null);

    // Instagram's spelled-out age under the post ("22 minutes ago"), as opposed
    // to the terse "22 m" it uses on each comment. Falls back to a date once the
    // post is old enough that a relative age stops being useful.
    const agoLong = (iso) => {
        const d = new Date(iso);
        if (isNaN(d)) return '';
        const s = Math.max(1, (Date.now() - d.getTime()) / 1000);
        const unit = (n, w) => `${n} ${w}${n === 1 ? '' : 's'} ago`;
        if (s < 60)     return 'Just now';
        if (s < 3600)   return unit(Math.floor(s / 60), 'minute');
        if (s < 86400)  return unit(Math.floor(s / 3600), 'hour');
        if (s < 604800) return unit(Math.floor(s / 86400), 'day');
        return d.toLocaleDateString(undefined, { month: 'long', day: 'numeric' });
    };

    // "48 w" / "3 d" — Instagram's terse age, not a full timestamp.
    const age = (iso) => {
        const d = new Date(iso);
        if (isNaN(d)) return '';
        const s = Math.max(1, (Date.now() - d.getTime()) / 1000);
        if (s < 3600) return `${Math.floor(s / 60)} m`;
        if (s < 86400) return `${Math.floor(s / 3600)} h`;
        if (s < 604800) return `${Math.floor(s / 86400)} d`;
        if (s < 2419200) return `${Math.floor(s / 604800)} w`;
        return d.toLocaleDateString();
    };

    const setSend = () => { if (sendBtn) sendBtn.disabled = !input?.value.trim(); };
    const clearReplyTarget = () => { replyTo = null; replyBar?.classList.add('hidden'); if (input) input.placeholder = 'Add a comment…'; };

    // Silhouette for anyone whose picture Instagram won't give us.
    const silhouette = `<svg viewBox="0 0 24 24" class="w-6 h-6 text-ink-400" fill="currentColor"><circle cx="12" cy="9" r="3.6"/><path d="M12 13.4c-3.4 0-6.2 2.1-7.1 5 1.8 2 4.3 3.2 7.1 3.2s5.3-1.2 7.1-3.2c-.9-2.9-3.7-5-7.1-5z"/></svg>`;
    const imgAvatar = (url) => `<img src="${esc(url)}" alt="" class="w-7 h-7 rounded-full object-cover"
        onerror="this.outerHTML='${silhouette.replace(/'/g, '&#39;')}'">`;
    // Avatar priority: the picture the server resolved for THIS commenter
    // (from their IGSID) → our own avatar when it's our comment → silhouette.
    // `onerror` matters: IG avatar URLs are signed and expire, so a stored one
    // can 404 later — swap in the silhouette rather than show a broken image.
    const avatarFor = (username, url) => {
        if (url) return imgAvatar(url);
        const u = String(username || '').replace(/^@/, '').toLowerCase();
        return (myAvatar && u && u === myHandle) ? imgAvatar(myAvatar) : silhouette;
    };

    // ── comment row (+ its replies, indented like the app) ──
    const commentRow = (c, depth = 0) => {
        const likes = Number(c.like_count || 0);
        const dim = c.hidden ? ' opacity-50 italic' : '';
        return `
        <div class="ig-comment flex gap-2.5 ${depth ? 'ml-9' : ''}" data-cid="${esc(c.id)}">
            <span class="w-7 h-7 rounded-full bg-paper-200 grid place-items-center overflow-hidden shrink-0">
                ${avatarFor(c.username, c.avatar_url)}
            </span>
            <div class="min-w-0 flex-1">
                <div data-ctext class="text-[12.5px] leading-snug${dim}"><b class="font-semibold">${esc(c.username || 'instagram user')}</b> ${esc(c.text || '')}</div>
                <div class="flex items-center gap-3 mt-1 text-[10.5px] text-ink-400">
                    <span class="mono">${esc(age(c.timestamp))}</span>
                    ${likes ? `<span>${likes} ${likes === 1 ? 'like' : 'likes'}</span>` : ''}
                    <button type="button" class="ig-c-reply font-semibold hover:text-ink-700" data-id="${esc(c.id)}" data-user="${esc(c.username || '')}">Reply</button>
                    <button type="button" class="ig-c-hide hover:text-ink-700" data-id="${esc(c.id)}" data-hidden="${c.hidden ? '1' : '0'}">${c.hidden ? 'Unhide' : 'Hide'}</button>
                </div>
            </div>
        </div>`;
    };

    // `comments = null` + `err` renders a FAILURE state. An empty array is the
    // genuine "this post has no comments" state. Keeping them distinct is the
    // difference between a visible bug and a silent lie.
    const renderThread = (comments, err = '') => {
        // The caption is always ours, so it gets our avatar — and, like Instagram,
        // it leads with our handle in bold and reads as one continuous sentence.
        const cap = post?.caption
            ? `<div class="flex gap-2.5">
                 <span class="w-7 h-7 rounded-full ig-grad-soft grid place-items-center overflow-hidden shrink-0">${myAvatar ? avatarFor(myHandle) : ''}</span>
                 <div class="min-w-0 flex-1">
                   <div class="text-[12.5px] leading-snug whitespace-pre-wrap break-words">${myHandle ? `<b class="font-semibold">${esc(myHandle)}</b> ` : ''}${esc(post.caption)}</div>
                   <div class="mono text-[10.5px] text-ink-400 mt-1">${esc(age(post.ts))}</div>
                 </div>
               </div>
               <div class="h-px bg-paper-200"></div>`
            : '';

        if (comments === null) {
            elThread.innerHTML = cap + `<div class="py-10 text-center text-[12px] text-accent-coral px-4">${esc(err || 'Could not load comments.')}</div>`;
            return;
        }

        const rows = (comments || []).map((c) => {
            const reps = c.replies?.data || [];
            let block = commentRow(c, 0);
            if (reps.length) {
                // Instagram collapses replies behind a "View all N replies"
                // toggle (with a leading rule), expanded to "Hide replies".
                const repHtml = reps.map((r) => commentRow(r, 1)).join('');
                block += `<div class="ml-9 mt-0.5">
                    <button type="button" class="ig-rep-toggle inline-flex items-center gap-2 text-[11px] font-semibold text-ink-500 hover:text-ink-800" data-open="0" data-count="${reps.length}">
                        <span class="w-6 h-px bg-paper-300"></span>
                        <span class="ig-rep-label">View ${reps.length === 1 ? '1 reply' : `all ${reps.length} replies`}</span>
                    </button>
                    <div class="ig-rep-list hidden mt-1">${repHtml}</div>
                </div>`;
            }
            return block;
        }).join('');

        elThread.innerHTML = cap + (rows || `<div class="py-10 text-center text-[12px] text-ink-400">No comments yet.</div>`);
    };

    const loadComments = async () => {
        elThread.innerHTML = `<div class="py-10 text-center text-[12px] text-ink-400">Loading comments…</div>`;
        try {
            // MUST stay a plain string. base-url.js prefixes the install's
            // sub-folder (e.g. /public) onto root-relative fetches, but it can
            // only patch strings and Requests — a URL object slips through
            // unprefixed, hits a 404, and the thread renders as "no comments".
            const qs  = new URLSearchParams({ account_id: accountId, media_id: post.id });
            const res = await fetch(`/instagram/posts/api/comments?${qs.toString()}`, {
                headers: { Accept: 'application/json' }, credentials: 'same-origin',
            });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) {
                // Never render "No comments yet." for a FAILED load — that reads
                // as "this post has no comments" and hides the real problem.
                renderThread(null, j.error || `Couldn't load comments (HTTP ${res.status}).`);
                return;
            }
            renderThread(j.comments || []);
        } catch (e) {
            renderThread(null, "Couldn't reach the server.");
        }
    };

    const openPost = (p) => {
        post = p;
        replyTo = null; clearReplyTarget();
        if (input) input.value = '';
        setSend();

        // max-w-full max-h-full + object-contain: the image shrinks to fit the
        // box on WHICHEVER axis is tighter and letterboxes the rest — never
        // cropped, tall or wide — exactly like Instagram. (w-full h-full forced
        // the element to the box size and a tall image still overflowed.)
        const isVid = p.type === 'VIDEO' || p.type === 'REELS';
        elMedia.innerHTML = isVid && p.media
            ? `<video src="${esc(p.media)}" controls playsinline poster="${esc(p.thumb || '')}" class="max-w-full max-h-full object-contain"></video>`
            : `<img src="${esc(p.media || p.thumb)}" alt="" class="max-w-full max-h-full object-contain">`;

        const likes = Number(p.likes ?? 0);
        elLikes.textContent = likes;
        elComs.textContent  = p.comments ?? 0;
        if (elLikeLn) elLikeLn.textContent = likes
            ? `${likes.toLocaleString()} ${likes === 1 ? 'like' : 'likes'}`
            : 'Be the first to like this';
        // Instagram puts the spelled-out age here, under the counts.
        elWhen.textContent = p.ts ? agoLong(p.ts) : '';
        if (elLink) elLink.href = p.permalink || '#';

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        loadComments();
    };

    const closePost = () => {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        elMedia.innerHTML = '';           // stop any playing video
    };

    document.querySelectorAll('.ig-post-tile').forEach((t) => t.addEventListener('click', () => {
        try { openPost(JSON.parse(t.dataset.post)); } catch (_) {}
    }));
    modal.querySelectorAll('[data-close-post]').forEach((b) => b.addEventListener('click', closePost));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closePost(); });

    input?.addEventListener('input', setSend);
    document.getElementById('ig-post-reply-cancel')?.addEventListener('click', clearReplyTarget);

    // ── emoji picker ── the SAME lazy-loaded <emoji-picker> web component the
    //    Team Inbox / Instagram inbox composer uses (not a hand-rolled grid).
    //    The smiley opens a floating picker anchored to the button; picking an
    //    emoji inserts it at the caret.
    const emojiBtn = document.getElementById('ig-post-emoji-btn');
    let emojiHolder = null;
    const closeEmoji = () => { if (emojiHolder) { emojiHolder.remove(); emojiHolder = null; } };
    if (emojiBtn && input) {
        emojiBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            if (emojiHolder) { closeEmoji(); return; }
            const a = emojiBtn.getBoundingClientRect();
            const holder = document.createElement('div');
            holder.className = 'fixed z-[80] rounded-2xl overflow-hidden shadow-soft border border-paper-200 bg-paper-0';
            holder.style.width = '320px';
            emojiHolder = holder;
            document.body.appendChild(holder);
            const W = 320, H = 400;
            holder.style.left = Math.min(Math.max(8, a.left), window.innerWidth - 8 - W) + 'px';
            // Prefer opening ABOVE the composer (like Instagram); clamp otherwise.
            holder.style.top = (a.top - H - 6 > 8 ? a.top - H - 6 : a.bottom + 6) + 'px';
            try {
                await import('emoji-picker-element');
                if (!emojiHolder) return;                 // closed while loading
                const picker = document.createElement('emoji-picker');
                const dark = document.documentElement.getAttribute('data-theme') === 'dark'
                    || document.body.getAttribute('data-theme') === 'dark';
                picker.classList.add('chat-emoji-picker', dark ? 'dark' : 'light');
                picker.style.width = '100%';
                picker.addEventListener('emoji-click', (ev) => {
                    const native = ev.detail?.unicode || ev.detail?.emoji?.unicode;
                    if (native) {
                        const s = input.selectionStart ?? input.value.length;
                        const en = input.selectionEnd ?? input.value.length;
                        input.value = input.value.slice(0, s) + native + input.value.slice(en);
                        const caret = s + native.length;
                        input.setSelectionRange(caret, caret);
                        setSend();
                    }
                    closeEmoji();
                    input.focus();
                });
                holder.appendChild(picker);
            } catch (_) { closeEmoji(); toast('Could not load emojis.', 'error'); }
        });
        document.addEventListener('click', (e) => {
            if (emojiHolder && !emojiHolder.contains(e.target) && e.target !== emojiBtn) closeEmoji();
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeEmoji(); });
    }

    // Reply / Hide are delegated — the thread is re-rendered on every load.
    elThread?.addEventListener('click', async (e) => {
        const rep = e.target.closest('.ig-c-reply');
        if (rep) {
            replyTo = { id: rep.dataset.id, username: rep.dataset.user };
            replyWho.textContent = '@' + (replyTo.username || '');
            replyBar.classList.remove('hidden');
            input?.focus();
            return;
        }

        // Collapse / expand a reply thread in place — pure UI, no fetch.
        const tog = e.target.closest('.ig-rep-toggle');
        if (tog) {
            const list = tog.parentElement.querySelector('.ig-rep-list');
            const open = tog.dataset.open === '1';
            list?.classList.toggle('hidden', open);       // open → collapse
            tog.dataset.open = open ? '0' : '1';
            const n = Number(tog.dataset.count || 0);
            const label = tog.querySelector('.ig-rep-label');
            if (label) label.textContent = open
                ? (n === 1 ? 'View 1 reply' : `View all ${n} replies`)
                : (n === 1 ? 'Hide reply' : 'Hide replies');
            return;
        }

        const hid = e.target.closest('.ig-c-hide');
        if (hid) {
            const hide = hid.dataset.hidden !== '1';
            const box  = hid.closest('[data-cid]');
            const text = box?.querySelector('[data-ctext]');
            // OPTIMISTIC — flip the button + dim/undim this ONE comment instantly,
            // exactly like Instagram; NO full thread reload. Revert on failure.
            const apply = (isHidden) => {
                hid.textContent = isHidden ? 'Unhide' : 'Hide';
                hid.dataset.hidden = isHidden ? '1' : '0';
                if (text) { text.classList.toggle('opacity-50', isHidden); text.classList.toggle('italic', isHidden); }
            };
            apply(hide);
            try {
                const res = await fetch('/instagram/posts/api/hide', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ account_id: accountId, comment_id: hid.dataset.id, hide }),
                });
                if (!res.ok) { apply(!hide); toast('Instagram refused that.', 'error'); }
            } catch (_) { apply(!hide); toast('Network error.', 'error'); }
            return;
        }
    });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text || !post) return;
        sendBtn.disabled = true;

        // Two DIFFERENT Instagram endpoints, so send two different fields:
        //   comment_id → POST /{comment-id}/replies  (threaded reply)
        //   media_id   → POST /{media-id}/comments   (top-level comment)
        // Sending a media id as `comment_id` hits /{media-id}/replies, which
        // does not exist — Meta rejects it. That was the "Instagram rejected
        // the reply." on every top-level comment.
        const body = replyTo
            ? { account_id: accountId, comment_id: replyTo.id, text }
            : { account_id: accountId, media_id: post.id, text };

        try {
            const res = await fetch('/instagram/posts/api/reply', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body),
            });
            const j = await res.json().catch(() => ({}));
            if (!res.ok || !j.ok) { toast(j.error || 'Instagram rejected the reply.', 'error'); setSend(); return; }
            input.value = ''; clearReplyTarget(); setSend();
            loadComments();
        } catch (_) { toast('Network error — nothing was posted.', 'error'); setSend(); }
    });
}

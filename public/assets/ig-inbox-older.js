/**
 * Inbox chat — load OLDER messages on scroll-up.
 *
 * The thread opens with only the latest batch (fast). When the operator scrolls
 * near the top, this fetches the previous batch from /instagram/inbox/older,
 * prepends the SAME server-rendered bubbles just below the sentinel, and keeps
 * the viewport pinned to the message they were reading (no jump). Repeats until
 * data-has-more flips to 0 — so the whole history is reachable by scrolling.
 */
(function () {
    'use strict';

    var scroll = document.getElementById('ig-message-scroll');
    if (!scroll) return;

    var sentinel = document.getElementById('ig-older-sentinel');
    var loading = false;

    function hasMore() { return scroll.getAttribute('data-has-more') === '1'; }

    async function loadOlder() {
        if (loading || !hasMore()) return;

        var oldest  = scroll.getAttribute('data-oldest-id') || '0';
        var account = scroll.getAttribute('data-account-id') || '';
        var igsid   = scroll.getAttribute('data-igsid') || '';
        var base    = scroll.getAttribute('data-older-url') || '';
        if (!base || !account || !igsid || oldest === '0') return;

        loading = true;
        if (sentinel) sentinel.classList.remove('hidden');

        try {
            var url = base + '?account=' + encodeURIComponent(account) +
                      '&igsid=' + encodeURIComponent(igsid) +
                      '&before_id=' + encodeURIComponent(oldest);
            var res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            var j = await res.json();

            if (j && j.ok && j.html) {
                // Preserve scroll position: measure height, prepend, re-add the delta.
                var prevHeight = scroll.scrollHeight;
                var prevTop = scroll.scrollTop;

                var tmp = document.createElement('div');
                tmp.innerHTML = j.html;
                var frag = document.createDocumentFragment();
                while (tmp.firstChild) frag.appendChild(tmp.firstChild);

                // Insert older messages directly below the sentinel (above the
                // current batch), so chronological order is preserved.
                if (sentinel && sentinel.parentNode === scroll) {
                    scroll.insertBefore(frag, sentinel.nextSibling);
                } else {
                    scroll.insertBefore(frag, scroll.firstChild);
                }

                scroll.scrollTop = prevTop + (scroll.scrollHeight - prevHeight);

                scroll.setAttribute('data-oldest-id', String(j.oldest_id || oldest));
                scroll.setAttribute('data-has-more', j.has_more ? '1' : '0');

                // Re-run any per-message initializers (local timestamps, day
                // stamps) the app exposes for freshly-inserted bubbles.
                if (typeof window.igApplyLocalTimes === 'function') {
                    try { window.igApplyLocalTimes(scroll); } catch (e) {}
                }
            } else {
                scroll.setAttribute('data-has-more', '0');
            }
        } catch (e) {
            // Leave has-more as-is so a transient failure can be retried on next scroll.
        } finally {
            loading = false;
            if (sentinel) sentinel.classList.add('hidden');
        }
    }

    scroll.addEventListener('scroll', function () {
        if (scroll.scrollTop < 90) loadOlder();
    }, { passive: true });
})();

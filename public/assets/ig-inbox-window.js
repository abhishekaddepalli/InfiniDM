/**
 * Live 24-hour messaging-window indicator for the Instagram inbox.
 *
 * Instagram only lets you message a person freely for 24h after THEIR last
 * message. This reads the open thread's last INBOUND bubble timestamp and shows
 * the operator whether the window is open (with time left) or closed — matching
 * the WhatsApp inbox behaviour. Warn only; it never blocks the composer.
 *
 * Self-contained: reads the DOM the inbox bundle already renders
 * (#ig-message-scroll → .ig-msg-row.justify-start rows, each with a
 * .ig-localtime[data-ts] ISO timestamp). No dependency on the minified bundle.
 */
(function () {
    function boot() {
        var scroll = document.getElementById('ig-message-scroll');
        var note = document.getElementById('ig-window-note');
        var dot = document.getElementById('ig-window-dot');
        var txt = document.getElementById('ig-window-text');
        if (!scroll || !note || !dot || !txt) return;

        var WINDOW_MS = 24 * 60 * 60 * 1000;
        var DEFAULT = txt.textContent;
        var T = function (s) { try { return (window.t ? window.t(s) : s); } catch (e) { return s; } };

        // Parse a data-ts that may be an ISO string OR epoch seconds/millis.
        function parseTs(raw) {
            if (!raw) return null;
            var d = new Date(raw);
            if (!isNaN(d.getTime())) return d.getTime();
            var n = parseInt(raw, 10);
            if (!isNaN(n)) { d = new Date(n < 1e12 ? n * 1000 : n); if (!isNaN(d.getTime())) return d.getTime(); }
            return null;
        }

        // Robust: the timestamp element is not always nested inside the inbound
        // row (the inbox bundle renders it separately), so scan EVERY [data-ts]
        // in the thread, prefer the last one that belongs to an inbound
        // (justify-start) row, and fall back to the last timestamp of any kind.
        function lastInboundTs() {
            var stamped = scroll.querySelectorAll('[data-ts]');
            var lastInbound = null, lastAny = null;
            for (var i = 0; i < stamped.length; i++) {
                var t = parseTs(stamped[i].getAttribute('data-ts'));
                if (t === null) continue;
                lastAny = t;
                var row = stamped[i].closest ? stamped[i].closest('.ig-msg-row') : null;
                if (row && row.classList.contains('justify-start')) lastInbound = t;
            }
            return lastInbound !== null ? lastInbound : lastAny;
        }

        function render() {
            var ts = lastInboundTs();
            if (ts === null) {
                dot.style.background = '#c7c7c7';
                note.className = 'mono text-[10px] mt-1.5 px-1 flex items-center gap-1.5 text-ink-400';
                txt.textContent = DEFAULT;
                return;
            }
            var left = WINDOW_MS - (Date.now() - ts);
            if (left > 0) {
                var h = Math.floor(left / 3600000);
                var m = Math.floor((left % 3600000) / 60000);
                dot.style.background = '#1f9d55';   // green
                note.className = 'mono text-[10px] mt-1.5 px-1 flex items-center gap-1.5 text-ig-magenta';
                txt.textContent = h >= 1
                    ? T('Messaging window open') + ' — ' + T('about') + ' ' + h + 'h ' + m + 'm ' + T('left to reply.')
                    : T('Messaging window open') + ' — ' + T('about') + ' ' + m + 'm ' + T('left to reply.');
            } else {
                dot.style.background = '#e0245e';   // red
                note.className = 'mono text-[10px] mt-1.5 px-1 flex items-center gap-1.5 text-accent-coral';
                txt.textContent = T('24-hour window closed — they must message you again before you can reply.');
            }
        }

        // Recompute when the thread changes (conversation opened / new message)…
        try {
            new MutationObserver(function () { render(); }).observe(scroll, { childList: true, subtree: true });
        } catch (e) { /* MutationObserver unavailable — the interval still refreshes it */ }
        // …and every minute so the countdown / expiry stays live.
        setInterval(render, 60000);
        render();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

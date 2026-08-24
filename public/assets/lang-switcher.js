/**
 * Language switcher behaviour.
 *
 *  • Header  — a native <details> pill; opens/closes itself. This script only
 *    closes it on an outside click / Esc, and closes other open switchers.
 *  • Rail    — an explicit button + a menu that is hidden until tapped. The
 *    rail column clips overflow, so the menu is positioned FIXED (viewport
 *    coordinates) just to the right of the button and clamped on-screen.
 *    (<details> broke here: display:contents made it a row but ate the click.)
 */
(function () {
    'use strict';

    function closeDetails(except) {
        document.querySelectorAll('details.ig-lang[open]').forEach(function (d) {
            if (d !== except) d.removeAttribute('open');
        });
    }

    function closeCompact(except) {
        document.querySelectorAll('[data-lang-compact]').forEach(function (c) {
            if (c === except) return;
            var m = c.querySelector('[data-lang-menu]');
            var b = c.querySelector('[data-lang-toggle]');
            if (m) m.hidden = true;
            if (b) b.setAttribute('aria-expanded', 'false');
        });
    }

    // Place a rail menu just right of its button, clamped to the viewport.
    function placeFixed(menu, anchor) {
        if (!menu || !anchor) return;
        var r = anchor.getBoundingClientRect();
        menu.style.position = 'fixed';
        menu.style.left = (r.right + 8) + 'px';
        menu.style.right = 'auto';
        menu.style.bottom = 'auto';
        menu.style.zIndex = '9999';
        requestAnimationFrame(function () {
            var mh = menu.offsetHeight;
            var top = Math.min(Math.max(8, r.top - 4), window.innerHeight - mh - 8);
            menu.style.top = Math.max(8, top) + 'px';
        });
    }

    document.addEventListener('click', function (e) {
        var t = e.target;
        var toggle = (t && t.closest) ? t.closest('[data-lang-toggle]') : null;

        // Rail button tapped → toggle its menu.
        if (toggle) {
            e.preventDefault();
            var wrap = toggle.closest('[data-lang-compact]');
            var menu = wrap && wrap.querySelector('[data-lang-menu]');
            if (!menu) return;
            var willOpen = menu.hidden;
            closeCompact(wrap);
            closeDetails(null);
            menu.hidden = !willOpen;
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            if (willOpen) placeFixed(menu, toggle);
            return;
        }

        // A click elsewhere closes any open rail menu / header details, unless
        // it landed inside them (e.g. picking a language, which then submits).
        var openWrap = t && t.closest ? t.closest('[data-lang-compact]') : null;
        if (!openWrap) closeCompact(null);

        var openD = document.querySelector('details.ig-lang[open]');
        if (openD && !openD.contains(t)) openD.removeAttribute('open');
    });

    // Opening a header details closes the others (and any rail menu).
    document.addEventListener('toggle', function (e) {
        var d = e.target;
        if (d && d.matches && d.matches('details.ig-lang') && d.open) {
            closeDetails(d);
            closeCompact(null);
        }
    }, true);

    // Esc closes everything.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeDetails(null); closeCompact(null); }
    });

    // Keep an open rail menu glued to its button on scroll / resize.
    ['scroll', 'resize'].forEach(function (ev) {
        window.addEventListener(ev, function () {
            document.querySelectorAll('[data-lang-compact]').forEach(function (c) {
                var m = c.querySelector('[data-lang-menu]');
                var b = c.querySelector('[data-lang-toggle]');
                if (m && !m.hidden) placeFixed(m, b);
            });
        }, true);
    });
})();

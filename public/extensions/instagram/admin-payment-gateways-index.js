/**
 * /admin/payment-gateways page module.
 *
 * Loaded directly by <x-layouts.admin> as a plain ES module (no bundler)
 * when page="admin-payment-gateways-index". Three responsibilities:
 *
 *   1. Category tabs + search — each card carries data-cat="all india …"
 *      and data-search="razorpay razorpay"; the tab bar + search box filter
 *      conjunctively. Active-tab styling is applied by swapping the plain
 *      utility classes named in each tab's data-on-class / data-off-class
 *      (arbitrary data-[…] variants aren't in the compiled CSS, so state is
 *      driven from JS instead).
 *   2. Collapsible cards — every card is collapsed by default; clicking the
 *      header row expands / collapses its credentials form.
 *   3. Currency-pill picker — clickable badges each wrap a hidden checkbox,
 *      so the form still submits a plain supported_currencies[] array. The
 *      pill's selected look is swapped the same on/off-class way.
 */
(function () {
    'use strict';

    function splitClasses(v) {
        return (v || '').split(/\s+/).filter(Boolean);
    }

    function paint(el, on) {
        var onC = splitClasses(el.getAttribute('data-on-class'));
        var offC = splitClasses(el.getAttribute('data-off-class'));
        (on ? offC : onC).forEach(function (c) { el.classList.remove(c); });
        (on ? onC : offC).forEach(function (c) { el.classList.add(c); });
    }

    function init() {
        // ── tab + search filter ─────────────────────────────────────
        var activeCat = 'all';
        var activeSearch = '';
        var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-gateway-cat]'));
        var cards = Array.prototype.slice.call(document.querySelectorAll('[data-gateway-card]'));
        var search = document.getElementById('gateway-search');
        var empty = document.getElementById('gw-empty-state');

        function applyFilter() {
            var shown = 0;
            cards.forEach(function (c) {
                var cats = (c.getAttribute('data-cat') || '').split(/\s+/);
                var matchCat = activeCat === 'all' || cats.indexOf(activeCat) >= 0;
                var matchSearch = !activeSearch || (c.getAttribute('data-search') || '').indexOf(activeSearch) >= 0;
                var visible = matchCat && matchSearch;
                c.style.display = visible ? '' : 'none';
                if (visible) shown++;
            });
            if (empty) empty.classList.toggle('hidden', shown > 0);
        }

        tabs.forEach(function (t) {
            t.addEventListener('click', function () {
                tabs.forEach(function (x) {
                    paint(x, false);
                    x.setAttribute('aria-pressed', 'false');
                });
                paint(t, true);
                t.setAttribute('aria-pressed', 'true');
                activeCat = t.getAttribute('data-gateway-cat');
                applyFilter();
            });
        });

        if (search) {
            search.addEventListener('input', function (e) {
                activeSearch = (e.target.value || '').toLowerCase();
                applyFilter();
            });
        }

        // ── collapsible cards ───────────────────────────────────────
        cards.forEach(function (card) {
            var head = card.querySelector('[data-gateway-head]');
            var body = card.querySelector('[data-gateway-body]');
            var chev = card.querySelector('[data-gateway-chev]');
            if (!head || !body) return;
            head.addEventListener('click', function (e) {
                // Let the Activate/Disable form button submit normally.
                if (e.target.closest('form, button, a, input')) return;
                var open = !body.classList.contains('hidden');
                body.classList.toggle('hidden', open);
                if (chev) chev.style.transform = open ? '' : 'rotate(180deg)';
            });
        });

        // ── currency-pill picker ────────────────────────────────────
        document.querySelectorAll('[data-currency-pills]').forEach(function (grid) {
            var pills = Array.prototype.slice.call(grid.querySelectorAll('[data-currency-pill]'));
            var count = grid.parentElement && grid.parentElement.querySelector('[data-currency-count]');
            function updateCount() {
                if (!count) return;
                count.textContent = String(grid.querySelectorAll('input[type="checkbox"]:checked').length);
            }
            pills.forEach(function (pill) {
                var cb = pill.querySelector('input[type="checkbox"]');
                if (!cb) return;
                // The native <label> toggles the checkbox; sync the visual
                // after the browser flips it.
                pill.addEventListener('click', function () {
                    setTimeout(function () {
                        paint(pill, cb.checked);
                        updateCount();
                    }, 0);
                });
            });
            updateCount();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

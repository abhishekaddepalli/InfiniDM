/**
 * Tabs for /instagram/automations — splits the long page into
 * "Auto-reply rules", "Ice breakers" and "Persistent menu" panels so it isn't
 * one endless scroll. Pure DOM toggling; remembers the last tab per browser.
 */
(function () {
    function boot() {
        var group = document.querySelector('[data-igtab-group]');
        if (!group) return;
        var tabs = group.querySelectorAll('[data-igtab]');
        var panels = document.querySelectorAll('[data-igtab-panel]');
        if (!tabs.length || !panels.length) return;

        function activate(name) {
            var found = false;
            panels.forEach(function (p) {
                var on = p.getAttribute('data-igtab-panel') === name;
                p.classList.toggle('hidden', !on);
                if (on) found = true;
            });
            if (!found) { activate('rules'); return; }
            tabs.forEach(function (t) {
                var on = t.getAttribute('data-igtab') === name;
                t.classList.toggle('bg-white', on);
                t.classList.toggle('shadow-sm', on);
                t.classList.toggle('text-ig-magenta', on);
                t.classList.toggle('text-ink-600', !on);
            });
            try { localStorage.setItem('ig-autotab', name); } catch (e) { /* private mode */ }
        }

        tabs.forEach(function (t) {
            t.addEventListener('click', function () { activate(t.getAttribute('data-igtab')); });
        });

        // The header "Menu & greeters" button opens the greeters tab instead of
        // anchor-scrolling (the sections are now tabbed, not stacked).
        document.querySelectorAll('[data-igtab-go]').forEach(function (b) {
            b.addEventListener('click', function (e) { e.preventDefault(); activate(b.getAttribute('data-igtab-go')); });
        });

        var saved = 'rules';
        try { saved = localStorage.getItem('ig-autotab') || 'rules'; } catch (e) { /* ignore */ }
        activate(saved);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();

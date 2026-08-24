/**
 * Schedule timezone — use the VIEWER'S real timezone.
 *
 * The composer/calendar convert the chosen "date + time + timezone" into the
 * server's clock for storage, so the timezone the user picks decides the actual
 * fire time. If it defaults to the server zone (often UTC), a user in IST who
 * picks 2:00 PM gets a post that fires at 2:00 PM UTC (= 7:30 PM IST). To fix
 * that, pre-select the browser's own timezone on load (whenever it's an option),
 * and stamp any hidden timezone field with it — so 2:00 PM means 2:00 PM where
 * the user actually is. If they manually change the picker, we leave their choice.
 */
(function () {
    'use strict';
    var tz;
    try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) { tz = ''; }
    if (!tz) return;

    // Pre-select the viewer's zone in the schedule pickers (only if not touched).
    document.querySelectorAll('#cal-timezone, #cmp-sched-tz, select[data-tz-auto]').forEach(function (sel) {
        if (sel.getAttribute('data-tz-picked')) return;
        var hasOpt = Array.prototype.some.call(sel.options, function (o) { return o.value === tz; });
        if (hasOpt) sel.value = tz;
        sel.addEventListener('change', function () { sel.setAttribute('data-tz-picked', '1'); });
    });

    // Fill hidden timezone inputs (e.g. the inline "edit scheduled post" form).
    document.querySelectorAll('input[type="hidden"][data-tz-now]').forEach(function (i) {
        if (!i.value) i.value = tz;
    });
})();

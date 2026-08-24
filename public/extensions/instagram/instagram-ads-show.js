// Instaflow Meta Ads — campaign detail. Page key: instagram-ads-show.
//   • confirm before a destructive form submit (data-confirm)
//   • "Estimate reach" fetches the delivery estimate on demand
// Both are enhancements: the delete form still works without JS (no confirm),
// and the estimate button is only shown when the campaign is on Meta.
function init() {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
        });
    });

    const panel = document.querySelector('[data-estimate]');
    if (!panel) return;
    const btn = panel.querySelector('[data-estimate-btn]');
    const out = panel.querySelector('[data-estimate-out]');
    if (!btn || !out) return;

    btn.addEventListener('click', async () => {
        out.textContent = 'Estimating…';
        try {
            const r = await fetch(panel.dataset.endpoint, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const d = await r.json();
            if (!d.ok) { out.textContent = d.error || 'Estimate unavailable.'; return; }
            const fmt = (n) => Number(n || 0).toLocaleString();
            out.textContent = d.ready
                ? `${fmt(d.lower)} – ${fmt(d.upper)} people`
                : 'Audience still calculating — try again shortly.';
        } catch (e) {
            out.textContent = 'Could not fetch an estimate.';
        }
    });
}

(function(){var run=function(){try{init();}catch(e){console.error("[instagram] instagram-ads-show failed",e);}};if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",run);else run();})();

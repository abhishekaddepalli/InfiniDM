// Instaflow Meta Ads — analytics. Page key: instagram-ads-analytics.
// The daily bar chart is drawn server-side (static, works with no JS). This
// overlays a hover tooltip so each bar reads its day's spend + clicks.
function init() {
    const box = document.querySelector('[data-ads-chart]');
    if (!box) return;
    const bars = box.querySelectorAll('[data-bar]');
    if (!bars.length) return;

    // One floating tip shared by every bar.
    const tip = document.createElement('div');
    tip.style.cssText = 'position:fixed;z-index:60;pointer-events:none;display:none;'
        + 'background:#111;color:#fff;font-size:11px;line-height:1.3;padding:6px 8px;'
        + 'border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.25);white-space:nowrap;';
    document.body.appendChild(tip);

    const fmt = (n) => Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });

    bars.forEach((bar) => {
        bar.addEventListener('mouseenter', () => {
            const date = bar.getAttribute('data-date') || '';
            const spend = bar.getAttribute('data-spend') || '0';
            const clicks = bar.getAttribute('data-clicks') || '0';
            tip.innerHTML = `<strong>${date}</strong><br>Spend ${fmt(spend)} · ${fmt(clicks)} clicks`;
            tip.style.display = 'block';
        });
        bar.addEventListener('mousemove', (e) => {
            tip.style.left = `${e.clientX + 12}px`;
            tip.style.top = `${e.clientY - 8}px`;
        });
        bar.addEventListener('mouseleave', () => { tip.style.display = 'none'; });
    });
}

(function(){var run=function(){try{init();}catch(e){console.error("[instagram] instagram-ads-analytics failed",e);}};if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",run);else run();})();

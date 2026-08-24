/* Instaflow public site — loaded on every front page.
   Handles the cookie-consent bar and (when enabled) PWA service-worker
   registration. Everything is guarded so a page without a given widget is fine. */
(function () {
  /* ---- Reveal-on-scroll ----
     The editorial pages (about / contact / legal / features / blog) wrap
     sections in .reveal, which ships at opacity:0 and only becomes visible
     once .in is added. Without this observer those sections stay invisible and
     the page looks empty. Runs on every front page; home also has its own copy. */
  (function () {
    var els = document.querySelectorAll('.reveal');
    if (!els.length) return;
    if (!('IntersectionObserver' in window)) {
      els.forEach(function (e) { e.classList.add('in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.08 });
    els.forEach(function (e) { io.observe(e); });
  })();

  /* ---- Cookie consent ---- */
  var bar = document.getElementById('cookieBar');
  if (bar) {
    var KEY = 'if_cookie_consent';
    var stored = null;
    try { stored = localStorage.getItem(KEY); } catch (e) {}
    if (!stored) {
      // Reveal after paint so it slides in rather than flashing.
      setTimeout(function () { bar.classList.remove('hidden'); }, 400);
    }
    bar.querySelectorAll('[data-cookie]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        try { localStorage.setItem(KEY, btn.getAttribute('data-cookie')); } catch (e) {}
        bar.classList.add('hidden');
      });
    });
  }

  /* ---- PWA ---- */
  var cfg = window.__IF_PWA__ || {};
  if (cfg.enabled && cfg.sw && 'serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      // Relative scope so a sub-folder install (…/instaflow/public) registers
      // correctly; the route sets Service-Worker-Allowed to widen it.
      navigator.serviceWorker.register(cfg.sw, { scope: './' }).catch(function () {});
    });
  }
})();

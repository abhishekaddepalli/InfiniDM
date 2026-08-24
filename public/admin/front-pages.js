/* Admin → Front pages: tab switcher. All panels live in one <form> so hidden
   tabs still submit; this only toggles which panel is visible. */
(function () {
  var tabs = document.querySelectorAll('[data-front-tab]');
  var panels = document.querySelectorAll('[data-front-panel]');
  if (!tabs.length) return;

  var activeCls = ['ig-grad-soft', 'text-white', 'border-transparent'];
  var idleCls = ['border-paper-200', 'text-ink-600', 'hover:border-wa-deep'];

  function show(key) {
    panels.forEach(function (p) { p.classList.toggle('hidden', p.getAttribute('data-front-panel') !== key); });
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-front-tab') === key;
      activeCls.forEach(function (c) { t.classList.toggle(c, on); });
      idleCls.forEach(function (c) { t.classList.toggle(c, !on); });
    });
  }

  tabs.forEach(function (t) {
    t.addEventListener('click', function () { show(t.getAttribute('data-front-tab')); });
  });
})();

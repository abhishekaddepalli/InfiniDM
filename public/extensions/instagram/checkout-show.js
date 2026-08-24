/**
 * Checkout — gateway picker highlight.
 *
 * The Instaflow CSS build has no `has-[:checked]:` variant, so the selected
 * gateway card is highlighted here instead: on load and on every radio change,
 * the label wrapping the checked radio gets the accent border + mint fill, the
 * rest fall back to the plain paper border. Pure presentation — the <input>
 * radios are the real state, so this degrades gracefully with JS off.
 */
(function () {
  const labels = Array.from(document.querySelectorAll('label[data-gw]'));
  if (!labels.length) return;

  function sync() {
    labels.forEach((label) => {
      const radio = label.querySelector('input[type="radio"]');
      const on = !!(radio && radio.checked);
      label.classList.toggle('border-wa-deep', on);
      label.classList.toggle('bg-wa-mint/30', on);
      label.classList.toggle('border-paper-200', !on);
    });
  }

  labels.forEach((label) => {
    const radio = label.querySelector('input[type="radio"]');
    if (radio) radio.addEventListener('change', sync);
  });

  sync();
})();

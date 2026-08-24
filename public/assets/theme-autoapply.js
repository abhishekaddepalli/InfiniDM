/**
 * Appearance page — apply a choice the instant it's clicked.
 *
 * The page saves through a real POST (Laravel encrypts the cookie, so writing it
 * from JS would fail decryption on the next request). So instead of writing the
 * cookie ourselves, we just SUBMIT the form the moment a radio changes — a
 * genuine POST that saves + reloads with the new theme applied. Clicking a
 * swatch now visibly does something; the "Save appearance" button still works
 * for anyone with JS disabled.
 */
(function () {
    'use strict';
    var form = document.querySelector('form[action$="/instagram/theme"]');
    if (!form) return;
    form.addEventListener('change', function (e) {
        var t = e.target;
        if (t && t.type === 'radio' && form.contains(t)) {
            form.submit();
        }
    });
})();

/**
 * Live preview for brand-asset uploads on /admin/settings.
 *
 * Each <input type="file" data-logo-preview="<imgId>"> renders the chosen file
 * into <img id="<imgId>"> immediately (client-side FileReader — nothing is
 * uploaded until the form is saved). The sibling placeholder marked
 * data-empty-for="<imgId>" is hidden once an image is shown.
 */
(function () {
    'use strict';

    function show(imgId, dataUrl) {
        var img = document.getElementById(imgId);
        if (!img) return;
        img.src = dataUrl;
        img.classList.remove('hidden');
        var empty = document.querySelector('[data-empty-for="' + imgId + '"]');
        if (empty) empty.classList.add('hidden');
    }

    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input || input.type !== 'file' || !input.dataset.logoPreview) return;
        var file = input.files && input.files[0];
        if (!file || !/^image\//.test(file.type)) return;
        var reader = new FileReader();
        reader.onload = function (ev) { show(input.dataset.logoPreview, ev.target.result); };
        reader.readAsDataURL(file);
    });
})();

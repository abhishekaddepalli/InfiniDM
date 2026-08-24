// Files — personal media library. Server-rendered; this only adds the niceties:
// auto-submit on file pick, check-all + a live "delete selected" state, delete
// confirms, and turning "Edit" into an inline rename (falling back to opening the
// file in a new tab when the user cancels). All destructive calls confirm first.

function init() {
    const t = (s) => (window.t ? window.t(s) : s);
    const toast = (m, kind) => (window.toast ? window.toast(m, kind) : undefined);

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // --- Upload: submit the moment files are chosen -----------------------
    const uploadInput = document.getElementById('ig-files-input');
    const uploadForm = document.getElementById('ig-files-upload');
    if (uploadInput && uploadForm) {
        uploadInput.addEventListener('change', () => {
            if (uploadInput.files && uploadInput.files.length) {
                toast(t('Uploading…'));
                uploadForm.submit();
            }
        });
    }

    // --- Check-all + delete-selected state --------------------------------
    const bulkForm = document.getElementById('ig-files-bulk');
    const checkAll = document.getElementById('ig-files-check-all');
    const delBtn = document.getElementById('ig-files-delete-selected');
    const countEl = document.getElementById('ig-files-count');
    const boxes = () => Array.from(document.querySelectorAll('.ig-file-check'));

    const refresh = () => {
        const sel = boxes().filter((b) => b.checked).length;
        if (delBtn) delBtn.disabled = sel === 0;
        if (countEl) countEl.textContent = sel > 0 ? sel + ' ' + t('selected') : '';
        if (checkAll) {
            const all = boxes();
            checkAll.checked = all.length > 0 && sel === all.length;
            checkAll.indeterminate = sel > 0 && sel < all.length;
        }
    };

    if (checkAll) {
        checkAll.addEventListener('change', () => {
            boxes().forEach((b) => { b.checked = checkAll.checked; });
            refresh();
        });
    }
    boxes().forEach((b) => b.addEventListener('change', refresh));
    refresh();

    if (bulkForm) {
        bulkForm.addEventListener('submit', (e) => {
            const sel = boxes().filter((b) => b.checked).length;
            if (sel === 0) { e.preventDefault(); return; }
            if (!window.confirm(bulkForm.dataset.confirm || t('Delete the selected files?'))) {
                e.preventDefault();
            }
        });
    }

    // --- Per-file delete: confirm before the (real) form submits ----------
    document.querySelectorAll('.ig-file-delete').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            const name = btn.dataset.name || t('this file');
            if (!window.confirm(t('Delete') + ' “' + name + '”? ' + t('This cannot be undone.'))) {
                e.preventDefault();
            }
        });
    });

    // --- Edit → inline rename (cancel = open in new tab, the no-JS default) -
    document.querySelectorAll('.ig-file-edit').forEach((a) => {
        a.addEventListener('click', async (e) => {
            const id = a.dataset.id;
            if (!id) return; // no id → let the link open the file
            const current = a.dataset.name || '';
            const next = window.prompt(t('Rename file'), current);
            if (next === null) return; // cancelled → allow default (open in new tab)
            e.preventDefault();
            const name = next.trim();
            if (!name || name === current) return;

            try {
                const body = new FormData();
                body.append('_token', csrf);
                body.append('original_name', name);
                const res = await fetch('/instagram/files/' + id + '/rename', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    body,
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.ok) {
                    a.dataset.name = data.name || name;
                    const card = a.closest('.ig-file-card');
                    const title = card?.querySelector('.font-semibold');
                    if (title) { title.textContent = data.name || name; title.setAttribute('title', data.name || name); }
                    toast(data.message || t('Renamed'), 'success');
                } else {
                    toast(data.message || t('Could not rename the file.'), 'error');
                }
            } catch (_) {
                toast(t('Could not rename the file.'), 'error');
            }
        });
    });

    // --- Move → prompt for a folder name, POST, reload so chips + counts update -
    document.querySelectorAll('.ig-file-move').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            if (!id) return;
            const current = btn.dataset.folder || '';
            const next = window.prompt(t('Move to folder (leave blank for none)'), current);
            if (next === null) return; // cancelled
            const folder = next.trim();
            if (folder === current) return;

            try {
                const body = new FormData();
                body.append('_token', csrf);
                body.append('folder', folder);
                const res = await fetch('/instagram/files/' + id + '/move', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    body,
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.ok) {
                    toast(data.message || t('Moved'), 'success');
                    // A new folder needs to appear in the chips/filter, so reload.
                    window.location.reload();
                } else {
                    toast(data.message || t('Could not move the file.'), 'error');
                }
            } catch (_) {
                toast(t('Could not move the file.'), 'error');
            }
        });
    });
}

/* runtime shim — matches vite.instagram.config.mjs wrapper */
(function(){var run=function(){try{init();}catch(e){console.error('[instagram] instagram-files failed',e);}};if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();})();

// Attributes (Instagram view) — the tag chips copy themselves. Editing lives on
// /attributes; this page only surfaces what exists, so there is nothing else to wire.

export default function init() {
    document.querySelectorAll('.ig-copy-tag').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const tag = btn.dataset.tag || '';
            try {
                await navigator.clipboard.writeText(tag);
            } catch (_) {
                // clipboard API needs a secure context — fall back to a hidden field
                const ta = document.createElement('textarea');
                ta.value = tag; ta.style.position = 'fixed'; ta.style.opacity = '0';
                document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); } catch (_) {}
                ta.remove();
            }
            const was = btn.textContent;
            btn.textContent = window.t ? window.t('Copied') : 'Copied';
            setTimeout(() => { btn.textContent = was; }, 1100);
        });
    });
}

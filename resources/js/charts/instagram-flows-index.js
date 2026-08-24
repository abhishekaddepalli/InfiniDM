/*
 * Flows list — delete action. Standalone Instagram product only.
 *
 * Posts to the SAME /flows/api/{id} endpoint the builder uses, rather than a
 * second delete route, so there is one deletion path to keep correct.
 */
export default function init() {
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
    const toast = (m, k) => (window.toast ? window.toast(m, k === 'error' ? 'error' : 'success') : null);

    document.querySelectorAll('[data-flow-delete]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.flowDelete;
            const name = btn.dataset.flowName || 'this flow';

            // Deleting a published flow stops it answering DMs immediately, so
            // name the flow in the prompt rather than asking a generic
            // "are you sure".
            const ok = window.confirmModal
                ? await window.confirmModal({
                    title: 'Delete ' + name + '?',
                    body: 'If it is published it will stop replying to messages straight away.',
                    confirm: 'Delete',
                    danger: true,
                })
                : true;
            if (!ok) return;

            btn.disabled = true;
            try {
                const r = await fetch(`/flows/api/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok || !d.success) throw new Error(d.message || `HTTP ${r.status}`);

                toast(d.message || 'Flow deleted', 'success');
                btn.closest('[class*="px-5"]')?.remove();
            } catch (e) {
                toast('Could not delete: ' + e.message, 'error');
                btn.disabled = false;
            }
        });
    });
}

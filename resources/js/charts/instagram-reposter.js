// IgDesk Reels Autopilot — Overview / Autopilot-settings tabs, so the long
// config form is tucked behind a tab instead of one big scroll. Page key:
// instagram-reposter (registered in app.js).
export default function init() {
    const tabs = Array.from(document.querySelectorAll('[data-rp-tab]'));
    if (!tabs.length) return;

    const show = (name) => {
        document.querySelectorAll('[data-rp-panel]').forEach((p) => {
            p.classList.toggle('hidden', p.dataset.rpPanel !== name);
        });
        tabs.forEach((t) => {
            const on = t.dataset.rpTab === name;
            t.classList.toggle('ig-grad-soft', on);
            t.classList.toggle('text-white', on);
            t.classList.toggle('font-semibold', on);
            t.classList.toggle('text-ink-600', !on);
            t.classList.toggle('font-medium', !on);
        });
    };

    tabs.forEach((t) => t.addEventListener('click', () => show(t.dataset.rpTab)));
    // Open the settings tab automatically if the autopilot save bounced with errors.
    show(document.querySelector('[data-rp-open]')?.dataset.rpOpen || 'overview');
}

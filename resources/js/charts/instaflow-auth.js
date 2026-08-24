/*
 * Sign-in / sign-up card behaviour: password reveal + strength meter.
 *
 * Progressive — with the bundle blocked the fields are still normal password
 * inputs and both forms still submit. The meter is guidance only; the server
 * enforces Password::defaults() regardless of what it shows.
 */

function wireReveal() {
    document.querySelectorAll('[data-pw-toggle]').forEach((btn) => {
        const input = document.getElementById(btn.dataset.pwToggle);
        if (!input) return;

        btn.addEventListener('click', () => {
            const wasText = input.type === 'text';
            input.type = wasText ? 'password' : 'text';
            btn.style.color = wasText ? '' : '#C13584';
            btn.setAttribute('aria-label', wasText ? 'Show password' : 'Hide password');

            // Keep the caret at the end so revealing mid-typing costs no place.
            const end = input.value.length;
            input.focus();
            input.setSelectionRange(end, end);
        });
    });
}

function wireStrength() {
    const input = document.querySelector('[data-pw-meter]');
    if (!input) return;

    const bars = [
        document.querySelector('[data-pw-b1]'),
        document.querySelector('[data-pw-b2]'),
        document.querySelector('[data-pw-b3]'),
    ];
    const label = document.querySelector('[data-pw-label]');

    // Three independent tests → 0..3 filled bars. Deliberately loose: it nudges
    // toward a stronger password, it does not gate submission.
    const COLORS = ['#E1306C', '#F77737', '#22C55E'];
    const LABELS = ['Weak', 'Okay', 'Strong'];

    const paint = (v) => {
        let score = 0;
        if (v.length >= 8) score++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
        if (/\d/.test(v) || /[^A-Za-z0-9]/.test(v)) score++;

        bars.forEach((bar, i) => {
            if (!bar) return;
            const on = i < score;
            bar.style.width = on ? '100%' : '0';
            bar.style.background = on ? COLORS[score - 1] : 'transparent';
        });

        if (label) {
            label.textContent = v ? (LABELS[score - 1] || 'Weak') : '—';
            label.style.color = v && score ? COLORS[score - 1] : '';
        }
    };

    input.addEventListener('input', (e) => paint(e.target.value));
    paint(input.value);   // repopulated value after a validation bounce
}

export default function init() {
    wireReveal();
    wireStrength();
}

/*
 * Vendored from core for the standalone Instagram product.
 *
 * instagram-flow-builder.js imports themeColor() and standalone does not
 * ship core's resources/js/. Copied rather than inlined so the import path
 * in the builder stays byte-identical to core's — that keeps regenerating
 * the builder from core a straight copy with three constant swaps, not a
 * merge.
 */
/**
 * Runtime access to the admin-controlled theme tokens.
 *
 * Charts and any other JS that needs a brand colour must read it from here
 * rather than hardcoding a hex. The admin recolours the app by overriding
 * `--color-*` custom properties at :root (see theme_css() in
 * app/Support/fc_helpers.php) — a literal '#25D366' in a chart config is
 * invisible to that override, which is exactly how a "navy" dashboard ends up
 * with green charts.
 *
 * Values are read from the live computed style, so they already reflect the
 * admin's override, the active data-theme, and any @media adjustments.
 *
 *   import { themeColor, themeAlpha, chartPalette } from '../theme-colors.js';
 *   borderColor: themeColor('wa-deep')
 *   backgroundColor: themeAlpha('wa-deep', 0.12)
 */

/*
 * MUST mirror the :root block in resources/css/instaflow.css.
 *
 * These were copied over from core unchanged, so every entry was a WhatsApp
 * value — #075E54 green for wa-deep, #DCF8C6 mint, a warm paper scale. They are
 * only reached when getComputedStyle cannot read the property, but that window
 * is real: the builder calls themeColor() while building its node catalog, which
 * runs at module scope, and any token that misses lands a WhatsApp green in the
 * middle of an Instagram canvas — a node icon or border that disagrees with
 * everything around it, with nothing in the console to explain it.
 *
 * Values below are the light-theme tokens. A dark-theme miss resolves a shade
 * light rather than green, which is the far cheaper failure.
 */
const FALLBACKS = {
    'wa-deep': '#C13584',
    'wa-teal': '#E1306C',
    'wa-green': '#15803D',
    'wa-mint': '#FBE4F0',
    'wa-bubble': '#FDF2F8',
    'paper-0': '#FFFFFF',
    'paper-50': '#FBF8FC',
    'paper-100': '#F4EFF6',
    'paper-200': '#EBE3EE',
    'ink-500': '#928699',
    'ink-700': '#46394F',
    'ink-900': '#1A1320',
    'accent-coral': '#C22B4A',
    'accent-amber': '#E5A04E',
    'accent-plum': '#833AB4',
    'accent-sky': '#405DE6',
};

/** Effective hex for a theme token, e.g. themeColor('wa-deep'). */
export function themeColor(token) {
    const raw = getComputedStyle(document.documentElement)
        .getPropertyValue('--color-' + token)
        .trim();
    return raw || FALLBACKS[token] || '#000000';
}

/**
 * Same token as an rgba() at the given alpha — for chart fills that need to
 * sit translucently over the page. Handles #rgb and #rrggbb; anything else
 * (a named colour, an oklch(), a color-mix()) is handed to color-mix() so the
 * browser resolves it rather than us guessing.
 */
export function themeAlpha(token, alpha = 0.15) {
    const hex = themeColor(token);
    const m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(hex);
    if (!m) return `color-mix(in srgb, ${hex} ${Math.round(alpha * 100)}%, transparent)`;

    let h = m[1];
    if (h.length === 3) h = h.split('').map((c) => c + c).join('');
    const n = parseInt(h, 16);
    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
}

/**
 * Ordered series colours for multi-dataset charts. Brand tokens first so the
 * primary series always carries the admin's colour, then the accents — which
 * are themselves admin-editable, so a fully recoloured dashboard stays
 * internally consistent instead of mixing a custom brand with stock greens.
 */
export function chartPalette() {
    return [
        themeColor('wa-deep'),
        themeColor('wa-green'),
        themeColor('accent-sky'),
        themeColor('accent-amber'),
        themeColor('accent-plum'),
        themeColor('accent-coral'),
        themeColor('wa-teal'),
    ];
}

/** Axis/grid/label colours so charts stay legible in every theme. */
export function chartInk() {
    return {
        text: themeColor('ink-500'),
        grid: themeAlpha('ink-500', 0.14),
        border: themeColor('paper-200'),
    };
}

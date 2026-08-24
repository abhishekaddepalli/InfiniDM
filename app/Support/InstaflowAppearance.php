<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;

/**
 * Per-device appearance: mode, accent, typeface, text size.
 *
 * ONE definition, read by the picker page (to render the options), the
 * controller (to validate a save) and the layout (to emit the overrides). A
 * second copy anywhere is a preset that renders but cannot be saved, or saves
 * but never applies.
 *
 * Stored as a single JSON cookie rather than user columns because the standalone
 * schema has no settings table, and appearance is genuinely per-device — the
 * same account on a laptop and a phone can reasonably differ.
 *
 * WRITE IT THROUGH LARAVEL. A cookie set by document.cookie is plaintext, and
 * EncryptCookies rejects it on the next request, so the choice silently reverts
 * on every navigation. That bug is the reason this is a POST and not a toggle.
 */
class InstaflowAppearance
{
    public const COOKIE = 'instaflow_appearance';

    /**
     * Accent presets.
     *
     * `solid` carries UI text on white, `dark` carries it on #121212 — one value
     * cannot do both, which is why every preset ships the pair. All 28 values
     * clear WCAG AA (4.5:1) against their own surface; the weakest is 4.92:1.
     */
    public const ACCENTS = [
        'instagram' => ['label' => 'Instagram', 'solid' => '#C13584', 'dark' => '#F06AA0',
            'grad' => 'linear-gradient(135deg,#833AB4 0%,#E1306C 55%,#F77737 100%)'],
        'sunset'    => ['label' => 'Sunset',    'solid' => '#C62F52', 'dark' => '#F4708A',
            'grad' => 'linear-gradient(135deg,#F77737 0%,#FD1D1D 55%,#C13584 100%)'],
        'violet'    => ['label' => 'Violet',    'solid' => '#7B33C0', 'dark' => '#B98AE8',
            'grad' => 'linear-gradient(135deg,#6A2FB5 0%,#833AB4 55%,#B14AE0 100%)'],
        'ocean'     => ['label' => 'Ocean',     'solid' => '#3F51D8', 'dark' => '#8FA0F2',
            'grad' => 'linear-gradient(135deg,#405DE6 0%,#5851DB 55%,#833AB4 100%)'],
        'rose'      => ['label' => 'Rose',      'solid' => '#C42A6B', 'dark' => '#F075A6',
            'grad' => 'linear-gradient(135deg,#E1306C 0%,#FD1D1D 55%,#FCAF45 100%)'],
        'graphite'  => ['label' => 'Graphite',  'solid' => '#46394F', 'dark' => '#B7AEBE',
            'grad' => 'linear-gradient(135deg,#2A2233 0%,#46394F 55%,#6B5E74 100%)'],
        'plum'      => ['label' => 'Plum',      'solid' => '#86198F', 'dark' => '#F0ABFC',
            'grad' => 'linear-gradient(135deg,#701A75 0%,#A21CAF 55%,#E879F9 100%)'],
        'crimson'   => ['label' => 'Crimson',   'solid' => '#BE123C', 'dark' => '#FDA4AF',
            'grad' => 'linear-gradient(135deg,#9F1239 0%,#E11D48 55%,#FB7185 100%)'],
        'indigo'    => ['label' => 'Indigo',    'solid' => '#4338CA', 'dark' => '#A5B4FC',
            'grad' => 'linear-gradient(135deg,#3730A3 0%,#4F46E5 55%,#818CF8 100%)'],
        'teal'      => ['label' => 'Teal',      'solid' => '#0E7490', 'dark' => '#67E8F9',
            'grad' => 'linear-gradient(135deg,#155E75 0%,#0891B2 55%,#22D3EE 100%)'],
        'emerald'   => ['label' => 'Emerald',   'solid' => '#0F766E', 'dark' => '#5EEAD4',
            'grad' => 'linear-gradient(135deg,#115E59 0%,#0D9488 55%,#2DD4BF 100%)'],
        'forest'    => ['label' => 'Forest',    'solid' => '#15803D', 'dark' => '#86EFAC',
            'grad' => 'linear-gradient(135deg,#166534 0%,#16A34A 55%,#4ADE80 100%)'],
        'amber'     => ['label' => 'Amber',     'solid' => '#A16207', 'dark' => '#FCD34D',
            'grad' => 'linear-gradient(135deg,#854D0E 0%,#CA8A04 55%,#FACC15 100%)'],
        'slate'     => ['label' => 'Slate',     'solid' => '#334155', 'dark' => '#CBD5E1',
            'grad' => 'linear-gradient(135deg,#1E293B 0%,#475569 55%,#94A3B8 100%)'],
    ];

    /**
     * Typefaces. `import` is the Google Fonts family fragment, or null for a
     * stack already on the machine — a system choice costs no network request,
     * which is the point of offering it.
     */
    public const FONTS = [
        'inter'   => ['label' => 'Inter',     'stack' => "'Inter', sans-serif",     'import' => 'Inter:wght@400;500;600;700'],
        'dmsans'  => ['label' => 'DM Sans',   'stack' => "'DM Sans', sans-serif",   'import' => 'DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700'],
        'poppins' => ['label' => 'Poppins',   'stack' => "'Poppins', sans-serif",   'import' => 'Poppins:wght@400;500;600;700'],
        'nunito'  => ['label' => 'Nunito',    'stack' => "'Nunito', sans-serif",    'import' => 'Nunito:wght@400;500;600;700'],
        'mulish'  => ['label' => 'Mulish',    'stack' => "'Mulish', sans-serif",    'import' => 'Mulish:wght@400;500;600;700'],
        'manrope' => ['label' => 'Manrope',   'stack' => "'Manrope', sans-serif",   'import' => 'Manrope:wght@400;500;600;700'],
        'jakarta' => ['label' => 'Jakarta',   'stack' => "'Plus Jakarta Sans', sans-serif", 'import' => 'Plus+Jakarta+Sans:wght@400;500;600;700'],
        'outfit'  => ['label' => 'Outfit',    'stack' => "'Outfit', sans-serif",    'import' => 'Outfit:wght@400;500;600;700'],
        'figtree' => ['label' => 'Figtree',   'stack' => "'Figtree', sans-serif",   'import' => 'Figtree:wght@400;500;600;700'],
        'sora'    => ['label' => 'Sora',      'stack' => "'Sora', sans-serif",      'import' => 'Sora:wght@400;500;600;700'],
        'worksans'=> ['label' => 'Work Sans', 'stack' => "'Work Sans', sans-serif", 'import' => 'Work+Sans:wght@400;500;600;700'],
        'system'  => ['label' => 'System',    'stack' => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif", 'import' => null],
    ];

    /**
     * Text size. Applied as the root font-size, so every rem-based Tailwind
     * utility scales with it — spacing and controls included, not just copy.
     */
    public const SIZES = [
        'small'   => ['label' => 'Small',   'root' => '15px',   'sample' => '17px'],
        'default' => ['label' => 'Default', 'root' => '16px',   'sample' => '21px'],
        'large'   => ['label' => 'Large',   'root' => '17.5px', 'sample' => '25px'],
    ];

    public const DEFAULTS = [
        'theme'  => 'paper',
        'accent' => 'instagram',
        'font'   => 'inter',
        'size'   => 'default',
    ];

    /** The saved appearance, every key guaranteed valid. */
    public static function current(): array
    {
        $raw = request()->cookie(self::COOKIE);
        $saved = is_string($raw) ? json_decode($raw, true) : null;
        $saved = is_array($saved) ? $saved : [];

        $a = self::sanitise($saved);

        // Resolved extras the views want but should not have to look up.
        $a['accent_solid'] = self::ACCENTS[$a['accent']]['solid'];
        $a['accent_dark']  = self::ACCENTS[$a['accent']]['dark'];
        $a['accent_grad']  = self::ACCENTS[$a['accent']]['grad'];
        $a['font_stack']   = self::FONTS[$a['font']]['stack'];
        $a['font_import']  = self::FONTS[$a['font']]['import'];
        $a['root_size']    = self::SIZES[$a['size']]['root'];

        return $a;
    }

    /** Drop anything unknown — a hand-edited cookie must not reach the CSS. */
    public static function sanitise(array $in): array
    {
        return [
            'theme'  => in_array($in['theme'] ?? null, ['paper', 'dark'], true) ? $in['theme'] : self::DEFAULTS['theme'],
            'accent' => isset(self::ACCENTS[$in['accent'] ?? '']) ? $in['accent'] : self::DEFAULTS['accent'],
            'font'   => isset(self::FONTS[$in['font'] ?? ''])     ? $in['font']   : self::DEFAULTS['font'],
            'size'   => isset(self::SIZES[$in['size'] ?? ''])     ? $in['size']   : self::DEFAULTS['size'],
        ];
    }

    /** A forever cookie carrying the sanitised set. */
    public static function cookie(array $appearance): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::forever(self::COOKIE, json_encode(self::sanitise($appearance)));
    }

    /**
     * The <style> block the layout emits.
     *
     * Overrides the same custom properties instaflow.css declares, so every
     * existing utility and every hardcoded var() reference follows along with no
     * call-site changes. The dark values are scoped to [data-theme="dark"] so a
     * light accent never leaks onto a light surface.
     */
    public static function css(): string
    {
        $a = self::current();

        $out = ':root{'
            . '--font-sans:' . $a['font_stack'] . ';'
            . '--color-wa-deep:' . $a['accent_solid'] . ';'
            . '--ig-grad:' . $a['accent_grad'] . ';'
            . '}'
            . 'html{font-size:' . $a['root_size'] . ';}'
            // .ig-grad-soft and friends are literal gradients in the stylesheet;
            // repoint them at the chosen one.
            . '.ig-grad-soft,.ig-grad{background:' . $a['accent_grad'] . ';}'
            . '.ig-text{background:' . $a['accent_grad'] . ';-webkit-background-clip:text;background-clip:text;color:transparent;}'
            . '.ig-ring{background:' . $a['accent_grad'] . ';}'
            // Gradient border on a selected flow-builder node reads this layer.
            // (The theme picker's own cards use a plain accent border — a
            // background-clip gradient border toggled by :has() mis-paints.)
            . '.node-card.selected{'
            . 'background-image:linear-gradient(var(--color-paper-0),var(--color-paper-0)),' . $a['accent_grad'] . ';}'
            . '.ig-tab-active{border-image:' . $a['accent_grad'] . ' 1;}';

        // Dark mode needs the lighter partner, or the accent stops carrying text.
        $out .= '[data-theme="dark"]{--color-wa-deep:' . $a['accent_dark'] . ';}';

        return $out;
    }
}

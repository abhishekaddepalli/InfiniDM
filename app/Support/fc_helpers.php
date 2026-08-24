<?php

/**
 * Standalone helper shims.
 *
 * A handful of global helpers are called RAW from shared Blade views — Blade
 * cannot guard a bare function call, so an undefined one is a hard
 * "Call to undefined function" 500 on the page that renders it. In WaDesk these
 * live in core's app/Support/fc_helpers.php; that file is host-owned and ships
 * in neither distribution, so standalone supplies its own.
 *
 * This file loads ONLY in standalone: it sits under standalone/, which the
 * addon build excludes, and it is wired in via the "files" autoload of
 * standalone/composer.json (also standalone-only). In WaDesk neither the file
 * nor that autoload entry is present, so core's definitions are the only ones
 * that ever load. The function_exists() guards are belt-and-braces on top of
 * that — matching how core guards every helper — so no double-definition is
 * possible even if the two trees were ever merged by mistake.
 *
 * ONLY the helpers actually reached by shared code are shimmed. Node-bridge
 * helpers (wd_node_url/node_token) are deliberately absent: every call site is
 * already behind `function_exists()` in InstagramGate, which falls back to
 * config('instagram.node.*') when they are missing — so defining them here
 * would override the standalone config path, not complete it.
 */

if (! function_exists('brand_name')) {
    /**
     * The product name shown in the header, the auth screens and the browser
     * title. Standalone is a single-brand product, so this is config-driven
     * (APP_NAME) rather than the per-workspace lookup core performs.
     */
    function brand_name(): string
    {
        // Admin-editable: the name typed in General Settings drives the header,
        // auth screens, buttons and browser title everywhere. Falls back to
        // APP_NAME (then a literal) before the settings table exists.
        return (string) (setting('site_name') ?: config('app.name', 'InstaMagic'));
    }
}

if (! function_exists('workspace_brand_color')) {
    /**
     * Verbatim port of core's helper — a pure string function with no WaDesk
     * dependency. Kept identical so a colour authored either side renders the
     * same. The legacy WhatsApp teal (#075E54) is treated as "unset" and the
     * caller's fallback wins, so a workspace that never picked a colour shows
     * the Instagram-magenta default the header passes in.
     */
    function workspace_brand_color(mixed $workspace, string $fallback = '#C13584'): string
    {
        $raw = is_object($workspace) ? (string) ($workspace->brand_color ?? '') : (string) $workspace;
        $raw = trim($raw);

        if ($raw === '' || strcasecmp($raw, '#075E54') === 0) {
            return $fallback;
        }
        return preg_match('/^#[0-9A-Fa-f]{3,8}$/', $raw) ? $raw : $fallback;
    }
}

if (! function_exists('setting')) {
    /**
     * Admin-editable global config. Reads through the cached Setting store, with
     * a graceful fallback to the passed default (or config/env) before the
     * settings table exists — so a page never fatals mid-install.
     */
    function setting(string $key, $default = null)
    {
        try {
            return \App\Models\Setting::get($key, $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

if (! function_exists('front')) {
    /**
     * Front-of-site editable copy. Resolves to the admin-saved value
     * (setting front_<key>) or the built-in registry default. Used across the
     * public marketing pages so every line is editable from admin → Front pages.
     */
    function front(string $key, $fallback = null): string
    {
        return \App\Support\FrontContent::get($key, $fallback);
    }
}

if (! function_exists('front_bool')) {
    /** Boolean-ish front flag (e.g. show/hide a page). Defaults to true. */
    function front_bool(string $key, bool $default = true): bool
    {
        $v = \App\Support\FrontContent::get($key, $default ? '1' : '0');
        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'on', 'yes'], true);
    }
}

if (! function_exists('site_name')) {
    /** The SaaS brand name shown across the app; defaults to IgDesk. */
    function site_name(): string
    {
        return (string) (setting('site_name') ?: 'InstaMagic');
    }
}

if (! function_exists('brand_asset')) {
    /**
     * Resolve a stored brand-asset path to a servable URL.
     *
     * New uploads live DIRECTLY under public/ (uploads/branding/…) so they are
     * always web-served — no `storage:link` symlink, which is what returns a 403
     * on sub-folder installs like /instaflow/public. Legacy values that were
     * saved on the public disk (branding/…) still resolve through /storage.
     */
    function brand_asset(?string $path): ?string
    {
        $p = ltrim((string) $path, '/');
        if ($p === '') return null;
        return str_starts_with($p, 'uploads/') ? asset($p) : asset('storage/' . $p);
    }
}

if (! function_exists('site_logo')) {
    /** Public URL of the uploaded logo, or null to fall back to the mark. */
    function site_logo(): ?string
    {
        return brand_asset(setting('site_logo'));
    }
}

if (! function_exists('site_favicon')) {
    /** Public URL of the uploaded favicon (admin General settings), or null. */
    function site_favicon(): ?string
    {
        return brand_asset(setting('brand_favicon'));
    }
}

if (! function_exists('site_logo_dark')) {
    /** Public URL of the uploaded DARK-mode logo, or null if none was set. */
    function site_logo_dark(): ?string
    {
        return brand_asset(setting('logo_dark'));
    }
}

/*
|--------------------------------------------------------------------------
| WaDesk frontend live-editor shims
|--------------------------------------------------------------------------
| The ported marketing blades (about / contact / legal + the frontend
| components) call these raw. WaDesk backs them with a full live-editor
| store; standalone has no such editor, so these are lean passthroughs:
| every line still resolves to an admin-editable Setting override
| (front_<dotted.key>) and otherwise falls back to the caller's shipped
| default — so an un-edited install renders exactly the ported design.
*/

if (! function_exists('fc')) {
    /**
     * Front-of-site editable copy, WaDesk-style dotted keys (e.g.
     * "about.hero.headline"). Returns the admin override if one was saved,
     * else the shipped default the blade passes in.
     */
    function fc(string $key, $default = ''): string
    {
        $v = setting('front_' . $key, null);
        return $v === null || $v === '' ? (string) $default : (string) $v;
    }
}

if (! function_exists('fcp')) {
    /** Page-scoped variant — identical resolution in standalone. */
    function fcp(string $key, $default = ''): string
    {
        return fc($key, $default);
    }
}

if (! function_exists('fc_skey')) {
    /** The data-fc attribute value the editor bridge reads; just the key here. */
    function fc_skey(string $key): string
    {
        return $key;
    }
}

if (! function_exists('fc_editing')) {
    /** No inline live editor in standalone — the bridge script never loads. */
    function fc_editing(): bool
    {
        return false;
    }
}

if (! function_exists('fc_section_order')) {
    /** No admin re-ordering in standalone — render sections in shipped order. */
    function fc_section_order(string $page, array $slugs): array
    {
        return $slugs;
    }
}

if (! function_exists('fc_section_visible')) {
    /** Every ported section is visible in standalone. */
    function fc_section_visible(string $page, string $slug): bool
    {
        return true;
    }
}

if (! function_exists('site_info')) {
    /**
     * Shared site-identity value (phone, address, socials…). Reads the
     * admin-editable Setting (site_<key>), else the caller's default.
     */
    function site_info(string $key, $default = null)
    {
        $v = setting('site_' . $key, null);
        return $v === null || $v === '' ? $default : $v;
    }
}

if (! function_exists('brand_email')) {
    /**
     * A role-based contact address (support / sales / security / legal /
     * careers). Prefers an explicit admin Setting (email_<role>), then the
     * general contact address, and finally derives role@<site-domain>.
     */
    function brand_email(string $role = 'support'): string
    {
        $explicit = setting('email_' . $role, null);
        if (is_string($explicit) && filter_var($explicit, FILTER_VALIDATE_EMAIL)) {
            return $explicit;
        }

        $general = (string) (front('contact_email', '') ?: setting('site_email', ''));
        if ($general !== '' && filter_var($general, FILTER_VALIDATE_EMAIL)) {
            // Reuse the domain of the configured contact address for other roles.
            [$user, $domain] = array_pad(explode('@', $general, 2), 2, '');
            return $role === 'support' || $domain === '' ? $general : $role . '@' . $domain;
        }

        $host = parse_url((string) config('app.url', 'https://localhost'), PHP_URL_HOST) ?: 'example.com';
        $host = preg_replace('/^www\./', '', $host);
        return $role . '@' . $host;
    }
}

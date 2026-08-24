<?php

namespace App\Services\Instagram;

/**
 * The ONE place that knows whether this install sells Instagram through
 * WaDesk plans or as a standalone product.
 *
 * Every Instagram controller, view and route asks this class — never
 * PlanLimitGuard, never Package, never a `access_instagram_*` column name.
 * That indirection is the whole point: the same controllers ship in
 *
 *   - WaDesk, where a workspace's plan decides what it gets, and
 *   - the standalone Instagram product, which has no plans at all.
 *
 * Feature names here are SHORT and product-neutral ('inbox', 'commerce').
 * The WaDesk driver maps them onto WaDesk's `access_instagram_*` columns;
 * standalone never sees a column name. Adding a feature means adding it to
 * FEATURES below and to extension.json — not touching call sites.
 */
class InstagramGate
{
    /**
     * Every gateable Instagram feature.
     *
     * key => [label, wadesk_column]
     *
     * The WaDesk column is what an operator ticks on the package form. Keep
     * the `access_instagram_` prefix — ExtensionRegistry uses it to spot
     * flags belonging to an extension that is no longer installed, so a plan
     * still carrying a stale flag fails closed instead of granting access.
     */
    public const FEATURES = [
        // Master switch. Gates the whole suite at the route-group level, so a
        // plan with no Instagram at all fails once rather than eighteen times.
        'master'        => ['Instagram',                            'access_instagram'],

        'inbox'         => ['Instagram inbox (DMs)',                'access_instagram_inbox'],
        'composer'      => ['Post composer / publishing',           'access_instagram_composer'],
        'scheduler'     => ['Scheduled posts + content calendar',   'access_instagram_scheduler'],
        'broadcast'     => ['DM broadcast',                         'access_instagram_broadcast'],
        'analytics'     => ['Analytics + post insights',            'access_instagram_analytics'],
        'posts'         => ['My posts grid',                        'access_instagram_posts'],
        'comments'      => ['Live comment moderation',              'access_instagram_comments'],
        'automations'   => ['Automations (triggers, ice-breakers)', 'access_instagram_automations'],
        'auto_comments' => ['Auto-comment replies',                 'access_instagram_auto_comments'],
        'templates'     => ['Saved DM templates',                   'access_instagram_templates'],
        'discovery'     => ['Discovery (competitor + hashtag)',     'access_instagram_discovery'],
        'reposter'      => ['Reels Autopilot (reposter)',           'access_instagram_reposter'],
        'ads'           => ['Boost post / Instagram ads',           'access_instagram_ads'],
        'commerce'      => ['Commerce (catalog + products)',        'access_instagram_commerce'],
        'leads'         => ['Lead capture + Lead Ads',              'access_instagram_leads'],
        'orders'        => ['In-DM ordering + orders',              'access_instagram_orders'],
        'ai'            => ['AI agent replies in the inbox',        'access_instagram_ai'],
        'flows'         => ['Instagram flow builder',               'access_instagram_flows'],
    ];

    /**
     * Numeric limits. key => [label, wadesk_column].
     *
     * null from limit() means UNLIMITED, which is deliberately a different
     * answer from 0. WaDesk stores 0 for "unlimited" in its limit columns
     * (see the package form's "∞ unlimited" placeholder), so the driver
     * translates that here rather than leaking the convention to callers.
     */
    public const LIMITS = [
        'instagram_accounts'          => ['Instagram accounts',       'instagram_accounts_limit'],
        'instagram_flows'             => ['Instagram flows',          'instagram_flows_limit'],
        'instagram_monthly_dms'       => ['Instagram DMs / month',    'instagram_monthly_dms_limit'],
        'instagram_monthly_broadcast' => ['Broadcast DMs / month',    'instagram_monthly_broadcast_limit'],
        'instagram_scheduled_posts'   => ['Scheduled posts',          'instagram_scheduled_posts_limit'],
        'instagram_automations'       => ['Automation rules',         'instagram_automations_limit'],
        'instagram_templates'         => ['Saved DM templates',       'instagram_templates_limit'],
    ];

    // ─────────────────────────────────────────────────────────────────────
    // Standalone plan enforcement (IgDesk packages).
    //
    // The gate's feature/limit vocabulary is finer-grained than a Package's,
    // so these maps translate. A gate feature mapped to NULL is BASELINE —
    // every tenant on any plan (or the default plan) has it; only the mapped
    // ones are actually sold. A limit mapped to NULL has no plan cap.
    //
    // Enforcement is OFF unless the operator opts in (enforcing()), so adding
    // packages never silently locks out a half-configured install. Admins
    // always bypass — they run the platform.
    // ─────────────────────────────────────────────────────────────────────
    private const PACKAGE_FEATURE_MAP = [
        'master'        => null,        // the product itself — baseline
        'inbox'         => 'inbox',
        'composer'      => 'composer',
        'scheduler'     => 'scheduler',
        'broadcast'     => 'broadcast',
        'analytics'     => 'analytics',
        'posts'         => 'posts',
        'comments'      => 'comments',
        'automations'   => 'automations',
        'auto_comments' => 'auto_comments',
        'templates'     => 'templates',
        'discovery'     => 'discovery',
        'reposter'      => 'reposter',
        'ads'           => 'ads',
        'commerce'      => 'commerce',
        'leads'         => 'leads',
        'orders'        => 'orders',
        'ai'            => 'ai',
        'flows'         => 'flows',
    ];

    /** Gate limit key => Package column. NULL = no plan cap for this key. */
    private const PACKAGE_LIMIT_MAP = [
        'instagram_accounts'          => 'max_accounts',
        'instagram_flows'             => 'max_flows',
        'instagram_automations'       => 'max_automations',
        'instagram_monthly_dms'       => 'monthly_dms',
        'instagram_monthly_broadcast' => null,
        'instagram_scheduled_posts'   => null,
        'instagram_templates'         => null,
    ];

    /**
     * Is standalone plan enforcement switched on?
     *
     * Default OFF. A Setting row 'enforce_plans' (admin-toggleable) wins over
     * the config default so the operator can flip it from the UI without an
     * env change. WaDesk never consults this — allows()/limit() route it to
     * PlanLimitGuard before ever reaching the standalone branch.
     */
    public static function enforcing(): bool
    {
        try {
            $s = \App\Models\Setting::get('enforce_plans', null);
            if ($s !== null) {
                return (bool) $s;
            }
        } catch (\Throwable $e) {
        }

        return (bool) config('instagram.standalone.enforce_plans', false);
    }

    /**
     * The plan whose entitlements apply to the current request.
     *
     * The signed-in user's own plan, falling back to the install's default
     * plan (what a fresh signup would land on) so an unassigned account is
     * measured against the same baseline rather than nothing.
     */
    public static function currentPackage(): ?\App\Models\Package
    {
        try {
            $u = auth()->user();
            if ($u && $u->package_id && $u->package) {
                return $u->package;
            }
        } catch (\Throwable $e) {
        }

        try {
            return \App\Models\Package::default();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** 'wadesk' | 'standalone' */
    public static function mode(): string
    {
        $configured = (string) config('instagram.mode', 'auto');
        if ($configured === 'wadesk' || $configured === 'standalone') {
            return $configured;
        }

        // Auto: WaDesk is identified by its plan machinery actually being
        // present. Checking for the class rather than a config flag means a
        // standalone install cannot accidentally be misconfigured into asking
        // a Package model that does not exist.
        return class_exists(\App\Services\PlanLimitGuard::class)
            && class_exists(\App\Models\Package::class)
                ? 'wadesk'
                : 'standalone';
    }

    public static function isWaDesk(): bool
    {
        return self::mode() === 'wadesk';
    }

    /**
     * May the current tenant use this feature?
     *
     * Unknown feature names return FALSE. A typo'd gate that silently allows
     * everything is the worst possible failure for a paywall.
     */
    public static function allows(string $feature, $workspace = null): bool
    {
        if (!isset(self::FEATURES[$feature])) {
            return false;
        }

        if (!self::isWaDesk()) {
            return self::standaloneAllows($feature);
        }

        $column = self::FEATURES[$feature][1];

        try {
            $workspace = $workspace ?? (auth()->user()->currentWorkspace ?? null);

            return (bool) \App\Services\PlanLimitGuard::hasFeature($workspace, $column);
        } catch (\Throwable $e) {
            // Fail CLOSED. If entitlement cannot be determined, the safe
            // answer for a paid feature is "no", not "yes".
            return false;
        }
    }

    private static function standaloneAllows(string $feature): bool
    {
        // Operators administer the platform — NEVER gated, whether or not plan
        // enforcement is on, and regardless of any per-feature config override.
        // Mirrors WaDesk, where an operator bypasses every plan feature. Checked
        // first so no later branch can accidentally lock an admin out.
        try {
            if (auth()->user()?->is_admin) {
                return true;
            }
        } catch (\Throwable $e) {
        }

        // Enforcement off → legacy behaviour: everything on unless a per-feature
        // config override says otherwise. This is the default, so enabling
        // packages cannot lock a live install out of its own features until the
        // operator explicitly turns enforcement on.
        if (! self::enforcing()) {
            $overrides = (array) config('instagram.standalone.features', []);
            if (array_key_exists($feature, $overrides)) {
                return (bool) $overrides[$feature];
            }

            return (bool) config('instagram.standalone.features_all_enabled', true);
        }

        // Baseline features (mapped to null, or not sold at all) are granted to
        // every tenant. Only mapped features are paywalled.
        $pkgKey = self::PACKAGE_FEATURE_MAP[$feature] ?? null;
        if ($pkgKey === null) {
            return true;
        }

        $pkg = self::currentPackage();
        if (! $pkg) {
            // Enforcement on but no plan exists anywhere — don't strand the
            // install; fall back to the open default.
            return (bool) config('instagram.standalone.features_all_enabled', true);
        }

        return $pkg->hasFeature($pkgKey);
    }

    /**
     * Numeric cap for a limit key, or null for unlimited.
     */
    public static function limit(string $key, $workspace = null): ?int
    {
        if (!isset(self::LIMITS[$key])) {
            return null;
        }

        if (!self::isWaDesk()) {
            // Operators are uncapped — on or off enforcement (WaDesk parity).
            // Checked first so no later branch can cap an admin.
            try {
                if (auth()->user()?->is_admin) {
                    return null;
                }
            } catch (\Throwable $e) {
            }

            // Enforcement off → legacy config caps (default: unlimited).
            if (! self::enforcing()) {
                $v = config('instagram.standalone.limits.' . $key, null);

                return $v === null ? null : (int) $v;
            }

            $col = self::PACKAGE_LIMIT_MAP[$key] ?? null;
            if ($col === null) {
                return null; // no plan cap for this key
            }

            $pkg = self::currentPackage();
            if (! $pkg) {
                return null;
            }

            $v = $pkg->{$col};

            // NULL or 0 on a Package limit both mean "unlimited" — the plan
            // form ships blank as unlimited, and 0 matches WaDesk's convention.
            return ($v === null || (int) $v === 0) ? null : (int) $v;
        }

        $column = self::LIMITS[$key][1];

        try {
            $workspace = $workspace ?? (auth()->user()->currentWorkspace ?? null);
            $value = \App\Services\PlanLimitGuard::limit($workspace, $column);

            // WaDesk stores 0 to mean unlimited on its limit columns.
            return ($value === null || (int) $value === 0) ? null : (int) $value;
        } catch (\Throwable $e) {
            // Unlike features, an indeterminate LIMIT fails open — blocking
            // every send because a lookup hiccuped would be worse than
            // briefly not enforcing a cap.
            return null;
        }
    }

    /**
     * Is $used already at or past the cap? Unlimited is never exceeded.
     */
    public static function exceeded(string $key, int $used, $workspace = null): bool
    {
        $cap = self::limit($key, $workspace);

        return $cap !== null && $used >= $cap;
    }

    /** Remaining headroom, or null when unlimited. */
    public static function remaining(string $key, int $used, $workspace = null): ?int
    {
        $cap = self::limit($key, $workspace);

        return $cap === null ? null : max(0, $cap - $used);
    }

    /** Human label for a feature, for paywall copy. */
    public static function label(string $feature): string
    {
        return self::FEATURES[$feature][0] ?? ucfirst(str_replace('_', ' ', $feature));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Host integration
    //
    // Everything above answers "what has this tenant PAID for" and keys off
    // mode(). Everything below answers "what does this INSTALL physically
    // have" and keys off the class actually existing.
    //
    // Keeping the two apart is load-bearing. config('instagram.mode') can be
    // forced to 'standalone' on a real WaDesk install (the documented case
    // where an operator sells Instagram to everyone unplan-gated). If the
    // helpers below keyed off mode(), that one env var would also cut them
    // off from SystemSetting and core's Flow model — silently breaking OAuth
    // and every flow trigger on an install that still has both. Entitlement
    // is a business answer; integration is a fact about the filesystem.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Optional host subsystems the Instagram feature uses when they are there.
     *
     * Listed as ::class strings in a const, which does NOT autoload — the
     * array is inert until hasCore() asks about one entry. A subsystem counts
     * as present only when EVERY class it needs is, so a half-shipped module
     * cannot pass the check and then fatal one call later.
     */
    private const CORE = [
        'crm'         => [\App\Models\Deal::class, \App\Models\Pipeline::class],
        'storefront'  => [\App\Models\WaStorefront::class, \App\Services\Storefront\StorefrontPaymentService::class],
        'ai_training' => [\App\Models\AiChatAssistant::class],
        'attributes'  => [\App\Models\Attribute::class],
        'translator'  => [\App\Services\Translator::class],
        'meta_ads'    => [\App\Models\WaProviderConfig::class, \App\Services\MetaGraphClient::class],
        'settings'    => [\App\Models\SystemSetting::class],
        'flows'       => [\App\Models\Flow::class],
    ];

    /** @var array<string,bool> */
    private static array $coreCache = [];

    /**
     * Is a host subsystem installed?
     *
     * Memoised because the hot paths ask repeatedly — the InstagramService
     * constructor reads the graph version, so hasCore('settings') sits in
     * front of every Graph API call, and a failed class_exists() is the
     * expensive kind (it walks the whole autoloader before giving up).
     */
    public static function hasCore(string $subsystem): bool
    {
        if (isset(self::$coreCache[$subsystem])) {
            return self::$coreCache[$subsystem];
        }

        $classes = self::CORE[$subsystem] ?? null;
        if ($classes === null) {
            // Unknown subsystem = absent. Same reasoning as an unknown
            // feature in allows(): a typo must not unlock a code path.
            return self::$coreCache[$subsystem] = false;
        }

        foreach ($classes as $class) {
            if (! class_exists($class)) {
                return self::$coreCache[$subsystem] = false;
            }
        }

        return self::$coreCache[$subsystem] = true;
    }

    // ───────────────────────── Platform settings ─────────────────────────

    /**
     * Read a platform setting (app id, secret, graph version, verify token).
     *
     * WaDesk keeps these in the admin-editable SystemSetting table; standalone
     * has no admin UI for them and reads config/instagram.php, which in turn
     * reads .env. Same keys either side, so no call site needs a mapping.
     *
     * An empty configured value falls through to $default deliberately: an
     * unset env var arrives as '', and that must behave like the missing DB
     * row it stands in for, not like a real setting of "".
     */
    public static function setting(string $key, mixed $default = null): mixed
    {
        if (self::hasCore('settings')) {
            return \App\Models\SystemSetting::get($key, $default);
        }

        // Standalone IgDesk has no SystemSetting core but DOES have a writable
        // key/value store (App\Models\Setting). Read from it so the admin page and
        // the OAuth runtime see the same saved credentials. env config is the last
        // resort for a fresh install that only set values in .env.
        if (class_exists(\App\Models\Setting::class)) {
            $v = \App\Models\Setting::get($key, null);
            if ($v !== null && $v !== '') {
                return $v;
            }
        }

        $v = config('instagram.settings.' . $key, null);

        return ($v === null || $v === '') ? $default : $v;
    }

    /** Has this setting been configured at all? Drives "•••• set" badges. */
    public static function settingExists(string $key): bool
    {
        if (self::hasCore('settings')) {
            return \App\Models\SystemSetting::where('key', $key)->exists();
        }

        if (class_exists(\App\Models\Setting::class)) {
            $v = \App\Models\Setting::get($key, null);
            if ($v !== null && $v !== '') {
                return true;
            }
        }

        return trim((string) config('instagram.settings.' . $key, '')) !== '';
    }

    /**
     * Persist a platform setting. False means there is nowhere to write.
     *
     * Standalone genuinely has no writable store — these values live in .env,
     * which the app must not rewrite at runtime. The admin settings page that
     * calls this is WaDesk-only anyway (routes/instagram.php only registers it
     * when the `admin` middleware exists), so false is unreachable in practice
     * and exists to keep the failure honest rather than silent.
     */
    public static function putSetting(string $key, mixed $value, string $type = 'string', ?string $description = null): bool
    {
        if (self::hasCore('settings')) {
            \App\Models\SystemSetting::set($key, $value, $type, $description);

            return true;
        }

        // Standalone IgDesk: persist to its own Setting store so the admin
        // settings page actually saves (SystemSetting does not exist here).
        if (class_exists(\App\Models\Setting::class)) {
            \App\Models\Setting::set($key, $value, $type);

            return true;
        }

        return false;
    }

    // ─────────────────────────────── Flows ───────────────────────────────

    /**
     * The Eloquent model for the `flows` table in THIS install.
     *
     * Both classes map the same table with the same columns, so a flow
     * authored either side is read identically by the Node runtime. WaDesk
     * must keep using core's Flow (its observers mirror keyword triggers into
     * keyword_replies, which standalone has no table for), hence the
     * class_exists preference rather than a mode() check.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public static function flowModel(): string
    {
        return self::hasCore('flows')
            ? \App\Models\Flow::class
            : \App\Instagram\Models\InstagramFlow::class;
    }

    /** A fresh query on the flows table, whichever model owns it here. */
    public static function flows(): \Illuminate\Database\Eloquent\Builder
    {
        $model = self::flowModel();

        return $model::query();
    }

    // ───────────────────────── Node bridge ─────────────────────────

    /**
     * Base URL of the Node engine, without a trailing slash.
     *
     * Defers to core's wd_node_url() when it exists so WaDesk keeps ONE
     * resolver — an install that moves its Node URL in the admin UI must not
     * leave Instagram pointing at a stale .env value.
     */
    public static function nodeUrl(): string
    {
        if (function_exists('wd_node_url')) {
            return rtrim((string) wd_node_url(), '/');
        }

        return rtrim((string) (config('instagram.node.url') ?: ''), '/');
    }

    /** Shared secret for the Laravel↔Node hop. '' disables the bridge. */
    public static function nodeToken(): string
    {
        // Admin → Instagram settings → "Node token" (the `node_token` Setting) is
        // the operator's control and wins. Falls back to the helper, then
        // config/env (NODE_WEBHOOK_TOKEN), so an .env-only setup still works.
        try {
            $fromSetting = (string) (\App\Models\Setting::get('node_token', '') ?? '');
            if ($fromSetting !== '') return $fromSetting;
        } catch (\Throwable $e) {
            // Setting table not ready (fresh install) — fall through.
        }

        if (function_exists('node_token')) {
            return (string) node_token();
        }

        return (string) (config('instagram.node.token') ?: env('NODE_WEBHOOK_TOKEN', '') ?: '');
    }

    // ───────────────────── Optional host subsystems ─────────────────────

    /**
     * AI-Training assistants for the knowledge-base picker.
     *
     * Empty collection when the host has no AI-Training module — every caller
     * either iterates this for a <select> or checks it, so "none available"
     * renders as an empty picker instead of a 500.
     */
    public static function aiAssistants(int $workspaceId): \Illuminate\Support\Collection
    {
        if (! self::hasCore('ai_training')) {
            return collect();
        }

        return \App\Models\AiChatAssistant::where('workspace_id', $workspaceId)
            ->orderBy('name')->get(['id', 'name']);
    }

    /**
     * One assistant, scoped to the workspace so a stashed meta_json id cannot
     * reach across tenants. Null when absent — callers already skip the
     * knowledge-base injection on null.
     */
    public static function aiAssistant(int $workspaceId, int $id): ?object
    {
        if (! self::hasCore('ai_training') || $id <= 0) {
            return null;
        }

        return \App\Models\AiChatAssistant::where('workspace_id', $workspaceId)
            ->where('id', $id)->first();
    }

    /**
     * Contact merge-tag attributes, shared with the WhatsApp side so a tag
     * typed in an IG template resolves identically. Standalone has no such
     * table yet, so the attributes page lists nothing rather than failing.
     */
    public static function contactAttributes(int $workspaceId): \Illuminate\Support\Collection
    {
        if (! self::hasCore('attributes')) {
            return collect();
        }

        return \App\Models\Attribute::where('workspace_id', $workspaceId)
            ->orderBy('attribute_name')->get();
    }

    /**
     * Translate on demand. Null means "no translation available", which is
     * exactly what the host's Translator already returns when no provider is
     * configured — so the caller's existing null branch covers both.
     */
    public static function translate(string $text, string $from, string $to): ?string
    {
        if (! self::hasCore('translator')) {
            return null;
        }

        return \App\Services\Translator::translate($text, $from, $to);
    }
}

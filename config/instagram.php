<?php

/**
 * Instagram extension configuration.
 *
 * This package ships as TWO products from one codebase:
 *
 *   - as a WaDesk extension, where entitlement comes from WaDesk's plans and
 *     packages (a workspace's plan decides which Instagram features it gets);
 *   - as a standalone Instagram product, which has no WaDesk plans at all and
 *     must not pretend to.
 *
 * Everything below exists so the SAME controllers, routes and views serve both
 * without a single `if (wadesk)` scattered through the feature code. The only
 * place that knows the difference is InstagramGate.
 */
return [

    /*
     |----------------------------------------------------------------------
     | Mode
     |----------------------------------------------------------------------
     |
     | 'auto'       detect it — WaDesk if this is running inside a WaDesk
     |              install, standalone otherwise. Right for both shipped
     |              products, which is why it is the default.
     | 'wadesk'     force plan/package gating.
     | 'standalone' force no plan gating.
     |
     | Forcing is for the awkward middle case: a WaDesk install where the
     | operator sells Instagram to everyone and does not want it plan-gated.
     */
    'mode' => env('INSTAGRAM_MODE', 'auto'),

    /*
     |----------------------------------------------------------------------
     | Standalone entitlements
     |----------------------------------------------------------------------
     |
     | Used ONLY in standalone mode. The standalone product is sold whole, so
     | every feature is on and limits are unlimited unless the operator caps
     | them here. null means unlimited — deliberately distinct from 0, which
     | would mean "none allowed".
     */
    'standalone' => [
        'features_all_enabled' => true,

        // Named exceptions, if the operator wants to sell tiers themselves.
        // e.g. 'broadcast' => false
        'features' => [
            // Boost/ads is not a tier decision — it drives WaDesk's Meta Ads
            // module (ad accounts, funding source, MetaGraphClient), which the
            // standalone product does not ship. Advertising it and then
            // erroring on submit is worse than not showing it at all.
            'ads' => false,
        ],

        'limits' => [
            'instagram_accounts'          => null,
            'instagram_monthly_dms'       => null,
            'instagram_monthly_broadcast' => null,
            'instagram_scheduled_posts'   => null,
            'instagram_automations'       => null,
            'instagram_templates'         => null,
        ],
    ],

    /*
     |----------------------------------------------------------------------
     | Platform settings
     |----------------------------------------------------------------------
     |
     | Read through InstagramGate::setting(). In WaDesk these same keys come
     | from the admin-editable SystemSetting table and NOTHING below is
     | consulted — the operator sets them at /admin/settings/instagram and
     | expects the UI, not a deploy, to be the source of truth.
     |
     | Standalone has no such admin page, so .env is the store. Key names are
     | deliberately identical on both sides so no call site needs to know
     | which one answered.
     |
     | Every default is '' rather than a placeholder: InstagramGate::setting()
     | treats '' as "not configured" and falls back to the caller's own
     | default, which is where the real defaults (graph version, login type)
     | already live next to the code that depends on them.
     */
    'settings' => [
        'instagram_enabled'              => env('INSTAGRAM_ENABLED', true),
        'instagram_app_id'               => env('INSTAGRAM_APP_ID', ''),
        'instagram_app_secret'           => env('INSTAGRAM_APP_SECRET', ''),
        'instagram_config_id'            => env('INSTAGRAM_CONFIG_ID', ''),

        // Instagram-Login (graph.instagram.com) uses its own app credentials.
        // Blank falls back to the Facebook-Login pair above, matching how the
        // connect flow already reads them.
        'instagram_ig_app_id'            => env('INSTAGRAM_IG_APP_ID', ''),
        'instagram_ig_app_secret'        => env('INSTAGRAM_IG_APP_SECRET', ''),

        'instagram_login_type'           => env('INSTAGRAM_LOGIN_TYPE', 'facebook'),
        'instagram_webhook_verify_token' => env('INSTAGRAM_WEBHOOK_VERIFY_TOKEN', ''),
        'instagram_graph_version'        => env('INSTAGRAM_GRAPH_VERSION', ''),
        'instagram_giphy_key'            => env('INSTAGRAM_GIPHY_KEY', ''),

        // WaDesk connection (standalone IgDesk → WaDesk unified inbox). Env
        // fallbacks only: the live values are normally generated/set at runtime
        // on the "Connect WaDesk" page and stored in storage/app/instaflow-connection.json
        // (see App\Services\Instagram\WadeskLink).
        'instagram_wadesk_url'           => env('INSTAGRAM_WADESK_URL', ''),
        'instagram_wadesk_secret'        => env('INSTAGRAM_WADESK_SECRET', ''),
        'instagram_wadesk_workspace_id'  => env('INSTAGRAM_WADESK_WORKSPACE_ID', 1),
        'instagram_wadesk_push_enabled'  => env('INSTAGRAM_WADESK_PUSH', true),
    ],

    /*
     |----------------------------------------------------------------------
     | Node engine bridge
     |----------------------------------------------------------------------
     |
     | Only consulted when the host does not define wd_node_url()/node_token().
     | Inside WaDesk those helpers win, so an admin who moves the Node URL in
     | the UI moves it for Instagram too — see InstagramGate::nodeUrl().
     |
     | Empty url or token disables the bridge, and IgFlowNodeBridge falls back
     | to the in-process PHP runner. That is a supported way to run standalone,
     | not a broken one: flows still execute, timed Waits just park in the DB.
     */
    'node' => [
        'url'   => env('SERVER_URL', ''),
        'token' => env('NODE_WEBHOOK_TOKEN', ''),
    ],

];

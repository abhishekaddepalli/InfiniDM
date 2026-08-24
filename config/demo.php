<?php

/*
|--------------------------------------------------------------------------
| Demo lock
|--------------------------------------------------------------------------
|
| When DEMO_MODE=true, the DemoMode middleware blocks WRITE requests
| (POST/PUT/PATCH/DELETE) to sensitive endpoints — password changes, admin
| settings, credentials, and destructive deletes — so a public demo can't be
| broken. Everything else (creating flows, sending messages, scheduling posts,
| logging in/out) stays fully usable. Matching is by route NAME first, then
| request PATH; either wins. Str::is() wildcards ('*') apply.
|
*/

return [

    'enabled' => filter_var(env('DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN),

    'message' => env('DEMO_MESSAGE', 'This action is disabled in the demo.'),

    // Route names ALWAYS allowed even if a broad block pattern would catch them.
    'allow_names' => [
        'login', 'logout', 'locale.update',
    ],

    // Blocked route-name patterns (write methods only).
    'block_names' => [
        // Auth / password
        'password.update', 'password.email',
        // Admin — settings, credentials, config
        'admin.settings', 'admin.settings.*',
        'admin.site', 'admin.site.*',
        'admin.security', 'admin.security.*',
        'admin.api-keys.*',
        'admin.payment-gateways.*',
        'admin.currencies.*', 'admin.languages.*',
        'admin.translation*',
        'admin.billing', 'admin.billing.*',
        'admin.front', 'admin.front.*',
        'admin.mail*',
        // Admin — people / plans / destructive
        'admin.users.*', 'admin.workspaces.*', 'admin.roles.*',
        'admin.packages.*', 'admin.coupons.*', 'admin.credit-packages.*',
        'admin.pricing-faqs.*', 'admin.announcements.*', 'admin.flow-templates.*',
    ],

    // Blocked PATH patterns (write methods only) — catches things whose route
    // name we don't know, plus anything containing "password".
    'block_paths' => [
        '*password*',       // change / reset / forgot password anywhere
        '*disconnect*',     // disconnecting a connected Instagram account
        'install', 'install/*',
        'admin/settings*', 'admin/site-settings*', 'admin/security*',
        'admin/api-keys*', 'admin/payment-gateways*', 'admin/currencies*',
        'admin/languages*', 'admin/front*',
    ],
];

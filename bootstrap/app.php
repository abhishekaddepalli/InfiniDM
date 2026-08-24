<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
|--------------------------------------------------------------------------
| Instaflow — standalone application bootstrap
|--------------------------------------------------------------------------
|
| The SAME feature code runs in two places: bolted into WaDesk as an
| extension, and here as its own product. Only this file differs — inside
| WaDesk the host's bootstrap owns the middleware stack and this file is
| never shipped (build.py excludes standalone/ from the addon archive).
|
| routes/instagram.php is the one route file both modes share. In WaDesk it
| is required by the host's bootstrap via extension.json's "routes" key; here
| it is mounted directly. Because it is loaded rather than duplicated, a route
| added for one mode exists in the other automatically.
|
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            // The shared feature routes. Guarded so a partial checkout gives a
            // clear boot rather than a confusing 404 on every Instagram page.
            $shared = __DIR__ . '/../routes/instagram.php';
            if (is_file($shared)) {
                // Mounted with NO outer middleware. Nested groups MERGE their
                // stacks rather than replace them, so wrapping this in 'web'
                // would push StartSession + CSRF onto the file's Node-bridge
                // 'api' group. Node authenticates with X-Node-Token and sends
                // no CSRF token, so every claim/result POST would 419 and jobs
                // would silently never complete. Each group in the file already
                // declares the stack it needs.
                Illuminate\Support\Facades\Route::group([], $shared);
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Meta posts webhooks with no CSRF token, and signature verification
        // (not a session token) is what authenticates them. The exemption that
        // actually runs lives in routes/instagram.php, which drops CSRF
        // per-route via withoutMiddleware() so the extension stays
        // self-contained; this global list is a second net for the day that
        // group is restructured. URIs must match the real routes, not the
        // route names — add any new webhook path to both places.
        $middleware->validateCsrfTokens(except: [
            'install',
            'install/*',
            'webhooks/instagram',
            'webhooks/instagram-pay',
            // Developer code-push endpoint (WdSyncController): a machine-to-machine
            // POST with no cookie, authed by X-Sync-Key. INERT unless WD_SYNC_KEY
            // is set in .env, so exempting it here is safe when the key is unset.
            'wd-sync',
        ]);

        $middleware->alias([
            // Feature gating. In standalone every feature is on (config
            // instagram.standalone entitlements); in WaDesk the same class
            // defers to the host's plan limits. One middleware, two modes.
            'instagram.feature' => App\Http\Middleware\InstagramFeature::class,

            // SaaS admin gate. Named 'admin.gate' NOT 'admin' on purpose: the
            // shared routes/instagram.php keys WaDesk-only behaviour off an
            // 'admin' alias (with an 'ip.allowlist' partner this build lacks),
            // so registering 'admin' here would activate routes that then 500.
            'admin.gate' => App\Http\Middleware\EnsureAdmin::class,
        ]);

        // Until storage/installed.lock exists, every web request is sent to the
        // /install wizard (and afterwards kept out of it). Appended to the web
        // group only, so the Node-bridge api routes are never redirected.
        $middleware->web(append: [
            App\Http\Middleware\EnsureInstalled::class,
            App\Http\Middleware\MaintenanceMode::class,
            App\Http\Middleware\SetLocale::class,
            // Demo lock — blocks sensitive writes (password / admin settings /
            // credentials / destructive deletes) when DEMO_MODE=true. No-op
            // otherwise. See config/demo.php.
            App\Http\Middleware\DemoMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Before the app is installed there is no database, so almost any request
        // throws (session store, config, a model call) BEFORE EnsureInstalled can
        // redirect. Rather than show a raw 500, send the visitor to the installer —
        // that is the only thing they can usefully do on a fresh upload.
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (\App\Http\Controllers\InstallController::isInstalled()) {
                return null; // installed — fall through to normal error handling
            }
            // Don't loop the installer or its assets/health check onto themselves.
            if ($request->is('install', 'install/*', 'up', 'build/*', 'extensions/*', 'storage/*', 'css/*', 'js/*', 'favicon.ico', 'robots.txt')) {
                return null;
            }
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => 'not_installed', 'redirect' => url('/install')], 503);
            }
            return redirect('/install');
        });
    })->create();

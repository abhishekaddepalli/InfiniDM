<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Standalone-only service provider.
 *
 * Inside WaDesk the host application owns bootstrapping and this file is never
 * shipped (build.py excludes standalone/ from the addon archive). Everything
 * here is therefore safe to assume is standalone-specific.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // MySQL < 5.7.7 / MariaDB < 10.2.2 cap index keys at 767 bytes, which a
        // utf8mb4 VARCHAR(255) unique index exceeds. Shared hosts still run
        // these, and the failure only shows up mid-migration.
        Schema::defaultStringLength(191);

        if (\App\Http\Controllers\InstallController::isInstalled()) {
            // Apply admin-saved SMTP settings onto the live mail config so every
            // mailable sends through them without an .env edit or restart.
            \App\Support\MailConfig::apply();

            // Enforce the admin Security-page policies that are config-driven.
            // Wrapped so a missing settings table (fresh install / pre-migration)
            // never fatals the whole app during boot.
            try {
                if (Schema::hasTable('settings')) {
                    // Session timeout — push the admin-saved minutes into live config
                    // so the session guard actually expires at that window.
                    $sessMins = (int) \App\Models\Setting::get('session_lifetime_minutes', 0);
                    if ($sessMins > 0) {
                        config(['session.lifetime' => $sessMins]);
                    }
                    // Force HTTPS when the admin turns it on (in addition to the
                    // production/APP_URL heuristic below).
                    if ((bool) \App\Models\Setting::get('force_https', false)) {
                        URL::forceScheme('https');
                    }
                }
            } catch (\Throwable $e) {
                // Boot must never hard-fail on an optional policy read.
            }
        }

        // Meta requires HTTPS callback + webhook URLs. Behind a proxy or load
        // balancer Laravel can generate http:// links, which Meta then rejects
        // with an unhelpful error, so force the scheme in production — but ONLY
        // when the configured APP_URL is itself https. Otherwise a local
        // `php artisan serve` run (plain HTTP) would be redirected to https and
        // the dev server, which speaks no TLS, floods with "Unsupported SSL
        // request". Production behind real SSL sets APP_URL=https and is unaffected.
        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Subfolder deploy fix (e.g. https://templatecookies.com/instaflow/public).
        // Behind that base path Laravel's url()/route()/asset() otherwise generate
        // ROOT-absolute links (…/instagram/callback instead of
        // …/instaflow/public/instagram/callback), so the OAuth redirect_uri, the
        // webhook URL, and every fetch the inbox JS builds from <meta app-base>
        // 404. Forcing the root URL to the configured APP_URL — which already
        // carries the base path — makes all generated URLs include it. Guard on a
        // non-empty, absolute APP_URL so a bare `artisan serve` run is untouched.
        $appUrl = (string) config('app.url');
        if ($appUrl !== '' && (str_starts_with($appUrl, 'http://') || str_starts_with($appUrl, 'https://'))) {
            URL::forceRootUrl($appUrl);
            config(['filesystems.disks.public.url' => rtrim($appUrl, '/') . '/storage']);
        }
    }
}

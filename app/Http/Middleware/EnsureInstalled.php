<?php

namespace App\Http\Middleware;

use App\Http\Controllers\InstallController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until storage/installed.lock exists, send every web request to the /install
 * wizard (and, once installed, keep anyone from re-opening it). Static assets,
 * the health check and the installer itself are exempt so the wizard can render
 * and post to itself before the database exists.
 *
 * Standalone only — registered from standalone/bootstrap/app.php, which the
 * WaDesk addon build never ships. WaDesk has its own installed host.
 */
class EnsureInstalled
{
    /** Paths that must work before installation completes. */
    private const EXEMPT = [
        'install', 'install/*',
        'up',                    // health check
        'build/*', 'extensions/*', 'storage/*', 'css/*', 'js/*',
        'favicon.ico', 'robots.txt',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $installed = InstallController::isInstalled();

        if (! $installed && ! $request->is(self::EXEMPT)) {
            return redirect('/install');
        }

        // Nothing to install anymore — don't let the wizard run twice.
        if ($installed && $request->is('install', 'install/*')) {
            return redirect('/login');
        }

        return $next($request);
    }
}

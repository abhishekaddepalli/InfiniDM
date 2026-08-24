<?php

namespace App\Http\Middleware;

use App\Support\LocaleSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale, in priority order:
 *   1. The signed-in user's saved choice (users.locale)
 *   2. A session pick (header dropdown, before it's saved / for guests)
 *   3. The platform default (the language flagged default in /admin/languages)
 *   4. config('app.locale') → 'en'
 *
 * Wraps everything in try/catch so a fresh install with no languages table
 * (or no locale column) never 500s — it just keeps the fallback.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = LocaleSettings::defaultLocale();

        try {
            $user = Auth::user();

            // 1. Per-user saved choice.
            if ($user && ! empty($user->locale) && LocaleSettings::isAvailable((string) $user->locale)) {
                $locale = (string) $user->locale;
            }
            // 2. Session pick (guests, or before a signed-in user's row is written).
            elseif ($request->hasSession()) {
                $sess = (string) $request->session()->get('app_locale', '');
                if ($sess !== '' && LocaleSettings::isAvailable($sess)) {
                    $locale = $sess;
                }
            }
        } catch (\Throwable $e) {
            // Schema missing or any startup-time failure — keep the fallback.
        }

        app()->setLocale($locale);

        return $next($request);
    }
}

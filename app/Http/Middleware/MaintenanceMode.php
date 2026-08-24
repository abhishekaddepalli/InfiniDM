<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the admin "Maintenance mode" toggle (settings → maintenance_mode).
 *
 * When ON, every non-admin page shows a "be right back" placeholder — but the
 * admin area, the auth routes (so an admin can still sign in), and the machine
 * endpoints (webhooks / node bridge) stay reachable. Admin users bypass it
 * entirely. Off (default) = no effect.
 */
class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $on = (bool) \App\Models\Setting::get('maintenance_mode', false);
        } catch (\Throwable $e) {
            $on = false;
        }

        if (! $on) {
            return $next($request);
        }

        // Admins always get through (so they can turn it back off).
        $user = $request->user();
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        // Paths that must stay open even during maintenance: the admin area, the
        // auth flow (login/logout/register/password reset), and non-browser
        // endpoints (webhooks, node bridge, wd-sync, health).
        $open = [
            'admin', 'admin/*',
            'login', 'logout', 'register',
            'forgot-password', 'reset-password', 'reset-password/*',
            'webhooks/*', 'api/*', 'instagram/webhook', 'instagram/webhook/*',
            'wd-sync', 'up', 'ig-avatar/*',
        ];
        if ($request->is($open)) {
            return $next($request);
        }

        return response()->view('errors.maintenance', [
            'brand' => function_exists('brand_name') ? brand_name() : 'App',
        ], 503);
    }
}

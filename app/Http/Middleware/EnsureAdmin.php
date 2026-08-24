<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the SaaS admin area.
 *
 * 404, not 403, for a signed-in non-admin — the admin URLs should not even
 * announce that they exist to a customer. A guest is bounced to login (the auth
 * middleware runs first in the group, so by here the user is present).
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            abort(404);
        }

        // Admin IP allowlist (Security page). When a list is configured, only
        // those IPs / CIDR ranges may reach the admin area. Empty = no gate, so
        // this never accidentally locks anyone out until it is deliberately set.
        try {
            $raw = (string) \App\Models\Setting::get('allowed_admin_ips', '');
            if (trim($raw) !== '') {
                $allow = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $raw) ?: [])));
                if (! empty($allow) && ! \Symfony\Component\HttpFoundation\IpUtils::checkIp((string) $request->ip(), $allow)) {
                    abort(404);
                }
            }
        } catch (\Throwable $e) {
            // A malformed list must never hard-block the admin area.
        }

        return $next($request);
    }
}

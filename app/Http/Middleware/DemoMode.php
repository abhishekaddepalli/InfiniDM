<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Demo lock. When config('demo.enabled') is true, blocks WRITE requests to
 * sensitive endpoints (password changes, admin settings, credentials,
 * destructive deletes) so a public demo can't be tampered with. Read (GET) and
 * all non-sensitive writes pass through untouched. Config-driven — see
 * config/demo.php. Off by default (DEMO_MODE=false), so production is unaffected.
 */
class DemoMode
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('demo.enabled')) {
            return $next($request);
        }

        // Only writes can mutate state; reads are always fine.
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $name = optional($request->route())->getName() ?? '';
        $path = trim($request->path(), '/');

        // Explicit allow-list wins (e.g. login / logout).
        foreach ((array) config('demo.allow_names', []) as $p) {
            if ($name !== '' && Str::is($p, $name)) {
                return $next($request);
            }
        }

        $blocked = false;
        foreach ((array) config('demo.block_names', []) as $p) {
            if ($name !== '' && Str::is($p, $name)) { $blocked = true; break; }
        }
        if (! $blocked) {
            foreach ((array) config('demo.block_paths', []) as $p) {
                if (Str::is($p, $path)) { $blocked = true; break; }
            }
        }

        if (! $blocked) {
            return $next($request);
        }

        $message = (string) config('demo.message', 'This action is disabled in the demo.');

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'demo' => true, 'message' => $message], 403);
        }

        return back()->with('error', $message);
    }
}

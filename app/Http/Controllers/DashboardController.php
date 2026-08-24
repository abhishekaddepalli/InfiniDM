<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Standalone landing routes.
 *
 * IgDesk has exactly one dashboard — the Instagram one, assembled by the
 * shared InstagramController::dashboard(). This class holds no view logic and
 * is not meant to grow any: duplicating that page's data gathering to serve a
 * second URL is how the two copies start to drift.
 *
 * It exists so `/` and `/dashboard` resolve to controller actions instead of
 * route Closures. `route:cache` refuses to serialize a Closure, and a
 * production install that cannot cache its route table pays for it on every
 * request.
 *
 * Deliberately extends nothing. App\Http\Controllers\Controller is a core
 * WaDesk class that does not ship in the shared tree, and a base class this
 * controller does not use is not worth the dependency.
 */
class DashboardController
{
    /**
     * GET /
     *
     * Standalone has no marketing page, so `/` only ever bounces. It has to
     * answer for both auth states: Laravel's `guest` middleware redirects an
     * already-signed-in visitor here, and sending them back to /login would
     * loop.
     */
    public function home(): RedirectResponse
    {
        return auth()->check()
            ? redirect()->to('/instagram')
            : redirect()->route('login');
    }

    /**
     * GET /dashboard
     *
     * Two things point here that this product cannot change: the shared rail's
     * "Back to dashboard" arrow, which is a WaDesk concept with nowhere to go
     * standalone, and Laravel's own post-authentication convention.
     *
     * Redirecting rather than rendering keeps /instagram the single canonical
     * dashboard URL, so a bookmark, the rail's brand mark and this route all
     * end up in the same place.
     */
    public function index(): RedirectResponse
    {
        return redirect()->to('/instagram');
    }
}

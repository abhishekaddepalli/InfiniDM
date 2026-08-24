<?php

namespace App\Http\Middleware;

use App\Services\Instagram\InstagramGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level Instagram feature gate.
 *
 *   ->middleware(InstagramFeature::class . ':inbox')
 *   ->middleware(InstagramFeature::class . ':commerce,orders')
 *
 * Referenced by class name rather than a registered alias on purpose: an
 * extension cannot edit bootstrap/app.php to register one, and the standalone
 * product would need its own registration anyway. FQCN works in both with no
 * setup at all.
 *
 * In standalone mode InstagramGate lets everything through, so these same
 * annotations are harmless there — one route file, two products.
 *
 * Rejection deliberately mirrors WaDesk's EnforcePlanFeature so the paywall
 * behaves identically whichever gate blocked it:
 *   - mutations are blocked hard, server-side, even if the UI is bypassed;
 *   - safe GETs still render, with the paywall overlay shared into the view.
 */
class InstagramFeature
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        foreach ($features as $feature) {
            $feature = trim($feature);
            if ($feature === '') {
                continue;
            }
            if (InstagramGate::allows($feature)) {
                continue;
            }

            $label = InstagramGate::label($feature);

            if (! $request->isMethodSafe()) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'ok'      => false,
                        'error'   => 'plan_feature_disabled',
                        'feature' => 'instagram.' . $feature,
                        'message' => "Your plan doesn't include {$label}. Upgrade to unlock this feature.",
                    ], 403);
                }

                return back()->with('warning', "Your plan doesn't include {$label}. Upgrade to unlock this feature.");
            }

            // Safe GET on a locked feature. Standalone (Instaflow) sends the user
            // straight to the pricing page — the operator asked for a clean
            // "upgrade to reach this" redirect rather than a half-rendered page
            // behind an overlay. WaDesk keeps its shared paywall overlay so its
            // existing bottom-sheet UX is untouched.
            if (! InstagramGate::isWaDesk()
                && \Illuminate\Support\Facades\Route::has('instagram.plans')) {
                return redirect()->route('instagram.plans')
                    ->with('warning', "Your plan doesn't include {$label}. Upgrade to unlock this feature.");
            }

            \Illuminate\Support\Facades\View::share('planPaywall', [
                'feature' => 'instagram.' . $feature,
                'label'   => $label,
            ]);

            return $next($request);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * User-facing plan picker for IgDesk — mounted at /instagram/plans.
 *
 * Ported from WaDesk's in-app pricing page (pricing/index.blade.php) and
 * recoloured to the Instagram theme. Consumes the enriched Package (plan_amount /
 * offer_price / plan_unit / plan_duration / is_highlighted / free /
 * is_custom_quote / chargeableAmount()) exactly as WaDesk does.
 *
 * IgDesk has a single billing interval per plan and no add-on catalogue, so
 * WaDesk's monthly/yearly toggle, add-on section and featureCatalog() comparison
 * table are dropped — they have no data to drive them. Each card's "Choose" CTA
 * hands off to the one-time checkout flow (CheckoutController).
 */
class PricingController extends Controller
{
    public function index(Request $request): View
    {
        // Every active plan, in the operator's chosen order. `plan_amount`/`price`
        // break ties so equal-sort rows read cheapest-first, matching WaDesk.
        $packages = Package::active()->plans()
            ->orderBy('sort')
            ->orderBy('plan_amount')
            ->orderBy('price')
            ->get();

        $user = auth()->user();

        // Current plan → "Current plan" pill + Upgrade-vs-Choose relabelling.
        $currentPackageId = $user?->package_id;
        $currentPackage   = $currentPackageId ? Package::find($currentPackageId) : null;
        // Stable tier ranking for the upgrade/downgrade copy (own-currency amount).
        $currentPlanAmount = $currentPackage ? (float) $currentPackage->chargeableAmount() : null;

        // Plan-window banner: plan_ends_at drives active/expired; trial_ends_at the
        // trial notice. Set on the User (there is no Workspace model in IgDesk).
        $currentPlanEndsAt = $user?->plan_ends_at;                 // Carbon|null
        $planExpired       = (bool) ($currentPlanEndsAt && $currentPlanEndsAt->isPast());
        $trialEndsAt       = ($user?->trial_ends_at && $user->trial_ends_at->isFuture() && ! $currentPlanEndsAt)
            ? $user->trial_ends_at
            : null;

        // Platform default currency — only used as the fallback label when a plan
        // carries no currency of its own. Each card prices in its OWN currency.
        $currency = strtoupper((string) Setting::get('default_currency', 'USD'));

        return view('instagram.plans', compact(
            'packages',
            'currentPackageId',
            'currentPackage',
            'currentPlanAmount',
            'currency',
            'currentPlanEndsAt',
            'planExpired',
            'trialEndsAt',
        ));
    }
}

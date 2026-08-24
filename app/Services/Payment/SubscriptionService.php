<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owns the recurring-subscription lifecycle. The gateway is the clock — it
 * charges the customer every cycle and POSTs a renewal webhook; this service
 * turns that webhook into a plan extension. There is NO Laravel scheduler in
 * the loop: renewals are entirely webhook-driven.
 *
 * INSTAFLOW ADAPTATION: WaDesk stored the plan window on Workspace::package()
 * / workspace.plan_ends_at. IgDesk ships no Workspace model, so the plan
 * window lives on the USER: users.package_id + users.plan_ends_at. Every
 * renewal here does one job: push the buyer's plan_ends_at one more period
 * forward. A later phase's gate reads users.plan_ends_at to decide whether the
 * paid plan is still active (falling back to the default/free plan once it
 * passes).
 */
class SubscriptionService
{
    /**
     * Create (or reuse) the pending Subscription row when a recurring checkout
     * starts. We may not know the gateway's subscription id yet (Stripe mints
     * it during checkout), so it's filled in later by activate()/the webhook.
     */
    public function recordPending(Order $order, string $gateway, array $attrs = []): Subscription
    {
        $interval = $this->cycleFor($order->package);

        // Reuse an existing pending row for this order if process() is retried.
        $sub = Subscription::query()
            ->where('workspace_id', $order->workspace_id)
            ->where('gateway', $gateway)
            ->where('status', Subscription::STATUS_PENDING)
            ->where('meta->order_id', (string) $order->id)
            ->first();

        $data = array_merge([
            'workspace_id'  => $order->workspace_id,
            'user_id'       => $order->user_id,
            'package_id'    => $order->package_id,
            'plan_id'       => (string) $order->package_id,
            'gateway'       => $gateway,
            'billing_cycle' => $interval,
            'status'        => Subscription::STATUS_PENDING,
            'amount'        => $order->amount,
            'currency'      => $order->currency,
            'meta'          => ['order_id' => (string) $order->id, 'order_number' => $order->order_number],
        ], array_filter($attrs, fn ($v) => $v !== null));

        if ($sub) {
            $sub->fill($data)->save();
            return $sub;
        }
        return Subscription::create($data);
    }

    /**
     * First successful charge confirmed (via callback or the create webhook).
     * Mark the subscription active, store the gateway ids, and make sure the
     * buyer's plan window covers at least this first period.
     *
     * @param  array  $attrs  gateway_subscription_id / gateway_customer_id /
     *                        gateway_plan_id / current_period_end (Carbon|unix)
     */
    public function activate(Subscription $sub, array $attrs = []): Subscription
    {
        $periodEnd = $this->normalizePeriodEnd($attrs['current_period_end'] ?? null);
        unset($attrs['current_period_end']);

        $sub->fill(array_filter($attrs, fn ($v) => $v !== null));
        $sub->status = Subscription::STATUS_ACTIVE;
        if ($periodEnd) {
            $sub->current_period_end = $periodEnd;
        }
        $sub->save();

        // The checkout's order finalisation already set plan_ends_at for the
        // first period; align the subscription's period_end and extend only if
        // the gateway told us a concrete date that's further out.
        $this->syncUserWindow($sub, $periodEnd, extend: false);

        return $sub;
    }

    /**
     * A renewal charge succeeded — push the buyer's plan one more cycle. If the
     * gateway handed us an explicit period end we trust it; otherwise we add one
     * package cycle to the later of (now, current plan_ends_at) so a slightly-
     * early webhook never shortens the plan.
     */
    public function renew(Subscription $sub, $periodEnd = null, array $attrs = []): Subscription
    {
        $periodEnd = $this->normalizePeriodEnd($periodEnd);

        $sub->fill(array_filter($attrs, fn ($v) => $v !== null));
        $sub->status = Subscription::STATUS_ACTIVE;
        $sub->renewals_count = (int) $sub->renewals_count + 1;
        if ($periodEnd) {
            $sub->current_period_end = $periodEnd;
        }
        $sub->save();

        $this->syncUserWindow($sub, $periodEnd, extend: true);

        Log::info('[SUBSCRIPTION] renewed', [
            'subscription_id'         => $sub->id,
            'gateway'                 => $sub->gateway,
            'gateway_subscription_id' => $sub->gateway_subscription_id,
            'user_id'                 => $sub->user_id,
            'renewals'                => $sub->renewals_count,
        ]);

        return $sub;
    }

    /** Customer/admin canceled at the gateway — stop auto-renew. The plan keeps
     *  running until the already-paid period_end (the plan gate expires it
     *  naturally), so we DON'T clear plan_ends_at here. */
    public function cancel(Subscription $sub): Subscription
    {
        $sub->status = Subscription::STATUS_CANCELED;
        $sub->canceled_at = now();
        $sub->save();

        Log::info('[SUBSCRIPTION] canceled', [
            'subscription_id' => $sub->id,
            'gateway'         => $sub->gateway,
            'user_id'         => $sub->user_id,
        ]);
        return $sub;
    }

    /** A renewal charge failed (card declined, etc.). Flag past_due but leave
     *  the current paid period intact — the gateway will retry, and only a real
     *  cancel/expiry should downgrade the plan. */
    public function markPastDue(Subscription $sub): Subscription
    {
        $sub->status = Subscription::STATUS_PAST_DUE;
        $sub->save();

        Log::warning('[SUBSCRIPTION] payment failed (past_due)', [
            'subscription_id' => $sub->id,
            'gateway'         => $sub->gateway,
            'user_id'         => $sub->user_id,
        ]);
        return $sub;
    }

    // ── internals ────────────────────────────────────────────────────

    /**
     * Move the buyer's plan_ends_at forward. When $extend is true we add one
     * package cycle (or jump to the gateway's period_end if it's further out).
     * When false (activation) we only stretch the window if the gateway's
     * period_end is beyond what the checkout already set — never shorten it.
     */
    private function syncUserWindow(Subscription $sub, ?Carbon $periodEnd, bool $extend): void
    {
        $user = User::find($sub->user_id);
        if (! $user) {
            return;
        }

        $currentEnd = ($user->plan_ends_at && $user->plan_ends_at->isFuture())
            ? $user->plan_ends_at->copy()
            : now();

        if ($periodEnd) {
            // Trust the gateway's date, but never roll backwards.
            $newEnd = $periodEnd->greaterThan($currentEnd) ? $periodEnd : $currentEnd;
        } elseif ($extend) {
            $newEnd = $this->addCycle($currentEnd, $sub->package ?: Package::find($sub->package_id));
        } else {
            return; // activation with no concrete date — checkout already handled it
        }

        DB::transaction(function () use ($user, $sub, $newEnd) {
            $user->forceFill([
                'package_id'    => $sub->package_id ?: $user->package_id,
                'trial_ends_at' => null,
                'plan_ends_at'  => $newEnd,
            ])->save();
        });
    }

    /** Add one package billing cycle to a base date (mirrors the checkout finaliser). */
    private function addCycle(Carbon $base, ?Package $pkg): Carbon
    {
        $dur  = max(1, (int) ($pkg->plan_duration ?? 1));
        $unit = strtolower((string) ($pkg->plan_unit ?? 'months'));
        return match ($unit) {
            'days', 'day'   => $base->copy()->addDays($dur),
            'weeks', 'week' => $base->copy()->addWeeks($dur),
            'years', 'year' => $base->copy()->addYears($dur),
            default         => $base->copy()->addMonths($dur),
        };
    }

    /** monthly | yearly label from the package cycle, for storage + UI. */
    private function cycleFor(?Package $pkg): string
    {
        $unit = strtolower((string) ($pkg->plan_unit ?? 'months'));
        return in_array($unit, ['years', 'year'], true) ? 'yearly' : 'monthly';
    }

    /** Accept a Carbon, a unix timestamp, or an ISO string; return Carbon|null. */
    private function normalizePeriodEnd($value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::createFromTimestamp((int) $value);
            }
            return Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

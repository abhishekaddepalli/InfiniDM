<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Package;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\PaymentResult;
use App\Support\FormatSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-time plan checkout for IgDesk.
 *
 *   GET  /checkout/{package}          → order summary + gateway picker
 *   POST /checkout/{package}          → create a pending Order, kick the gateway
 *   *    /payment/callback/{gateway}  → gateway returns the buyer here; verify + activate
 *   GET  /checkout/{order}/cancel     → buyer bailed; mark the order failed
 *
 * Ported from WaDesk's CheckoutController with ALL recurring / subscription
 * logic stripped: IgDesk sells a plan for a single period only. On a paid
 * order we set users.package_id + users.plan_ends_at (there is no Workspace
 * model here — the buyer IS the tenant). A free plan skips the gateway entirely
 * and activates on the spot.
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly PaymentGatewayManager $manager) {}

    /**
     * Checkout page: order summary + billing form + gateway picker. Free plans
     * render a single "Activate" button instead of a payment method list.
     */
    public function show(Request $request, Package $package)
    {
        if (! $package->is_active) abort(404);
        $this->manager->ensureCatalogRows();

        $amountBase = round($package->chargeableAmount(), 2);          // in the plan's own currency
        $isFree     = $package->free || $amountBase <= 0;
        $baseCur    = strtoupper((string) ($package->currency ?: Setting::get('default_currency', 'USD')));

        // The buyer may pay in any currency an ACTIVE gateway accepts (WaDesk
        // parity). ?currency= wins when valid; otherwise the plan's own currency.
        $available = $isFree ? [$baseCur] : $this->availableCheckoutCurrencies();
        $requested = strtoupper((string) $request->query('currency', ''));
        $currency  = ($requested && in_array($requested, $available, true)) ? $requested : $baseCur;
        if (! in_array($currency, $available, true)) { array_unshift($available, $currency); }

        // Convert the price into the chosen currency (rates from the currencies table).
        $amount = ($baseCur === $currency)
            ? $amountBase
            : round((float) FormatSettings::convert($amountBase, $baseCur, $currency), 2);

        $gateways = $isFree ? collect() : $this->manager->activeGateways($currency);

        $taxEnabled = (bool) Setting::get('tax_enabled', false);
        $taxRate    = $taxEnabled ? (float) Setting::get('tax_rate', 0) : 0.0;

        // AJAX re-price when the buyer flips the currency dropdown — updates the
        // summary + gateway list in place so typed billing fields survive.
        if ($request->boolean('ajax')) {
            return $this->pricingJson($currency, round((float) $amount, 2), $taxRate, $gateways);
        }

        $countries = ['India', 'United States', 'United Kingdom', 'United Arab Emirates',
            'Canada', 'Australia', 'Germany', 'France', 'Singapore', 'Other'];

        return view('checkout.show', [
            'package'             => $package,
            'gateways'            => $gateways,
            'currency'            => $currency,
            'amount'              => $amount,
            'isFree'              => $isFree,
            'taxEnabled'          => $taxEnabled,
            'taxRate'             => $taxRate,
            'taxLabel'            => (string) (Setting::get('tax_label', 'VAT') ?: 'VAT'),
            'countries'           => $countries,
            'refundDays'          => (int) Setting::get('refund_window_days', 14),
            'availableCurrencies' => $available,
        ]);
    }

    /**
     * Currencies the buyer may pay in: enabled currencies that at least one
     * ACTIVE gateway accepts. A gateway with an empty supported-currencies
     * whitelist accepts anything → every enabled currency is offered.
     */
    private function availableCheckoutCurrencies(): array
    {
        $enabled = [];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('currencies')) {
                $enabled = DB::table('currencies')->where('is_active', true)->orderBy('code')
                    ->pluck('code')->map(fn ($c) => strtoupper((string) $c))->filter()->unique()->values()->all();
            }
        } catch (\Throwable $e) {
        }
        if (empty($enabled)) {
            $enabled = [strtoupper((string) Setting::get('default_currency', 'USD'))];
        }

        $acceptAny = false;
        $union     = [];
        foreach (PaymentGateway::where('is_active', true)->get() as $g) {
            $list = $g->supported_currencies ?? [];
            if (empty($list)) { $acceptAny = true; continue; }
            foreach ((array) $list as $c) { $union[strtoupper((string) $c)] = true; }
        }
        if ($acceptAny || empty($union)) {
            return $enabled;
        }
        $codes = array_values(array_filter($enabled, fn ($c) => isset($union[$c])));
        return $codes ?: $enabled;
    }

    /** JSON the checkout page fetches on a live currency switch (no reload). */
    private function pricingJson(string $currency, float $amount, float $taxRate, $gateways)
    {
        $tax   = round($amount * $taxRate / 100, 2);
        $total = round($amount + $tax, 2);
        $fmt   = fn ($n) => FormatSettings::formatIn($n, $currency);

        return response()->json([
            'ok'        => true,
            'currency'  => $currency,
            'amountRaw' => $amount,
            'amountFmt' => $fmt($amount),
            'taxFmt'    => $fmt($tax),
            'totalRaw'  => $total,
            'totalFmt'  => $fmt($total),
            'gateways'  => collect($gateways)->map(fn ($g) => [
                'id' => $g->id, 'name' => $g->name, 'description' => $g->description, 'mode' => $g->mode,
            ])->values(),
        ]);
    }

    /**
     * Create a pending Order and start the one-time charge. Free plans skip the
     * gateway and activate immediately.
     */
    public function pay(Request $request, Package $package)
    {
        if (! $package->is_active) abort(404);

        $user = $request->user();
        if (! $user) abort(401);

        $amountBase = round($package->chargeableAmount(), 2);
        $isFree     = $package->free || $amountBase <= 0;
        $baseCur    = strtoupper((string) ($package->currency ?: Setting::get('default_currency', 'USD')));

        // Charge in the currency the buyer picked on the checkout page (validated
        // against what an active gateway accepts), converting from the plan's own.
        $available = $this->availableCheckoutCurrencies();
        $reqCur    = strtoupper((string) $request->input('currency', ''));
        $currency  = ($reqCur && in_array($reqCur, $available, true)) ? $reqCur : $baseCur;
        $amount    = ($baseCur === $currency) ? $amountBase
            : round((float) FormatSettings::convert($amountBase, $baseCur, $currency), 2);

        $data = $request->validate([
            'gateway_id'      => [$isFree ? 'nullable' : 'required', 'integer', 'exists:payment_gateways,id'],
            'currency'        => ['nullable', 'string', 'max:8'],
            'customer_name'   => ['nullable', 'string', 'max:191'],
            'customer_email'  => ['nullable', 'email', 'max:191'],
            'billing_company' => ['nullable', 'string', 'max:191'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_city'    => ['nullable', 'string', 'max:120'],
            'billing_postal'  => ['nullable', 'string', 'max:32'],
            'billing_country' => ['nullable', 'string', 'max:80'],
            'billing_tax_id'  => ['nullable', 'string', 'max:64'],
        ]);

        // Tax breakdown (admin toggle + rate).
        $taxEnabled = (bool) Setting::get('tax_enabled', false);
        $taxRate    = $taxEnabled ? (float) Setting::get('tax_rate', 0) : 0.0;
        $taxAmount  = round($amount * $taxRate / 100, 2);
        $total      = round($amount + $taxAmount, 2);
        $baseUsd    = round((float) FormatSettings::convert($total, $currency, 'USD'), 2);

        // ── Free plan → no gateway, activate straight away. ──
        if ($isFree) {
            $order = Order::create([
                'order_number'   => Order::generateOrderNumber(),
                'user_id'        => $user->id,
                'package_id'     => $package->id,
                'billing_period' => 'onetime',
                'currency'       => $currency,
                'amount'         => 0,
                'tax_rate'       => 0,
                'tax_amount'     => 0,
                'total_amount'   => 0,
                'base_amount_usd'=> 0,
                'status'         => 'pending',
                'customer_name'  => $data['customer_name']  ?? $user->name,
                'customer_email' => $data['customer_email'] ?? $user->email,
                'billing_company'=> $data['billing_company'] ?? null,
                'billing_address'=> $data['billing_address'] ?? null,
                'billing_city'   => $data['billing_city']    ?? null,
                'billing_postal' => $data['billing_postal']  ?? null,
                'billing_country'=> $data['billing_country'] ?? null,
                'billing_tax_id' => $data['billing_tax_id']  ?? null,
            ]);
            $this->finalizeOrder($order, PaymentResult::paid('free-' . $order->id));
            return redirect()->route('instagram.plans')->with('success', __('Your plan is now active.'));
        }

        $gateway = PaymentGateway::findOrFail($data['gateway_id']);
        if (! $gateway->is_active) return back()->with('error', __('That payment method is not currently available.'));
        if (! $gateway->acceptsCurrency($currency)) {
            return back()->with('error', $gateway->name . ' ' . __('does not support') . ' ' . $currency . '.');
        }

        $order = Order::create([
            'order_number'   => Order::generateOrderNumber(),
            'user_id'        => $user->id,
            'package_id'     => $package->id,
            'billing_period' => 'onetime',
            'gateway_id'     => $gateway->id,
            'gateway_slug'   => $gateway->slug,
            'currency'       => $currency,
            'amount'         => $total,      // what the driver charges
            'tax_rate'       => $taxRate,
            'tax_amount'     => $taxAmount,
            'total_amount'   => $total,
            'base_amount_usd'=> $baseUsd,
            'status'         => 'pending',
            'customer_name'  => $data['customer_name']  ?? $user->name,
            'customer_email' => $data['customer_email'] ?? $user->email,
            'billing_company'=> $data['billing_company'] ?? null,
            'billing_address'=> $data['billing_address'] ?? null,
            'billing_city'   => $data['billing_city']    ?? null,
            'billing_postal' => $data['billing_postal']  ?? null,
            'billing_country'=> $data['billing_country'] ?? null,
            'billing_tax_id' => $data['billing_tax_id']  ?? null,
        ]);

        $callbackUrl = route('payment.callback', ['gateway' => $gateway->slug]);
        $driver      = $this->manager->driverFromModel($gateway);

        try {
            $result = $driver->initiate($order, $callbackUrl);
        } catch (\Throwable $e) {
            Log::error('[CHECKOUT] driver initiate threw', ['err' => $e->getMessage(), 'order' => $order->id]);
            $order->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            return back()->with('error', __('Payment provider error. Try again or pick a different method.'));
        }

        if ($result->gatewayOrderId) {
            $order->update(['gateway_order_id' => $result->gatewayOrderId, 'gateway_payload' => $result->payload]);
        }

        // Some gateways confirm synchronously (offline / manual mark).
        if ($result->status === 'paid') {
            $this->finalizeOrder($order, $result);
            return redirect()->route('instagram.plans')->with('success', __('Payment received. Your plan is now active.'));
        }
        if ($result->status === 'failed') {
            $order->update(['status' => 'failed', 'failure_reason' => $result->error, 'gateway_payload' => $result->payload]);
            return back()->with('error', __('Payment failed:') . ' ' . ($result->error ?: __('unknown')));
        }

        // Pending — remember the order for the callback, then redirect off-site
        // or render the gateway's inline redirect form.
        session(['checkout_order_id' => $order->id]);
        if ($result->redirectUrl) return redirect()->away($result->redirectUrl);
        if ($result->html) return response($result->html);
        return back()->with('error', __('Payment provider did not return a usable response.'));
    }

    /**
     * Gateway return handler. Verifies the payment with the driver and, on
     * success, marks the order paid + activates the plan. One generic handler
     * dispatches by gateway slug, mirroring WaDesk.
     */
    public function callback(Request $request, string $gateway)
    {
        $gw     = PaymentGateway::query()->where('slug', $gateway)->firstOrFail();
        $driver = $this->manager->driverFromModel($gw);
        $payload = array_merge($request->query() ?: [], $request->post() ?: []);

        // Resolve the order — prefer a gateway echo, fall back to the session.
        $order = null;
        $hint = $payload['session_id']
            ?? $payload['razorpay_order_id']
            ?? $payload['token']
            ?? $payload['txnid']                 // PayU echoes our txn id
            ?? $payload['merchantTransactionId'] // PhonePe
            ?? $payload['merchantOrderId']       // Duitku (= our gateway_order_id)
            ?? $payload['order_id']
            ?? null;
        if ($hint) $order = Order::where('gateway_order_id', $hint)->first();
        // PayU cross-site POST drops the session cookie; it echoes our order id in udf2.
        if (! $order && isset($payload['udf2']) && ctype_digit((string) $payload['udf2'])) {
            $order = Order::find((int) $payload['udf2']);
        }
        if (! $order && session('checkout_order_id')) $order = Order::find(session('checkout_order_id'));
        if (! $order) return redirect()->route('instagram.plans')->with('error', __('Order not found.'));

        try {
            $result = $driver->handleCallback($payload);
        } catch (\Throwable $e) {
            Log::error('[CHECKOUT] callback threw', ['err' => $e->getMessage(), 'order' => $order->id]);
            $result = PaymentResult::failed('callback_exception: ' . $e->getMessage());
        }

        if ($result->status === 'paid') {
            $this->finalizeOrder($order, $result);
            return redirect()->route('instagram.plans')->with('success', __('Payment received. Your plan is now active.'));
        }

        $order->update([
            'status'          => 'failed',
            'failure_reason'  => $result->error,
            'gateway_payload' => array_merge((array) $order->gateway_payload, (array) $result->payload),
        ]);
        return redirect()->route('instagram.plans')->with('error', __('Payment did not complete:') . ' ' . ($result->error ?: __('unknown')));
    }

    /** Buyer cancelled at the gateway. Mark the pending order failed. */
    public function cancel(Request $request, Order $order)
    {
        if ((int) $order->user_id === (int) $request->user()?->id && $order->status === 'pending') {
            $order->update(['status' => 'failed', 'failure_reason' => 'cancelled_by_user']);
        }
        return redirect()->route('instagram.plans')->with('error', __('Checkout cancelled.'));
    }

    /**
     * Admin MANUALLY approves an offline / bank-transfer plan order — the buyer
     * paid out-of-band, so we run the SAME finalizeOrder() path the online
     * gateways use and the plan activates identically. Idempotent on a paid
     * order. Stamps who reviewed it + when.
     */
    public function markPaidManually(Order $order, ?int $reviewerId = null, ?string $reference = null, ?string $note = null): void
    {
        if ($order->status === 'paid') return;

        $order->forceFill([
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        $this->finalizeOrder($order, PaymentResult::paid(
            $reference ?: ('manual-' . $order->id),
            $order->gateway_order_id,
            ['manual' => true, 'reviewed_by' => $reviewerId],
        ));
    }

    /** Admin REJECTS a pending offline order — records the reason, marks failed. No plan applied. */
    public function markRejected(Order $order, ?int $reviewerId = null, ?string $note = null): void
    {
        if ($order->status === 'paid') return;
        $order->forceFill([
            'status'         => 'failed',
            'failure_reason' => $note ?: 'Rejected by admin',
            'reviewed_by'    => $reviewerId,
            'reviewed_at'    => now(),
            'review_note'    => $note,
        ])->save();
    }

    /**
     * Mark an order paid and apply the plan to the buyer. Idempotent — a paid
     * order no-ops. Sets users.package_id + users.plan_ends_at and lifts any
     * trial. This is the SINGLE place a plan is activated.
     */
    private function finalizeOrder(Order $order, PaymentResult $result): void
    {
        if ($order->status === 'paid') return;

        DB::transaction(function () use ($order, $result) {
            $order->update([
                'status'             => 'paid',
                'gateway_payment_id' => $result->gatewayPaymentId,
                'gateway_payload'    => array_merge((array) $order->gateway_payload, (array) $result->payload),
                'paid_at'            => now(),
                'failure_reason'     => null,
            ]);

            $user = \App\Models\User::find($order->user_id);
            $pkg  = $order->package_id ? Package::find($order->package_id) : null;
            if ($user && $pkg) {
                $user->forceFill([
                    'package_id'    => $pkg->id,
                    'trial_ends_at' => null,
                    'plan_ends_at'  => $this->planEndsFor($pkg, $user->plan_ends_at),
                ])->save();
            }

            // Increment coupon usage if one was attached (kept for parity —
            // IgDesk's simplified checkout does not apply coupons itself).
            if ($order->coupon_id) {
                \App\Models\Coupon::where('id', $order->coupon_id)->increment('uses_count');
            }
        });

        Log::info('[CHECKOUT] order paid + plan applied', [
            'order_id'   => $order->id,
            'user_id'    => $order->user_id,
            'package_id' => $order->package_id,
        ]);
    }

    /**
     * Compute the new plan window end from the package's billing cycle
     * (plan_duration × plan_unit, falling back to the native `interval`).
     * Free / custom-quote plans never expire (null). Renewals extend from the
     * later of now and the current end so paying early never loses days.
     */
    private function planEndsFor(Package $pkg, $currentEndsAt): ?\Illuminate\Support\Carbon
    {
        if ($pkg->free || $pkg->is_custom_quote) return null;

        $unit = strtolower((string) ($pkg->plan_unit ?: $pkg->interval ?: 'month'));
        $dur  = max(1, (int) ($pkg->plan_duration ?: 1));
        $base = ($currentEndsAt && $currentEndsAt->isFuture()) ? $currentEndsAt->copy() : now();

        return match (true) {
            str_contains($unit, 'year') => $base->addYears($dur),
            str_contains($unit, 'week') => $base->addWeeks($dur),
            str_contains($unit, 'day')  => $base->addDays($dur),
            default                     => $base->addMonths($dur),
        };
    }
}

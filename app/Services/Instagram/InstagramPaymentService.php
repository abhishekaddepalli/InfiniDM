<?php

namespace App\Services\Instagram;

use App\Models\InstagramOrder;
use App\Models\WaStorefront;
use App\Services\Storefront\StorefrontPaymentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Payment prompts for in-DM Instagram orders — GLOBAL, not India-only.
 *
 * Meta has no in-DM checkout API, so the buyer gets a pay link/handle in the DM.
 * We honour whatever gateway the merchant already configured on their WhatsApp
 * storefront (WaStorefront.payment_provider + payment_config_json), so the money
 * lands with THEM:
 *   - razorpay_api  → dynamic Razorpay payment link (amount-aware) + webhook auto-confirm   [India]
 *   - stripe_link   → the merchant's Stripe Payment Link                                     [global]
 *   - paypal_me     → paypal.me/<handle>/<amount><CUR> (amount-aware)                        [global]
 *   - razorpay_link → the merchant's static Razorpay link                                    [India]
 *   - upi           → upi://pay intent to the merchant's VPA (amount-aware)                  [India]
 *   - bank_transfer → the bank details the merchant entered
 * No gateway configured → null (ordering falls back to seller-confirmed).
 */
class InstagramPaymentService
{
    private const RZP_API = 'https://api.razorpay.com/v1';

    /**
     * Nullable + defaulted so the container can still build this service when
     * the host ships no storefront module. A bare `StorefrontPaymentService $sf`
     * made the class unconstructible standalone: Laravel turns the missing
     * class into a BindingResolutionException at resolve time, which the
     * ordering path caught and logged as a generic "pay prompt failed". With a
     * default available the container hands us null instead, and storefront()
     * short-circuits below.
     *
     * The default alone is not enough, though — it would break WaDesk too.
     * Container::resolveClass returns a parameter's DEFAULT instead of
     * autowiring it whenever a default exists and the class is not explicitly
     * bound, and nothing binds StorefrontPaymentService. So on a full WaDesk
     * install we would also get null, and both entry points fail SILENTLY:
     * paymentPrompt() drops every order's pay link, and applyWebhook() stops
     * marking orders paid. Hence the lazy resolve — the promoted default keeps
     * standalone constructible, this body keeps WaDesk wired.
     */
    public function __construct(private ?StorefrontPaymentService $sf = null)
    {
        if ($this->sf === null && InstagramGate::hasCore('storefront')) {
            $this->sf = app(StorefrontPaymentService::class);
        }
    }

    /** The workspace's first storefront that has a payment method configured. */
    private function storefront(int $wsId): ?WaStorefront
    {
        if (! InstagramGate::hasCore('storefront') || ! $this->sf) return null;

        return WaStorefront::where('workspace_id', $wsId)
            ->whereNotNull('payment_provider')->where('payment_provider', '!=', '')
            ->orderBy('id')->first();
    }

    /**
     * Build the payment message to send in the order-confirmation DM, using the
     * merchant's configured gateway. Returns the ready-to-send string, or null
     * when nothing is configured (caller sends the seller-confirmed line).
     */
    public function paymentPrompt(InstagramOrder $order): ?string
    {
        $store = $this->storefront((int) $order->workspace_id);
        if (! $store) return null;

        $provider = (string) $store->payment_provider;
        $cfg      = is_array($store->payment_config_json) ? $store->payment_config_json : [];
        $handle   = trim((string) ($cfg['handle'] ?? ''));
        $amount   = number_format((float) $order->total, 2, '.', '');            // "3998.00"
        $cur      = strtoupper(trim((string) ($order->currency ?: $store->currency_code ?: '')));

        $link = fn ($url) => __('Pay securely to confirm your order: :url', ['url' => $url]);

        switch ($provider) {
            case 'razorpay_api':
                $url = $this->mintRazorpayLink($store, $order);
                return $url ? $link($url) : null;

            case 'stripe_link':
            case 'razorpay_link':
                return $handle !== '' ? $link($handle) : null;

            case 'paypal_me':
                if ($handle === '') return null;
                $h = rtrim(preg_replace('#^https?://(www\.)?paypal\.me/#i', '', $handle), '/');
                $url = 'https://paypal.me/' . $h . '/' . $amount . ($cur ?: 'USD');
                return $link($url);

            case 'upi':
                if ($handle === '') return null;
                $uri = 'upi://pay?pa=' . rawurlencode($handle) . '&am=' . $amount
                     . '&cu=' . ($cur ?: 'INR') . '&tn=' . rawurlencode('Order ' . $order->order_ref);
                return __('Pay :amt via UPI to :vpa — tap: :uri', ['amt' => trim(($cur ? $cur . ' ' : '') . $amount), 'vpa' => $handle, 'uri' => $uri]);

            case 'bank_transfer':
                return $handle !== '' ? __('Payment details: :details', ['details' => $handle]) : null;
        }
        return null;
    }

    /** Mint an amount-aware Razorpay payment link with the merchant's keys. */
    private function mintRazorpayLink(WaStorefront $store, InstagramOrder $order): ?string
    {
        if (! $this->sf->supportsLinks($store)) return null;
        $c = $this->sf->config($store);

        $amountMinor = (int) round(((float) $order->total) * 100);
        if ($amountMinor < 100) return null; // Razorpay minimum

        try {
            $r = Http::withBasicAuth($c['key_id'], $c['key_secret'])->timeout(20)
                ->post(self::RZP_API . '/payment_links', [
                    'amount'          => $amountMinor,
                    'currency'        => strtoupper($order->currency ?: 'INR'),
                    'accept_partial'  => false,
                    'reference_id'    => 'IGORD-' . $order->id,
                    'description'     => 'Order ' . $order->order_ref,
                    'customer'        => array_filter(['name' => $order->customer_name ?: null]),
                    'notify'          => ['sms' => false, 'email' => false],
                    'reminder_enable' => false,
                    'notes'           => ['ig_order_id' => (string) $order->id, 'workspace_id' => (string) $order->workspace_id],
                ]);
            if (! $r->successful()) {
                Log::warning('[IG-PAY] razorpay mint failed: ' . ($r->json('error.description') ?: ('HTTP ' . $r->status())));
                return null;
            }
            $url = (string) ($r->json('short_url') ?? '');
            if ($url !== '') {
                $meta = (array) $order->meta;
                $meta['payment_link']   = $url;
                $meta['razorpay_plink'] = (string) ($r->json('id') ?? '');
                $order->forceFill(['payment_provider' => 'razorpay', 'meta' => $meta])->save();
            }
            return $url ?: null;
        } catch (\Throwable $e) {
            Log::warning('[IG-PAY] razorpay mint exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Apply a verified Razorpay `payment_link.paid` / `payment.captured` webhook
     * to an IG order (razorpay_api merchants): flip paid (idempotent) + advance
     * the pipeline to Paid. Signature verified fail-closed against the merchant's
     * storefront webhook secret. Returns the order or null. Other providers are
     * confirmed by the seller (no unified webhook across gateways).
     */
    public function applyWebhook(string $rawBody, ?string $signature, array $payload): ?InstagramOrder
    {
        $entity = $payload['payload']['payment_link']['entity']
            ?? $payload['payload']['payment']['entity']
            ?? [];
        $notes   = is_array($entity['notes'] ?? null) ? $entity['notes'] : [];
        $orderId = (int) ($notes['ig_order_id'] ?? 0);
        if ($orderId < 1 && ! empty($entity['reference_id'])) {
            $orderId = (int) preg_replace('/\D+/', '', (string) $entity['reference_id']);
        }
        if ($orderId < 1) return null;

        $order = InstagramOrder::find($orderId);
        if (! $order) return null;

        $store = $this->storefront((int) $order->workspace_id);
        if (! $store || ! $this->sf->verifyWebhook($store, $rawBody, $signature)) {
            Log::warning('[IG-PAY] webhook verify failed', ['order' => $orderId]);
            return null;
        }

        if ($order->payment_status === 'paid') return $order; // idempotent
        $order->forceFill([
            'payment_status' => 'paid',
            'status'         => 'paid',
            'paid_at'        => $order->paid_at ?: now(),
        ])->save();

        app(InstagramPipelineService::class)->moveDeal((int) data_get($order->meta, 'deal_id'), 'paid');

        Log::info('[IG-PAY] order paid via link', ['order' => $orderId]);
        return $order;
    }
}

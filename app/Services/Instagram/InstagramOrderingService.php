<?php

namespace App\Services\Instagram;

use App\Models\Deal;
use App\Models\InstagramAccount;
use App\Models\InstagramMessage;
use App\Models\InstagramOrder;
use App\Models\InstagramProduct;
use App\Models\Pipeline;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * In-DM ordering (Phase 3). Deterministic, button-driven — no AI, no ban risk:
 *
 *   Order button (postback ORDER_<retailer_id>)
 *      → "How many?"  (quick replies)
 *      → "Name?"      (free text)
 *      → "Address?"   (free text)
 *      → order recorded (status=placed) + pipeline deal + summary DM.
 *
 * Session state lives on the DRAFT instagram_orders row (meta.step), keyed by
 * igsid, so no extra table. A draft older than 2h is abandoned. Meta has no
 * in-DM checkout API, so payment is seller-confirmed for now (a payment-link
 * step slots into finalize() later).
 */
class InstagramOrderingService
{
    /**
     * Handle one inbound message. Returns true if it belonged to ordering (a new
     * ORDER_ tap or an in-progress order), so the webhook stops here; false lets
     * keyword / AI handling run.
     */
    public function handle(InstagramAccount $account, string $igsid, string $text): bool
    {
        $text = trim($text);

        if (preg_match('/^ORDER_(.+)$/i', $text, $m)) {
            return $this->start($account, $igsid, trim($m[1]));
        }

        $order = InstagramOrder::forWorkspace((int) $account->workspace_id)
            ->where('instagram_account_id', $account->id)
            ->where('igsid', $igsid)
            ->where('status', 'draft')
            ->where('updated_at', '>=', now()->subHours(2))
            ->latest('id')->first();

        if (! $order) return false;
        return $this->advance($account, $igsid, $order, $text);
    }

    /** Start a fresh order for a tapped product. */
    private function start(InstagramAccount $account, string $igsid, string $retailerId): bool
    {
        $product = InstagramProduct::forWorkspace((int) $account->workspace_id)
            ->where('retailer_id', $retailerId)->first();
        if (! $product) {
            Log::info('[IG-ORDER] ORDER_ for unknown product — passing through', ['retailer' => $retailerId, 'account' => $account->id]);
            return false;
        }

        // Abandon any stale draft for this customer so we don't stack sessions.
        InstagramOrder::forWorkspace((int) $account->workspace_id)
            ->where('instagram_account_id', $account->id)->where('igsid', $igsid)
            ->where('status', 'draft')->update(['status' => 'cancelled']);

        $unit  = (float) ($product->price ?? 0);
        $order = InstagramOrder::create([
            'workspace_id'         => (int) $account->workspace_id,
            'instagram_account_id' => $account->id,
            'igsid'                => $igsid,
            'order_ref'            => 'IG' . strtoupper(Str::random(8)),
            'status'               => 'draft',
            'currency'             => $product->currency,
            'subtotal'             => $unit,
            'total'                => $unit,
            'meta'                 => ['step' => 'qty', 'retailer_id' => $retailerId, 'product_name' => $product->name, 'unit_price' => $unit],
        ]);
        $order->items()->create([
            'product_id'  => $product->id,
            'retailer_id' => $retailerId,
            'name'        => mb_substr((string) $product->name, 0, 512),
            'qty'         => 1,
            'unit_price'  => $unit,
            'line_total'  => $unit,
        ]);

        $this->say($account, $igsid, __('How many ":name" would you like?', ['name' => mb_substr((string) $product->name, 0, 40)]), [
            ['title' => '1', 'payload' => 'QTY_1'],
            ['title' => '2', 'payload' => 'QTY_2'],
            ['title' => '3', 'payload' => 'QTY_3'],
            ['title' => '5', 'payload' => 'QTY_5'],
        ]);
        return true;
    }

    /** Advance the in-progress order by one step. */
    private function advance(InstagramAccount $account, string $igsid, InstagramOrder $order, string $text): bool
    {
        $meta = (array) $order->meta;
        $step = (string) ($meta['step'] ?? 'qty');

        switch ($step) {
            case 'qty':
                $qty = $this->parseQty($text);
                if ($qty < 1) {
                    $this->say($account, $igsid, __('Please tap or type a quantity (e.g. 2).'));
                    return true;
                }
                $unit = (float) ($meta['unit_price'] ?? 0);
                $order->items()->update(['qty' => $qty, 'line_total' => $unit * $qty]);
                $meta['step'] = 'name';
                $order->update(['subtotal' => $unit * $qty, 'total' => $unit * $qty, 'meta' => $meta]);
                $this->say($account, $igsid, __('Great — what name should we put on the order?'));
                return true;

            case 'name':
                $meta['step'] = 'address';
                $order->update(['customer_name' => mb_substr($text, 0, 120), 'meta' => $meta]);
                $this->say($account, $igsid, __('Thanks! Please share your full delivery address.'));
                return true;

            case 'address':
                $meta['step'] = 'done';
                $order->update(['shipping_address' => mb_substr($text, 0, 1000), 'status' => 'placed', 'placed_at' => now(), 'meta' => $meta]);
                $this->finalize($account, $igsid, $order);
                return true;
        }
        return false;
    }

    /** Confirm the order + drop it into the pipeline. */
    private function finalize(InstagramAccount $account, string $igsid, InstagramOrder $order): void
    {
        $item = $order->items()->first();
        $cur  = trim((string) ($order->currency ?? ''));
        $total = trim(($cur !== '' ? $cur . ' ' : '') . number_format((float) $order->total, 2));

        $lines = [
            __('Order :ref confirmed.', ['ref' => $order->order_ref]),
            trim(((string) ($item->name ?? __('Item'))) . ' × ' . (int) ($item->qty ?? 1)),
            __('Total: :total', ['total' => $total]),
            __('Deliver to: :name', ['name' => trim((string) $order->customer_name)]),
        ];
        $this->say($account, $igsid, implode("\n", array_filter($lines)));

        // Payment prompt using whatever gateway the merchant configured on their
        // storefront (Stripe / PayPal / Razorpay / UPI / bank — global). Falls
        // back to seller-confirmed when nothing is set up. Ban-safe: normal link.
        $payMsg = null;
        try { $payMsg = app(InstagramPaymentService::class)->paymentPrompt($order); }
        catch (\Throwable $e) { Log::warning('[IG-ORDER] pay prompt failed: ' . $e->getMessage()); }

        $this->say($account, $igsid, $payMsg ?: __('Our team will confirm payment and delivery details with you shortly. Thank you!'));

        $this->maybeCreateDeal($order, $igsid);

        Log::info('[IG-ORDER] placed', ['account' => $account->id, 'order' => $order->id, 'ref' => $order->order_ref, 'total' => $order->total]);
    }

    /** One pipeline deal per order at the "Ordered" stage of the Instagram funnel. */
    private function maybeCreateDeal(InstagramOrder $order, string $igsid): void
    {
        try {
            $pipe     = app(InstagramPipelineService::class);
            $pipeline = $pipe->pipeline((int) $order->workspace_id, $order->currency ?: null);
            if (! $pipeline) return;                       // no CRM in this install
            $stageId  = $pipe->stageId($pipeline, 'ordered')
                ?? optional($pipeline->stages()->orderBy('sort_order')->first())->id;
            if (! $stageId) return;

            $deal = Deal::create([
                'workspace_id' => (int) $order->workspace_id,
                'pipeline_id'  => $pipeline->id,
                'stage_id'     => $stageId,
                'title'        => mb_substr((($order->customer_name ?: 'Instagram order') . ' — ' . $order->order_ref), 0, 191),
                'value_minor'  => (int) round(((float) $order->total) * 100),
                'currency'     => $order->currency ?: $pipeline->currency,
                'source'       => 'ig_order',
                'meta'         => ['ig_order_id' => (int) $order->id, 'igsid' => $igsid],
            ]);
            $meta = (array) $order->meta;
            $meta['deal_id'] = $deal->id;
            $order->update(['meta' => $meta]);
        } catch (\Throwable $e) {
            Log::warning('[IG-ORDER] deal create failed: ' . $e->getMessage());
        }
    }

    /** Pull a 1–99 quantity from a tap payload (QTY_2), a bare number, or words. */
    private function parseQty(string $text): int
    {
        $t = strtolower(trim($text));
        if (preg_match('/qty_(\d+)/', $t, $m)) return max(1, min(99, (int) $m[1]));
        if (preg_match('/\b(\d{1,2})\b/', $t, $m)) return max(1, min(99, (int) $m[1]));
        $words = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10];
        foreach ($words as $w => $n) if (str_contains($t, $w)) return $n;
        return 0;
    }

    /** Send a DM (optionally with quick replies) and mirror it into the inbox. */
    private function say(InstagramAccount $account, string $igsid, string $text, array $quickReplies = []): void
    {
        $svc = new InstagramService($account);
        $r = $quickReplies ? $svc->sendQuickReplies($igsid, $text, $quickReplies) : $svc->sendDm($igsid, $text);
        if (! empty($r['ok'])) {
            InstagramMessage::log($account, $igsid, 'out', $text, 'order', $r['mid'] ?? null);
        }
    }
}

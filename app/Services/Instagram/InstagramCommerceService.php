<?php

namespace App\Services\Instagram;

use App\Models\InstagramAccount;
use App\Models\InstagramMessage;
use App\Models\InstagramProduct;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Commerce — the business layer on top of InstagramService's DM
 * messaging primitives. Phase 1: pull the merchant's Commerce Manager catalog
 * into instagram_products so the (already-built) DM carousel / product template
 * has real data to send.
 */
class InstagramCommerceService
{
    /**
     * Sync every product in the account's configured catalog into
     * instagram_products (upsert by workspace+catalog+retailer_id). Paginates
     * to the end. Returns [ok, count, error]. Best-effort: a mid-run page
     * failure stops and reports what it managed.
     */
    public function syncCatalog(InstagramAccount $account): array
    {
        $catalogId = trim((string) ($account->catalog_id ?? ''));
        if ($catalogId === '') {
            return ['ok' => false, 'count' => 0,
                'error' => 'No catalog connected. Add your Commerce Manager Catalog ID first.'];
        }

        $svc   = new InstagramService($account);
        $wsId  = (int) $account->workspace_id;
        $now   = now();
        $count = 0;
        $after = null;
        $guard = 0; // hard page cap — a runaway cursor can't loop forever

        do {
            $page = $svc->getCatalogProducts($catalogId, $after);
            if ($page['error']) {
                Log::warning('[IG-CATALOG] sync page failed', [
                    'account' => $account->id, 'catalog' => $catalogId,
                    'after'   => $after, 'error' => $page['error'],
                ]);
                // Return what we have; surface the reason so the operator can act
                // (bad catalog id / no access → Meta's own message).
                return ['ok' => $count > 0, 'count' => $count, 'error' => $page['error']];
            }

            foreach ($page['data'] as $p) {
                $retailer = (string) ($p['retailer_id'] ?? $p['id'] ?? '');
                if ($retailer === '') continue;

                [$currency, $amount] = $this->parsePrice($p);

                InstagramProduct::updateOrCreate(
                    ['workspace_id' => $wsId, 'catalog_id' => $catalogId, 'retailer_id' => $retailer],
                    [
                        'instagram_account_id' => $account->id,
                        'product_id'   => (string) ($p['id'] ?? ''),
                        'name'         => mb_substr((string) ($p['name'] ?? 'Product'), 0, 512),
                        'description'  => (string) ($p['description'] ?? ''),
                        'image_url'    => (string) ($p['image_url'] ?? ''),
                        'url'          => (string) ($p['url'] ?? ''),
                        'currency'     => $currency,
                        'price'        => $amount,
                        'availability' => (string) ($p['availability'] ?? ''),
                        'raw'          => $p,
                        'synced_at'    => $now,
                    ]
                );
                $count++;
            }

            $after = $page['after'];
        } while ($after && ++$guard < 200);

        $account->forceFill(['catalog_synced_at' => $now])->save();

        Log::info('[IG-CATALOG] sync complete', [
            'account' => $account->id, 'catalog' => $catalogId, 'count' => $count,
        ]);

        return ['ok' => true, 'count' => $count, 'error' => null];
    }

    /**
     * Zero-config storefront: if the account enabled "auto-shop" and the inbound
     * text matches a keyword (shop / catalog / menu / …), instantly send the
     * product carousel — no flow to build. Returns true if it handled the
     * message. Throttled to once / customer / 60s so it can't spam.
     */
    public function maybeAutoShop(InstagramAccount $account, string $igsid, string $text): bool
    {
        $cfg = (array) data_get($account->meta_json, 'shop_auto', []);
        if (empty($cfg['enabled'])) return false;

        $words = array_filter(array_map('trim', preg_split('/[,\n]+/', mb_strtolower((string) ($cfg['keywords'] ?? 'shop, catalog, menu, products, price')))));
        if (empty($words)) return false;

        $t   = mb_strtolower(trim($text));
        $hit = false;
        foreach ($words as $w) {
            if ($w !== '' && preg_match('/(^|\W)' . preg_quote($w, '/') . '($|\W)/u', $t)) { $hit = true; break; }
        }
        if (! $hit) return false;

        // Matched — but throttle so a chatty customer can't trigger a carousel
        // storm. Still return true so nothing else replies to this message.
        if (! \Illuminate\Support\Facades\Cache::add('ig_shop_auto:' . $account->id . ':' . $igsid, 1, 60)) {
            return true;
        }

        $svc   = new InstagramService($account);
        $intro = trim((string) ($cfg['intro'] ?? ''));
        if ($intro !== '') {
            $ir = $svc->sendDm($igsid, $intro);
            if (! empty($ir['ok'])) InstagramMessage::log($account, $igsid, 'out', $intro, 'shop', $ir['mid'] ?? null);
        }
        $res = $this->sendProducts($account, $igsid, ['selection' => 'all', 'limit' => 10, 'orderButton' => true, 'detailsButton' => true]);
        if (! empty($res['ok'])) InstagramMessage::log($account, $igsid, 'out', '[products]', 'shop', $res['mid'] ?? null);

        Log::info('[IG-SHOP] auto-catalog sent', ['account' => $account->id, 'igsid' => $igsid, 'ok' => (bool) ($res['ok'] ?? false)]);
        return true;
    }

    // ── Phase 2: send synced products as a shoppable DM carousel ─────────────

    /**
     * Send a product carousel to one IGSID, built from the account's synced
     * catalog. $cfg is the flow node's data (selection/productIds/limit/buttons).
     * Uses sendGenericTemplate (our cached rows) — reliable regardless of whether
     * the catalog is wired into the account's native commerce. Returns the
     * InstagramService send result (['ok'=>bool,'mid'?,'error'?]).
     */
    public function sendProducts(InstagramAccount $account, string $igsid, array $cfg = []): array
    {
        $products = $this->resolveProducts($account, $cfg);
        if (empty($products)) {
            return ['ok' => false, 'error' => 'No matching products synced. Sync your catalog on the Commerce page first.'];
        }
        $elements = $this->productCards($products, $cfg);
        return (new InstagramService($account))->sendGenericTemplate($igsid, $elements);
    }

    /**
     * Pick the products a "Send products" node should show. selection='pick'
     * sends the chosen ids in the CONFIGURED order; anything else sends the most
     * recently-synced N. Resolved by WORKSPACE (not account) so a picker choice
     * always maps even when several accounts share one catalog. Capped at 10 —
     * the generic-template hard limit.
     */
    private function resolveProducts(InstagramAccount $account, array $cfg): array
    {
        $wsId = (int) $account->workspace_id;
        $ids  = array_values(array_filter(array_map('intval', (array) ($cfg['productIds'] ?? []))));

        if (($cfg['selection'] ?? 'pick') === 'pick' && $ids) {
            $rows = InstagramProduct::forWorkspace($wsId)->whereIn('id', $ids)->get()->keyBy('id');
            $out  = [];
            foreach ($ids as $id) {
                if (isset($rows[$id])) $out[] = $rows[$id];
                if (count($out) >= 10) break;
            }
            return $out;
        }

        $limit = max(1, min(10, (int) ($cfg['limit'] ?? 10)));
        return InstagramProduct::forWorkspace($wsId)->orderByDesc('synced_at')->limit($limit)->get()->all();
    }

    /**
     * Turn product rows into generic-template elements. Each card carries the
     * name (title), formatted price (subtitle), image, and up to two buttons:
     * an "Order" postback (payload ORDER_<retailer_id> — the ordering flow /
     * keyword rule catches it) and a "View" web_url to the product page.
     *
     * @param iterable<InstagramProduct> $products
     */
    public function productCards(iterable $products, array $cfg = []): array
    {
        $orderOn      = (bool) ($cfg['orderButton'] ?? true);
        $orderLabel   = trim((string) ($cfg['orderLabel'] ?? 'Order')) ?: 'Order';
        $detailsOn    = (bool) ($cfg['detailsButton'] ?? true);
        $detailsLabel = trim((string) ($cfg['detailsLabel'] ?? 'View')) ?: 'View';

        $out = [];
        foreach ($products as $p) {
            $subtitle = '';
            if ($p->price !== null) {
                $cur      = trim((string) ($p->currency ?? ''));
                $subtitle = trim(($cur !== '' ? $cur . ' ' : '') . number_format((float) $p->price, 2));
            }

            $btns = [];
            if ($orderOn && $p->retailer_id) {
                $btns[] = ['type' => 'postback', 'title' => $orderLabel, 'payload' => 'ORDER_' . $p->retailer_id];
            }
            if ($detailsOn && $p->url) {
                $btns[] = ['type' => 'web_url', 'title' => $detailsLabel, 'url' => (string) $p->url];
            }

            $out[] = array_filter([
                'title'     => (string) $p->name,
                'subtitle'  => $subtitle,
                'image_url' => (string) ($p->image_url ?? ''),
                'url'       => (string) ($p->url ?? ''),
                'buttons'   => $btns,
            ], fn ($v) => $v !== '' && $v !== []);
        }
        return $out;
    }

    /**
     * Catalog API returns price as a formatted string ("1,999.00 INR" / "$19.99")
     * — shape varies by version/locale — so parse defensively: pull the first
     * number and the 3-letter currency if present, else fall back to `currency`.
     * Returns [currency|null, amount|null].
     */
    private function parsePrice(array $p): array
    {
        $raw = (string) ($p['price'] ?? '');
        $currency = null;
        if (! empty($p['currency'])) $currency = strtoupper((string) $p['currency']);
        elseif (preg_match('/\b([A-Z]{3})\b/', $raw, $m)) $currency = $m[1];

        $amount = null;
        // Strip thousands separators, keep the first decimal number.
        $clean = preg_replace('/[^0-9.,]/', '', $raw);
        $clean = str_replace(',', '', (string) $clean);
        if ($clean !== '' && is_numeric($clean)) $amount = (float) $clean;

        return [$currency, $amount];
    }
}

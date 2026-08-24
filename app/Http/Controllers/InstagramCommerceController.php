<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Models\InstagramProduct;
use App\Services\Instagram\InstagramCommerceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Instagram Commerce — catalog + (later) orders. Phase 1 = catalog: connect a
 * Commerce Manager Catalog ID to an IG account and sync its products into
 * instagram_products, which the DM product carousel then draws from.
 */
class InstagramCommerceController extends Controller
{
    /** IDs of the accounts this user owns — the tenancy boundary for products. */
    private function accountIds(): array
    {
        return InstagramAccount::where('user_id', (int) auth()->id())->pluck('id')->all();
    }

    /** A user-owned account by id, ownership-checked. */
    private function account(int $id): InstagramAccount
    {
        return InstagramAccount::where('user_id', (int) auth()->id())->where('id', $id)->firstOrFail();
    }

    /** GET /instagram/commerce — catalog status + product list per account. */
    public function index(): \Illuminate\Contracts\View\View
    {
        $accounts = InstagramAccount::where('user_id', (int) auth()->id())->orderBy('id')->get();
        $products = InstagramProduct::whereIn('instagram_account_id', $this->accountIds() ?: [0])
            ->orderByDesc('synced_at')->limit(200)->get();

        return view('instagram.commerce.index', compact('accounts', 'products'));
    }

    /** POST /instagram/commerce/catalog — save the Catalog ID on an account. */
    public function saveCatalog(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => 'required|integer',
            'catalog_id' => 'nullable|string|max:64|regex:/^[0-9]*$/',
        ]);
        $account = $this->account((int) $data['account_id']);
        $account->forceFill(['catalog_id' => trim((string) ($data['catalog_id'] ?? '')) ?: null])->save();

        return back()->with('status', 'Catalog ID saved. Click "Sync products" to pull your catalog.');
    }

    /** POST /instagram/commerce/shop-auto — toggle the "type a keyword → get the catalog" auto-reply. */
    public function saveShopAuto(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => 'required|integer',
            'enabled'    => 'nullable|boolean',
            'keywords'   => 'nullable|string|max:255',
            'intro'      => 'nullable|string|max:500',
        ]);
        $account = $this->account((int) $data['account_id']);
        $meta = (array) $account->meta_json;
        $meta['shop_auto'] = [
            'enabled'  => (bool) ($data['enabled'] ?? false),
            'keywords' => trim((string) ($data['keywords'] ?? '')) ?: 'shop, catalog, menu, products, price',
            'intro'    => trim((string) ($data['intro'] ?? '')),
        ];
        $account->forceFill(['meta_json' => $meta])->save();

        return back()->with('status', 'Auto-catalog settings saved.');
    }

    /** POST /instagram/commerce/sync — pull catalog products for an account. */
    public function sync(Request $request, InstagramCommerceService $svc): RedirectResponse
    {
        $data    = $request->validate(['account_id' => 'required|integer']);
        $account = $this->account((int) $data['account_id']);

        $res = $svc->syncCatalog($account);
        if (! $res['ok']) {
            return back()->withErrors(['catalog' => 'Sync failed: ' . ($res['error'] ?? 'unknown error')]);
        }
        $note = 'Synced ' . $res['count'] . ' product(s).';
        if ($res['error']) $note .= ' (partial — ' . $res['error'] . ')';

        return back()->with('status', $note);
    }

    /** GET /instagram/commerce/products.json — for the DM "Send products" picker. */
    public function productsJson(Request $request): JsonResponse
    {
        $q    = trim((string) $request->query('q', ''));
        $rows = InstagramProduct::whereIn('instagram_account_id', $this->accountIds() ?: [0])
            ->when($q !== '', fn ($qq) => $qq->where('name', 'like', "%{$q}%"))
            ->orderBy('name')->limit(100)
            ->get(['id', 'retailer_id', 'name', 'image_url', 'price', 'currency']);

        return response()->json(['products' => $rows]);
    }
}

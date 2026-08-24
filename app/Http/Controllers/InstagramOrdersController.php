<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Models\InstagramOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Instagram Orders — everything placed through the in-DM ordering flow
 * (Order button → quantity → address). Sellers advance each order along its
 * fulfilment funnel and see the linked pipeline deal.
 */
class InstagramOrdersController extends Controller
{
    /** IDs of the accounts this user owns — the tenancy boundary for orders. */
    private function accountIds(): array
    {
        return InstagramAccount::where('user_id', (int) auth()->id())->pluck('id')->all();
    }

    /** GET /instagram/orders */
    public function index(Request $request): \Illuminate\Contracts\View\View
    {
        $ids    = $this->accountIds() ?: [0];
        $status = (string) $request->query('status', '');
        $q      = trim((string) $request->query('q', ''));

        $orders = InstagramOrder::whereIn('instagram_account_id', $ids)
            ->where('status', '!=', 'draft')   // hide in-progress carts
            ->with('items')
            ->when($status !== '', fn ($x) => $x->where('status', $status))
            ->when($q !== '', function ($x) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                $x->where(fn ($w) => $w->where('order_ref', 'like', $like)
                    ->orWhere('customer_name', 'like', $like));
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $base   = InstagramOrder::whereIn('instagram_account_id', $ids)->where('status', '!=', 'draft');
        $counts = [
            'all'        => (clone $base)->count(),
            'placed'     => (clone $base)->where('status', 'placed')->count(),
            'paid'       => (clone $base)->where('status', 'paid')->count(),
            'dispatched' => (clone $base)->where('status', 'dispatched')->count(),
        ];

        return view('instagram.orders.index', compact('orders', 'counts', 'status', 'q'));
    }

    /** POST /instagram/orders/{order}/status — advance the fulfilment funnel. */
    public function setStatus(Request $request, int $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:placed,paid,dispatched,cancelled',
        ]);
        $row = InstagramOrder::whereIn('instagram_account_id', $this->accountIds() ?: [0])->findOrFail($order);

        $patch = ['status' => $data['status']];
        if ($data['status'] === 'paid' && ! $row->paid_at)             $patch['paid_at'] = now();
        if ($data['status'] === 'dispatched' && ! $row->dispatched_at) $patch['dispatched_at'] = now();
        $row->forceFill($patch)->save();

        // Auto-advance the linked pipeline deal along the Instagram funnel.
        $stageKey = \App\Services\Instagram\InstagramPipelineService::STATUS_STAGE[$data['status']] ?? null;
        if ($stageKey) {
            app(\App\Services\Instagram\InstagramPipelineService::class)
                ->moveDeal((int) data_get($row->meta, 'deal_id'), $stageKey);
        }

        return back()->with('status', 'Order ' . $row->order_ref . ' marked ' . $data['status'] . '.');
    }
}

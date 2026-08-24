<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin approval queue for PLAN-purchase orders (App\Models\Order) paid via an
 * offline / bank-transfer gateway. Those drivers leave the order `pending`
 * (awaiting_admin_verification); this screen lets an operator approve → the
 * plan activates through CheckoutController::markPaidManually(), or reject.
 *
 * Online gateways (Stripe/Razorpay/…) settle themselves and never appear here
 * except as already-paid history.
 */
class PlanOrderController extends Controller
{
    public function index(Request $r)
    {
        $filter = $r->get('status', 'pending');

        $orders = Order::with(['user', 'package'])
            ->when($filter !== 'all', fn ($q) => $q->where('status', $filter))
            ->when(($q = trim((string) $r->get('q', ''))) !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('order_number', 'like', "%$q%")
                ->orWhere('customer_email', 'like', "%$q%")
                ->orWhere('customer_name', 'like', "%$q%")
                ->orWhere('payment_reference', 'like', "%$q%")))
            // Pending-with-proof first, then newest.
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'pending'  => Order::where('status', 'pending')->count(),
            'paid'     => Order::where('status', 'paid')->count(),
            'revenue'  => (float) Order::where('status', 'paid')->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(total_amount, amount)')),
            'currency' => (string) (\App\Models\Setting::get('default_currency', '') ?: 'USD'),
        ];

        return view('admin.plan-orders', compact('orders', 'stats', 'filter'));
    }

    /** Approve a pending offline order → activate the plan (same path the gateways use). */
    public function approve(Request $r, Order $order)
    {
        if ($order->status === 'paid') {
            return back()->with('error', __('That order is already paid.'));
        }

        $data = $r->validate([
            'reference' => 'nullable|string|max:191',
            'note'      => 'nullable|string|max:500',
        ]);

        app(CheckoutController::class)->markPaidManually(
            $order,
            $r->user()?->id,
            $data['reference'] ?? $order->payment_reference,
            $data['note'] ?? null,
        );

        Log::info('[PLAN-ORDER] manually approved', [
            'order'    => $order->order_number,
            'admin_id' => $r->user()?->id,
        ]);

        return back()->with('success', __('Order :n approved — plan activated.', ['n' => $order->order_number]));
    }

    /** Reject a pending offline order — no plan applied. */
    public function reject(Request $r, Order $order)
    {
        if ($order->status === 'paid') {
            return back()->with('error', __('That order is already paid and cannot be rejected.'));
        }

        $data = $r->validate(['note' => 'nullable|string|max:500']);

        app(CheckoutController::class)->markRejected($order, $r->user()?->id, $data['note'] ?? null);

        Log::info('[PLAN-ORDER] rejected', ['order' => $order->order_number, 'admin_id' => $r->user()?->id]);

        return back()->with('success', __('Order :n rejected.', ['n' => $order->order_number]));
    }
}

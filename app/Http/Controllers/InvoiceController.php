<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Per-order invoice surface. Renders a printable HTML invoice the buyer can
 * save as PDF via the browser's print dialog — no PDF library involved.
 *
 * Scoped strictly to the order's user_id: a buyer can only fetch their own
 * invoices. Admins reach every order through /admin/order-history.
 *
 * INSTAFLOW NOTE: WaDesk pulled the company/billing identity from
 * App\Support\Brand, which standalone does not ship. Here the same block is
 * assembled from the admin Billing-settings keys (company_name / company_address
 * / company_tax_id / tax_label) with a fall back to the site name + logo.
 */
class InvoiceController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if ((int) $order->user_id !== (int) $user->id) {
            abort(403, 'Not your order.');
        }

        $order->loadMissing(['package', 'user']);

        // Company / billing identity, from admin Billing settings. Every key is
        // optional — a fresh install with nothing configured still renders a
        // clean invoice headed by the site name.
        $brand = [
            'name'      => (string) (setting('company_name') ?: site_name()),
            'address'   => (string) (setting('company_address') ?: ''),
            'tax_id'    => (string) (setting('company_tax_id') ?: ''),
            'tax_label' => (string) (setting('tax_label', 'Tax')),
            'email'     => (string) (setting('support_email') ?: setting('company_email') ?: ''),
            'logo'      => site_logo(),
        ];

        return view('invoices.show', compact('order', 'brand'));
    }
}

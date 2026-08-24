<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstagramOrder;
use App\Models\InstagramOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Admin billing lists over the instagram_orders table: a full order history and
 * a paid-only invoices view. Records normally arrive from the DM checkout flow /
 * gateway webhooks, but the admin can ALSO cut a manual invoice here (for an
 * off-platform sale, a wire transfer, etc.) and view/print any invoice.
 */
class BillingListController extends Controller
{
    public function orderHistory(Request $r)
    {
        $q = trim((string) $r->get('q', ''));

        $orders = InstagramOrder::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('order_ref', 'like', "%$q%")
                ->orWhere('customer_name', 'like', "%$q%")
                ->orWhere('payment_ref', 'like', "%$q%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'    => InstagramOrder::count(),
            'paid'     => InstagramOrder::where('payment_status', 'paid')->count(),
            'revenue'  => (float) InstagramOrder::where('payment_status', 'paid')->sum('total'),
            'currency' => (string) (\App\Models\Setting::get('default_currency', '') ?: 'USD'),
        ];

        return view('admin.order-history', compact('orders', 'stats', 'q'));
    }

    public function invoices(Request $r)
    {
        $q = trim((string) $r->get('q', ''));

        $orders = InstagramOrder::where('payment_status', 'paid')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('order_ref', 'like', "%$q%")
                ->orWhere('customer_name', 'like', "%$q%")
                ->orWhere('payment_ref', 'like', "%$q%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'     => InstagramOrder::where('payment_status', 'paid')->count(),
            'revenue'   => (float) InstagramOrder::where('payment_status', 'paid')->sum('total'),
            'currency'  => (string) (\App\Models\Setting::get('default_currency', '') ?: 'USD'),
            'thisMonth' => InstagramOrder::where('payment_status', 'paid')
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];

        $currencies = $this->currencyOptions();

        return view('admin.invoices', compact('orders', 'stats', 'q', 'currencies'));
    }

    /**
     * Cut a manual invoice — an admin-created, already-settled order for an
     * off-platform sale (wire transfer, cash, etc.). Persists a real
     * InstagramOrder (payment_provider=manual, payment_status=paid) plus its
     * line items, so it shows up in the same paid-invoices list and prints with
     * the same template as gateway orders. The buyer email lives in meta since
     * the table has no email column.
     */
    public function storeInvoice(Request $r)
    {
        $data = $r->validate([
            'customer_name'   => ['required', 'string', 'max:191'],
            'customer_email'  => ['nullable', 'email', 'max:191'],
            'customer_phone'  => ['nullable', 'string', 'max:64'],
            'currency'        => ['required', 'string', 'max:8'],
            'note'            => ['nullable', 'string', 'max:2000'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.name'          => ['required', 'string', 'max:191'],
            'items.*.qty'           => ['nullable', 'numeric', 'min:0'],
            'items.*.price'         => ['nullable', 'numeric', 'min:0'],
        ]);

        $currency = strtoupper($data['currency']);
        $subtotal = 0.0;
        $lines = [];
        foreach ($data['items'] as $it) {
            $name = trim((string) ($it['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $qty   = max(1, (int) ($it['qty'] ?? 1));
            $price = round((float) ($it['price'] ?? 0), 2);
            $lineTotal = round($qty * $price, 2);
            $subtotal += $lineTotal;
            $lines[] = compact('name', 'qty', 'price', 'lineTotal');
        }

        if (empty($lines)) {
            return back()->withInput()->withErrors(['items' => __('Add at least one line item.')]);
        }

        $order = InstagramOrder::create([
            'workspace_id'     => 0,
            'order_ref'        => $this->nextInvoiceRef(),
            'status'           => 'paid',
            'payment_status'   => 'paid',
            'payment_provider' => 'manual',
            'payment_ref'      => 'MANUAL',
            'currency'         => $currency,
            'subtotal'         => $subtotal,
            'total'            => $subtotal,
            'customer_name'    => $data['customer_name'],
            'customer_phone'   => $data['customer_phone'] ?? null,
            'meta'             => array_filter([
                'manual'         => true,
                'customer_email' => $data['customer_email'] ?? null,
                'note'           => $data['note'] ?? null,
                'created_by'     => optional($r->user())->id,
            ], fn ($v) => $v !== null && $v !== ''),
            'placed_at'        => now(),
            'paid_at'          => now(),
        ]);

        foreach ($lines as $l) {
            InstagramOrderItem::create([
                'order_id'   => $order->id,
                'name'       => $l['name'],
                'qty'        => $l['qty'],
                'unit_price' => $l['price'],
                'line_total' => $l['lineTotal'],
            ]);
        }

        return redirect()->route('admin.invoices')
            ->with('success', __('Manual invoice :ref created.', ['ref' => $order->order_ref]));
    }

    /**
     * Printable invoice for any order (gateway or manual). Reuses the same
     * company/billing identity block as the buyer-facing invoice. ?download=1
     * auto-triggers the browser print/save-as-PDF dialog on load.
     */
    public function showInvoice(Request $r, InstagramOrder $order)
    {
        $order->load('items');

        $brand = [
            'name'      => (string) (setting('company_name') ?: site_name()),
            'address'   => (string) (setting('company_address') ?: ''),
            'tax_id'    => (string) (setting('company_tax_id') ?: ''),
            'tax_label' => (string) (setting('tax_label', 'Tax')),
            'email'     => (string) (setting('support_email') ?: setting('company_email') ?: ''),
            'logo'      => function_exists('site_logo') ? site_logo() : null,
        ];

        $autoPrint = (bool) $r->boolean('download');

        return view('admin.invoice-print', compact('order', 'brand', 'autoPrint'));
    }

    /** Active currency codes for the manual-invoice picker, default-currency first. */
    private function currencyOptions(): array
    {
        $default = strtoupper((string) (\App\Models\Setting::get('default_currency', '') ?: 'USD'));
        $codes = [$default];
        try {
            if (class_exists(\App\Models\Currency::class)) {
                $rows = \App\Models\Currency::query()
                    ->when(\Illuminate\Support\Facades\Schema::hasColumn('currencies', 'is_active'),
                        fn ($q) => $q->where('is_active', true))
                    ->pluck('code')->map(fn ($c) => strtoupper((string) $c))->all();
                $codes = array_merge($codes, $rows);
            }
        } catch (\Throwable $e) {
        }
        return array_values(array_unique(array_filter($codes)));
    }

    /** Sequential-ish unique reference for a manual invoice, using the admin invoice prefix. */
    private function nextInvoiceRef(): string
    {
        $prefix = strtoupper(trim((string) (\App\Models\Setting::get('invoice_prefix', '') ?: 'INV')));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?: 'INV';
        do {
            $ref = $prefix . '-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));
        } while (InstagramOrder::where('order_ref', $ref)->exists());
        return $ref;
    }
}

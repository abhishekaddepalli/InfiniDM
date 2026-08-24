<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>{{ __('Invoice') }} {{ $order->order_number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f4f7;
            color: #1a1523;
            margin: 0;
            padding: 30px 20px;
        }

        .invoice {
            max-width: 780px;
            margin: 0 auto;
            background: #fff;
            padding: 48px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border-radius: 8px;
        }

        .hd {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #ece7f0;
        }

        .hd .brand h1 { margin: 0 0 6px; font-size: 24px; }
        .hd .brand .meta { font-size: 11px; color: #7a7385; line-height: 1.5; }
        .hd .invno { text-align: right; }

        .hd .invno .lbl {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            color: #7a7385;
        }

        .hd .invno .num {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 16px;
            margin-top: 4px;
            color: #C13584;
        }

        .hd .invno .dt {
            font-size: 11px;
            color: #7a7385;
            margin-top: 6px;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
        }

        .bill { display: flex; gap: 24px; margin-bottom: 32px; }
        .bill .col { flex: 1; }

        .bill .col .lbl {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            color: #7a7385;
            margin-bottom: 6px;
        }

        .bill .col .name { font-weight: 600; font-size: 13px; }
        .bill .col .row { font-size: 12px; color: #4a4458; line-height: 1.5; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }

        th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: #7a7385;
            padding: 10px 8px;
            border-bottom: 1px solid #ece7f0;
            background: #faf9fb;
        }

        td {
            padding: 14px 8px;
            font-size: 13px;
            border-bottom: 1px solid #f3f0f6;
        }

        td.amt { text-align: right; font-family: 'JetBrains Mono', ui-monospace, monospace; }

        .totals { margin-left: auto; width: 300px; }

        .totals .row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
        }

        .totals .row .lbl { color: #4a4458; }
        .totals .row .val { font-family: 'JetBrains Mono', ui-monospace, monospace; }

        .totals .row.total {
            border-top: 1px solid #ece7f0;
            padding-top: 12px;
            margin-top: 6px;
            font-size: 18px;
            font-weight: 600;
        }

        .totals .row.total .val { color: #C13584; }
        .totals .row.discount .val,
        .totals .row.discount .lbl { color: #C13584; }

        .status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .status.paid { background: #FCE7F3; color: #A21567; }
        .status.pending { background: #FFF4E0; color: #7B5A14; }
        .status.failed { background: #FCE0DA; color: #A1431F; }
        .status.refunded { background: #FCE0DA; color: #A1431F; }

        .ft {
            margin-top: 36px;
            padding-top: 18px;
            border-top: 1px solid #ece7f0;
            font-size: 11px;
            color: #7a7385;
            line-height: 1.6;
        }

        .ft .row { display: flex; justify-content: space-between; gap: 24px; }
        .ft .ref { font-family: 'JetBrains Mono', ui-monospace, monospace; }

        .actions {
            max-width: 780px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .actions a {
            color: #C13584;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .actions a:hover { text-decoration: underline; }

        .actions button {
            background: linear-gradient(135deg, #F58529 0%, #DD2A7B 50%, #8134AF 100%);
            color: #fff;
            border: 0;
            padding: 10px 22px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .actions button:hover { opacity: 0.95; }

        @media (max-width: 640px) {
            body { padding: 16px 12px; }
            .invoice { padding: 24px 20px; }
            .hd { flex-direction: column; gap: 16px; }
            .hd .invno { text-align: left; }
            .bill { flex-direction: column; gap: 16px; }
            .totals { width: 100%; margin-left: 0; }
            .ft .row { flex-direction: column; gap: 8px; }
            .actions { flex-wrap: wrap; gap: 12px; }
        }

        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .invoice { box-shadow: none; padding: 24px; border-radius: 0; max-width: 100%; }
        }
    </style>
</head>

<body>

    <div class="actions">
        <a href="{{ route('account.index', ['tab' => 'invoices']) }}">&larr; {{ __('Back to invoices') }}</a>
        <button onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
    </div>

    <div class="invoice">

        <div class="hd">
            <div class="brand">
                @if (!empty($brand['logo']))
                    <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}"
                        style="max-height:48px;max-width:220px;width:auto;object-fit:contain;display:block;margin-bottom:8px;">
                @else
                    <h1>{{ $brand['name'] }}</h1>
                @endif
                <div class="meta">
                    @if (!empty($brand['address']))
                        <div>{{ $brand['address'] }}</div>
                    @endif
                    @if (!empty($brand['email']))
                        <div>{{ $brand['email'] }}</div>
                    @endif
                    @if (!empty($brand['tax_id']))
                        <div>{{ $brand['tax_label'] ?: 'Tax ID' }}: {{ $brand['tax_id'] }}</div>
                    @endif
                </div>
            </div>
            <div class="invno">
                <div class="lbl">{{ __('Invoice') }}</div>
                <div class="num">{{ $order->order_number }}</div>
                <div class="dt">{{ optional($order->created_at)->format('M j, Y') }}</div>
                <div style="margin-top:10px"><span class="status {{ $order->status }}">{{ $order->status }}</span></div>
            </div>
        </div>

        <div class="bill">
            <div class="col">
                <div class="lbl">{{ __('Billed to') }}</div>
                <div class="name">{{ $order->customer_name ?: optional($order->user)->name }}</div>
                <div class="row">{{ $order->customer_email ?: optional($order->user)->email }}</div>
                @if ($order->billing_company)
                    <div class="row">{{ $order->billing_company }}</div>
                @endif
                @if ($order->billing_address)
                    <div class="row">{{ $order->billing_address }}</div>
                @endif
                @if ($order->billing_city || $order->billing_postal)
                    <div class="row">{{ trim($order->billing_city . ' ' . $order->billing_postal) }}</div>
                @endif
                @if ($order->billing_country)
                    <div class="row">{{ $order->billing_country }}</div>
                @endif
                @if ($order->billing_tax_id)
                    <div class="row">{{ __('Tax ID:') }} <span
                            style="font-family:'JetBrains Mono', ui-monospace, monospace">{{ $order->billing_tax_id }}</span></div>
                @endif
            </div>
            <div class="col">
                <div class="lbl">{{ __('Payment') }}</div>
                <div class="row">{{ __('Method') }}:
                    {{ \Illuminate\Support\Str::title(str_replace('_', ' ', $order->gateway_slug ?: 'manual')) }}</div>
                @if ($order->gateway_payment_id)
                    <div class="row">{{ __('Reference:') }} <span class="ref"
                            style="font-family:'JetBrains Mono', ui-monospace, monospace">{{ $order->gateway_payment_id }}</span>
                    </div>
                @endif
                @if ($order->paid_at)
                    <div class="row">{{ __('Paid on') }}: {{ $order->paid_at->format('M j, Y H:i') }}</div>
                @endif
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:60%">{{ __('Item') }}</th>
                    <th style="width:15%; text-align:right">{{ __('Qty') }}</th>
                    <th style="width:25%; text-align:right">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div style="font-weight:600">
                            {{ optional($order->package)->name ?: 'Plan #' . $order->package_id }}</div>
                        @if (optional($order->package)->plan_duration)
                            <div style="font-size:11px; color:#7a7385; margin-top:2px">
                                {{ $order->package->plan_duration }} {{ $order->package->plan_unit }}
                            </div>
                        @endif
                    </td>
                    <td class="amt">1</td>
                    <td class="amt">{!! \App\Support\FormatSettings::formatIn(
                        $order->amount + $order->discount_amount - $order->tax_amount,
                        $order->currency,
                    ) !!}</td>
                </tr>
            </tbody>
        </table>

        <div class="totals">
            <div class="row">
                <span class="lbl">{{ __('Subtotal') }}</span>
                <span class="val">{!! \App\Support\FormatSettings::formatIn(
                    $order->amount + $order->discount_amount - $order->tax_amount,
                    $order->currency,
                ) !!}</span>
            </div>
            @if ($order->discount_amount > 0)
                <div class="row discount">
                    <span class="lbl">{{ __('Discount') }} {{ $order->coupon_code ? '· ' . $order->coupon_code : '' }}</span>
                    <span class="val">&minus;{!! \App\Support\FormatSettings::formatIn($order->discount_amount, $order->currency) !!}</span>
                </div>
            @endif
            @if ($order->tax_amount > 0)
                <div class="row">
                    <span class="lbl">{{ setting('tax_label', 'Tax') }}
                        ({{ rtrim(rtrim(number_format((float) $order->tax_rate, 2), '0'), '.') }}%)</span>
                    <span class="val">{!! \App\Support\FormatSettings::formatIn($order->tax_amount, $order->currency) !!}</span>
                </div>
            @endif
            <div class="row total">
                <span class="lbl">{{ __('Total') }}</span>
                <span class="val">{!! \App\Support\FormatSettings::formatIn($order->amount, $order->currency) !!}</span>
            </div>
        </div>

        <div class="ft">
            <div class="row">
                <div>{{ __('Thank you for your purchase.') }}
                    @php $refundDays = (int) setting('refund_days', 7); @endphp
                    @if ($brand['email'])
                        {{ __('For support or refunds within :days days, reply to :email.', ['days' => $refundDays, 'email' => $brand['email']]) }}
                    @endif
                </div>
                <div class="ref">{{ __('Order ID') }}: {{ $order->id }}</div>
            </div>
        </div>

    </div>

</body>

</html>

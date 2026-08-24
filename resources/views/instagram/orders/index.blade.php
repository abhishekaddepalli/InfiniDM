<x-layouts.instagram :title="__('Orders')" ig-active="orders" page="instagram-orders">
    <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">

        <div class="mb-5">
            <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">{{ __('Instagram · Commerce') }}</div>
            <h1 class="serif font-serif font-normal tracking-[-0.01em] text-[26px] leading-tight">{{ __('Orders') }}</h1>
            <p class="text-[13px] text-ink-600 mt-1 max-w-2xl">
                {{ __('Orders placed in DMs through the "Order" button on your product cards. Advance each one as you fulfil it.') }}
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">{{ session('status') }}</div>
        @endif

        @php
            $chip = function ($label, $val, $count) use ($status) {
                $on = $status === $val;
                $qs = array_filter(['status' => $val !== '' ? $val : null] + request()->except('page', 'status'));
                return [$label, url('/instagram/orders') . (($u = http_build_query($qs)) ? '?' . $u : ''), $count, $on];
            };
        @endphp
        <div class="flex flex-wrap items-center gap-2 mb-4">
            @foreach ([
                $chip(__('All'), '', $counts['all']),
                $chip(__('Placed'), 'placed', $counts['placed']),
                $chip(__('Paid'), 'paid', $counts['paid']),
                $chip(__('Dispatched'), 'dispatched', $counts['dispatched']),
            ] as [$label, $href, $count, $on])
                <a href="{{ $href }}" @class([
                    'inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-[12px] font-medium border transition',
                    'ig-grad-soft text-white border-transparent' => $on,
                    'bg-paper-0 border-paper-200 text-ink-700 hover:bg-paper-50' => !$on,
                ])>
                    <span>{{ $label }}</span>
                    <span class="font-mono text-[10px] px-1.5 py-0.5 rounded-full {{ $on ? 'bg-white/25 text-white' : 'bg-paper-100 text-ink-600' }}">{{ $count }}</span>
                </a>
            @endforeach

            <form method="GET" action="{{ url('/instagram/orders') }}" class="ml-auto">
                @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <input name="q" value="{{ $q }}" placeholder="{{ __('Search ref / customer') }}" class="field w-[220px] max-w-full text-[12.5px]" type="search">
            </form>
        </div>

        @if ($orders->isEmpty())
            <div class="border border-dashed border-paper-200 rounded-2xl bg-paper-0 py-12 text-center">
                <div class="text-[13px] text-ink-600">{{ __('No orders yet.') }}</div>
                <p class="text-[12px] text-ink-500 mt-1 max-w-md mx-auto">
                    {{ __('Send a product carousel in a flow (the "Send products" node). When a customer taps "Order", the DM guides them through quantity + address and the order lands here.') }}
                </p>
                <a href="{{ url('/instagram/commerce') }}" class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold">{{ __('Set up your catalog') }}</a>
            </div>
        @else
            <div class="border border-paper-200 rounded-2xl bg-paper-0 shadow-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead>
                            <tr class="text-left text-ink-500 mono font-mono text-[10px] uppercase tracking-[0.12em] border-b border-paper-200">
                                <th class="px-4 py-2.5 font-medium">{{ __('Ref') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Customer') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Items') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Total') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Placed') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Status') }}</th>
                                <th class="px-4 py-2.5 font-medium text-right">{{ __('Deal') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-100">
                            @foreach ($orders as $order)
                                <tr class="hover:bg-paper-50/60 align-top">
                                    <td class="px-4 py-3 font-mono text-[11.5px] text-ink-900">{{ $order->order_ref }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-ink-900">{{ $order->customer_name ?: __('—') }}</div>
                                        @if ($order->shipping_address)
                                            <div class="text-[11px] text-ink-500 truncate max-w-[220px]" title="{{ $order->shipping_address }}">{{ $order->shipping_address }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-ink-700">
                                        @foreach ($order->items as $it)
                                            <div class="truncate max-w-[220px]">{{ $it->name }} <span class="text-ink-500">× {{ (int) $it->qty }}</span></div>
                                        @endforeach
                                    </td>
                                    <td class="px-4 py-3 font-mono text-ink-900">{{ trim(($order->currency ? $order->currency . ' ' : '') . number_format((float) $order->total, 2)) }}</td>
                                    <td class="px-4 py-3 text-ink-500 font-mono text-[11.5px]">{{ optional($order->placed_at ?? $order->created_at)->diffForHumans() }}</td>
                                    <td class="px-4 py-3">
                                        <form method="POST" action="{{ route('instagram.orders.status', $order->id) }}" class="flex items-center gap-1.5">
                                            @csrf
                                            <select name="status" class="field text-[11.5px] py-1 pr-6">
                                                @foreach (['placed' => __('Placed'), 'paid' => __('Paid'), 'dispatched' => __('Dispatched'), 'cancelled' => __('Cancelled')] as $sv => $sl)
                                                    <option value="{{ $sv }}" @selected(($order->status ?: 'placed') === $sv)>{{ $sl }}</option>
                                                @endforeach
                                            </select>
                                            <button class="px-2 py-1 rounded-lg bg-ink-900 text-white text-[11px] font-semibold">{{ __('Save') }}</button>
                                        </form>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @php $dealId = data_get($order->meta, 'deal_id'); @endphp
                                        @if ($dealId)
                                            <a href="{{ url('/deals/' . $dealId) }}" class="text-ig-purple underline text-[11.5px]">{{ __('Deal') }} #{{ $dealId }}</a>
                                        @else
                                            <span class="text-ink-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $orders->links() }}</div>
        @endif
    </div>
</x-layouts.instagram>

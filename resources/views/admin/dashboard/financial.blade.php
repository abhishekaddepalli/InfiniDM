{{-- Financial tab body — shared by /admin/financial and /admin/insights. --}}
<main class="px-4 sm:px-7 py-7 space-y-5">

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Revenue ops') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[40px] leading-[1.0]">{{ __('Financial') }}
                <span class="italic ig-text">{{ __('dashboard') }}</span>.</h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('MRR, ARR, payments, refunds and outstanding orders across the platform.') }}</p>
        </div>
        <div class="flex items-center gap-2 shrink-0 pb-1 flex-wrap">
            <select onchange="var u=new URL(location);u.searchParams.set('window',this.value);location=u"
                class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                <option value="7d" @selected($window === '7d')>{{ __('Last 7 days') }}</option>
                <option value="30d" @selected($window === '30d')>{{ __('Last 30 days') }}</option>
                <option value="90d" @selected($window === '90d')>{{ __('Last 90 days') }}</option>
                <option value="1y" @selected($window === '1y')>{{ __('Last year') }}</option>
            </select>
            <a href="{{ route('admin.invoices') }}" class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Invoices') }}</a>
            <a href="{{ route('admin.orders') }}" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-medium hover:bg-wa-teal">{{ __('Order history') }}</a>
        </div>
    </div>

    <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
            <div class="text-[11px] text-ink-500">{{ __('Revenue (window)') }}</div>
            <div class="font-semibold text-[22px] mt-2">{{ $kpis['revenue']['display'] }}</div>
            <div class="mt-2 inline-flex items-center gap-1 rounded-full {{ $kpis['revenue']['positive'] ? 'bg-wa-bubble text-wa-deep' : 'bg-accent-coral/10 text-accent-coral' }} px-2 py-0.5 text-[10px] font-mono">
                {{ ($kpis['revenue']['positive'] ? '+' : '') . number_format($kpis['revenue']['delta'], 2) }}%
            </div>
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
            <div class="text-[11px] text-ink-500">{{ __('MRR (active)') }}</div>
            <div class="font-semibold text-[22px] mt-2">{{ $kpis['mrr']['display'] }}</div>
            <div class="mt-2 text-[10.5px] text-ink-400">{{ __('Snapshot now') }}</div>
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
            <div class="text-[11px] text-ink-500">{{ __('ARR (projected)') }}</div>
            <div class="font-semibold text-[22px] mt-2">{{ $kpis['arr']['display'] }}</div>
            <div class="mt-2 text-[10.5px] text-ink-400">{{ __('MRR × 12') }}</div>
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
            <div class="text-[11px] text-ink-500">{{ __('Refunds') }}</div>
            <div class="font-semibold text-[22px] mt-2">{{ $kpis['refunds']['display'] }}</div>
            <div class="mt-2 text-[10.5px] text-ink-400">{{ __('In window') }}</div>
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
            <div class="text-[11px] text-ink-500">{{ __('Outstanding') }}</div>
            <div class="font-semibold text-[22px] mt-2">{{ $kpis['outstanding']['display'] }}</div>
            <div class="mt-2 text-[10.5px] text-ink-400">{{ __('Pending orders') }}</div>
        </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-8 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-start justify-between gap-4 mb-3">
                <div>
                    <h2 class="font-semibold text-[14px]">{{ __('Revenue over time') }}</h2>
                    <p class="text-[11px] text-ink-500 mt-1">{{ __('Paid orders') }} · {{ $revenueDailyMeta['total'] }}</p>
                </div>
            </div>
            <div id="financial-revenue-chart"></div>
        </div>
        <div class="lg:col-span-4 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <h2 class="font-semibold text-[14px]">{{ __('Payment methods') }}</h2>
            <p class="text-[11px] text-ink-500 mt-1">{{ __('Share by gateway') }}</p>
            <div id="financial-gateway-chart"></div>
        </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-8 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-[14px]">{{ __('Top paying customers') }}</h2>
                    <p class="text-[11px] text-ink-500 mt-1">{{ __('Highest spend in window') }}</p>
                </div>
                <a href="{{ route('admin.users') }}" class="rounded-full border border-paper-200 px-3 py-1.5 text-[11.5px] font-semibold hover:bg-paper-50">{{ __('All users') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[440px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-3">{{ __('Customer') }}</th>
                            <th class="text-left px-3 py-3 w-[100px]">{{ __('Orders') }}</th>
                            <th class="text-left px-4 py-3 w-[140px]">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($topCustomers as $c)
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-4 py-3 font-semibold">{{ $c['name'] }}</td>
                                <td class="px-3 py-3 font-mono">{{ $c['orders'] }}</td>
                                <td class="px-4 py-3 font-mono">{{ $c['total'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-ink-500">{{ __('No paid orders yet in this window.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="lg:col-span-4 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <h2 class="font-semibold text-[14px]">{{ __('Order status') }}</h2>
            <p class="text-[11px] text-ink-500 mt-1">{{ __('Mix in window') }}</p>
            <div id="financial-status-chart"></div>
        </div>
    </section>

    <section class="bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
        <div class="px-5 py-4 border-b border-paper-200">
            <h2 class="font-semibold text-[14px]">{{ __('Recent orders') }}</h2>
            <p class="text-[11px] text-ink-500 mt-1">{{ __('Last 8 from the platform') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] min-w-[640px]">
                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-4 py-3 w-[170px]">{{ __('Order #') }}</th>
                        <th class="text-left px-3 py-3">{{ __('Customer') }}</th>
                        <th class="text-left px-3 py-3 w-[130px]">{{ __('Amount') }}</th>
                        <th class="text-left px-3 py-3 w-[110px]">{{ __('Status') }}</th>
                        <th class="text-left px-4 py-3 w-[130px]">{{ __('When') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @forelse ($recentOrders as $o)
                        @php $tone = match ($o['status']) {
                            'paid' => 'bg-wa-bubble text-wa-deep',
                            'pending' => 'bg-accent-amber/10 text-accent-amber',
                            'failed' => 'bg-accent-coral/10 text-accent-coral',
                            default => 'bg-paper-100 text-ink-600',
                        }; @endphp
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 font-mono text-[11.5px]">{{ $o['number'] }}</td>
                            <td class="px-3 py-3">{{ $o['customer'] }}</td>
                            <td class="px-3 py-3 font-mono">{{ $o['amount'] }}</td>
                            <td class="px-3 py-3"><span class="px-2 py-1 rounded-full {{ $tone }} text-[10px] font-semibold">{{ ucfirst($o['status']) }}</span></td>
                            <td class="px-4 py-3 text-ink-500">{{ $o['date'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-ink-500">{{ __('No orders yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <script>
        window.IG_CURRENCY = @json($cur);
        window.adminFinancial = {
            revenueDaily: @json($revenueDaily),
            refundsDaily: @json($refundsDaily),
            gateways: @json($gateways),
            statusMix: @json($statusMix),
        };
    </script>
</main>

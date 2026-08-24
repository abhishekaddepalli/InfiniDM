<x-layouts.admin :title="__('Admin · Order history')" admin-key="orders">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Order history') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Billing & plans') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Order') }}
                    <span class="italic ig-text">{{ __('history') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Every order placed through an Instagram DM checkout flow — placed, paid, and dispatched — with the gateway reference behind each payment.') }}
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif

        <section class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total orders') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('all time') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Paid') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['paid']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">
                    {{ $stats['total'] > 0 ? round($stats['paid'] / $stats['total'] * 100, 1) : 0 }}% {{ __('conversion') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Revenue') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['currency'] }} {{ number_format($stats['revenue'], 2) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('from paid orders') }}</div>
            </div>
        </section>

        {{-- Search bar --}}
        <form method="get" action="{{ route('admin.orders') }}"
            class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-2 flex flex-wrap items-center gap-1 shadow-card">
            <div class="flex-1 min-w-0"></div>
            <div class="relative">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5" /><path d="m11 11 3 3" /></svg>
                <input name="q" value="{{ $q }}" placeholder="{{ __('Search ref, customer, payment…') }}"
                    class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full max-w-72 sm:w-72 focus:outline-none focus:border-wa-deep" />
            </div>
        </form>

        {{-- Orders table --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[760px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-3 py-2.5 w-[150px]">{{ __('Ref') }}</th>
                            <th class="text-left px-2 py-2.5">{{ __('Customer') }}</th>
                            <th class="text-right px-2 py-2.5 w-[130px]">{{ __('Amount') }}</th>
                            <th class="text-center px-2 py-2.5 w-[100px]">{{ __('Payment') }}</th>
                            <th class="text-center px-2 py-2.5 w-[100px]">{{ __('Status') }}</th>
                            <th class="text-right px-2 py-2.5 w-[120px]">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($orders as $o)
                            @php
                                $payClass = match ($o->payment_status) {
                                    'paid' => 'bg-wa-mint text-wa-deep',
                                    'pending' => 'bg-accent-amber/15 text-accent-amber',
                                    default => 'bg-paper-100 text-ink-500',
                                };
                                $statusClass = match ($o->status) {
                                    'paid', 'dispatched' => 'bg-wa-mint text-wa-deep',
                                    'placed', 'pending' => 'bg-accent-amber/15 text-accent-amber',
                                    default => 'bg-paper-100 text-ink-500',
                                };
                            @endphp
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-3 py-2.5 font-mono font-semibold text-[11.5px]">{{ $o->order_ref }}</td>
                                <td class="px-2 py-2.5 min-w-0">
                                    <div class="font-semibold leading-tight text-[12.5px] truncate">{{ $o->customer_name ?: __('—') }}</div>
                                    @if ($o->customer_phone)
                                        <div class="text-[10.5px] text-ink-500 mt-0.5 font-mono truncate">{{ $o->customer_phone }}</div>
                                    @endif
                                </td>
                                <td class="px-2 py-2.5 text-right font-mono whitespace-nowrap">{{ strtoupper($o->currency) }} {{ number_format((float) $o->total, 2) }}</td>
                                <td class="px-2 py-2.5 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full {{ $payClass }} text-[10.5px] font-mono">{{ $o->payment_status ?: __('—') }}</span>
                                </td>
                                <td class="px-2 py-2.5 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full {{ $statusClass }} text-[10.5px] font-mono">{{ $o->status ?: __('—') }}</span>
                                </td>
                                <td class="px-2 py-2.5 text-right font-mono text-[10.5px] text-ink-500 whitespace-nowrap">{{ $o->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-ink-500">{{ __('No orders yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 flex flex-wrap gap-2 items-center justify-between rounded-b-2xl">
                <div class="text-[11px] font-mono text-ink-500">
                    {{ __('Showing') }} {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} {{ __('of') }} {{ number_format($orders->total()) }} {{ __('orders') }}
                </div>
                <div>{{ $orders->onEachSide(1)->links() }}</div>
            </div>
        </div>
    </main>

</x-layouts.admin>

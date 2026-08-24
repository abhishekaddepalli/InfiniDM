<x-layouts.admin :title="__('Admin · Plan orders')" admin-key="plan-orders">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Plan orders') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Billing & plans') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Plan') }} <span class="italic ig-text">{{ __('orders') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Plan purchases paid by an offline or bank-transfer gateway sit here as “pending” until you approve them. Approving activates the plan on the buyer; online gateways settle automatically and never need review.') }}</p>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-2 text-[12.5px]">{{ session('error') }}</div>
        @endif

        <section class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Awaiting approval') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['pending']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('pending orders') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Paid') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['paid']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('all time') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Revenue') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['currency'] }} {{ number_format($stats['revenue'], 2) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('from paid orders') }}</div>
            </div>
        </section>

        {{-- Status filter + search --}}
        <form method="get" action="{{ route('admin.plan-orders') }}" class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-2 flex flex-wrap items-center gap-1.5 shadow-card">
            @php $tabs = ['pending' => __('Pending'), 'paid' => __('Paid'), 'failed' => __('Rejected'), 'all' => __('All')]; @endphp
            @foreach ($tabs as $key => $label)
                <button name="status" value="{{ $key }}" class="px-3 py-1.5 rounded-full text-[12px] font-medium {{ $filter === $key ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">{{ $label }}</button>
            @endforeach
            <div class="flex-1 min-w-0"></div>
            <input name="q" value="{{ request('q') }}" placeholder="{{ __('Search order #, email…') }}"
                   class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 w-full max-w-64 sm:w-64 focus:outline-none focus:border-wa-deep" />
        </form>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead class="bg-paper-50 text-ink-500 text-[10.5px] font-mono uppercase tracking-[0.12em]">
                        <tr>
                            <th class="text-left px-4 py-3">{{ __('Order') }}</th>
                            <th class="text-left px-4 py-3">{{ __('Buyer') }}</th>
                            <th class="text-left px-4 py-3">{{ __('Plan') }}</th>
                            <th class="text-left px-4 py-3">{{ __('Amount') }}</th>
                            <th class="text-left px-4 py-3">{{ __('Status') }}</th>
                            <th class="text-right px-4 py-3">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($orders as $o)
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    <div class="font-mono text-[12px] text-ink-900">{{ $o->order_number }}</div>
                                    <div class="text-[11px] text-ink-500 mt-0.5">{{ $o->created_at?->format('d M Y, H:i') }}</div>
                                    @if ($o->gateway_slug)<div class="text-[10.5px] text-ink-500 mt-0.5 uppercase tracking-wide">{{ $o->gateway_slug }}</div>@endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-ink-900">{{ $o->customer_name ?: ($o->user->name ?? '—') }}</div>
                                    <div class="text-[11px] text-ink-500">{{ $o->customer_email ?: ($o->user->email ?? '') }}</div>
                                    @if ($o->payment_reference)<div class="text-[11px] text-ink-500 mt-0.5">{{ __('Ref') }}: {{ $o->payment_reference }}</div>@endif
                                    @if ($o->payment_proof_path)
                                        <a href="{{ brand_asset($o->payment_proof_path) ?? asset('storage/'.$o->payment_proof_path) }}" target="_blank" class="text-[11px] ig-text font-medium">{{ __('View proof') }} ↗</a>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $o->package->name ?? '—' }}</td>
                                <td class="px-4 py-3 font-medium">{{ $o->currency }} {{ number_format((float) ($o->total_amount ?? $o->amount), 2) }}</td>
                                <td class="px-4 py-3">
                                    @php $s = $o->status; @endphp
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10.5px] font-semibold {{ $s === 'paid' ? 'bg-wa-bubble text-wa-deep' : ($s === 'pending' ? 'bg-accent-amber/15 text-accent-amber' : 'bg-accent-coral/10 text-accent-coral') }}">
                                        {{ $s === 'failed' ? __('rejected') : $s }}
                                    </span>
                                    @if ($o->reviewed_at)<div class="text-[10.5px] text-ink-500 mt-1">{{ __('by admin') }} · {{ $o->reviewed_at->format('d M') }}</div>@endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($o->status === 'pending')
                                        <div class="flex flex-col items-end gap-1.5">
                                            <form method="post" action="{{ route('admin.plan-orders.approve', $o->id) }}" class="flex items-center gap-1.5">
                                                @csrf
                                                <input name="reference" value="{{ $o->payment_reference }}" placeholder="{{ __('Payment ref') }}"
                                                       class="hairline border border-paper-200 rounded-lg px-2 py-1 text-[11px] bg-paper-0 w-28 focus:outline-none focus:border-wa-deep" />
                                                <button class="px-3 py-1.5 rounded-lg bg-wa-deep text-paper-0 text-[11.5px] font-semibold hover:bg-wa-teal whitespace-nowrap">{{ __('Approve') }}</button>
                                            </form>
                                            <form method="post" action="{{ route('admin.plan-orders.reject', $o->id) }}"
                                                  onsubmit="return confirm('{{ __('Reject this order? The plan will not be applied.') }}')">
                                                @csrf
                                                <button class="px-3 py-1 rounded-lg border border-paper-200 text-ink-600 text-[11px] hover:bg-paper-50">{{ __('Reject') }}</button>
                                            </form>
                                        </div>
                                    @else
                                        <div class="text-right text-[11px] text-ink-400">—</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-ink-500 text-[13px]">{{ __('No orders in this view.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div>{{ $orders->links() }}</div>

    </main>

</x-layouts.admin>

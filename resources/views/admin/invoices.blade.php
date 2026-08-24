<x-layouts.admin :title="__('Admin · Invoices')" admin-key="invoices">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Invoices') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Billing & plans') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Paid') }}
                    <span class="italic ig-text">{{ __('invoices') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Every settled order — the paid trail of your Instagram DM checkout revenue, ready to reconcile against gateway payouts.') }}
                </p>
            </div>
            <button type="button" data-open-invoice-modal
                class="inline-flex items-center gap-2 rounded-full ig-grad text-white px-5 py-2.5 text-[12.5px] font-semibold shadow-card hover:opacity-95">
                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 3v10M3 8h10" stroke-linecap="round"/></svg>
                {{ __('Create manual invoice') }}
            </button>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-accent-red/40 bg-accent-red/10 text-accent-red px-4 py-2 text-[12.5px]">
                {{ $errors->first() }}</div>
        @endif

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif

        <section class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total invoices') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('all time') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Revenue') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['currency'] }} {{ number_format($stats['revenue'], 2) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('collected') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('This month') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['thisMonth']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('paid this month') }}</div>
            </div>
        </section>

        {{-- Search bar --}}
        <form method="get" action="{{ route('admin.invoices') }}"
            class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-2 flex flex-wrap items-center gap-1 shadow-card">
            <div class="flex-1 min-w-0"></div>
            <div class="relative">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5" /><path d="m11 11 3 3" /></svg>
                <input name="q" value="{{ $q }}" placeholder="{{ __('Search ref, customer, payment…') }}"
                    class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full max-w-72 sm:w-72 focus:outline-none focus:border-wa-deep" />
            </div>
        </form>

        {{-- Invoices table --}}
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
                            <th class="text-right px-2 py-2.5 w-[110px]">{{ __('Date') }}</th>
                            <th class="text-right px-3 py-2.5 w-[130px]">{{ __('Invoice') }}</th>
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
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.invoices.view', $o) }}" target="_blank"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold ig-text hover:underline" title="{{ __('View invoice') }}">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 8s2.5-4.5 7-4.5S15 8 15 8s-2.5 4.5-7 4.5S1 8 1 8Z"/><circle cx="8" cy="8" r="2"/></svg>
                                        {{ __('View') }}
                                    </a>
                                    <a href="{{ route('admin.invoices.view', ['order' => $o, 'download' => 1]) }}" target="_blank"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-ink-500 hover:text-ink-900 ml-2" title="{{ __('Download / print') }}">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 2v8m0 0 3-3m-3 3L5 7M2.5 12.5h11" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        {{ __('PDF') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-ink-500">{{ __('No paid invoices yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 flex flex-wrap gap-2 items-center justify-between rounded-b-2xl">
                <div class="text-[11px] font-mono text-ink-500">
                    {{ __('Showing') }} {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} {{ __('of') }} {{ number_format($orders->total()) }} {{ __('invoices') }}
                </div>
                <div>{{ $orders->onEachSide(1)->links() }}</div>
            </div>
        </div>
    </main>

    {{-- Create manual invoice modal --}}
    <div id="ig-invoice-modal" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-sm" data-close-invoice-modal></div>
        <div class="absolute inset-0 flex items-start sm:items-center justify-center p-4 overflow-y-auto">
            <div class="relative bg-paper-0 border border-paper-200 rounded-2xl shadow-xl w-full max-w-2xl my-6">
                <form method="post" action="{{ route('admin.invoices.store') }}" id="ig-invoice-form">
                    @csrf
                    @if ($errors->any())<span data-server-open class="hidden"></span>@endif
                    <div class="flex items-center justify-between px-5 py-4 border-b border-paper-200">
                        <div>
                            <h2 class="font-serif text-[20px] leading-tight">{{ __('New manual invoice') }}</h2>
                            <p class="text-[11.5px] text-ink-500 mt-0.5">{{ __('Record an off-platform paid sale. It appears in this list and prints like any invoice.') }}</p>
                        </div>
                        <button type="button" data-close-invoice-modal class="text-ink-400 hover:text-ink-900">
                            <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/></svg>
                        </button>
                    </div>

                    <div class="px-5 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-medium text-ink-600 mb-1">{{ __('Customer name') }} <span class="text-accent-red">*</span></label>
                                <input name="customer_name" required value="{{ old('customer_name') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-ink-600 mb-1">{{ __('Customer email') }}</label>
                                <input name="customer_email" type="email" value="{{ old('customer_email') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-ink-600 mb-1">{{ __('Phone') }}</label>
                                <input name="customer_phone" value="{{ old('customer_phone') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-ink-600 mb-1">{{ __('Currency') }}</label>
                                <select name="currency" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep">
                                    @foreach ($currencies as $code)
                                        <option value="{{ $code }}" @selected(old('currency') === $code)>{{ $code }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-[11px] font-medium text-ink-600">{{ __('Line items') }} <span class="text-accent-red">*</span></label>
                                <button type="button" data-add-invoice-line class="text-[11px] font-semibold ig-text hover:underline">+ {{ __('Add line') }}</button>
                            </div>
                            <div id="ig-invoice-lines" class="space-y-2">
                                <div class="ig-invoice-line grid grid-cols-12 gap-2 items-center">
                                    <input name="items[0][name]" placeholder="{{ __('Description') }}" class="col-span-6 rounded-xl border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep" />
                                    <input name="items[0][qty]" type="number" min="1" value="1" data-line-qty class="col-span-2 rounded-xl border border-paper-200 bg-paper-0 px-2 py-2 text-[13px] text-center focus:outline-none focus:border-wa-deep" />
                                    <input name="items[0][price]" type="number" min="0" step="0.01" placeholder="0.00" data-line-price class="col-span-3 rounded-xl border border-paper-200 bg-paper-0 px-2 py-2 text-[13px] text-right focus:outline-none focus:border-wa-deep" />
                                    <button type="button" data-remove-invoice-line class="col-span-1 text-ink-400 hover:text-accent-red flex justify-center">
                                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10M6.5 4V3h3v1M5 4l.5 9h5L11 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="flex justify-end mt-2 text-[12.5px] text-ink-600">
                                {{ __('Total') }}: <span class="font-mono font-semibold ml-2" id="ig-invoice-total">0.00</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-medium text-ink-600 mb-1">{{ __('Note (optional)') }}</label>
                            <textarea name="note" rows="2" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep">{{ old('note') }}</textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-paper-200">
                        <button type="button" data-close-invoice-modal class="rounded-full px-4 py-2 text-[12.5px] font-medium text-ink-600 hover:bg-paper-100">{{ __('Cancel') }}</button>
                        <button type="submit" class="rounded-full ig-grad text-white px-5 py-2 text-[12.5px] font-semibold hover:opacity-95">{{ __('Create invoice') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ url('assets/admin-invoice-modal.js') }}?v=1" defer></script>
    @endpush

</x-layouts.admin>

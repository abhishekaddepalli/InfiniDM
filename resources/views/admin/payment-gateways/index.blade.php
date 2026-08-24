<x-layouts.admin :title="__('Payment gateways')" admin-key="payment-gateways" page="admin-payment-gateways-index">

    @php
        /**
         * Bucket gateways by region / type so the page isn't a 32-item dump.
         * Each row can belong to several categories; "other" is the catch-all so
         * a new driver never disappears off the page.
         */
        $catMap = [
            'popular' => ['stripe', 'paypal', 'razorpay', 'paddle', 'lemonsqueezy', 'square', 'braintree', 'mollie', 'authorize_net'],
            'india'   => ['razorpay', 'instamojo', 'cashfree', 'paytm', 'phonepe', 'payu'],
            'asia'    => ['xendit', 'midtrans', 'duitku', 'paytm', 'phonepe', 'sslcommerz', 'hyperpay', 'tap'],
            'africa'  => ['paystack', 'flutterwave', 'cinetpay'],
            'latam'   => ['mercadopago'],
            'mena'    => ['hyperpay', 'tap', 'paytr', 'iyzico', 'fondy'],
            'europe'  => ['mollie', 'paddle', 'stripe', 'paytr', 'iyzico', 'fondy'],
            'us'      => ['stripe', 'paypal', 'square', 'braintree', 'authorize_net', 'twocheckout'],
            'crypto'  => ['coinbase'],
            'offline' => ['offline', 'bank_transfer'],
        ];
        $catLabels = [
            'all'     => ['All', 'Every gateway available'],
            'popular' => ['Popular', 'The big global ones — Stripe / PayPal / Razorpay etc.'],
            'india'   => ['India', 'Razorpay, Paytm, PhonePe, Cashfree, PayU, Instamojo'],
            'asia'    => ['Asia', 'Xendit, Midtrans, SSLCommerz, HyperPay, Tap'],
            'mena'    => ['MENA', 'HyperPay, Tap, PayTR, iyzico, Fondy'],
            'europe'  => ['Europe', 'Mollie, Paddle, Stripe, regional'],
            'us'      => ['US / CA', 'Stripe, PayPal, Square, Braintree, Authorize.Net, 2Checkout'],
            'africa'  => ['Africa', 'Paystack, Flutterwave, CinetPay'],
            'latam'   => ['LATAM', 'Mercado Pago'],
            'crypto'  => ['Crypto', 'Coinbase Commerce'],
            'offline' => ['Offline', 'Bank transfer / cash on delivery'],
        ];
        // Counts + per-card category list for the filter.
        $catCounts = ['all' => $gateways->count()];
        foreach ($catMap as $cat => $slugs) {
            $catCounts[$cat] = $gateways->filter(fn ($g) => in_array($g->slug, $slugs, true))->count();
        }
        $gatewayCats = $gateways->mapWithKeys(function ($g) use ($catMap) {
            $cats = ['all'];
            foreach ($catMap as $cat => $slugs) {
                if (in_array($g->slug, $slugs, true)) {
                    $cats[] = $cat;
                }
            }
            if (count($cats) === 1) {
                $cats[] = 'other';
            }
            return [$g->id => $cats];
        });
        $activeCount = $gateways->where('is_active', true)->count();

        // Active tab pill / inactive tab pill — plain classes (JS swaps them).
        $tabOn  = 'bg-wa-deep text-paper-0';
        $tabOff = 'text-ink-700 hover:bg-paper-50';
        // Selected / unselected currency pill — plain classes (JS swaps them).
        $pillOn  = 'bg-wa-deep text-paper-0 border-wa-deep';
        $pillOff = 'bg-paper-0 text-ink-700 border-paper-200 hover:border-wa-deep';
    @endphp

    <header class="h-16 bg-paper-0 border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Payment gateways') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Billing · Gateways') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Payment') }}
                <span class="italic ig-text">{{ __('gateways') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                {{ $gateways->count() }} {{ __('drivers shipped — split by region / type so you only fill in the ones your customers will actually use. Credentials are encrypted at rest. Workspaces only see gateways that accept their currency.') }}
            </p>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-accent-coral/30 bg-accent-coral/10 text-accent-coral px-4 py-2 text-[12.5px]">
                {{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-2xl border border-accent-coral/30 bg-accent-coral/10 text-accent-coral px-4 py-2 text-[12.5px]">
                {{ $errors->first() }}</div>
        @endif

        {{-- KPI strip --}}
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $gateways->count() }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('drivers available') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $activeCount }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('accepting payments') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('India region') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $catCounts['india'] ?? 0 }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('Razorpay, Paytm, Cashfree…') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Crypto') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $catCounts['crypto'] ?? 0 }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('on-chain settlement') }}</div>
            </div>
        </section>

        {{-- Category tabs + search --}}
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-3 py-2 border-b border-paper-200 flex items-center gap-1.5 flex-wrap" role="tablist"
                aria-label="{{ __('Filter by region') }}">
                @foreach ($catLabels as $key => [$label, $hint])
                    <button type="button" data-gateway-cat="{{ $key }}"
                        data-on-class="{{ $tabOn }}" data-off-class="{{ $tabOff }}"
                        class="px-3 py-1.5 rounded-full text-[11.5px] font-medium border border-transparent transition {{ $key === 'all' ? $tabOn : $tabOff }}"
                        aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}" title="{{ $hint }}">
                        {{ $label }}
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[9.5px] font-mono bg-paper-100 text-ink-700 align-middle">{{ $catCounts[$key] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
            <div class="px-3 py-2 border-t border-paper-200 bg-paper-50/40">
                <input id="gateway-search" type="search" placeholder="{{ __('Filter by name or slug…') }}"
                    class="w-full px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
            </div>
        </section>

        {{-- Gateway cards. Server renders ALL; the page module filters via data-cat
             + data-search and expands the credentials form on header click. --}}
        @forelse ($gateways as $g)
            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden"
                data-gateway-card data-cat="{{ implode(' ', $gatewayCats[$g->id] ?? ['all']) }}"
                data-search="{{ strtolower($g->name . ' ' . $g->slug) }}">
                <div data-gateway-head
                    class="px-5 py-4 border-b border-paper-200 flex items-center gap-3 flex-wrap cursor-pointer hover:bg-paper-50/60">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-serif text-[20px] leading-tight">{{ $g->name }}</h3>
                            <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full border {{ $g->is_active ? 'bg-wa-mint text-wa-deep border-wa-green/40' : 'bg-paper-100 text-ink-700 border-paper-200' }}">{{ $g->is_active ? __('Active') : __('Disabled') }}</span>
                            <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full border {{ $g->mode === 'live' ? 'bg-accent-coral/10 text-accent-coral border-accent-coral/40' : 'bg-accent-amber/10 text-accent-amber border-accent-amber/40' }}">{{ $g->mode }}</span>
                            @php $hasCreds = ! empty(array_filter($g->credentials_decrypted ?? [])); @endphp
                            @if ($hasCreds)
                                <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full bg-paper-100 text-ink-700">{{ __('Keys saved') }}</span>
                            @endif
                            @foreach (array_slice(array_filter($gatewayCats[$g->id] ?? [], fn ($c) => $c !== 'all'), 0, 3) as $cat)
                                <span class="text-[9.5px] font-mono uppercase px-1.5 py-0.5 rounded-full bg-paper-50 text-ink-500 border border-paper-200">{{ $catLabels[$cat][0] ?? $cat }}</span>
                            @endforeach
                        </div>
                        <div class="text-[11.5px] text-ink-500 mt-0.5 font-mono">slug: {{ $g->slug }}{{ $g->description ? ' · ' . $g->description : '' }}</div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <form method="POST" action="{{ route('admin.payment-gateways.toggle', $g->id) }}" class="inline">@csrf
                            <button class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-semibold">{{ $g->is_active ? __('Disable') : __('Activate') }}</button>
                        </form>
                        <svg data-gateway-chev viewBox="0 0 16 16"
                            class="w-3.5 h-3.5 text-ink-500 transition-transform duration-150" fill="none"
                            stroke="currentColor" stroke-width="1.7">
                            <path d="M4 6l4 4 4-4" />
                        </svg>
                    </div>
                </div>

                <form data-gateway-body method="POST" action="{{ route('admin.payment-gateways.update', $g->id) }}"
                    class="p-5 space-y-4 hidden">
                    @csrf @method('PATCH')

                    @if (! empty($g->credential_fields))
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach ($g->credential_fields as $key => $spec)
                                @php $existingVal = $g->credentials_decrypted[$key] ?? ''; @endphp
                                <label class="text-[12px] text-ink-700 {{ ($spec['type'] ?? 'text') === 'textarea' ? 'sm:col-span-2' : '' }}">
                                    {{ $spec['label'] ?? $key }}
                                    @if (! empty($spec['required']))
                                        <span class="text-accent-coral">*</span>
                                    @endif
                                    @if (($spec['type'] ?? 'text') === 'textarea')
                                        <textarea name="credentials[{{ $key }}]" rows="2"
                                            class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep">{{ $existingVal }}</textarea>
                                    @elseif (($spec['type'] ?? 'text') === 'password')
                                        {{-- Stored secret is NEVER preloaded into the DOM. Field stays empty;
                                             placeholder signals a value is on file. The controller merges
                                             blank-as-keep so leaving this empty preserves the credential. --}}
                                        <input type="password" name="credentials[{{ $key }}]" value=""
                                            placeholder="{{ $existingVal ? '•••••••••• ' . __('(saved — leave blank to keep)') : ($spec['placeholder'] ?? '') }}"
                                            autocomplete="new-password"
                                            class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    @elseif (($spec['type'] ?? 'text') === 'select')
                                        <select name="credentials[{{ $key }}]"
                                            class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                            @foreach ($spec['options'] ?? [] as $optVal => $optLabel)
                                                <option value="{{ $optVal }}" @selected((string) $existingVal === (string) $optVal)>{{ $optLabel }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" name="credentials[{{ $key }}]" value="{{ $existingVal }}"
                                            class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep"
                                            @if (! empty($spec['placeholder'])) placeholder="{{ $spec['placeholder'] }}" @endif>
                                    @endif
                                    @if (! empty($spec['hint']))
                                        <span class="block text-[10px] text-ink-500 mt-0.5">{{ $spec['hint'] }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-paper-200 bg-paper-50 px-4 py-3 text-[12px] text-ink-500">
                            {{ __('No credentials required — gateway uses manual / out-of-band confirmation.') }}
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-paper-100 pt-4">
                        <label class="text-[12px] text-ink-700">{{ __('Mode') }}
                            <select name="mode"
                                class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                <option value="sandbox" @selected($g->mode === 'sandbox')>{{ __('Sandbox') }}</option>
                                <option value="live" @selected($g->mode === 'live')>{{ __('Live') }}</option>
                            </select>
                        </label>
                        <label class="text-[12px] text-ink-700">{{ __('Sort order') }}
                            <input type="number" name="sort_order" value="{{ $g->sort_order }}" min="0"
                                class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                        </label>
                        <div class="text-[12px] text-ink-700 col-span-1">
                            <div class="flex items-center justify-between mb-1.5">
                                <span>{{ __('Supported currencies') }} <span class="font-mono text-[10px] text-ink-500">{{ __('(none = all)') }}</span></span>
                                <span class="font-mono text-[10px] text-ink-500"><span data-currency-count>0</span> {{ __('selected') }}</span>
                            </div>
                            {{-- Pill-grid currency picker. Each pill wraps a hidden checkbox so
                                 the form still submits a plain supported_currencies[] array. --}}
                            <div data-currency-pills
                                class="flex flex-wrap gap-1.5 overflow-y-auto p-2 border border-paper-200 rounded-lg bg-paper-50/40"
                                style="max-height:180px">
                                @foreach ($currencies as $c)
                                    @php $isSel = in_array($c->code, $g->supported_currencies ?? []); @endphp
                                    <label data-currency-pill data-on-class="{{ $pillOn }}" data-off-class="{{ $pillOff }}"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-full border text-[11px] font-mono cursor-pointer transition select-none {{ $isSel ? $pillOn : $pillOff }}">
                                        <input type="checkbox" name="supported_currencies[]" value="{{ $c->code }}" @checked($isSel) class="sr-only">
                                        {{ $c->code }}
                                    </label>
                                @endforeach
                            </div>
                            <span class="block text-[10px] text-ink-500 mt-1">{{ __('Click to toggle. Leave blank to accept every currency.') }}</span>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">{{ __('Save') }} {{ $g->name }}</button>
                    </div>
                </form>
            </section>
        @empty
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-12 text-center">
                <div class="font-serif text-[22px] text-ink-700">{{ __('No payment gateways found') }}</div>
                <p class="text-[12.5px] text-ink-500 mt-2">{{ __('Run') }} <code class="bg-paper-100 px-1.5 py-0.5 rounded font-mono text-[11px]">php artisan db:seed --class=PaymentGatewaySeeder</code>.</p>
            </div>
        @endforelse

        <div id="gw-empty-state" class="hidden bg-paper-50 border border-dashed border-paper-200 rounded-2xl p-8 text-center">
            <div class="font-serif text-[18px] text-ink-700">{{ __('No gateways in this category') }}</div>
            <div class="text-[11.5px] text-ink-500 mt-1">{{ __('Try "All" or use the search above.') }}</div>
        </div>

    </main>

    {{-- Tabs, search, collapsible cards + currency pills are wired by
         public/extensions/instagram/admin-payment-gateways-index.js, auto-loaded
         by the admin layout when page="admin-payment-gateways-index". No inline JS. --}}

</x-layouts.admin>

{{--
    One-time plan checkout — ported from WaDesk's checkout/show.blade.php,
    recoloured to the Instagram theme. Full WaDesk layout: stepped billing form,
    a "Pay in your currency" switcher (live AJAX re-price + gateway re-filter),
    gateway picker, and a sticky order summary. No recurring / auto-renew.
--}}
<x-layouts.instagram :title="__('Checkout')" ig-active="plans">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-10 max-w-[1100px]">

        @php
            $user = auth()->user();
            $amountRaw = (float) $amount;
            $taxPct = (float) ($taxRate ?? 0);
            $tax = round(($amountRaw * $taxPct) / 100, 2);
            $total = round($amountRaw + $tax, 2);
            $billed = ($package->plan_unit && $package->plan_duration)
                ? $package->plan_duration . ' ' . $package->plan_unit
                : 'one-time';
            $currencyFn = fn ($n) => \App\Support\FormatSettings::formatIn($n, $currency);
        @endphp

        {{-- breadcrumb stepper --}}
        <ol class="flex items-center gap-3 text-[11px] font-mono uppercase tracking-[0.16em] text-ink-500 mb-6">
            <li class="text-wa-deep flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[10px]">1</span>{{ __('Choose plan') }}</li>
            <li class="w-6 h-px bg-paper-300"></li>
            <li class="text-ink-900 flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-ink-900 text-paper-0 grid place-items-center text-[10px]">2</span>{{ __('Checkout') }}</li>
            <li class="w-6 h-px bg-paper-200"></li>
            <li class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center text-[10px]">3</span>{{ __('Confirmation') }}</li>
        </ol>

        <h1 class="font-serif text-[30px] sm:text-[36px] lg:text-[44px] leading-none tracking-[-0.01em]">{{ __('Complete your') }} <span class="italic ig-text">{{ __('order') }}</span></h1>
        <p class="text-[13px] text-ink-600 mt-2 max-w-xl">{{ __('Secure checkout. Cards, UPI, netbanking and wallets accepted — pay in your own currency.') }}</p>

        @if (session('error'))
            <div class="mt-5 bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2 text-[12.5px] text-accent-coral">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('checkout.pay', $package->id) }}" id="checkout-form" class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 mt-6">
            @csrf
            <input type="hidden" name="currency" id="currency-input" value="{{ $currency }}">

            {{-- LEFT: form --}}
            <div class="space-y-5">

                {{-- Step 1 · Account --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Step 1') }}</div>
                    <h2 class="font-serif text-[20px] leading-tight mt-0.5 mb-4">{{ __('Account') }}</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Full name') }} <span class="text-accent-coral">*</span></label>
                            <input required type="text" name="customer_name" value="{{ old('customer_name', $user?->name) }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Email') }} <span class="text-accent-coral">*</span></label>
                            <input required type="email" name="customer_email" value="{{ old('customer_email', $user?->email) }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                    </div>
                </div>

                {{-- Step 2 · Billing --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Step 2') }}</div>
                    <h2 class="font-serif text-[20px] leading-tight mt-0.5 mb-4">{{ __('Billing address') }}</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Company (optional)') }}</label>
                            <input type="text" name="billing_company" value="{{ old('billing_company') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Address') }}</label>
                            <input type="text" name="billing_address" value="{{ old('billing_address') }}" placeholder="{{ __('Street + house number') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('City') }}</label>
                            <input type="text" name="billing_city" value="{{ old('billing_city') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Postal code') }}</label>
                            <input type="text" name="billing_postal" value="{{ old('billing_postal') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Country') }}</label>
                            <select name="billing_country" class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                @foreach ($countries as $country)
                                    <option value="{{ $country }}" @selected(old('billing_country') === $country)>{{ $country }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Tax ID (optional)') }}</label>
                            <input type="text" name="billing_tax_id" value="{{ old('billing_tax_id') }}" placeholder="{{ __('GSTIN / VAT / EIN') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                        </div>
                    </div>
                </div>

                {{-- Currency — only when more than one is available. Changing it
                     re-prices + re-filters gateways live (AJAX), so typed billing
                     fields are never wiped by a reload. --}}
                @if (!$isFree && count($availableCurrencies ?? []) > 1)
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Currency') }}</div>
                        <h2 class="font-serif text-[20px] leading-tight mt-0.5 mb-1">{{ __('Pay in your currency') }}</h2>
                        <p class="text-[12px] text-ink-500 mb-3">{{ __('Prices convert instantly. Only currencies your available payment methods accept are shown.') }}</p>
                        <select id="currency-select" class="w-full max-w-xs px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            @foreach ($availableCurrencies as $code)
                                <option value="{{ strtoupper($code) }}" @selected(strtoupper($code) === strtoupper($currency))>{{ strtoupper($code) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Step 3 · Payment method --}}
                @unless ($isFree)
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Step 3') }}</div>
                        <h2 class="font-serif text-[20px] leading-tight mt-0.5 mb-4">{{ __('Payment method') }}</h2>

                        @if ($gateways->isEmpty())
                            <div class="px-4 py-3 rounded-lg bg-accent-coral/10 border border-accent-coral/40 text-[12.5px] text-accent-coral">
                                {{ __('No payment gateway is configured for') }} <strong>{{ $currency }}</strong> {{ __('yet. Ask your admin to activate one at') }} <span class="font-mono">/admin/payment-gateways</span>.
                            </div>
                        @else
                            <div class="space-y-2" id="gateway-list">
                                @foreach ($gateways as $i => $g)
                                    <label data-gw class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer transition {{ $i === 0 ? 'border-wa-deep bg-wa-mint' : 'border-paper-200 hover:border-wa-deep' }}">
                                        <input type="radio" name="gateway_id" value="{{ $g->id }}" @checked($i === 0) required class="w-4 h-4">
                                        <span class="w-10 h-10 rounded-lg bg-paper-50 border border-paper-200 grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="12" height="9" rx="1.5"/><path d="M2 7h12"/></svg>
                                        </span>
                                        <span class="flex-1 min-w-0">
                                            <span class="block font-semibold text-[13px]">{{ $g->name }}</span>
                                            @if ($g->description)<span class="block text-[11px] text-ink-500 truncate">{{ $g->description }}</span>@endif
                                        </span>
                                        <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full {{ $g->mode === 'live' ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-accent-amber/10 text-accent-amber border border-accent-amber/40' }}">{{ $g->mode }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <div class="flex items-center gap-2 mt-4 px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[10.5px] text-ink-700">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="7" width="10" height="7" rx="1.5"/><path d="M5 7V5a3 3 0 0 1 6 0v2"/></svg>
                                {{ __('Payments are processed over a 256-bit TLS connection. We never see your card details.') }}
                            </div>
                        @endif
                    </div>
                @endunless
            </div>

            {{-- RIGHT: order summary --}}
            <aside class="space-y-3 lg:sticky lg:top-4 self-start">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">{{ __('Order summary') }}</div>

                    <div class="flex items-start gap-3">
                        <span class="w-11 h-11 rounded-xl bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 3l8 5-8 5z"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-[14px]">{{ $package->pname }}</div>
                            <div class="text-[11px] text-ink-500">{{ __('Billed for') }} {{ $billed }}</div>
                            <a href="{{ route('instagram.plans') }}" class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Change') }}</a>
                        </div>
                        <div class="font-serif text-[18px]" id="item-price">{!! $currencyFn($amountRaw) !!}</div>
                    </div>

                    <div class="border-t border-paper-200 mt-4 pt-3 space-y-1.5 text-[12.5px]">
                        <div class="flex items-center justify-between">
                            <span class="text-ink-600">{{ __('Subtotal') }}</span>
                            <span class="font-mono" id="sub-amt" data-base="{{ $amountRaw }}">{!! $currencyFn($amountRaw) !!}</span>
                        </div>
                        @if ($taxPct > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-ink-600">{{ $taxLabel }} ({{ rtrim(rtrim(number_format($taxPct, 2), '0'), '.') }}%)</span>
                                <span class="font-mono" id="tax-amt">{!! $currencyFn($tax) !!}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between border-t border-paper-200 pt-2 mt-2">
                            <span class="font-semibold">{{ __('Total today') }}</span>
                            <span class="font-serif text-[26px] leading-none" id="total-amt">{!! $currencyFn($total) !!}</span>
                        </div>
                    </div>

                    @if ($isFree)
                        <button type="submit" form="checkout-form" class="w-full mt-5 px-4 py-3 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold">{{ __('Activate free plan') }}</button>
                    @elseif ($gateways->isNotEmpty())
                        <button type="submit" form="checkout-form" class="w-full mt-5 px-4 py-3 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="7" width="10" height="7" rx="1.5"/><path d="M5 7V5a3 3 0 0 1 6 0v2"/></svg>
                            {{ __('Pay') }} <span id="cta-amt">{!! $currencyFn($total) !!}</span>
                        </button>
                    @else
                        <button disabled class="w-full mt-5 px-4 py-3 rounded-full bg-paper-100 text-ink-500 text-[13px] font-semibold cursor-not-allowed">{{ __('No gateway configured') }}</button>
                    @endif
                </div>

                @if (($refundDays ?? 0) > 0)
                    <div class="bg-wa-mint border border-wa-green/40 rounded-2xl p-4 text-[11.5px] text-ink-700 leading-relaxed">
                        <div class="font-semibold flex items-center gap-1.5 mb-1 text-wa-deep">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="currentColor"><path d="M3 8l3 3 7-7-1.4-1.4L6 8.2 4.4 6.6z"/></svg>{{ $refundDays }}-{{ __('day money-back') }}
                        </div>
                        {{ __('Not happy? Email us within the first') }} {{ $refundDays }} {{ __('days and we refund the full amount.') }}
                    </div>
                @endif
            </aside>
        </form>
    </main>

    <script>
        (function () {
            const form = document.getElementById('checkout-form');
            const curSel = document.getElementById('currency-select');
            const curInp = document.getElementById('currency-input');
            const itemPrice = document.getElementById('item-price');
            const subEl = document.getElementById('sub-amt');
            const taxEl = document.getElementById('tax-amt');
            const totalEl = document.getElementById('total-amt');
            const ctaEl = document.getElementById('cta-amt');
            let gwList = document.getElementById('gateway-list');

            // Selected-gateway highlight (the built CSS has no has-[:checked] variant).
            function paintGateways() {
                if (!gwList) return;
                gwList.querySelectorAll('[data-gw]').forEach((lbl) => {
                    const on = lbl.querySelector('input[type=radio]')?.checked;
                    lbl.classList.toggle('border-wa-deep', !!on);
                    lbl.classList.toggle('bg-wa-mint', !!on);
                    lbl.classList.toggle('border-paper-200', !on);
                    lbl.classList.toggle('hover:border-wa-deep', !on);
                });
            }
            form?.addEventListener('change', (e) => { if (e.target.name === 'gateway_id') paintGateways(); });
            paintGateways();

            function esc(s) {
                return String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            }
            function setPayEnabled(on) {
                document.querySelectorAll('button[type=submit][form="checkout-form"]').forEach((b) => {
                    b.disabled = !on;
                    b.classList.toggle('opacity-50', !on);
                    b.classList.toggle('cursor-not-allowed', !on);
                });
            }
            function rebuildGateways(list) {
                if (!gwList) return;
                if (!list || !list.length) {
                    gwList.innerHTML = '<div class="px-4 py-3 rounded-lg bg-accent-coral/10 border border-accent-coral/40 text-[12.5px] text-accent-coral">No payment method accepts this currency. Pick another, or ask your admin.</div>';
                    setPayEnabled(false);
                    return;
                }
                gwList.innerHTML = list.map((g, i) => `
                    <label data-gw class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer transition ${i === 0 ? 'border-wa-deep bg-wa-mint' : 'border-paper-200 hover:border-wa-deep'}">
                        <input type="radio" name="gateway_id" value="${g.id}" ${i === 0 ? 'checked' : ''} required class="w-4 h-4">
                        <span class="w-10 h-10 rounded-lg bg-paper-50 border border-paper-200 grid place-items-center shrink-0"><svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="12" height="9" rx="1.5"/><path d="M2 7h12"/></svg></span>
                        <span class="flex-1 min-w-0"><span class="block font-semibold text-[13px]">${esc(g.name)}</span>${g.description ? `<span class="block text-[11px] text-ink-500 truncate">${esc(g.description)}</span>` : ''}</span>
                        <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full ${g.mode === 'live' ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-accent-amber/10 text-accent-amber border border-accent-amber/40'}">${esc(g.mode)}</span>
                    </label>`).join('');
                setPayEnabled(true);
                paintGateways();
            }

            async function switchCurrency() {
                if (!curSel) return;
                curSel.disabled = true;
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('currency', curSel.value);
                    url.searchParams.set('ajax', '1');
                    const r = await fetch(url.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const j = await r.json();
                    if (!j || !j.ok) throw new Error('bad');
                    if (curInp) curInp.value = j.currency;
                    if (itemPrice) itemPrice.innerHTML = j.amountFmt;
                    if (subEl) { subEl.innerHTML = j.amountFmt; subEl.dataset.base = j.amountRaw; }
                    if (taxEl) taxEl.innerHTML = j.taxFmt;
                    if (totalEl) totalEl.innerHTML = j.totalFmt;
                    if (ctaEl) ctaEl.innerHTML = j.totalFmt;
                    rebuildGateways(j.gateways);
                    const clean = new URL(window.location.href);
                    clean.searchParams.set('currency', j.currency);
                    clean.searchParams.delete('ajax');
                    history.replaceState({}, '', clean.toString());
                } catch (e) {
                    /* keep the current view on error */
                } finally {
                    curSel.disabled = false;
                }
            }
            curSel?.addEventListener('change', switchCurrency);
        })();
    </script>

</x-layouts.instagram>

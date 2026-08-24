<x-layouts.admin :title="__('Wallet & affiliate')" admin-key="wallet">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Wallet & affiliate') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.wallet.save') }}">
        @csrf
        <main class="px-4 sm:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Billing & plans') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Wallet &') }}
                        <span class="italic ig-text">{{ __('affiliate') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Prepaid wallet balance and the referral / affiliate program — signup credit, commission and payout threshold.') }}</p>
                </div>
                <div class="shrink-0 pb-1">
                    <button type="submit" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 hover:bg-wa-teal text-[12px] font-semibold">{{ __('Save changes') }}</button>
                </div>
            </div>

            @if (session('success'))
                <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">{{ $errors->first() }}</div>
            @endif

            @php
                $fld = 'w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep';
                $fldMono = $fld . ' font-mono';
            @endphp

            <section class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5 items-start">

                <div class="space-y-5 min-w-0">

                    {{-- Wallet --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('wallet') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Wallet') }}</h2>
                            <p class="text-[12px] text-ink-600 mt-1">{{ __('Let customers hold a prepaid balance and spend it at checkout.') }}</p>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="sm:col-span-2 rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3 cursor-pointer">
                                <span>
                                    <span class="block text-[12.5px] font-semibold">{{ __('Enable wallet') }}</span>
                                    <span class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Show wallet balance and top-up in the customer account') }}</span>
                                </span>
                                <span class="relative inline-block w-9 h-5 shrink-0">
                                    <input type="hidden" name="wallet_enabled" value="0">
                                    <input type="checkbox" name="wallet_enabled" value="1" class="peer opacity-0 w-0 h-0" @checked(old('wallet_enabled', $values['wallet_enabled']))>
                                    <span class="absolute inset-0 bg-paper-200 rounded-full transition peer-checked:bg-wa-deep before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:before:translate-x-[16px]"></span>
                                </span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Wallet currency') }}</span>
                                <input name="wallet_currency" value="{{ old('wallet_currency', $values['wallet_currency']) }}" placeholder="USD" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Minimum payout amount') }}</span>
                                <input name="min_payout_amount" type="number" min="0" step="0.01" value="{{ old('min_payout_amount', $values['min_payout_amount']) }}" class="{{ $fldMono }}">
                            </label>
                        </div>
                    </section>

                    {{-- Affiliate program --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('affiliate-program') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Affiliate program') }}</h2>
                            <p class="text-[12px] text-ink-600 mt-1">{{ __('Reward customers for referrals with signup credit and recurring commission.') }}</p>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="sm:col-span-2 rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3 cursor-pointer">
                                <span>
                                    <span class="block text-[12.5px] font-semibold">{{ __('Enable affiliate program') }}</span>
                                    <span class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Give each customer a referral link and track conversions') }}</span>
                                </span>
                                <span class="relative inline-block w-9 h-5 shrink-0">
                                    <input type="hidden" name="affiliate_enabled" value="0">
                                    <input type="checkbox" name="affiliate_enabled" value="1" class="peer opacity-0 w-0 h-0" @checked(old('affiliate_enabled', $values['affiliate_enabled']))>
                                    <span class="absolute inset-0 bg-paper-200 rounded-full transition peer-checked:bg-wa-deep before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:before:translate-x-[16px]"></span>
                                </span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Referral signup credit') }}</span>
                                <input name="referral_signup_credit" type="number" min="0" step="0.01" value="{{ old('referral_signup_credit', $values['referral_signup_credit']) }}" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Affiliate commission (%)') }}</span>
                                <input name="affiliate_commission_percent" type="number" min="0" max="100" value="{{ old('affiliate_commission_percent', $values['affiliate_commission_percent']) }}" class="{{ $fldMono }}">
                            </label>
                        </div>
                    </section>

                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-50 border border-paper-200 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px] text-wa-deep">{{ __('Heads up') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('Signup credit is added to a new customer wallet when they join via a referral link. Commission accrues on each paid renewal and is payable once the balance clears the minimum payout.') }}</p>
                    </div>
                </aside>

            </section>

            <div class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Changes apply only after you save.') }}</span>
                <div class="flex items-center gap-2">
                    <button type="submit" class="px-5 py-2 rounded-full bg-wa-deep text-paper-0 hover:bg-wa-teal text-[12px] font-semibold">{{ __('Save changes') }}</button>
                </div>
            </div>

        </main>
    </form>

</x-layouts.admin>

{{--
    User-facing Pricing / Plans page — ported from WaDesk's pricing/index.blade.php
    and recoloured to the Instagram theme.

    Consumes the enriched Package (chargeableAmount() / offer_price / free /
    is_highlighted / is_custom_quote / periodLabel()) exactly as WaDesk does, and
    each card prices in its OWN currency ($p->currency). Deliberately simpler than
    WaDesk: one interval per plan means no monthly/yearly toggle, and there is no
    add-on catalogue or featureCatalog() comparison table — those have no data to
    drive them here. Each card's CTA hands off to the one-time checkout flow.
--}}
<x-layouts.instagram :title="__('Plans')" ig-active="plans" page="instagram-plans">

    @php
        // Human labels for the numeric limit columns, in render order.
        // NULL or 0 = "Unlimited" — the stored convention across the packages table.
        $limitLabels = [
            'max_accounts'    => __('Instagram accounts'),
            'max_flows'       => __('Automation flows'),
            'max_automations' => __('Auto-reply rules'),
            'monthly_dms'     => __('DMs / month'),
            'team_seats'      => __('Team seats'),
        ];
        $featureLabels = \App\Models\Package::FEATURES;

        $fmtLimit = function ($v) {
            if ($v === null || (int) $v === 0) return __('Unlimited');
            if ($v >= 1000) return number_format($v / 1000, $v >= 100000 ? 0 : 1) . 'k';
            return number_format($v);
        };

        // Checkout is wired centrally — guard the CTA so the page still renders if
        // it has not been registered yet, and resolves the moment it is.
        $checkoutHas = \Illuminate\Support\Facades\Route::has('checkout.show');

        // Grid width scales with how many plans exist, like WaDesk.
        $cols = min(4, max(1, $packages->count()));
        $gridCls = [
            1 => '',
            2 => 'md:grid-cols-2',
            3 => 'md:grid-cols-3',
            4 => 'md:grid-cols-2 lg:grid-cols-4',
        ][$cols] ?? 'md:grid-cols-2 lg:grid-cols-4';
    @endphp

    <div class="px-4 sm:px-7 py-7 max-w-[1140px] mx-auto">

        {{-- Hero — mono breadcrumb + serif headline with an ig-text accent. --}}
        <div class="text-center mb-9">
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Pricing & plans') }}
            </div>
            <h1 class="serif font-serif font-normal text-[32px] sm:text-[40px] lg:text-[44px] leading-[1.05] tracking-tight">
                {{ __('Pricing that') }} <span class="ig-text italic">{{ __('grows with you') }}</span>.
            </h1>
            <p class="text-[13.5px] text-ink-600 mt-3 max-w-xl mx-auto">
                {{ __('Pick the plan that fits today. Upgrade any time as your automations and audience grow — no contracts, no surprises.') }}
            </p>
        </div>

        {{-- Flash (success / error) — the layout also surfaces these, but an inline
             banner keeps the message next to the plans after a checkout return. --}}
        @if (session('success'))
            <div class="max-w-2xl mx-auto mb-6 bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono text-center">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="max-w-2xl mx-auto mb-6 bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2 text-[12.5px] text-[#A1431F] text-center">
                {{ session('error') }}
            </div>
        @endif

        {{-- Plan-window banner. plan_ends_at is set on checkout from the plan's
             duration (plan_duration × plan_unit); null = free / custom = no expiry. --}}
        @if ($planExpired)
            <div class="max-w-2xl mx-auto mb-6 bg-accent-coral/10 border border-accent-coral/40 rounded-xl px-4 py-3 text-[13px] text-[#A1431F] text-center">
                {{ __('Your plan expired on') }}
                <span class="font-semibold">{{ optional($currentPlanEndsAt)->format('M j, Y') }}</span>.
                {{ __('Paid features are paused — renew below to restore them.') }}
            </div>
        @elseif ($currentPlanEndsAt)
            <div class="max-w-2xl mx-auto mb-6 bg-wa-mint border border-wa-green/30 rounded-xl px-4 py-3 text-[13px] text-wa-deep text-center">
                {{ __('Your plan is active until') }}
                <span class="font-semibold">{{ $currentPlanEndsAt->format('M j, Y') }}</span>
                ({{ $currentPlanEndsAt->diffForHumans() }}).
            </div>
        @elseif ($trialEndsAt)
            <div class="max-w-2xl mx-auto mb-6 bg-accent-amber/20 border border-accent-amber/40 rounded-xl px-4 py-3 text-[13px] text-accent-amber text-center">
                {{ __('Free trial ends on') }}
                <span class="font-semibold">{{ $trialEndsAt->format('M j, Y') }}</span>
                ({{ $trialEndsAt->diffForHumans() }}). {{ __('Choose a plan to keep your automations running.') }}
            </div>
        @endif

        @if ($packages->isEmpty())
            <div class="text-center py-16 text-ink-500 text-[13px]">
                {{ __('No plans are available yet — please check back soon.') }}
            </div>
        @else
            {{-- Plan cards. items-start so a card that expands its feature list grows
                 on its own rather than stretching the whole row. --}}
            <div class="grid grid-cols-1 {{ $gridCls }} gap-4 items-start">
                @foreach ($packages as $p)
                    @php
                        $amount    = $p->chargeableAmount();
                        $isFree    = $p->free || $amount <= 0;
                        $isCustom  = (bool) $p->is_custom_quote;
                        $featured  = (bool) ($p->is_highlighted || $p->is_featured);
                        $isCurrent = $currentPackageId && (int) $p->id === (int) $currentPackageId;

                        $planCurrency = $p->currency ?: $currency;
                        $baseAmount   = (float) ($p->plan_amount ?? $p->price ?? 0);
                        $hasOffer     = ! $isFree && ! $isCustom
                            && $p->offer_price !== null && (float) $p->offer_price > 0
                            && (float) $p->offer_price < $baseAmount;

                        // Upgrade vs plain "Choose": only relabel when we know the
                        // current tier amount and this plan costs more.
                        $isUpgrade = ! $isCurrent && ! $isFree && ! $isCustom
                            && $currentPlanAmount !== null && $amount > $currentPlanAmount;

                        // Ordered bullets: numeric limits first, then enabled capabilities.
                        $bullets = [];
                        foreach ($limitLabels as $key => $label) {
                            $bullets[] = ['limit' => $fmtLimit($p->{$key} ?? null), 'label' => $label];
                        }
                        foreach ((array) ($p->features ?? []) as $fk) {
                            $bullets[] = ['label' => $featureLabels[$fk] ?? \Illuminate\Support\Str::of($fk)->replace('_', ' ')->title()->toString()];
                        }

                        $cardCls = $featured ? 'border-2 border-wa-deep shadow-soft' : 'border border-paper-200 shadow-card';
                        $ctaHref = $checkoutHas ? route('checkout.show', $p->id) : '#';
                    @endphp

                    <div class="relative bg-paper-0 {{ $cardCls }} rounded-2xl p-6 flex flex-col">

                        @if ($featured)
                            <span class="absolute -top-3 left-5 inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-wa-deep text-paper-0 text-[10px] font-semibold uppercase tracking-wider">
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M8 2.2l1.7 3.6 3.9.5-2.9 2.7.8 3.9L8 11.1 4.5 12.8l.8-3.9L2.4 6.3l3.9-.5z"/>
                                </svg>
                                {{ __('Most popular') }}
                            </span>
                        @endif

                        {{-- Plan name --}}
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] {{ $featured ? 'text-wa-deep' : 'text-ink-500' }}">
                            {{ $p->pname }}
                        </div>

                        {{-- Price --}}
                        <div class="mt-3 flex items-baseline gap-1.5 flex-wrap">
                            @if ($isCustom)
                                <span class="font-serif text-[42px] leading-none">{{ __('Custom') }}</span>
                            @elseif ($isFree)
                                <span class="font-serif text-[42px] leading-none">{{ __('Free') }}</span>
                            @else
                                <span class="font-serif text-[42px] leading-none">{!! \App\Support\FormatSettings::formatIn($amount, $planCurrency) !!}</span>
                                <span class="text-[12px] text-ink-500">{{ $p->periodLabel() }}</span>
                                @if ($hasOffer)
                                    <span class="text-[12px] text-ink-400 line-through ml-1">{!! \App\Support\FormatSettings::formatIn($baseAmount, $planCurrency) !!}</span>
                                @endif
                            @endif
                        </div>

                        {{-- Description --}}
                        @if ($p->description)
                            <p class="text-[12px] text-ink-500 mt-2 leading-snug">{{ $p->description }}</p>
                        @endif

                        {{-- CTA --}}
                        @if ($isCurrent)
                            <span class="mt-5 px-4 py-2 rounded-full bg-wa-mint text-wa-deep border border-paper-200 text-center text-[12px] font-semibold inline-flex items-center justify-center gap-1.5 cursor-default">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l3 3 7-7"/></svg>
                                {{ __('Current plan') }}
                            </span>
                        @elseif ($isCustom)
                            <a href="{{ url('/support') }}"
                                class="mt-5 px-4 py-2 rounded-full bg-paper-50 border border-paper-200 hover:bg-paper-100 text-center text-[12px] font-semibold transition">
                                {{ __('Talk to sales') }}
                            </a>
                        @elseif ($featured)
                            <a href="{{ $ctaHref }}"
                                class="mt-5 px-4 py-2 rounded-full ig-grad text-white text-center text-[12px] font-semibold hover:opacity-95 transition">
                                {{ $isUpgrade ? __('Upgrade to :plan', ['plan' => $p->pname]) : ($isFree ? __('Get started') : __('Choose :plan', ['plan' => $p->pname])) }}
                            </a>
                        @else
                            <a href="{{ $ctaHref }}"
                                class="mt-5 px-4 py-2 rounded-full bg-paper-50 border border-paper-200 hover:bg-paper-100 text-center text-[12px] font-semibold transition">
                                {{ $isUpgrade ? __('Upgrade to :plan', ['plan' => $p->pname]) : ($isFree ? __('Get started') : __('Choose :plan', ['plan' => $p->pname])) }}
                            </a>
                        @endif

                        {{-- Limits + features --}}
                        <ul class="mt-5 space-y-2 text-[12px] text-ink-700 flex-1">
                            @foreach ($bullets as $b)
                                <li class="flex gap-2">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 mt-0.5 text-wa-deep shrink-0" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l3 3 7-7"/></svg>
                                    <span>
                                        @isset($b['limit'])<span class="font-mono">{{ $b['limit'] }}</span>@endisset
                                        {{ $b['label'] }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        @if ($p->trial_days)
                            <div class="mt-4 pt-3 border-t border-paper-200 text-[11px] font-mono text-ink-500">
                                {{ __(':n-day free trial', ['n' => $p->trial_days]) }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Notes / short FAQ — admin-editable at /admin/pricing-faqs, with a
                 baked-in fallback so the section is never empty before it's seeded. --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-8">
                @php
                    $faqRows = collect();
                    try { $faqRows = \App\Models\PricingFaq::active()->get(); } catch (\Throwable $e) {}
                    if ($faqRows->isNotEmpty()) {
                        $notes = $faqRows->map(fn ($f) => ['q' => $f->question, 'a' => $f->answer])->all();
                    } else {
                        $notes = [
                            ['q' => __('Can I change plans later?'),   'a' => __('Yes. Move up to a bigger plan whenever your automations or audience grow — the change applies right away.')],
                            ['q' => __('What does “Unlimited” mean?'), 'a' => __('Where a limit shows “Unlimited”, that feature has no fixed cap on your plan.')],
                            ['q' => __('How is a plan paid for?'),     'a' => __('Pick a plan, choose a payment method at checkout, and pay once for the period. There is no auto-renewal — you buy each period yourself.')],
                            ['q' => __('Which currency am I charged in?'), 'a' => __('Each plan is priced and charged in its own currency, shown on the card and again at checkout before you pay.')],
                        ];
                    }
                @endphp
                @foreach ($notes as $i => $note)
                    <details class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card group" @if ($i === 0) open @endif>
                        <summary class="cursor-pointer font-serif text-[16px] flex items-center justify-between list-none">
                            <span>{{ $note['q'] }}</span>
                            <svg viewBox="0 0 16 16" class="w-3 h-3 text-ink-500 transition-transform group-open:rotate-180 shrink-0 ml-3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
                        </summary>
                        <p class="mt-2 text-[12.5px] text-ink-600 leading-relaxed">{{ $note['a'] }}</p>
                    </details>
                @endforeach
            </div>
        @endif

    </div>
</x-layouts.instagram>

{{--
 Public pricing page — editorial tier cards (ported from the WaDesk frontend
 design, recoloured to Instagram). Cards are driven by the admin-managed plans
 ($plans from FrontendController::pricing()); a shipped trio renders when no
 packages exist yet so the page never looks empty.
--}}
@extends('frontend.layout')
@section('title', __('Pricing'))

@section('content')

    {{-- ============== HERO ============== --}}
    <section class="relative overflow-hidden bg-paper-0">
        <div class="absolute inset-0 grid-bg opacity-30 pointer-events-none"></div>
        <div class="absolute -top-32 -right-32 w-[520px] h-[520px] rounded-full bg-wa-mint/50 blur-bub"></div>

        <div class="relative max-w-[1360px] mx-auto px-7 py-28">
            <div class="badge-num mb-6">{{ fc('pricing.hero.eyebrow', __('— Pricing')) }}</div>
            <h1 class="serif text-[64px] sm:text-[88px] leading-[0.92] tracking-[-0.02em]">
                {!! fc('pricing.hero.headline', __('Simple, honest,<br>and <span class="italic text-wa-deep">flat.</span>')) !!}
            </h1>
            <p class="text-[15.5px] text-ink-700 mt-6 max-w-2xl leading-relaxed">
                {{ fc('pricing.hero.intro', __('Pick a plan, connect Instagram, and go live in minutes. Free trial, no credit card, cancel anytime and keep your data.')) }}
            </p>
        </div>
    </section>

    @php
        // Shipped fallback trio — only used when the admin hasn't published any
        // active packages yet, so the public page always has something to show.
        if (empty($plans)) {
            $reg = \Route::has('register') ? route('register') : url('/');
            $plans = [
                ['name' => 'Starter', 'tagline' => __('Creators testing the waters. Live in minutes, no card.'), 'price' => '$0', 'period' => __('/forever'), 'badge' => __('free'), 'highlighted' => false,
                 'features' => [['label' => __('1 Instagram account'), 'included' => true], ['label' => __('Comment automation'), 'included' => true], ['label' => __('Basic flows'), 'included' => true]], 'cta_label' => __('Start free →'), 'cta_href' => $reg],
                ['name' => 'Pro', 'tagline' => __('Brands growing on Instagram. Everything unlocked, one bill.'), 'price' => '$29', 'period' => __('/month'), 'badge' => __('Most picked'), 'highlighted' => true,
                 'features' => [['label' => __('3 Instagram accounts'), 'included' => true], ['label' => __('Unlimited flows + DMs'), 'included' => true], ['label' => __('AI agent + templates'), 'included' => true], ['label' => __('Broadcasts + analytics'), 'included' => true]], 'cta_label' => __('Start free trial →'), 'cta_href' => $reg],
                ['name' => 'Scale', 'tagline' => __('High-volume teams. Custom limits and contracts.'), 'price' => '$99', 'period' => __('/month'), 'badge' => __('custom'), 'highlighted' => false,
                 'features' => [['label' => __('Unlimited accounts'), 'included' => true], ['label' => __('Priority support · SLA'), 'included' => true], ['label' => __('Data residency + DPA'), 'included' => true]], 'cta_label' => __('Start free trial →'), 'cta_href' => $reg],
            ];
        }
    @endphp

    {{-- ============== TIER CARDS ============== --}}
    <section class="bg-white" data-fc-section="pricing-strip">
        <div class="max-w-[1360px] mx-auto px-4 sm:px-7 py-24">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start reveal" style="--d:120ms">
                @foreach ($plans as $plan)
                    @php $hl = $plan['highlighted'] ?? false; @endphp
                    {{-- All cards are white for contrast; the one featured tier gets
                         a gradient border + gradient price/CTA (no heavy fill). --}}
                    <div class="col-span-12 lg:col-span-4 rounded-3xl p-8 flex flex-col bg-white {{ $hl ? 'card-grad-border lg:-mt-4 lg:mb-4 shadow-[0_40px_80px_-40px_rgba(193,53,132,0.4)]' : 'hairline' }}">
                        {{-- header --}}
                        <div class="hairline-b pb-5">
                            <div class="flex items-center justify-between mb-3">
                                <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500">— {{ __('Plan') }}</div>
                                @if ($hl)
                                    <span class="pill ig-grad-soft text-white text-[10px]">{{ $plan['badge'] }}</span>
                                @else
                                    <span class="badge-num">{{ $plan['badge'] }}</span>
                                @endif
                            </div>
                            <h3 class="serif text-[40px] leading-none mt-3">{{ $plan['name'] }}</h3>
                            @if (!empty($plan['tagline']))
                                <p class="text-[12.5px] text-ink-600 mt-3 leading-relaxed line-clamp-3">{{ $plan['tagline'] }}</p>
                            @endif
                        </div>

                        {{-- price --}}
                        <div class="hairline-b py-6">
                            <div class="flex items-baseline gap-2">
                                <span class="serif text-[72px] leading-none tabular {{ $hl ? 'ig-text' : '' }}">{{ $plan['price'] }}</span>
                                <span class="mono text-[11px] text-ink-500">{{ $plan['period'] }}</span>
                            </div>
                        </div>

                        {{-- features --}}
                        <div class="py-5 space-y-2 flex-1">
                            @if (!empty($plan['features']))
                                <div class="mono text-[9.5px] uppercase tracking-widest text-wa-deep mb-2">{{ __("What's included") }}</div>
                                <ul class="space-y-1.5 text-[12.5px] text-ink-700">
                                    @foreach ($plan['features'] as $row)
                                        <li class="flex gap-2"><span class="text-wa-green mt-0.5">✓</span>{{ $row['label'] }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        {{-- cta --}}
                        <a href="{{ $plan['cta_href'] }}"
                            class="block text-center w-full rounded-full py-3 text-[13px] font-semibold transition {{ $hl ? 'ig-grad-soft btn-grad text-white hover:opacity-90' : 'hairline hover:bg-paper-50 font-medium' }}">
                            {{ $plan['cta_label'] }}
                        </a>
                    </div>
                @endforeach
            </div>

            {{-- honesty band --}}
            <div class="mt-10 hairline rounded-2xl bg-paper-50 px-6 py-5 grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                <div class="col-span-12 lg:col-span-3 mono text-[10px] uppercase tracking-[0.22em] text-ink-500">— {{ fc('pricing.honest_label', __('Honest about pricing')) }}</div>
                <div class="col-span-12 lg:col-span-9 grid grid-cols-2 lg:grid-cols-4 gap-4 text-[12px]">
                    @foreach ([
                        [__('Pay per plan'), __('Not per seat. Ever.')],
                        [__('One invoice'), __('Every feature bundled.')],
                        [__('Free trial'), __('No card to start.')],
                        [__('Cancel anytime'), __('Keep your data.')],
                    ] as [$b, $s])
                        <div class="flex gap-2 items-start"><span class="text-wa-deep">✓</span><span class="text-ink-700"><b>{{ $b }}</b><br><span class="text-ink-500">{{ $s }}</span></span></div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============== FAQ ============== --}}
    <x-frontend.faq :kicker="__('Pricing FAQ')" scope="pricing" :headline="__('Pricing, <span class=\'italic text-wa-deep\'>answered.</span>')"
        :subtitle="__('Everything about plans, billing and trials.')"
        :items="[
            ['q' => __('Is there a free trial?'), 'a' => __('Yes — every paid plan starts with a free trial and no credit card. You only pay when you pick a plan.'), 'open' => true],
            ['q' => __('Do you charge per team member?'), 'a' => __('No. Plans are flat — invite your whole team at no extra per-seat cost.')],
            ['q' => __('Can I change plans later?'), 'a' => __('Anytime, from your account. Upgrades apply immediately; downgrades take effect next cycle.')],
            ['q' => __('What payment methods do you accept?'), 'a' => __('All major cards plus regional gateways, depending on your country — you will see the options at checkout.')],
            ['q' => __('Can I cancel?'), 'a' => __('Yes, anytime. Your plan stays active until the end of the paid period and you keep your data.')],
        ]" />

    {{-- ============== CTA ============== --}}
    <x-frontend.cta-final />

@endsection

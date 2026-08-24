{{--
 Public Features page — editorial, dense (ported from the WaDesk frontend
 design system, recoloured to Instagram). Hero is pinned; the rest are captured
 by slug and echoed in order.
--}}
@extends('frontend.layout')
@section('title', __('Features'))

@section('content')

    {{-- ============== HERO ============== --}}
    <section class="relative overflow-hidden bg-paper-0">
        <div class="absolute inset-0 grid-bg opacity-30 pointer-events-none"></div>
        <div class="absolute -top-32 -right-32 w-[520px] h-[520px] rounded-full bg-wa-mint/50 blur-bub"></div>
        <div class="absolute -bottom-40 -left-32 w-[460px] h-[460px] rounded-full bg-accent-amber/15 blur-bub"></div>

        <div class="relative max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 items-end">
                <div class="col-span-12 lg:col-span-8 reveal">
                    <span class="badge-num mb-6 inline-block">{{ fc('features.hero.eyebrow', __('— Features')) }}</span>
                    <h1 class="serif text-[72px] lg:text-[104px] leading-[0.92] tracking-[-0.025em]">
                        {!! fc('features.hero.headline', __('Everything your<br>Instagram needs —<br><span class="italic text-wa-deep">one workspace.</span>')) !!}
                    </h1>
                </div>
                <div class="col-span-12 lg:col-span-4 reveal" style="--d:120ms">
                    <p class="text-[15.5px] text-ink-700 leading-relaxed border-l-2 border-wa-deep pl-4">
                        {{ fc('features.hero.intro', __('Comment automation, story replies, DM flows, a unified inbox, an AI agent, broadcasts, templates and analytics — built to work together, never bolted on.')) }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    @php $sec = []; @endphp

    {{-- ============== BENTO GRID ============== --}}
    @php ob_start(); @endphp
    <section class="bg-white hairline-t">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2"><div class="feature-num">01</div></div>
                <div class="col-span-10 flex flex-col justify-end pb-3">
                    <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">— {{ fc('features.bento.eyebrow', __('The toolkit')) }}</div>
                    <h2 class="serif text-[44px] lg:text-[64px] leading-[0.95] tracking-[-0.02em]">
                        {!! fc('features.bento.headline', __('Eight tools. <span class="italic text-wa-deep">One login.</span>')) !!}</h2>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 reveal" style="--d:120ms">
                @foreach ([
                    [__('Comment automation'), __('Reply to every comment in seconds and slide the conversation into the DMs — by keyword or on any post.')],
                    [__('Story-reply flows'), __('Turn story mentions and replies into automated conversations that qualify and convert.')],
                    [__('Visual flow builder'), __('Drag-and-drop DM automations — buttons, quick replies, conditions, delays, webhooks, AI.')],
                    [__('Unified inbox'), __('Every DM, comment and story reply from all your accounts in one shared, assignable inbox.')],
                    [__('AI agent'), __('An always-on assistant trained on your brand that answers, recommends and hands off to a human.')],
                    [__('Broadcasts'), __('Re-engage your audience within policy windows with segmented, scheduled DM broadcasts.')],
                    [__('Templates'), __('Reusable message templates with buttons and quick replies — build once, send anywhere.')],
                    [__('Analytics'), __('See what converts — replies, DM rates, flow completion and revenue attribution.')],
                    [__('Lead capture'), __('Collect emails, phones and answers inside the DM and sync them to your CRM.')],
                ] as [$title, $desc])
                    <div class="hairline rounded-3xl bg-paper-50 p-7 hover:border-wa-deep transition">
                        <span class="w-12 h-12 rounded-2xl ig-grad-soft text-white flex items-center justify-center">
                            <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12l4 4L19 6"/></svg>
                        </span>
                        <h3 class="serif text-[26px] leading-tight mt-5">{{ $title }}</h3>
                        <p class="text-[13px] text-ink-600 mt-3 leading-relaxed">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @php $sec['bento'] = ob_get_clean(); @endphp

    {{-- ============== THREE PILLARS ============== --}}
    @php ob_start(); @endphp
    <section class="bg-paper-50 hairline-t hairline-b">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2"><div class="feature-num">02</div></div>
                <div class="col-span-10 flex flex-col justify-end pb-3">
                    <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">— {{ fc('features.pillars.eyebrow', __('Why it works')) }}</div>
                    <h2 class="serif text-[44px] lg:text-[64px] leading-[0.95] tracking-[-0.02em]">
                        {!! fc('features.pillars.headline', __('Automate. Reply. <span class="italic text-wa-deep">Grow.</span>')) !!}</h2>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 reveal" style="--d:120ms">
                @foreach ([
                    ['01', __('Capture'), __('Every comment, mention and DM becomes an entry point. Nothing your audience sends is ever missed or lost.')],
                    ['02', __('Convert'), __('Flows qualify, recommend and collect — moving people from a like to a lead to a sale, automatically.')],
                    ['03', __('Retain'), __('Broadcasts, tags and the AI agent keep the conversation going long after the first message.')],
                ] as [$n, $title, $desc])
                    <div class="hairline rounded-3xl bg-white p-8">
                        <div class="serif text-[64px] leading-none text-wa-deep/30">{{ $n }}</div>
                        <h3 class="serif text-[32px] leading-tight mt-4">{{ $title }}</h3>
                        <p class="text-[13.5px] text-ink-600 mt-3 leading-relaxed">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @php $sec['pillars'] = ob_get_clean(); @endphp

    {{-- ============== PULL QUOTE ============== --}}
    @php ob_start(); @endphp<x-frontend.pull-quote />@php $sec['pull-quote'] = ob_get_clean(); @endphp

    {{-- ============== FAQ ============== --}}
    @php ob_start(); @endphp
    <x-frontend.faq :kicker="__('FAQ')" scope="features" :headline="__('Feature <span class=\'italic text-wa-deep\'>questions.</span>')" />
    @php $sec['faq'] = ob_get_clean(); @endphp

    {{-- ============== CTA ============== --}}
    @php ob_start(); @endphp<x-frontend.cta-final :kicker="__('One login · one workspace')"
        :headline="__('Every tool.<br>One <span class=\'italic text-wa-green\'>Instagram</span> workspace.')" />@php $sec['cta-final'] = ob_get_clean(); @endphp

    @php
        foreach (fc_section_order('features', array_keys($sec)) as $slug) {
            if (fc_section_visible('features', $slug)) {
                echo $sec[$slug];
            }
        }
    @endphp

@endsection

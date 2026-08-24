{{--
 Public About page — editorial, dense (ported from the WaDesk frontend design,
 recoloured to the Instagram palette). The hero is pinned to the top; every
 other section is captured to a string keyed by its slug, then echoed in order.
 Sections: Hero → Origin story → Values → Timeline → Numbers → Press
 → Backers → Pull quote → CTA.
--}}
@extends('frontend.layout')
@section('title', __('About'))

@section('content')

    {{-- ============== HERO (pinned, rendered first) ============== --}}
    <section data-fc-section="hero" class="relative overflow-hidden bg-paper-0">
        <div class="absolute inset-0 grid-bg opacity-30 pointer-events-none"></div>
        <div class="absolute -top-32 -right-32 w-[520px] h-[520px] rounded-full bg-wa-mint/50 blur-bub"></div>
        <div class="absolute -bottom-40 -left-32 w-[460px] h-[460px] rounded-full bg-accent-amber/15 blur-bub"></div>

        <div class="relative max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 items-end">
                <div class="col-span-12 lg:col-span-8 reveal">
                    <span class="badge-num mb-6 inline-block">{{ fc('about.hero.eyebrow', __('— About us')) }}</span>
                    <h1 class="serif text-[80px] lg:text-[104px] leading-[0.92] tracking-[-0.025em]">
                        {!! fc(
                            'about.hero.headline',
                            __(
                                'We built <span class="italic text-wa-deep">one place</span><br>to run your whole<br><span class="italic">Instagram.</span>',
                            ),
                        ) !!}
                    </h1>
                </div>
                <div class="col-span-12 lg:col-span-4 reveal" style="--d:120ms">
                    <p class="text-[15.5px] text-ink-700 leading-relaxed border-l-2 border-wa-deep pl-4">
                        {{ fc('about.hero.intro', __('Comments, story replies, DMs, flows, and AI — one workspace that turns every Instagram interaction into a conversation, built to work together from day one.')) }}
                    </p>
                    <div class="mt-6 grid grid-cols-3 gap-0 hairline-t hairline-b py-4">
                        <div class="hairline-r pr-4">
                            <div class="serif text-[32px] leading-none tabular text-wa-deep">{{ fc('about.hero.stat1-value', '2024') }}</div>
                            <div class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1.5">
                                {{ fc('about.hero.stat1-label', __('founded')) }}</div>
                        </div>
                        <div class="hairline-r px-4">
                            <div class="serif text-[32px] leading-none tabular text-wa-deep">{{ fc('about.hero.stat2-value', '40') }}</div>
                            <div class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1.5">
                                {{ fc('about.hero.stat2-label', __('markets')) }}</div>
                        </div>
                        <div class="pl-4">
                            <div class="serif text-[32px] leading-none tabular text-wa-deep">{{ fc('about.hero.stat3-value', '90M') }}</div>
                            <div class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1.5">
                                {{ fc('about.hero.stat3-label', __('DMs / mo')) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @php $sec = []; @endphp

    {{-- ============== ORIGIN STORY ============== --}}
    @php ob_start(); @endphp
    <section data-fc-section="origin-story" class="bg-white">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2"><div class="feature-num">01</div></div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">
                        {{ fc('about.origin-story.eyebrow', __('— How we started')) }}</div>
                    <div class="text-[13px] font-semibold">
                        {{ fc('about.origin-story.sublabel', __('Origin story')) }}</div>
                </div>
                <div class="col-span-7 flex items-end justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                    <span>{{ fc('about.origin-story.location', __('Bengaluru + Berlin')) }}</span><span class="text-ink-300">·</span>
                    <span>{{ fc('about.origin-story.since', __('shipping since 2024')) }}</span>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-12 items-start">
                <div class="col-span-12 lg:col-span-7 reveal">
                    <p class="serif text-[36px] leading-[1.2] tracking-[-0.01em] text-ink-900">
                        {{ fc('about.origin-story.para1', __('In 2023, our founders were running a small boutique entirely through Instagram. Comments piling up under every post. DMs at midnight. Story replies lost forever. A spreadsheet nobody kept up with.')) }}
                    </p>
                    <p class="serif text-[36px] leading-[1.2] tracking-[-0.01em] text-ink-900 mt-6">
                        {{ fc('about.origin-story.para2', __('We built :brand as the workspace we wished we had — comment automation that replies in seconds, flows that know your catalog, and an inbox where nothing slips through.', ['brand' => brand_name()])) }}
                    </p>
                    <p class="serif text-[36px] leading-[1.2] tracking-[-0.01em] text-ink-900 mt-6">
                        {!! fc(
                            'about.origin-story.para3',
                            __('A year later <span class="italic text-wa-deep">6,400 creators & brands</span> automate 90M DMs a month through it.'),
                        ) !!}
                    </p>
                </div>

                <div class="col-span-12 lg:col-span-5 reveal" style="--d:160ms">
                    <div class="hairline rounded-3xl bg-paper-50 p-8 sticky top-28">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-4">
                            {{ fc('about.origin-story.glance-label', __('— At a glance')) }}</div>
                        <ul class="space-y-4">
                            @foreach ([
                                ['Founded', 'Mar 2024'],
                                ['Headquarters', 'Bengaluru, India'],
                                ['EU office', 'Berlin, Germany'],
                                ['Team', '32'],
                                ['Creators & brands', '6,400'],
                                ['Funding', 'Seed · $3.8M'],
                            ] as $ri => [$rl, $rv])
                                <li class="{{ ! $loop->last ? 'hairline-b pb-4' : 'pb-1' }} flex items-baseline justify-between">
                                    <span class="text-[13px] text-ink-700">{{ fc('about.origin-story.glance-r'.($ri+1).'-label', __($rl)) }}</span>
                                    <span class="serif text-[20px] text-wa-deep">{{ fc('about.origin-story.glance-r'.($ri+1).'-value', $rv) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @php $sec['origin-story'] = ob_get_clean(); @endphp

    {{-- ============== VALUES ============== --}}
    @php ob_start(); @endphp
    <section data-fc-section="values" class="bg-paper-50 hairline-t hairline-b">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2"><div class="feature-num">02</div></div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">
                        {{ fc('about.values.eyebrow', __('— What we believe')) }}</div>
                    <div class="text-[13px] font-semibold">
                        {{ fc('about.values.sublabel', __('Five operating principles')) }}</div>
                </div>
                <div class="col-span-7 flex items-end justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                    <span class="text-wa-deep">{{ fc('about.values.caption', __('printed on every team laptop')) }}</span>
                </div>
            </div>

            <h2 class="serif text-[88px] leading-[0.92] tracking-[-0.02em] mb-12 reveal">
                {!! fc('about.values.headline', __('Strong opinions,<br>loosely <span class="italic text-wa-deep">held.</span>')) !!}
            </h2>

            <div class="grid grid-cols-2 lg:grid-cols-5 gap-5 reveal" style="--d:120ms">
                @foreach ([
                    ['i', __('No reply left behind.'), __('Every comment, story mention and DM deserves an answer — automation makes sure none is ever missed.')],
                    ['ii', __('Tools should talk.'), __('Comment triggers open DMs. Flows know your catalog. The inbox sees everything in one thread.')],
                    ['iii', __('Support is a human.'), __('Email us, get a person inside 4 hours. Free plan included. Yes, really.')],
                    ['iv', __('Ship weekly, polish daily.'), __('We deploy every Tuesday. Nothing fancy — we just refuse to break our promises.')],
                    ['v', __('Default to honest.'), __('No dark patterns. No hidden tiers. No "contact sales" for the listed price. Ever.')],
                ] as [$num, $title, $desc])
                    <div class="hairline rounded-3xl bg-white p-6">
                        <div class="serif text-[48px] leading-none text-wa-deep/40">{{ $num }}</div>
                        <h3 class="serif text-[24px] leading-tight mt-4">
                            {{ fc('about.values.item'.$loop->iteration.'-title', $title) }}</h3>
                        <p class="text-[12.5px] text-ink-600 mt-3 leading-relaxed">
                            {{ fc('about.values.item'.$loop->iteration.'-desc', $desc) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @php $sec['values'] = ob_get_clean(); @endphp

    {{-- ============== TIMELINE ============== --}}
    @php ob_start(); @endphp
    <section data-fc-section="timeline" class="bg-white">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2"><div class="feature-num">03</div></div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">
                        {{ fc('about.timeline.eyebrow', __('— Timeline')) }}</div>
                    <div class="text-[13px] font-semibold">
                        {{ fc('about.timeline.sublabel', __('Two years in')) }}</div>
                </div>
            </div>

            <h2 class="serif text-[88px] leading-[0.92] tracking-[-0.02em] mb-12 reveal">
                {!! fc('about.timeline.headline', __('From one boutique<br>to <span class="italic text-wa-deep">90M DMs / month.</span>')) !!}
            </h2>

            <div class="hairline rounded-3xl bg-paper-50 overflow-hidden reveal" style="--d:120ms">
                @foreach ([
                    ['Q1 2024', __('Founded'), __('Priya & Dario start building :brand out of a Bengaluru studio. First commit lands on a Tuesday.', ['brand' => brand_name()]), true],
                    ['Q2 2024', __('Seed round · $3.8M'), __('Angels and an early fund back the team. First two engineers join, ship comment automation.'), true],
                    ['Q3 2024', __('First 100 accounts'), __('Bloom Studio signs up. Urban Threads migrates from ManyChat. Inbox + Flows go live the same week.'), true],
                    ['Q4 2024', __('SOC 2 Type II'), __('Independent audit completed. EU office opens in Berlin. Template library hits 60 starters.'), true],
                    ['Q1 2025', __('AI Copilot · v4.0'), __('Describe a flow in plain English, get a working automation in seconds. Adoption hits 64% in week one.'), true],
                    ['Q2 2025', __('Crossed 1,000 accounts'), __('Café Lumen · Paris, Pebble · Mumbai, FORMAS · São Paulo all migrate the same week.'), true],
                    ['Q1 2026', __('Story & comment AI · v4.2'), __('Latest release — full-funnel automation, version control, public roadmap. 6,400 accounts shipping.'), false],
                    ['Q2 2026', __('Next: in-DM checkout · v5.0'), __('Product links and payments right inside the DM. Beta opens to Pro accounts in June.'), false],
                ] as $i => [$qtr, $title, $desc, $shipped])
                    <div class="grid grid-cols-12 gap-6 px-8 py-7 {{ !$loop->last ? 'hairline-b' : '' }}">
                        <div class="col-span-2">
                            <div class="mono text-[10px] uppercase tracking-widest text-wa-deep">
                                {{ fc('about.timeline.item'.$loop->iteration.'-qtr', $qtr) }}</div>
                        </div>
                        <div class="col-span-1 flex justify-center relative">
                            <span class="w-3 h-3 rounded-full {{ $shipped ? 'bg-wa-green' : 'bg-paper-300' }} mt-2 relative z-10"></span>
                            @if (!$loop->last)<div class="absolute top-5 bottom-[-28px] w-px bg-paper-200"></div>@endif
                        </div>
                        <div class="col-span-9">
                            <h3 class="serif text-[28px] leading-tight">
                                {{ fc('about.timeline.item'.$loop->iteration.'-title', $title) }}</h3>
                            <p class="text-[13.5px] text-ink-700 mt-2 leading-relaxed max-w-2xl">
                                {{ fc('about.timeline.item'.$loop->iteration.'-desc', $desc) }}</p>
                            @if ($shipped)
                                <span class="pill bg-wa-bubble text-wa-deep text-[10px] mt-3 inline-flex">
                                    <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('shipped') }}
                                </span>
                            @else
                                <span class="pill bg-paper-100 text-ink-700 text-[10px] mt-3 inline-flex">{{ __('upcoming') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @php $sec['timeline'] = ob_get_clean(); @endphp

    {{-- ============== NUMBERS ============== --}}
    @php ob_start(); @endphp
    <section data-fc-section="numbers" class="bg-paper-50 hairline-t hairline-b">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2"><div class="feature-num">04</div></div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">
                        {{ fc('about.numbers.eyebrow', __('— By the numbers')) }}</div>
                    <div class="text-[13px] font-semibold">
                        {{ fc('about.numbers.sublabel', __('Where we are today')) }}</div>
                </div>
                <div class="col-span-7 flex items-end justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                    <span>{{ __('refreshed') }} {{ now()->format('M Y') }}</span>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 reveal" style="--d:120ms">
                @foreach ([
                    ['90M', __('DMs / month'), __('across 40 markets')],
                    ['6,400', __('accounts'), __('automating every day')],
                    ['$0', __('per-seat fees'), __('and never will be')],
                    ['96%', __('CSAT'), '11,900 ' . __('ratings')],
                    ['138%', __('net retention'), __('over 12 months')],
                    ['2.1s', __('reply latency'), __('median, comment → DM')],
                    ['42s', __('first response'), __('SLA, business hours')],
                    ['99.98%', __('uptime'), __('rolling 90 days')],
                ] as [$big, $label, $sub])
                    <div class="hairline rounded-2xl bg-white p-6">
                        <div class="serif text-[56px] leading-none tabular text-wa-deep">
                            {{ fc('about.numbers.item'.$loop->iteration.'-big', $big) }}</div>
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mt-3">
                            {{ fc('about.numbers.item'.$loop->iteration.'-label', $label) }}</div>
                        <div class="text-[12px] text-ink-600 mt-1">
                            {{ fc('about.numbers.item'.$loop->iteration.'-sub', $sub) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @php $sec['numbers'] = ob_get_clean(); @endphp

    {{-- ============== PRESS ============== --}}
    @php ob_start(); @endphp
    <section data-fc-section="press" class="bg-white">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2"><div class="feature-num">05</div></div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">
                        {{ fc('about.press.eyebrow', __('— In the news')) }}</div>
                    <div class="text-[13px] font-semibold">
                        {{ fc('about.press.sublabel', __('Press & coverage')) }}</div>
                </div>
            </div>

            <h2 class="serif text-[88px] leading-[0.92] tracking-[-0.02em] mb-12 reveal">
                {!! fc('about.press.headline', __('What people<br>are <span class="italic text-wa-deep">writing.</span>')) !!}
            </h2>

            <div class="grid grid-cols-2 lg:grid-cols-3 gap-5 reveal" style="--d:120ms">
                @foreach ([
                    ['TechCrunch', __('Mar 2026'), __('":brand quietly became the Instagram automation tool every creator wanted — without the per-seat trap."', ['brand' => brand_name()])],
                    ['The Information', __('Feb 2026'), __('"Bengaluru-Berlin startup hits 90M DMs a month with a 32-person team."')],
                    ['Forbes India', __('Jan 2026'), __('"How :brand is replacing three tools at every brand that sells through Instagram."', ['brand' => brand_name()])],
                    ['YourStory', __('Nov 2025'), __('"The bootstrap-to-Seed story behind India\'s fastest-growing Instagram automation platform."')],
                    ['Sifted', __('Sep 2025'), __('":brand opens Berlin office, signals European push for SOC 2 + GDPR-native automation."', ['brand' => brand_name()])],
                    ['Product Hunt', __('Aug 2025'), __('"#1 product of the week — AI flow generation goes viral, 12k upvotes in 24 hours."')],
                ] as [$outlet, $date, $blurb])
                    <div class="hairline rounded-2xl bg-paper-50 p-6 hover:border-wa-deep transition">
                        <div class="flex items-center justify-between mb-3">
                            <div class="serif text-[20px]">{{ fc('about.press.item'.$loop->iteration.'-outlet', $outlet) }}</div>
                            <div class="mono text-[10px] text-ink-500">{{ fc('about.press.item'.$loop->iteration.'-date', $date) }}</div>
                        </div>
                        <p class="text-[13px] text-ink-700 leading-relaxed">
                            {{ fc('about.press.item'.$loop->iteration.'-blurb', $blurb) }}</p>
                        <a href="#" class="text-[12px] text-wa-deep font-semibold mt-4 inline-block">{{ __('Read article →') }}</a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @php $sec['press'] = ob_get_clean(); @endphp

    {{-- ============== BACKERS ============== --}}
    @php ob_start(); @endphp
    <section data-fc-section="backers" class="bg-paper-50 hairline-t hairline-b">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2"><div class="feature-num">06</div></div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1">
                        {{ fc('about.backers.eyebrow', __('— Backed by')) }}</div>
                    <div class="text-[13px] font-semibold">
                        {{ fc('about.backers.sublabel', __('Investors & angels')) }}</div>
                </div>
            </div>

            <h2 class="serif text-[88px] leading-[0.92] tracking-[-0.02em] mb-12 reveal">
                {!! fc('about.backers.headline', __('Funded by people who<br>have <span class="italic text-wa-deep">shipped.</span>')) !!}
            </h2>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-5 reveal" style="--d:120ms">
                @foreach (['Y Combinator', 'Sequoia Surge', 'Lightspeed', 'Tiger Global', 'Better Capital', 'Peak XV'] as $vc)
                    <div class="hairline rounded-2xl bg-white p-6 flex items-center justify-center min-h-[100px]">
                        <div class="serif text-[20px] text-center">{{ fc('about.backers.vc'.$loop->iteration, $vc) }}</div>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 hairline rounded-3xl bg-white p-8">
                <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-4">
                    {{ fc('about.backers.operators-label', __('— And these operators')) }}</div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-3 text-[13px]">
                    @foreach ([
                        ['Naval Ravikant', __('Founder, AngelList')],
                        ['Patrick Collison', __('Co-founder, Stripe')],
                        ['Kunal Shah', __('Founder, CRED')],
                        ['Sahil Lavingia', __('Founder, Gumroad')],
                        ['Lenny Rachitsky', __("Lenny's Newsletter")],
                        ['Calvin French-Owen', __('Co-founder, Segment')],
                        ['Nikita Bier', __('Operator, ex-Meta')],
                        ['Suhail Doshi', __('Co-founder, Mixpanel')],
                    ] as [$name, $title])
                        <div>
                            <div class="font-semibold">{{ fc('about.backers.op'.$loop->iteration.'-name', $name) }}</div>
                            <div class="mono text-[10px] text-ink-500 mt-0.5">{{ fc('about.backers.op'.$loop->iteration.'-title', $title) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    @php $sec['backers'] = ob_get_clean(); @endphp

    {{-- ============== PULL QUOTE ============== --}}
    @php ob_start(); @endphp<x-frontend.pull-quote />@php $sec['pull-quote'] = ob_get_clean(); @endphp

    {{-- ============== FINAL CTA ============== --}}
    @php ob_start(); @endphp<x-frontend.cta-final :kicker="__('Come build with us')"
        :headline="__('Two founders.<br>Now <span class=\'italic text-wa-green\'>6,400</span> accounts.')"
        :subtitle="__('Live in 4 minutes. No credit card. Cancel anytime, keep your data.')"
        :secondaryLabel="__('Contact us')" :secondaryHref="url('/contact')" />@php $sec['cta-final'] = ob_get_clean(); @endphp

    @php
        foreach (fc_section_order('about', array_keys($sec)) as $slug) {
            if (fc_section_visible('about', $slug)) {
                echo $sec[$slug];
            }
        }
    @endphp

@endsection

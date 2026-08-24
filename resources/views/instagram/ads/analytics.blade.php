<x-layouts.instagram :title="__('Ads analytics')" ig-active="ads" page="instagram-ads-analytics">
    <div class="max-w-[1200px] mx-auto px-6 py-6">
        <a href="{{ route('instagram.ads') }}" class="inline-flex items-center gap-1.5 text-[12px] text-ink-500 hover:text-ink-800 mb-3">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 4l-4 4 4 4"/></svg>{{ __('Back to ads') }}
        </a>

        <section class="flex items-end justify-between gap-4 flex-wrap mb-5">
            <div>
                <div class="mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ brand_name() }} · {{ __('Ads analytics') }}</div>
                <h1 class="serif text-[28px] sm:text-[38px] leading-none">
                    {{ $mode === 'campaign' && $campaign ? $campaign->name : __('Performance') }}
                </h1>
            </div>
            @if ($picker->count())
                <form method="GET" action="{{ route('instagram.ads.analytics') }}">
                    <select name="id" onchange="this.form.submit()" class="field !py-1.5 !text-[12px]" style="min-width:240px;">
                        <option value="">{{ __('All campaigns (aggregate)') }}</option>
                        @foreach ($picker as $p)
                            <option value="{{ $p->id }}" @selected($mode === 'campaign' && $campaign && $campaign->id === $p->id)>{{ $p->name }} · {{ ucfirst(strtolower($p->status)) }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="ml-1 px-3 py-1.5 rounded-full ig-grad-soft text-white text-[12px] font-semibold">{{ __('Go') }}</button></noscript>
                </form>
            @endif
        </section>

        <x-admin.flash />

        @if ($mode === 'global')
            @php $a = $aggregate; @endphp
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                @foreach ([
                    [__('Campaigns'), number_format($a['total_campaigns']), 'ig-grad-soft text-white'],
                    [__('Active'), number_format($a['active']), 'bg-paper-0 hairline'],
                    [__('Spend'), number_format($a['spend'], 2), 'bg-paper-0 hairline'],
                    [__('Clicks'), number_format($a['clicks']), 'bg-paper-0 hairline'],
                    [__('Impressions'), number_format($a['impressions']), 'bg-paper-0 hairline'],
                    [__('Reach'), number_format($a['reach']), 'bg-paper-0 hairline'],
                    [__('CTR'), number_format($a['ctr'], 2).'%', 'bg-paper-0 hairline'],
                    [__('CPC'), number_format($a['cpc'], 2), 'bg-paper-0 hairline'],
                ] as $kpi)
                    <div class="{{ $kpi[2] }} rounded-2xl p-4 relative overflow-hidden">
                        @if (str_contains($kpi[2], 'ig-grad'))<div class="absolute inset-0 ig-dots opacity-25"></div>@endif
                        <div class="relative">
                            <div class="mono text-[10px] uppercase tracking-widest {{ str_contains($kpi[2], 'ig-grad') ? 'text-white/70' : 'text-ink-500' }}">{{ $kpi[0] }}</div>
                            <div class="serif text-[26px] leading-none mt-2 tabular">{{ $kpi[1] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Top campaigns by spend --}}
            <div class="bg-paper-0 hairline rounded-2xl p-5">
                <div class="serif text-[18px] mb-4">{{ __('Top campaigns by spend') }}</div>
                @php $maxSpend = max(1, collect($a['top'])->max('spend') ?? 1); @endphp
                @forelse ($a['top'] as $row)
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <a href="{{ route('instagram.ads.show', $row['id']) }}" class="text-ink-700 hover:text-ig-pink truncate" style="max-width:70%;">{{ $row['name'] }}</a>
                            <span class="mono text-ink-600">{{ number_format($row['spend'], 2) }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-paper-100 overflow-hidden"><div class="h-full ig-grad-soft" style="width:{{ round($row['spend'] / $maxSpend * 100) }}%"></div></div>
                    </div>
                @empty
                    <p class="text-[12.5px] text-ink-500">{{ __('No spend recorded yet.') }}</p>
                @endforelse
            </div>

        @elseif ($campaign)
            @php
                $m = $campaign->metrics;
                $cur = $campaign->insights['account_currency'] ?? '';
                $daily = is_array($campaign->insights['daily'] ?? null) ? $campaign->insights['daily'] : [];
                $maxSpend = 1;
                foreach ($daily as $d) $maxSpend = max($maxSpend, (float) ($d['spend'] ?? 0));
            @endphp
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                @foreach ([
                    [__('Spend'), $cur.' '.number_format($m['spend'], 2)],
                    [__('Clicks'), number_format($m['clicks'])],
                    [__('Impressions'), number_format($m['impressions'])],
                    [__('Conversions'), number_format($m['conversions'])],
                ] as $kpi)
                    <div class="bg-paper-0 hairline rounded-2xl p-4">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ $kpi[0] }}</div>
                        <div class="serif text-[26px] leading-none mt-2 tabular">{{ $kpi[1] }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Daily spend/clicks trend — static SVG, hydrated by the page JS for a hover tooltip. --}}
            <div class="bg-paper-0 hairline rounded-2xl p-5"
                 data-ads-chart
                 data-series='@json($daily)'>
                <div class="flex items-center justify-between mb-4">
                    <div class="serif text-[18px]">{{ __('Daily spend & clicks') }}</div>
                    <div class="flex items-center gap-3 text-[11px] text-ink-500">
                        <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm ig-grad-soft"></span>{{ __('Spend') }}</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-ig-pink"></span>{{ __('Clicks') }}</span>
                    </div>
                </div>
                @if (count($daily))
                    <div style="display:flex;align-items:flex-end;gap:4px;height:160px;">
                        @foreach ($daily as $d)
                            @php
                                $spend = (float) ($d['spend'] ?? 0);
                                $h = max(2, round($spend / $maxSpend * 150));
                            @endphp
                            <div style="flex:1 1 0;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;"
                                 data-bar data-date="{{ $d['date'] ?? '' }}" data-spend="{{ $spend }}" data-clicks="{{ (int) ($d['clicks'] ?? 0) }}" title="{{ $d['date'] ?? '' }}: {{ number_format($spend, 2) }} · {{ (int) ($d['clicks'] ?? 0) }} clicks">
                                <div class="ig-grad-soft rounded-t" style="width:70%;height:{{ $h }}px;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-between mono text-[9px] text-ink-400 mt-2">
                        <span>{{ \Illuminate\Support\Arr::first($daily)['date'] ?? '' }}</span>
                        <span>{{ \Illuminate\Support\Arr::last($daily)['date'] ?? '' }}</span>
                    </div>
                @else
                    <p class="text-[12.5px] text-ink-500 py-8 text-center">{{ __('No daily data yet — it appears once the ad starts delivering.') }}</p>
                @endif
            </div>
        @else
            <div class="bg-paper-0 hairline rounded-2xl p-12 text-center">
                <div class="serif text-[22px]">{{ __('Campaign not found') }}</div>
            </div>
        @endif
    </div>
</x-layouts.instagram>

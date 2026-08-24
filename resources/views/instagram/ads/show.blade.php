<x-layouts.instagram :title="$campaign->name" ig-active="ads" page="instagram-ads-show">
    @php
        $statusStyle = [
            'ACTIVE' => ['bg-wa-bubble text-wa-deep', __('Active')],
            'PAUSED' => ['bg-amber-100 text-amber-700', __('Paused')],
            'DRAFT'  => ['bg-paper-100 text-ink-600', __('Draft')],
            'FAILED' => ['bg-accent-coral/15 text-accent-coral', __('Failed')],
        ];
        $st  = $statusStyle[$campaign->status] ?? ['bg-paper-100 text-ink-600', ucfirst(strtolower((string) $campaign->status))];
        $m   = $campaign->metrics;
        $cur = $campaign->insights['account_currency'] ?? '';
        $img = $campaign->insights['creative_image_url']
            ?? ($campaign->creative_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($campaign->creative_image) : null);
        $adTypeLabel = ['ig_direct' => __('Click-to-Instagram-DM'), 'link' => __('Traffic'), 'boost' => __('Boosted post')];
    @endphp

    <div class="max-w-[1100px] mx-auto px-6 py-6">
        <a href="{{ route('instagram.ads') }}" class="inline-flex items-center gap-1.5 text-[12px] text-ink-500 hover:text-ink-800 mb-3">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 4l-4 4 4 4"/></svg>{{ __('Back to ads') }}
        </a>

        <x-admin.flash />

        {{-- ===== HEADER ===== --}}
        <section class="flex items-start justify-between gap-4 flex-wrap mb-5">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-[10px] px-2 py-0.5 rounded-full {{ $st[0] }}">{{ $st[1] }}</span>
                    <span class="mono text-[9.5px] uppercase tracking-widest text-ink-400">{{ $adTypeLabel[$campaign->adType()] ?? $campaign->adType() }}</span>
                </div>
                <h1 class="serif text-[26px] sm:text-[34px] leading-tight">{{ $campaign->name }}</h1>
                @if ($campaign->instagramAccount && $campaign->instagramAccount->username)
                    <div class="text-[12px] text-ink-500 mt-1">{{ '@'.$campaign->instagramAccount->username }}</div>
                @endif
            </div>
            <div class="flex items-center gap-2 shrink-0 flex-wrap">
                <a href="{{ route('instagram.ads.edit', $campaign->id) }}" class="px-4 py-2 rounded-full hairline bg-paper-0 text-ink-700 text-[12px] font-semibold hover:bg-paper-50">{{ __('Edit') }}</a>
                <a href="{{ route('instagram.ads.analytics', ['id' => $campaign->id]) }}" class="px-4 py-2 rounded-full hairline bg-paper-0 text-ink-700 text-[12px] font-semibold hover:bg-paper-50">{{ __('Analytics') }}</a>
                @if (in_array($campaign->status, ['ACTIVE', 'PAUSED'], true))
                    <form method="POST" action="{{ route('instagram.ads.toggle', $campaign->id) }}">@csrf
                        <button class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ $campaign->status === 'ACTIVE' ? __('Pause') : __('Activate') }}</button>
                    </form>
                @endif
            </div>
        </section>

        @if ($campaign->status === 'FAILED' && $campaign->meta_last_error)
            <div class="bg-accent-coral/10 hairline rounded-2xl p-4 mb-4">
                <div class="text-[12.5px] text-accent-coral font-semibold mb-1">{{ __('Meta rejected this ad') }}</div>
                <p class="text-[12px] text-ink-600">{{ $campaign->meta_last_error }}</p>
                <form method="POST" action="{{ route('instagram.ads.retry', $campaign->id) }}" class="mt-3">@csrf
                    <button class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ __('Retry publish') }}</button>
                </form>
            </div>
        @endif

        {{-- ===== KPIs ===== --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            @foreach ([
                [__('Spend'), $cur.' '.number_format($m['spend'], 2)],
                [__('Impressions'), number_format($m['impressions'])],
                [__('Clicks'), number_format($m['clicks'])],
                [__('Reach'), number_format($m['reach'])],
                [__('Conversions'), number_format($m['conversions'])],
                [__('CTR'), number_format($m['ctr'], 2).'%'],
                [__('CPC'), $cur.' '.number_format($m['cpc'], 2)],
                [__('Ad sets → Ads'), (int) $campaign->ad_set_count.' → '.(int) $campaign->ad_count],
            ] as $kpi)
                <div class="bg-paper-0 hairline rounded-2xl p-4">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ $kpi[0] }}</div>
                    <div class="serif text-[24px] leading-none mt-2 tabular">{{ $kpi[1] }}</div>
                </div>
            @endforeach
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-start;">
            {{-- ===== LEFT: creative + targeting ===== --}}
            <div style="flex:1 1 380px;min-width:0;display:flex;flex-direction:column;gap:1rem;">
                <div class="bg-paper-0 hairline rounded-2xl p-5">
                    <div class="serif text-[18px] mb-3">{{ __('Creative') }}</div>
                    <div class="flex gap-4">
                        <span class="w-28 h-28 rounded-xl hairline bg-paper-50 bg-center bg-cover grid place-items-center text-ink-300 shrink-0"
                              @if ($img) style="background-image:url('{{ $img }}')" @endif>
                            @unless ($img)<span class="text-[11px]">{{ __('No image') }}</span>@endunless
                        </span>
                        <div class="min-w-0">
                            @if ($campaign->creative_title)<div class="font-semibold text-[14px]">{{ $campaign->creative_title }}</div>@endif
                            @if ($campaign->creative_body)<p class="text-[12.5px] text-ink-600 mt-1">{{ $campaign->creative_body }}</p>@endif
                            @if ($campaign->dm_welcome)
                                <div class="mt-2 text-[11.5px] text-ink-500"><span class="mono uppercase tracking-widest text-[9px]">{{ __('DM welcome') }}</span><br>{{ $campaign->dm_welcome }}</div>
                            @endif
                            @if ($campaign->creative_link_url)<a href="{{ $campaign->creative_link_url }}" class="text-[11.5px] text-ig-pink break-all" target="_blank" rel="noopener">{{ $campaign->creative_link_url }}</a>@endif
                        </div>
                    </div>
                </div>

                @php $t = is_array($campaign->targeting) ? $campaign->targeting : []; @endphp
                @if ($t)
                    <div class="bg-paper-0 hairline rounded-2xl p-5">
                        <div class="serif text-[18px] mb-3">{{ __('Audience') }}</div>
                        <div class="text-[12.5px] text-ink-600 space-y-1.5">
                            @if (!empty($t['countries']))<div><span class="text-ink-400">{{ __('Countries:') }}</span> {{ implode(', ', (array) $t['countries']) }}</div>@endif
                            <div><span class="text-ink-400">{{ __('Age:') }}</span> {{ ($t['age_min'] ?? 18) }}–{{ ($t['age_max'] ?? 65) }}</div>
                            <div><span class="text-ink-400">{{ __('Gender:') }}</span> {{ ucfirst($t['gender'] ?? 'all') }}</div>
                            @if (!empty($t['interests']))<div><span class="text-ink-400">{{ __('Interests:') }}</span> {{ implode(', ', (array) $t['interests']) }}</div>@endif
                            @if (!empty($campaign->publisher_platforms))<div><span class="text-ink-400">{{ __('Placement:') }}</span> {{ implode(', ', array_map('ucfirst', (array) $campaign->publisher_platforms)) }}</div>@endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- ===== RIGHT: Meta tree + actions ===== --}}
            <div style="flex:0 1 320px;min-width:280px;display:flex;flex-direction:column;gap:1rem;">
                <div class="bg-paper-0 hairline rounded-2xl p-5">
                    <div class="serif text-[18px] mb-3">{{ __('Meta entities') }}</div>
                    <dl class="text-[12px] space-y-2">
                        @foreach ([
                            __('Campaign') => $campaign->facebook_id,
                            __('Ad set')   => $campaign->meta_adset_id,
                            __('Creative') => $campaign->meta_creative_id,
                            __('Ad')       => $campaign->meta_ad_id,
                        ] as $label => $val)
                            <div class="flex items-center justify-between gap-2">
                                <dt class="mono uppercase tracking-widest text-[9px] text-ink-500">{{ $label }}</dt>
                                <dd class="mono text-[11px] text-ink-700 truncate" style="max-width:60%;">{{ $val ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @if ($campaign->meta_synced_at)
                        <p class="text-[10.5px] text-ink-400 mt-3">{{ __('Synced') }} {{ $campaign->meta_synced_at->diffForHumans() }}</p>
                    @endif
                </div>

                <div class="bg-paper-0 hairline rounded-2xl p-5">
                    <div class="serif text-[16px] mb-3">{{ __('Actions') }}</div>
                    <div class="flex flex-col gap-2">
                        @if ($campaign->facebook_id)
                            <form method="POST" action="{{ route('instagram.ads.refresh', $campaign->id) }}">@csrf
                                <button class="w-full px-4 py-2 rounded-full hairline bg-paper-0 text-ink-700 text-[12px] font-semibold hover:bg-paper-50">{{ __('Refresh insights') }}</button>
                            </form>
                        @endif
                        @if ($campaign->status !== 'DRAFT')
                            <form method="POST" action="{{ route('instagram.ads.retry', $campaign->id) }}">@csrf
                                <button class="w-full px-4 py-2 rounded-full hairline bg-paper-0 text-ink-700 text-[12px] font-semibold hover:bg-paper-50">{{ __('Re-publish to Meta') }}</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('instagram.ads.destroy', $campaign->id) }}" data-confirm="{{ __('Delete this campaign? This also removes it from Meta.') }}">@csrf @method('DELETE')
                            <button class="w-full px-4 py-2 rounded-full bg-accent-coral/10 text-accent-coral text-[12px] font-semibold hover:opacity-80">{{ __('Delete campaign') }}</button>
                        </form>
                    </div>
                </div>

                @if ($campaign->facebook_id)
                    <div class="bg-paper-0 hairline rounded-2xl p-5" data-estimate data-endpoint="{{ route('instagram.ads.estimate', $campaign->id) }}">
                        <div class="serif text-[16px] mb-2">{{ __('Estimated reach') }}</div>
                        <button type="button" data-estimate-btn class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ __('Estimate') }}</button>
                        <div class="text-[13px] text-ink-700 mt-2 tabular" data-estimate-out></div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.instagram>

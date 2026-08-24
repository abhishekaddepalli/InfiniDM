<x-layouts.instagram :title="__('Ads')" ig-active="ads" page="instagram-ads-index">
    @php
        $statusStyle = [
            'ACTIVE' => ['bg-wa-bubble text-wa-deep', __('Active')],
            'PAUSED' => ['bg-amber-100 text-amber-700', __('Paused')],
            'DRAFT'  => ['bg-paper-100 text-ink-600', __('Draft')],
            'FAILED' => ['bg-accent-coral/15 text-accent-coral', __('Failed')],
        ];
        $adTypeLabel = [
            'ig_direct' => __('Click-to-DM'),
            'link'      => __('Traffic'),
            'boost'     => __('Boost'),
        ];
    @endphp

    <div class="max-w-[1500px] mx-auto px-6 py-6">
        <x-admin.flash />

        {{-- ===== HERO ===== --}}
        <section class="flex items-end justify-between mb-5 gap-4 flex-wrap">
            <div>
                <div class="mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ brand_name() }} · {{ __('Instagram ads') }}</div>
                <h1 class="serif text-[30px] sm:text-[40px] leading-[1.0]">{{ __('Promote &') }} <span class="ig-text italic">{{ __('grow') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Run click-to-Instagram-DM and traffic ads on the official Meta Marketing API — replies land in your :brand inbox.', ['brand' => brand_name()]) }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('instagram.ads.analytics') }}" class="px-4 py-2 rounded-full hairline bg-paper-0 text-ink-700 text-[12px] font-semibold hover:bg-paper-50 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 13h12M4 11V7M8 11V4M12 11V9"/></svg>{{ __('Analytics') }}
                </a>
                <a href="{{ route('instagram.ads.create') }}" class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('New campaign') }}
                </a>
            </div>
        </section>

        {{-- ===== CONNECT BANNER (no ad account) ===== --}}
        @unless ($hasAdAccount)
            <div class="bg-paper-0 hairline rounded-2xl p-5 mb-4 flex items-center justify-between gap-4 flex-wrap"
                 style="border-left:3px solid var(--ig-pink,#C13584);">
                <div class="flex items-start gap-3">
                    <span class="ig-grad w-10 h-10 rounded-xl grid place-items-center text-white shrink-0"><svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 6h12v6H2zM2 6l2-3h8l2 3"/></svg></span>
                    <div>
                        <div class="serif text-[18px]">{{ __('Connect your ad account') }}</div>
                        <p class="text-[12.5px] text-ink-600 mt-0.5">{{ __('Link a Meta ad account to your Instagram account before you can create campaigns.') }}</p>
                    </div>
                </div>
                <a href="{{ route('instagram.ads.connect') }}" class="px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90 shrink-0">{{ __('Connect ad account') }}</a>
            </div>
        @endunless

        {{-- ===== KPI ROW ===== --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="ig-grad-soft text-white rounded-2xl p-4 relative overflow-hidden">
                <div class="absolute inset-0 ig-dots opacity-25"></div>
                <div class="relative">
                    <div class="mono text-[10px] uppercase tracking-widest text-white/70">{{ __('Campaigns') }}</div>
                    <div class="serif text-[30px] leading-none mt-2 tabular">{{ number_format($totals['total']) }}</div>
                    <div class="text-[10.5px] text-white/80 mt-1">{{ __('all time') }}</div>
                </div>
            </div>
            <div class="bg-paper-0 hairline rounded-2xl p-4">
                <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Active') }}</div>
                <div class="serif text-[30px] leading-none mt-2 tabular text-wa-deep">{{ number_format($totals['active']) }}</div>
                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('running now') }}</div>
            </div>
            <div class="bg-paper-0 hairline rounded-2xl p-4">
                <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Spend') }}</div>
                <div class="serif text-[30px] leading-none mt-2 tabular">{{ number_format($totals['spend'], 2) }}</div>
                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('last 7 days') }}</div>
            </div>
            <div class="bg-paper-0 hairline rounded-2xl p-4">
                <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Clicks') }}</div>
                <div class="serif text-[30px] leading-none mt-2 tabular text-ig-pink">{{ number_format($totals['clicks']) }}</div>
                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('link taps') }}</div>
            </div>
        </div>

        {{-- ===== FILTER BAR ===== --}}
        <form method="GET" action="{{ route('instagram.ads') }}" class="flex items-center gap-2 flex-wrap mb-4">
            @foreach (['all' => __('All'), 'ACTIVE' => __('Active'), 'PAUSED' => __('Paused'), 'DRAFT' => __('Draft'), 'FAILED' => __('Failed')] as $key => $label)
                <a href="{{ route('instagram.ads', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $currentSearch ?: null])) }}"
                   class="px-3 py-1.5 rounded-full text-[12px] font-semibold {{ $currentStatus === $key ? 'ig-grad-soft text-white' : 'hairline bg-paper-0 text-ink-600 hover:bg-paper-50' }}">
                    {{ $label }}
                    <span class="ml-1 opacity-70">{{ (int) ($statusCounts[$key] ?? 0) }}</span>
                </a>
            @endforeach
            <div class="ml-auto relative">
                <input type="search" name="q" value="{{ $currentSearch }}" placeholder="{{ __('Search campaigns') }}" class="field !py-1.5 !text-[12px] !pl-8" style="min-width:220px;">
                <svg viewBox="0 0 16 16" class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="7" cy="7" r="4.5"/><path d="M11 11l3 3"/></svg>
                @if ($currentStatus !== 'all')<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
            </div>
        </form>

        {{-- ===== CAMPAIGN CARDS ===== --}}
        @if ($campaigns->isEmpty())
            <div class="bg-paper-0 hairline rounded-2xl p-12 text-center">
                <span class="ig-grad w-14 h-14 rounded-2xl grid place-items-center text-white mx-auto mb-3"><svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="18" height="12" rx="3"/><path d="M3 6l3-3h12l3 3"/></svg></span>
                <div class="serif text-[24px]">{{ __('No campaigns yet') }}</div>
                <p class="text-[12.5px] text-ink-500 mt-1">{{ __('Launch your first Instagram ad and it will appear here with live spend + clicks.') }}</p>
                <a href="{{ route('instagram.ads.create') }}" class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('New campaign') }}</a>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:0.75rem;">
                @foreach ($campaigns as $c)
                    @php
                        $m  = $c->metrics;
                        $st = $statusStyle[$c->status] ?? ['bg-paper-100 text-ink-600', ucfirst(strtolower((string) $c->status))];
                        $cur = $c->insights['account_currency'] ?? '';
                    @endphp
                    <div class="bg-paper-0 hairline rounded-2xl p-4 flex flex-col ig-card-hover">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] px-2 py-0.5 rounded-full {{ $st[0] }}">{{ $st[1] }}</span>
                            <span class="mono text-[9.5px] uppercase tracking-widest text-ink-400">{{ $adTypeLabel[$c->adType()] ?? $c->adType() }}</span>
                        </div>
                        <a href="{{ route('instagram.ads.show', $c->id) }}" class="serif text-[18px] leading-snug mt-2 hover:text-ig-pink line-clamp-2">{{ $c->name }}</a>
                        @if ($c->instagramAccount && $c->instagramAccount->username)
                            <div class="text-[11px] text-ink-500 mt-0.5">{{ '@'.$c->instagramAccount->username }}</div>
                        @endif

                        <div class="grid grid-cols-3 gap-2 mt-3 pt-3 hairline-t">
                            <div><div class="serif text-[18px] tabular leading-none">{{ $cur }} {{ number_format($m['spend'], 2) }}</div><div class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1">{{ __('Spend') }}</div></div>
                            <div><div class="serif text-[18px] tabular leading-none">{{ number_format($m['clicks']) }}</div><div class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1">{{ __('Clicks') }}</div></div>
                            <div><div class="serif text-[18px] tabular leading-none">{{ number_format($m['impressions']) }}</div><div class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1">{{ __('Impr.') }}</div></div>
                        </div>

                        @if ($c->status === 'FAILED' && $c->meta_last_error)
                            <div class="text-[10.5px] text-accent-coral mt-2 line-clamp-2">{{ $c->meta_last_error }}</div>
                        @endif

                        <div class="flex items-center gap-2 mt-3 pt-3 hairline-t">
                            <a href="{{ route('instagram.ads.show', $c->id) }}" class="text-[11.5px] font-semibold text-ig-pink hover:opacity-80">{{ __('View') }}</a>
                            <a href="{{ route('instagram.ads.edit', $c->id) }}" class="text-[11.5px] font-semibold text-ink-500 hover:text-ink-800">{{ __('Edit') }}</a>
                            @if (in_array($c->status, ['ACTIVE', 'PAUSED'], true))
                                <form method="POST" action="{{ route('instagram.ads.toggle', $c->id) }}" class="ml-auto">
                                    @csrf
                                    <button class="text-[11.5px] font-semibold text-ink-600 hover:text-wa-deep">{{ $c->status === 'ACTIVE' ? __('Pause') : __('Activate') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ===== BOOST AN EXISTING POST ===== --}}
        @if ($boostAcc && !empty($boostMedia))
            <section class="bg-paper-0 hairline rounded-2xl p-5 mt-5">
                <div class="serif text-[20px] mb-1">{{ __('Boost a recent post') }}</div>
                <p class="text-[12.5px] text-ink-600 mb-4">{{ __('Put budget behind a post you already published — runs as @') }}{{ $boostAcc->username ?: $boostAcc->ig_user_id }}. {{ __('Created paused so you review it first.') }}</p>
                @if (trim((string) $boostAcc->ad_account_id) === '')
                    <p class="text-[12px] text-ink-500">{{ __('Connect an ad account to enable boosting.') }} <a href="{{ route('instagram.ads.connect') }}" class="text-ig-pink font-semibold">{{ __('Connect') }}</a></p>
                @else
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:0.75rem;">
                        @foreach ($boostMedia as $mm)
                            @php $t = $mm['thumbnail_url'] ?? ($mm['media_url'] ?? ''); @endphp
                            <form method="POST" action="{{ route('instagram.ads.boost') }}" class="hairline rounded-xl p-3 flex gap-3">
                                @csrf
                                <input type="hidden" name="instagram_account_id" value="{{ $boostAcc->id }}">
                                <input type="hidden" name="media_id" value="{{ $mm['id'] }}">
                                <input type="hidden" name="caption" value="{{ \Illuminate\Support\Str::limit($mm['caption'] ?? '', 40, '') }}">
                                <span class="w-16 h-16 rounded-lg bg-center bg-cover hairline shrink-0" style="background-image:url('{{ $t }}')"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-[11.5px] text-ink-600 truncate">{{ \Illuminate\Support\Str::limit($mm['caption'] ?? ucfirst(strtolower($mm['media_type'] ?? 'post')), 48) }}</div>
                                    <div class="flex items-center gap-1.5 mt-2">
                                        <input type="number" name="daily_budget" min="1" step="1" value="5" class="field !py-1.5 !text-[12px]" style="width:4rem;" title="{{ __('Daily budget') }}">
                                        <input type="number" name="days" min="1" max="30" value="5" class="field !py-1.5 !text-[12px]" style="width:3.5rem;" title="{{ __('Days') }}">
                                        <button class="px-3 py-1.5 rounded-lg ig-grad-soft text-white text-[12px] font-semibold whitespace-nowrap">{{ __('Boost') }}</button>
                                    </div>
                                    <div class="mono text-[9.5px] text-ink-400 mt-1">{{ __('budget/day · days') }}</div>
                                </div>
                            </form>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        @if ($connected->count())
            <p class="text-[12px] text-ink-500 mt-4">{{ __('Ads run as') }}
                @foreach ($connected as $a)<span class="font-semibold text-ink-700">{{ '@'.($a->username ?: $a->ig_user_id) }}</span>@if (!$loop->last), @endif @endforeach
            </p>
        @endif
    </div>
</x-layouts.instagram>

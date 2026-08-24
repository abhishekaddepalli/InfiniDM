<x-layouts.instagram :title="__('Ads')" ig-active="ads" page="instagram-ads">
    <div class="max-w-[1440px] mx-auto px-5 sm:px-6 py-6 space-y-4">
        <section class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-1.5">{{ __('Instagram ads') }}</div>
                <h1 class="font-serif text-[36px] sm:text-[44px] leading-none">{{ __('Promote &') }} <span class="ig-text">{{ __('grow') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2">{{ __('Run click-to-Instagram-DM and post-boost ads on the official Meta Marketing API — replies land in your :brand inbox.', ['brand' => brand_name()]) }}</p>
            </div>
            <a href="{{ url('/meta-ads') }}" class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold inline-flex items-center gap-2 hover:opacity-90">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('Open Ads Manager') }}
            </a>
        </section>

        <x-admin.flash />

        <section class="grid sm:grid-cols-3 gap-3">
            @foreach ([
                ['Click-to-DM ads','Send people straight into a DM that your automations answer instantly.','<path d="M2 5h12v7H9l-3 2v-2H2z"/>','ig_direct'],
                ['Boost top posts','Put budget behind the posts already winning comments and saves.','<path d="M3 13l6-2.5 5-6 3.5 11-8.5-1.5z"/>','ig_direct'],
                ['Story & reel ads','Full-screen placements that drive followers and orders.','<rect x="4" y="2" width="8" height="12" rx="2"/>','ig_direct'],
            ] as $card)
                <a href="{{ url('/meta-ads').'?ad_type='.$card[3] }}" class="block bg-paper-0 border border-paper-200 rounded-2xl p-5 ig-card-hover">
                    <span class="w-10 h-10 rounded-xl bg-ig-pink/10 text-ig-pink grid place-items-center"><svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">{!! $card[2] !!}</svg></span>
                    <div class="font-serif text-[18px] mt-3">{{ __($card[0]) }}</div>
                    <p class="text-[12px] text-ink-600 mt-1">{{ __($card[1]) }}</p>
                    <span class="inline-flex items-center gap-1 text-[11.5px] text-ig-pink font-semibold mt-3">{{ __('Create') }}<svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 2l4 4-4 4"/></svg></span>
                </a>
            @endforeach
        </section>

        @if ($accounts->where('status', 'connected')->count())
            <p class="text-[12px] text-ink-500">{{ __('Ads run as') }}
                @foreach ($accounts->where('status', 'connected') as $a)<span class="font-semibold text-ink-700">{{ '@'.($a->username ?: $a->ig_user_id) }}</span>@if (!$loop->last), @endif @endforeach
            </p>
        @endif

        {{-- ===== BOOST AN EXISTING POST ===== --}}
        @php $boostAcc = $accounts->where('status', 'connected')->first(); @endphp
        @if ($boostAcc && !empty($boostMedia))
            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5">
                <div class="font-serif text-[20px] mb-1">{{ __('Boost a recent post') }}</div>
                <p class="text-[12.5px] text-ink-600 mb-4">{{ __('Put budget behind a post you already published — runs as @') }}{{ $boostAcc->username ?: $boostAcc->ig_user_id }}. {{ __('Created paused so you review it in Ads Manager first.') }}</p>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($boostMedia as $m)
                        @php $t = $m['thumbnail_url'] ?? ($m['media_url'] ?? ''); @endphp
                        <form method="POST" action="{{ route('instagram.ads.boost') }}" class="border border-paper-200 rounded-xl p-3 flex gap-3">
                            @csrf
                            <input type="hidden" name="instagram_account_id" value="{{ $boostAcc->id }}">
                            <input type="hidden" name="media_id" value="{{ $m['id'] }}">
                            <span class="w-16 h-16 rounded-lg bg-center bg-cover hairline shrink-0" style="background-image:url('{{ $t }}')"></span>
                            <div class="min-w-0 flex-1">
                                <div class="text-[11.5px] text-ink-600 truncate">{{ \Illuminate\Support\Str::limit($m['caption'] ?? ucfirst(strtolower($m['media_type'] ?? 'post')), 48) }}</div>
                                <div class="flex items-center gap-1.5 mt-2">
                                    <input type="number" name="daily_budget" min="1" step="1" value="5" class="field !py-1.5 !text-[12px] !w-16" title="{{ __('Daily budget') }}">
                                    <input type="number" name="days" min="1" max="30" value="5" class="field !py-1.5 !text-[12px] !w-14" title="{{ __('Days') }}">
                                    <button class="px-3 py-1.5 rounded-lg ig-grad-soft text-white text-[12px] font-semibold whitespace-nowrap">{{ __('Boost') }}</button>
                                </div>
                                <div class="mono text-[9.5px] text-ink-400 mt-1">{{ __('budget/day · days') }}</div>
                            </div>
                        </form>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="font-serif text-[20px]">{{ __('Ready to launch?') }}</div>
                <p class="text-[12.5px] text-ink-600 mt-1">{{ __('Campaigns are created and tracked in the unified Ads Manager — shared with your WhatsApp click-to-chat ads.') }}</p>
            </div>
            <a href="{{ url('/meta-ads') }}" class="px-5 py-2.5 rounded-full ig-grad text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('Go to Ads Manager') }}</a>
        </section>
    </div>
</x-layouts.instagram>

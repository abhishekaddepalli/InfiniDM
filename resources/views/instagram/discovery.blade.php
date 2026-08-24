<x-layouts.instagram :title="__('Discovery')" ig-active="discovery" page="instagram-discovery">
    @php
        $profile      = $profile ?? null;
        $hashtagMedia = $hashtagMedia ?? null;
        $firstAcc     = $accounts->first();
    @endphp
    <div class="max-w-[1500px] mx-auto px-6 py-6">

        {{-- HEADER --}}
        <section class="pb-4">
            <div class="flex items-center gap-3 mb-1.5 text-[11px] mono uppercase tracking-[0.18em] text-ink-500"><span>{{ __('Studio') }}</span><span class="w-1 h-1 rounded-full bg-ink-400"></span><span>{{ __('Discovery') }}</span></div>
            <h1 class="serif text-[40px] leading-none">{{ __('Research') }} <span class="ig-text italic">{{ __('Instagram') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Look up any public Business/Creator profile, and find top or recent posts for a hashtag. Needs a Facebook-login account with insights access.') }}</p>
        </section>

        <x-admin.flash />

        {{-- Facebook-Login requirement notice (Business Discovery is FB-Login only) --}}
        @php
            $igLoginAccounts = $accounts->where('login_type', 'instagram');
            $hasFbAccount    = $accounts->where('login_type', '!=', 'instagram')->isNotEmpty();
        @endphp
        @if ($accounts->isNotEmpty() && $igLoginAccounts->isNotEmpty())
            <div class="hairline rounded-2xl bg-ig-pink/5 p-4 mb-5 flex items-start gap-3">
                <svg viewBox="0 0 16 16" class="w-5 h-5 text-ig-pink shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6.5"/><path d="M8 5.5v3.5M8 11h.01"/></svg>
                <div class="text-[13px] text-ink-700 leading-relaxed">
                    <span class="font-semibold text-ink-900">{{ __('Discovery needs Facebook Login.') }}</span>
                    {{ __('Competitor + hashtag research uses Meta’s Business Discovery, which only works on an Instagram Business/Creator account linked to a Facebook Page and connected through “Connect with Facebook”.') }}
                    @if (!$hasFbAccount)
                        {{ __('None of your connected accounts use Facebook Login, so lookups will fail — connect Instagram through Facebook to enable this.') }}
                    @else
                        {{ __('Accounts connected via Instagram Login can’t be used here — pick a Facebook-connected account below, or reconnect one through Facebook.') }}
                    @endif
                    <a href="{{ url('/instagram/connect') }}" class="inline-flex items-center gap-1 mt-2 font-semibold text-ig-pink hover:underline">{{ __('Connect Instagram via Facebook') }} →</a>
                </div>
            </div>
        @endif

        @if ($accounts->isEmpty())
            <div class="bg-white hairline rounded-2xl p-12 text-center">
                <div class="serif text-[22px]">{{ __('No connected account') }}</div>
                <a href="{{ url('/instagram/connect') }}" class="inline-block mt-4 px-4 py-2 rounded-full ig-grad-soft text-white text-[13px] font-semibold">{{ __('Connect account') }}</a>
            </div>
        @else
            <div class="grid lg:grid-cols-2 gap-5 mb-5">
                {{-- COMPETITOR LOOKUP --}}
                <form method="POST" action="{{ route('instagram.discovery.search') }}" class="bg-white hairline rounded-2xl p-5">
                    @csrf
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('Competitor lookup') }}</div>
                    <div class="space-y-3">
                        <select name="instagram_account_id" class="field">
                            @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ '@'.($a->username ?: $a->ig_user_id) }}</option>@endforeach
                        </select>
                        <div class="flex items-end gap-2">
                            <input name="username" value="{{ old('username') }}" required class="field" placeholder="{{ __('competitor username (no @)') }}">
                            <button class="px-4 py-2.5 rounded-xl bg-ink-900 text-white text-[12.5px] font-semibold whitespace-nowrap">{{ __('Look up') }}</button>
                        </div>
                    </div>
                </form>

                {{-- HASHTAG INSIGHTS --}}
                <form method="POST" action="{{ route('instagram.discovery.hashtag') }}" class="bg-white hairline rounded-2xl p-5">
                    @csrf
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('Hashtag insights') }}</div>
                    <div class="space-y-3">
                        <select name="instagram_account_id" class="field">
                            @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ '@'.($a->username ?: $a->ig_user_id) }}</option>@endforeach
                        </select>
                        <div class="flex items-end gap-2">
                            <input name="hashtag" value="{{ old('hashtag') }}" required class="field" placeholder="{{ __('hashtag (no #)') }}">
                            <select name="kind" class="field !w-auto">
                                <option value="top_media">{{ __('Top') }}</option>
                                <option value="recent_media">{{ __('Recent') }}</option>
                            </select>
                            <button class="px-4 py-2.5 rounded-xl bg-ink-900 text-white text-[12.5px] font-semibold whitespace-nowrap">{{ __('Search') }}</button>
                        </div>
                        <p class="text-[11px] text-ink-400">{{ __('Up to 30 unique hashtags per rolling 7 days.') }}</p>
                    </div>
                </form>
            </div>

            {{-- COMPETITOR RESULT --}}
            @if ($profile)
                <div class="bg-white hairline rounded-2xl p-5 mb-5">
                    <div class="flex items-center gap-4">
                        @if (!empty($profile['profile_picture_url']))
                            <span class="block w-16 h-16 rounded-full bg-center bg-cover hairline shrink-0" style="background-image:url('{{ $profile['profile_picture_url'] }}')"></span>
                        @else
                            <span class="block w-16 h-16 rounded-full ig-grad-soft shrink-0"></span>
                        @endif
                        <div class="min-w-0">
                            <div class="serif text-[24px] leading-none">{{ '@'.($profile['username'] ?? '') }}</div>
                            <div class="text-[13px] text-ink-700">{{ $profile['name'] ?? '' }}</div>
                            @if (!empty($profile['website']))<a href="{{ $profile['website'] }}" target="_blank" rel="noopener" class="text-[12px] text-ig-pink">{{ $profile['website'] }}</a>@endif
                        </div>
                        <div class="flex-1"></div>
                        <div class="flex gap-6 text-center">
                            <div><div class="serif text-[22px] tabular">{{ number_format((int)($profile['followers_count'] ?? 0)) }}</div><div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Followers') }}</div></div>
                            <div><div class="serif text-[22px] tabular">{{ number_format((int)($profile['media_count'] ?? 0)) }}</div><div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Posts') }}</div></div>
                        </div>
                    </div>
                    @if (!empty($profile['biography']))<p class="text-[13px] text-ink-700 mt-3">{{ $profile['biography'] }}</p>@endif
                    @if (!empty($profile['media']['data']))
                        <div class="grid grid-cols-6 gap-2 mt-4">
                            @foreach ($profile['media']['data'] as $m)
                                @php $t = $m['media_url'] ?? ($m['thumbnail_url'] ?? ''); @endphp
                                <a href="{{ $m['permalink'] ?? '#' }}" target="_blank" rel="noopener" class="relative block aspect-square rounded-lg overflow-hidden hairline">
                                    @if ($t)<span class="block w-full h-full bg-center bg-cover" style="background-image:url('{{ $t }}')"></span>@else<span class="block w-full h-full bg-paper-100"></span>@endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            {{-- HASHTAG RESULT --}}
            @if (is_array($hashtagMedia))
                <div class="bg-white hairline rounded-2xl p-5">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">#{{ $queriedTag ?? '' }} · {{ ($hashtagKind ?? 'top_media') === 'recent_media' ? __('Recent') : __('Top') }}</div>
                    @if (empty($hashtagMedia))
                        <p class="text-[13px] text-ink-500">{{ __('No media returned for this hashtag.') }}</p>
                    @else
                        <div class="grid grid-cols-4 lg:grid-cols-6 gap-2">
                            @foreach ($hashtagMedia as $m)
                                @php $t = $m['media_url'] ?? ($m['thumbnail_url'] ?? ''); @endphp
                                <a href="{{ $m['permalink'] ?? '#' }}" target="_blank" rel="noopener" class="relative block aspect-square rounded-lg overflow-hidden hairline" title="{{ \Illuminate\Support\Str::limit($m['caption'] ?? '', 80) }}">
                                    @if ($t)<span class="block w-full h-full bg-center bg-cover" style="background-image:url('{{ $t }}')"></span>@else<span class="block w-full h-full bg-paper-100"></span>@endif
                                    <span class="absolute bottom-1 right-1 text-[9px] mono px-1 py-0.5 rounded bg-ink-900/70 text-white">♥ {{ (int)($m['like_count'] ?? 0) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        @endif
    </div>
</x-layouts.instagram>

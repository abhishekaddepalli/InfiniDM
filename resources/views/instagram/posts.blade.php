<x-layouts.instagram :title="__('My posts')" ig-active="posts" page="instagram-posts">
    @php
        $acctLabel = $account && $account->username ? '@'.$account->username : __('Select an account');
        $tabLink = fn ($t) => url('/instagram/posts?' . http_build_query(array_filter([
            'account' => $selectedAccountId ?: null,
            'tab'     => $t ?: null,
        ])));
        $compact = fn ($n) => $n >= 1000000 ? round($n / 1000000, 1) . 'M' : ($n >= 1000 ? round($n / 1000, 1) . 'K' : (string) $n);
    @endphp

    <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6" id="ig-posts"
         data-account="{{ $selectedAccountId }}"
         data-me="{{ $account?->username }}"
         data-me-avatar="{{ $account?->profile_pic_url }}">

        {{-- header --}}
        <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
            <div>
                <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-400">{{ __('Instagram') }} / {{ __('My posts') }}</div>
                <h1 class="serif text-[26px] leading-tight mt-1">{{ __('Your') }} <span class="ig-text italic">{{ __('grid') }}</span></h1>
            </div>

            <div class="flex items-center gap-2 shrink-0">
            {{-- Create post → the composer. Nothing is duplicated here: the grid
                 reads live from Graph, so whatever the composer publishes shows
                 up on the next load without us mirroring it into our own DB. --}}
            <a href="{{ url('/instagram/composer') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold shadow-soft hover:opacity-90 transition shrink-0">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M8 3.5v9M3.5 8h9"/></svg>{{ __('Create post') }}
            </a>

            {{-- account switcher — the grid is per-account, so this is the page's spine --}}
            @if ($accounts->count() > 1)
                <details class="relative">
                    <summary class="inline-flex items-center gap-2 px-3.5 py-2 rounded-full hairline bg-paper-0 text-[12.5px] font-medium cursor-pointer list-none select-none hover:bg-paper-50 transition">
                        <span class="ig-ring shrink-0">
                            @if ($account?->profile_pic_url)
                                <img src="{{ $account->profile_pic_url }}" alt="" class="block w-6 h-6 rounded-full object-cover">
                            @else
                                <span class="block w-6 h-6 rounded-full ig-grad-soft"></span>
                            @endif
                        </span>
                        {{ $acctLabel }}
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 6l4 4 4-4"/></svg>
                    </summary>
                    <div class="absolute right-0 mt-2 w-60 bg-paper-0 hairline rounded-2xl shadow-soft p-1.5 z-30">
                        @foreach ($accounts as $a)
                            <a href="{{ url('/instagram/posts?' . http_build_query(array_filter(['account' => $a->id, 'tab' => $tab !== 'all' ? $tab : null]))) }}"
                               class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl hover:bg-paper-50 transition {{ $a->id === $selectedAccountId ? 'bg-paper-50' : '' }}">
                                <span class="ig-ring shrink-0">
                                    @if ($a->profile_pic_url)
                                        <img src="{{ $a->profile_pic_url }}" alt="" class="block w-7 h-7 rounded-full object-cover">
                                    @else
                                        <span class="block w-7 h-7 rounded-full ig-grad-soft"></span>
                                    @endif
                                </span>
                                <span class="text-[12.5px] font-medium truncate">{{ '@'.($a->username ?: $a->ig_user_id) }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @elseif ($account)
                <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-full hairline bg-paper-0 text-[12.5px] font-medium">{{ $acctLabel }}</span>
            @endif
            </div>
        </div>

        @if (!$account)
            <div class="hairline rounded-2xl bg-paper-0 px-6 py-16 text-center">
                <span class="ig-grad-soft w-12 h-12 rounded-2xl grid place-items-center text-white mx-auto mb-3">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4"/></svg>
                </span>
                <div class="text-[13.5px] font-semibold">{{ __('No connected account') }}</div>
                <p class="text-[12px] text-ink-500 mt-1.5">{{ __('Connect an Instagram account to see your posts here.') }}</p>
                <a href="{{ route('instagram.connect') }}" class="inline-flex mt-4 px-4 py-2 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold">{{ __('Connect Instagram') }}</a>
            </div>
        @else
            {{-- tabs — Instagram's own: grid / reels / tagged. "Tagged" needs a
                 permission + endpoint we don't have, so it isn't faked here. --}}
            <div class="flex items-center justify-center gap-8 hairline-t pt-1 mb-5">
                @php $tabs = [['all', __('Posts'), '<rect x="2.6" y="2.6" width="4.6" height="4.6" rx="1"/><rect x="7.7" y="2.6" width="4.6" height="4.6" rx="1"/><rect x="12.8" y="2.6" width="4.6" height="4.6" rx="1"/><rect x="2.6" y="7.7" width="4.6" height="4.6" rx="1"/><rect x="7.7" y="7.7" width="4.6" height="4.6" rx="1"/><rect x="12.8" y="7.7" width="4.6" height="4.6" rx="1"/>'], ['reels', __('Reels'), '<rect x="3" y="4.3" width="14" height="11.4" rx="2.6"/><path d="M8.6 7.8 12.6 10l-4 2.2z" fill="currentColor" stroke="none"/>'], ['posts', __('Photos'), '<rect x="3" y="3" width="14" height="14" rx="3"/><circle cx="7.5" cy="7.5" r="1.4"/><path d="m3.6 14 4-4 3.4 3.2 2.2-2 3.2 3"/>'], ['stories', __('Stories'), '<circle cx="10" cy="10" r="7.5" stroke-dasharray="3 2.4"/><circle cx="10" cy="10" r="3"/>']]; @endphp
                @foreach ($tabs as [$key, $label, $icon])
                    <a href="{{ $tabLink($key === 'all' ? '' : $key) }}"
                       class="inline-flex items-center gap-1.5 py-3 -mt-px border-t-2 text-[11px] mono uppercase tracking-[0.12em] transition {{ $tab === $key ? 'border-ink-900 text-ink-900 font-semibold' : 'border-transparent text-ink-400 hover:text-ink-700' }}">
                        <svg viewBox="0 0 20 20" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">{!! $icon !!}</svg>{{ $label }}
                    </a>
                @endforeach
            </div>

            @if ($tab === 'stories')
                {{-- Active stories (last 24h). Tap any to play the full reel in an
                     Instagram-style viewer (progress bars, tap prev/next, hold to
                     pause). Data is stashed on the container for the player JS. --}}
                @php $needsFacebook = ($account?->login_type ?? '') === 'instagram' || !empty($storiesError); @endphp
                @if (empty($stories) && $needsFacebook)
                    {{-- Instagram-Login accounts CAN'T read stories — Meta only
                         exposes /stories on the Facebook-Login (Graph API) tier
                         with the insights permission. Make that crystal clear. --}}
                    <div class="max-w-lg mx-auto my-10 rounded-2xl hairline bg-amber-50/60 border-amber-200 p-6 text-center">
                        <span class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-amber-100 text-amber-600 grid place-items-center">
                            <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M12 8.5v4.5M12 16h.01"/><circle cx="12" cy="12" r="9"/></svg>
                        </span>
                        <div class="serif text-[18px] text-ink-900">{{ __('Story viewing needs a Facebook connection') }}</div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed mt-2">
                            {{ __('This account is connected with') }} <b>{{ __('Instagram Login') }}</b>. {{ __('Instagram’s API does not let apps read stories on that connection — it only works when the account is linked through') }} <b>{{ __('Facebook Login') }}</b> {{ __('(which grants the') }} <span class="mono text-[11.5px]">instagram_manage_insights</span> {{ __('permission).') }}
                        </p>
                        <div class="text-left text-[12px] text-ink-700 mt-4 space-y-1.5 bg-paper-0/70 rounded-xl p-3.5 hairline">
                            <div class="font-semibold text-ink-800 mb-1">{{ __('To enable it:') }}</div>
                            <div>1. {{ __('In Admin → Instagram settings, set login type to') }} <b>{{ __('Facebook') }}</b>.</div>
                            <div>2. {{ __('Make sure this Instagram account is linked to a Facebook Page.') }}</div>
                            <div>3. {{ __('Reconnect') }} {{ $account?->username ? '@'.$account->username : __('the account') }} {{ __('through the Facebook flow on') }} <a href="{{ route('instagram.connect') }}" class="ig-text font-semibold">/instagram</a>.</div>
                        </div>
                        @if (!empty($storiesError))
                            <p class="text-[10.5px] text-ink-400 mt-3">{{ __('Instagram said:') }} <span class="mono">{{ $storiesError }}</span></p>
                        @endif
                    </div>
                @elseif (empty($stories))
                    <div class="px-6 py-16 text-center">
                        <span class="ig-ring w-14 h-14 mx-auto mb-3 grid place-items-center"><span class="block w-[52px] h-[52px] rounded-full bg-paper-100 grid place-items-center text-ink-300"><svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9" stroke-dasharray="3 2.6"/></svg></span></span>
                        <div class="text-[13.5px] font-semibold">{{ __('No active stories') }}</div>
                        <p class="text-[12px] text-ink-500 mt-1.5">{{ __('Post a story on Instagram and it shows here for 24 hours.') }}</p>
                    </div>
                @else
                    <div id="ig-stories" data-stories='@json($stories)' data-me="{{ $account?->username }}" data-me-avatar="{{ $account?->profile_pic_url }}">
                        {{-- lead: your avatar ring — plays the whole reel from the top --}}
                        <div class="flex items-center gap-4 mb-6">
                            <button type="button" class="ig-story-open ig-ring shrink-0 grid place-items-center" data-index="0" title="{{ __('Watch your story') }}">
                                @if ($account?->profile_pic_url)
                                    <img src="{{ $account->profile_pic_url }}" alt="" class="block w-[68px] h-[68px] rounded-full object-cover ring-[2.5px] ring-white">
                                @else
                                    <span class="block w-[68px] h-[68px] rounded-full ig-grad-soft"></span>
                                @endif
                            </button>
                            <div>
                                <div class="serif text-[17px] text-ink-900">{{ __('Your story') }}</div>
                                <p class="text-[12px] text-ink-500 mt-0.5">{{ count($stories) }} {{ count($stories) === 1 ? __('frame') : __('frames') }} · {{ __('tap to play') }}</p>
                            </div>
                        </div>
                        {{-- each frame as a 9:16 tile --}}
                        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-1.5">
                            @foreach ($stories as $i => $s)
                                <button type="button" class="ig-story-open relative block rounded-xl overflow-hidden bg-paper-100 hairline" style="aspect-ratio:9/16" data-index="{{ $i }}">
                                    @if ($s['thumb'])
                                        <img src="{{ $s['thumb'] }}" alt="" loading="lazy" class="w-full h-full object-cover">
                                    @else
                                        <span class="w-full h-full grid place-items-center text-ink-300"><svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="2.5" width="16" height="19" rx="4"/></svg></span>
                                    @endif
                                    @if ($s['type'] === 'video')
                                        <span class="absolute top-1.5 right-1.5 text-white drop-shadow"><svg viewBox="0 0 20 20" class="w-3.5 h-3.5" fill="currentColor"><path d="M4 3.5v13l11-6.5z"/></svg></span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            @elseif (empty($media))
                <div class="px-6 py-16 text-center">
                    <span class="ig-grad-soft w-12 h-12 rounded-2xl grid place-items-center text-white mx-auto mb-3">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="4.5"/><path d="M12 8.5v7M8.5 12h7"/></svg>
                    </span>
                    <div class="text-[13.5px] font-semibold">{{ __('Nothing here yet') }}</div>
                    <p class="text-[12px] text-ink-500 mt-1.5">{{ $tab === 'all' ? __('Posts you publish will show up here.') : __('No media of this type on this account.') }}</p>
                    @if ($tab === 'all')
                        <a href="{{ url('/instagram/composer') }}"
                           class="inline-flex mt-4 px-4 py-2 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold">{{ __('Create your first post') }}</a>
                    @endif
                </div>
            @else
                {{-- the grid — square tiles, hover reveals likes/comments, exactly like the web app --}}
                <div class="grid grid-cols-3 lg:grid-cols-4 gap-1 sm:gap-1.5">
                    @foreach ($media as $m)
                        @php
                            $type  = strtoupper((string) ($m['media_type'] ?? ''));
                            $isVid = in_array($type, ['VIDEO', 'REELS'], true);
                            $thumb = $isVid ? ($m['thumbnail_url'] ?? $m['media_url'] ?? '') : ($m['media_url'] ?? '');
                            // Built here, not inline in the attribute: @json() can't
                            // take a multi-line array literal — Blade hands the raw
                            // text to PHP and the parser chokes on the unclosed '['.
                            $tile = [
                                'id'        => $m['id'] ?? '',
                                'caption'   => $m['caption'] ?? '',
                                'type'      => $type,
                                'media'     => $m['media_url'] ?? '',
                                'thumb'     => $thumb,
                                'permalink' => $m['permalink'] ?? '',
                                'likes'     => (int) ($m['like_count'] ?? 0),
                                'comments'  => (int) ($m['comments_count'] ?? 0),
                                'ts'        => $m['timestamp'] ?? '',
                            ];
                        @endphp
                        <button type="button" class="ig-post-tile group relative block aspect-square overflow-hidden bg-paper-100"
                                data-post='@json($tile)'>
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="" loading="lazy" class="w-full h-full object-cover">
                            @else
                                <span class="w-full h-full grid place-items-center text-ink-300">
                                    <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="m4 17 5-5 4 3.8 2.6-2.4L20 17"/></svg>
                                </span>
                            @endif

                            {{-- type glyph, like Instagram's corner marks --}}
                            @if ($type === 'CAROUSEL_ALBUM')
                                <span class="absolute top-2 right-2 text-white drop-shadow"><svg viewBox="0 0 20 20" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="6.5" y="2.5" width="11" height="11" rx="2"/><path d="M13.5 17.5h-9a2 2 0 0 1-2-2v-9"/></svg></span>
                            @elseif ($isVid)
                                <span class="absolute top-2 right-2 text-white drop-shadow"><svg viewBox="0 0 20 20" class="w-4 h-4" fill="currentColor"><path d="M4 3.5v13l11-6.5z"/></svg></span>
                            @endif

                            <span class="absolute inset-0 bg-black/45 opacity-0 group-hover:opacity-100 transition grid place-items-center">
                                <span class="flex items-center gap-5 text-white text-[13px] font-semibold">
                                    <span class="inline-flex items-center gap-1.5"><svg viewBox="0 0 20 20" class="w-4 h-4" fill="currentColor"><path d="M10 17s-6.5-4.2-6.5-8.2A3.8 3.8 0 0 1 10 6.3a3.8 3.8 0 0 1 6.5 2.5C16.5 12.8 10 17 10 17z"/></svg>{{ $compact((int) ($m['like_count'] ?? 0)) }}</span>
                                    <span class="inline-flex items-center gap-1.5"><svg viewBox="0 0 20 20" class="w-4 h-4" fill="currentColor"><path d="M10 2.8c-4.1 0-7.5 2.8-7.5 6.3 0 1.9 1 3.6 2.6 4.8L4 17.4l3.6-1.6c.8.2 1.6.3 2.4.3 4.1 0 7.5-2.8 7.5-6.3S14.1 2.8 10 2.8z"/></svg>{{ $compact((int) ($m['comments_count'] ?? 0)) }}</span>
                                </span>
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    {{-- ===== POST MODAL — Instagram's two-pane sheet: media left, caption +
         comments right, actions + composer at the bottom. ===== --}}
    <div id="ig-post-modal" class="hidden fixed inset-0 z-50 p-3 sm:p-6" aria-modal="true" role="dialog">
        <div class="absolute inset-0 bg-black/80" data-close-post></div>
        <button type="button" data-close-post aria-label="{{ __('Close') }}" class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full grid place-items-center text-white/80 hover:text-white hover:bg-white/10 transition">
            <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>
        </button>

        <div class="relative h-full grid place-items-center">
            {{-- h-[86vh] must be DEFINITE, not `h-full max-h-[86vh]`. This panel is
                 centred in a grid, so `h-full` resolved against an auto-height track
                 and the panel's height became content-driven. `max-h-full` on the
                 image then had no definite height to resolve against, a tall photo
                 rendered at full size, and overflow-hidden sliced the bottom off.
                 A fixed height makes the flex children stretch, so object-contain
                 can letterbox the image the way Instagram does. --}}
            <div class="w-full max-w-[1040px] h-[86vh] bg-paper-0 rounded-2xl overflow-hidden flex flex-col md:flex-row shadow-soft">
                {{-- media — flex-1 + min-h-0 gives this panel a DEFINITE height
                     (it stretches to the 86vh row), so the inner flex box and the
                     image's max-h-full actually have something to resolve against.
                     A `grid place-items-center` wrapper here made the box
                     content-sized instead, so a tall image overflowed and got
                     clipped. On mobile it takes the top ~45% and the thread
                     scrolls below. --}}
                <div class="flex-1 min-h-0 basis-[45%] md:basis-auto bg-black flex items-center justify-center overflow-hidden">
                    <div id="ig-post-media" class="w-full h-full flex items-center justify-center"></div>
                </div>

                {{-- side --}}
                <div class="w-full md:w-[380px] shrink-0 flex flex-col min-h-0 md:hairline-l">
                    <div class="px-4 py-3 hairline-b flex items-center gap-2.5 shrink-0">
                        <span class="ig-ring shrink-0">
                            @if ($account?->profile_pic_url)
                                <img src="{{ $account->profile_pic_url }}" alt="" class="block w-8 h-8 rounded-full object-cover">
                            @else
                                <span class="block w-8 h-8 rounded-full ig-grad-soft"></span>
                            @endif
                        </span>
                        {{-- Instagram's modal header is just avatar + handle; the
                             timestamp lives down by the composer, not up here. --}}
                        <div class="min-w-0 flex-1">
                            <div class="text-[13px] font-semibold truncate">{{ $account?->username ? '@'.$account->username : __('My account') }}</div>
                        </div>
                        <a id="ig-post-permalink" href="#" target="_blank" rel="noopener" title="{{ __('Open on Instagram') }}"
                           class="w-8 h-8 rounded-full grid place-items-center text-ink-500 hover:bg-paper-100 transition">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M6.5 3H3.5v9.5H13V9.5"/><path d="M9.5 3H13v3.5M13 3 7.5 8.5"/></svg>
                        </a>
                    </div>

                    {{-- caption + comments --}}
                    <div id="ig-post-thread" class="flex-1 min-h-0 overflow-y-auto scrollbar px-4 py-3 space-y-3.5"></div>

                    {{-- Counts block, laid out the way Instagram's post modal does it:
                         the likes line reads as a sentence ("Be the first to like
                         this"), with the relative age underneath, directly above the
                         composer. No like/save/share buttons — Instagram's Graph API
                         exposes no endpoint to like or save a post, so buttons here
                         would be decoration that does nothing. --}}
                    <div class="px-4 py-2.5 hairline-t shrink-0">
                        <div class="flex items-center gap-4 text-[12px] text-ink-500">
                            <span class="inline-flex items-center gap-1.5">
                                <svg viewBox="0 0 20 20" class="w-4 h-4 text-ig-pink" fill="currentColor"><path d="M10 17s-6.5-4.2-6.5-8.2A3.8 3.8 0 0 1 10 6.3a3.8 3.8 0 0 1 6.5 2.5C16.5 12.8 10 17 10 17z"/></svg>
                                <b id="ig-post-likes" class="text-ink-900">0</b>
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <svg viewBox="0 0 20 20" class="w-4 h-4 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M17 9.4c0 3.1-3.1 5.7-7 5.7-.9 0-1.8-.1-2.6-.4L3.7 16l1.2-3A5.4 5.4 0 0 1 3 9.4c0-3.1 3.1-5.7 7-5.7s7 2.6 7 5.7Z"/></svg>
                                <b id="ig-post-comments" class="text-ink-900">0</b>
                            </span>
                        </div>
                        <div id="ig-post-likeline" class="text-[13px] font-semibold mt-1.5"></div>
                        <div id="ig-post-when" class="text-[10.5px] uppercase tracking-wide text-ink-400 mt-0.5"></div>
                    </div>

                    {{-- reply composer — targets the selected comment, else the post.
                         `relative` anchors the emoji popover. The leading smiley
                         (an SVG icon, per house style) opens a picker; the emojis
                         inside are content the user inserts into their OWN comment,
                         exactly like Instagram's composer. --}}
                    <form id="ig-post-reply-form" class="relative px-3 py-2.5 hairline-t shrink-0 flex items-center gap-2">
                        <button type="button" id="ig-post-emoji-btn" aria-label="{{ __('Emoji') }}"
                                class="shrink-0 w-7 h-7 rounded-full grid place-items-center text-ink-500 hover:text-ink-800 hover:bg-paper-100 transition">
                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5a4.5 4.5 0 0 0 7 0" stroke-linecap="round"/><circle cx="9" cy="10" r="0.6" fill="currentColor"/><circle cx="15" cy="10" r="0.6" fill="currentColor"/></svg>
                        </button>
                        <div class="flex-1 min-w-0">
                            <div id="ig-post-replying" class="hidden text-[10.5px] text-ink-400 mb-1 flex items-center gap-1.5">
                                <span>{{ __('Replying to') }} <b id="ig-post-replying-to"></b></span>
                                <button type="button" id="ig-post-reply-cancel" class="text-ig-pink hover:opacity-70">{{ __('Cancel') }}</button>
                            </div>
                            <input id="ig-post-reply-text" type="text" autocomplete="off" placeholder="{{ __('Add a comment…') }}"
                                   class="w-full bg-transparent outline-none text-[13px] placeholder:text-ink-400">
                        </div>
                        <button type="submit" id="ig-post-reply-send" disabled
                                class="text-[12.5px] font-semibold ig-text disabled:opacity-40 disabled:cursor-not-allowed shrink-0 px-1">{{ __('Post') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.instagram>

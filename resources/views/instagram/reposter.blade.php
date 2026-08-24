<x-layouts.instagram :title="__('Reels Autopilot')" ig-active="reposter" page="instagram-reposter">

    {{-- ===== HEADER ===== --}}
    <section class="max-w-[1500px] mx-auto px-6 pt-6 pb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <div class="flex items-center gap-3 mb-1.5 text-[11px] mono uppercase tracking-[0.18em] text-ink-500">
                <span>{{ __('Studio') }}</span><span class="w-1 h-1 rounded-full bg-ink-400"></span><span>{{ __('Reels Autopilot') }}</span>
            </div>
            <h1 class="serif text-[40px] leading-none">{{ __('Auto-repost') }} <span class="ig-text italic">{{ __('reels on a schedule') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Pull reels from YouTube Shorts or public Instagram reel links, then auto-post them to your account through the official Instagram API on a cadence you set. Timing runs on the server, so it keeps posting even when you are offline.') }}</p>
        </div>
        <a href="{{ url('/instagram') }}" class="px-4 py-2 hairline rounded-full bg-white text-[12px] font-medium hover:bg-paper-50 flex items-center gap-2">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3L4 8l5 5M4 8h9"/></svg>{{ __('Dashboard') }}
        </a>
    </section>

    <div class="max-w-[1500px] mx-auto px-6"><x-admin.flash />
        @error('reposter')<div class="mb-3 hairline rounded-xl bg-ig-pink/5 px-4 py-2.5 text-[12.5px] text-ig-pink">{{ $message }}</div>@enderror
    </div>

    @if (!$hasFeature)
        <section class="max-w-[1500px] mx-auto px-6 pb-8">
            <div class="bg-white hairline rounded-2xl p-12 text-center">
                <span class="ig-grad w-14 h-14 rounded-2xl grid place-items-center text-white mx-auto mb-3">
                    <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                </span>
                <div class="serif text-[24px]">{{ __('Reels Autopilot is not in your plan') }}</div>
                <p class="text-[12.5px] text-ink-500 mt-1">{{ __('Upgrade to unlock automatic reel reposting.') }}</p>
                <a href="{{ url('/account/plans') }}" class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('View plans') }}</a>
            </div>
        </section>
    @elseif ($accounts->isEmpty())
        <section class="max-w-[1500px] mx-auto px-6 pb-8">
            <div class="bg-white hairline rounded-2xl p-12 text-center">
                <span class="ig-grad w-14 h-14 rounded-2xl grid place-items-center text-white mx-auto mb-3"><svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/></svg></span>
                <div class="serif text-[24px]">{{ __('Connect an account to start') }}</div>
                <p class="text-[12.5px] text-ink-500 mt-1">{{ __('Link a Professional / Creator account, then set sources & cadence here.') }}</p>
                <a href="{{ url('/instagram/connect') }}" class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('Connect Instagram') }}</a>
            </div>
        </section>
    @else
        <section class="max-w-[1500px] mx-auto px-6 pb-8 space-y-3">

            {{-- Account switcher --}}
            @if ($accounts->count() > 1)
                <div class="flex flex-wrap gap-2">
                    @foreach ($accounts as $acc)
                        <a href="{{ url('/instagram/reposter?account=' . $acc->id) }}" @class([
                            'px-3 py-1.5 rounded-full text-[12px] font-medium border transition',
                            'ig-grad-soft text-white border-transparent' => $acc->id === $accountId,
                            'bg-white hairline text-ink-700 hover:bg-paper-50' => $acc->id !== $accountId,
                        ])>{{ '@' . ($acc->username ?: $acc->ig_user_id) }}</a>
                    @endforeach
                </div>
            @endif

            {{-- ══ TABS ══ --}}
            <div class="flex items-center gap-1 hairline rounded-full p-1 bg-white w-max text-[12.5px]" data-rp-tabs>
                <button type="button" data-rp-tab="overview" class="rp-tab px-4 py-1.5 rounded-full font-semibold ig-grad-soft text-white">{{ __('Overview') }}</button>
                <button type="button" data-rp-tab="settings" class="rp-tab px-4 py-1.5 rounded-full font-medium text-ink-600 hover:bg-paper-50">{{ __('Autopilot settings') }}</button>
            </div>

            {{-- ══ OVERVIEW PANEL ══ --}}
            <div data-rp-panel="overview" class="space-y-3">

            {{-- KPI strip --}}
            <div class="grid grid-cols-3 gap-3">
                @foreach ([['Queued', $stats['queued']], ['Posted', $stats['posted']], ['Failed', $stats['failed']]] as [$lbl, $val])
                    <div class="bg-white hairline rounded-2xl p-4">
                        <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __($lbl) }}</div>
                        <div class="serif text-[34px] leading-none mt-1 tabular">{{ $val }}</div>
                    </div>
                @endforeach
            </div>

            {{-- How it works + honest worker status --}}
            @php
                $enabled = (bool) $setting->enabled;
                $steps = [
                    ['Fetch', 'Server worker pulls reels from your sources on the scrape interval.', 'M8 2v9M4.5 7.5L8 11l3.5-3.5M3 13h10'],
                    ['Queue', 'Each clip lands in the queue below as “queued”, ready to post.', 'M3 4h10M3 8h10M3 12h6'],
                    ['Auto-post', 'On the posting interval each clip publishes as a Reel via the Graph API.', 'M2 8l12-5-4.5 12-2.5-5z'],
                    ['Clean up', 'Hosted files are deleted after the retention window to save space.', 'M3 4.5h10M6 4.5V3h4v1.5M4.5 4.5l.6 9h5.8l.6-9'],
                ];
            @endphp
            <div class="bg-white hairline rounded-2xl p-5">
                <div class="flex items-center justify-between gap-3 flex-wrap mb-3.5">
                    <h2 class="serif text-[20px]">{{ __('How it works') }}</h2>
                    <div class="flex items-center gap-2 flex-wrap text-[11px] mono">
                        <span class="pill {{ $enabled ? 'bg-wa-bubble text-wa-deep' : 'bg-paper-100 text-ink-500' }}">{{ $enabled ? __('Autopilot ON') : __('Autopilot OFF') }}</span>
                        <span class="pill bg-paper-100 text-ink-600">{{ __('Last scrape') }}: {{ $setting->last_scrape_at ? $setting->last_scrape_at->diffForHumans() : __('never') }}</span>
                        <span class="pill bg-paper-100 text-ink-600">{{ __('Last post') }}: {{ $setting->last_post_at ? $setting->last_post_at->diffForHumans() : __('never') }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    @foreach ($steps as $i => $s)
                        <div class="hairline rounded-xl p-3.5">
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="w-7 h-7 rounded-lg ig-grad-soft text-white grid place-items-center shrink-0"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $s[2] }}"/></svg></span>
                                <span class="mono text-[9px] text-ink-400">{{ __('Step') }} {{ $i + 1 }}</span>
                            </div>
                            <div class="text-[13px] font-semibold">{{ __($s[0]) }}</div>
                            <p class="text-[11px] text-ink-500 leading-snug mt-1">{{ __($s[1]) }}</p>
                        </div>
                    @endforeach
                </div>
                <p class="text-[10.5px] text-ink-500 mt-3 leading-snug">{{ __('Steps 1–4 run on the background server worker. If “Last scrape / Last post” stays “never” while autopilot is ON, the worker is not running yet — use “Repost now” below to publish immediately, no worker required.') }}</p>
            </div>

            {{-- Repost now — works today, straight through the Graph API --}}
            <div class="bg-white hairline rounded-2xl p-5">
                <div class="flex items-center justify-between gap-2 mb-2 flex-wrap">
                    <h2 class="serif text-[20px]">{{ __('Repost a reel now') }}</h2>
                    <span class="pill bg-ig-pink/10 text-ig-pink mono">{{ __('Instant · official API') }}</span>
                </div>
                <p class="text-[12px] text-ink-500 mb-3.5">{{ __('Paste a direct public HTTPS video URL (.mp4 / .mov) and publish it to your account as a Reel right now — no scheduler needed.') }}</p>
                <form method="POST" action="{{ route('instagram.reposter.now') }}" class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-3 items-end">@csrf
                    <input type="hidden" name="instagram_account_id" value="{{ $accountId }}">
                    <div class="space-y-3">
                        <div>
                            <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Public video URL (HTTPS .mp4 / .mov)') }}</label>
                            <input type="url" name="video_url" required value="{{ old('video_url') }}" placeholder="https://cdn.example.com/reel.mp4" class="field mt-1.5">
                        </div>
                        <div>
                            <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Caption') }}</label>
                            <textarea name="caption" rows="2" maxlength="2200" placeholder="{{ __('Optional caption + #hashtags') }}" class="field mt-1.5 resize-y">{{ old('caption') }}</textarea>
                        </div>
                    </div>
                    <button class="rounded-full ig-grad-soft text-white px-5 py-2.5 text-[13px] font-semibold hover:opacity-90 flex items-center justify-center gap-2 sm:mb-[3px]">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l12-5-4.5 12-2.5-5z"/></svg>{{ __('Publish reel now') }}
                    </button>
                </form>
            </div>

            </div>{{-- /overview (part 1) --}}

            {{-- ══ SETTINGS PANEL (Autopilot config) ══ --}}
            <div data-rp-panel="settings" class="hidden">
                <div class="bg-white hairline rounded-2xl p-5">
                    <h2 class="serif text-[20px] mb-1">{{ __('Scheduled autopilot') }}</h2>
                    <p class="text-[11.5px] text-ink-500 mb-4">{{ __('Runs on the background server worker — set sources & cadence, then it scrapes and posts on its own.') }}</p>
                    <form method="POST" action="{{ route('instagram.reposter.save') }}" class="space-y-4">@csrf
                        <input type="hidden" name="instagram_account_id" value="{{ $accountId }}">

                        <label class="flex items-center gap-2.5 cursor-pointer hairline rounded-xl p-3 bg-paper-50">
                            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $setting->enabled)) class="accent-ig-pink w-4 h-4">
                            <span class="text-[13px] font-medium">{{ __('Enable Reels Autopilot for this account') }}</span>
                        </label>

                        {{-- Sources --}}
                        <div class="hairline-t pt-4 space-y-3">
                            <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sources') }}</div>

                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" name="youtube_enabled" value="1" @checked(old('youtube_enabled', $setting->youtube_enabled)) class="accent-ig-pink w-4 h-4">
                                <span class="text-[12.5px]">{{ __('YouTube Shorts') }}</span>
                            </label>
                            <div>
                                <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('YouTube channels (one per line)') }}</label>
                                <textarea name="source_yt_channels" rows="2" placeholder="https://www.youtube.com/@channel" class="field mt-1.5 resize-y">{{ old('source_yt_channels', implode("\n", (array) $setting->source_yt_channels)) }}</textarea>
                            </div>
                            <div>
                                <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('YouTube API key') }}</label>
                                <input type="password" name="youtube_api_key" autocomplete="off" placeholder="{{ $setting->youtube_api_key ? __('saved — leave blank to keep') : __('Required for YouTube source') }}" class="field mt-1.5">
                            </div>
                            <div>
                                <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Instagram public reel links (one per line)') }}</label>
                                <textarea name="source_ig_accounts" rows="2" placeholder="https://www.instagram.com/reel/XXXXXXXXX/" class="field mt-1.5 resize-y">{{ old('source_ig_accounts', implode("\n", (array) $setting->source_ig_accounts)) }}</textarea>
                                <p class="text-[10.5px] text-ink-500 mt-1">{{ __('Paste public reel URLs. Whole-account scraping needs a login and is not supported.') }}</p>
                            </div>
                        </div>

                        {{-- Cadence --}}
                        <div class="hairline-t pt-4 space-y-3">
                            <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Cadence & limits') }}</div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Fetch per run') }}</label>
                                    <input type="number" name="fetch_limit" min="1" max="50" value="{{ old('fetch_limit', $setting->fetch_limit ?? 10) }}" class="field mt-1.5">
                                </div>
                                <div>
                                    <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Scrape every (min)') }}</label>
                                    <input type="number" name="scraper_interval_min" min="10" max="1440" value="{{ old('scraper_interval_min', $setting->scraper_interval_min ?? 120) }}" class="field mt-1.5">
                                </div>
                                <div>
                                    <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Post every (min)') }}</label>
                                    <input type="number" name="posting_interval_min" min="1" max="1440" value="{{ old('posting_interval_min', $setting->posting_interval_min ?? 30) }}" class="field mt-1.5">
                                </div>
                                <div>
                                    <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Max posts / day') }}</label>
                                    <input type="number" name="daily_cap" min="1" max="50" value="{{ old('daily_cap', $setting->daily_cap ?? 10) }}" class="field mt-1.5">
                                </div>
                                <div>
                                    <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Delete files after (min)') }}</label>
                                    <input type="number" name="remove_after_min" min="5" max="1440" value="{{ old('remove_after_min', $setting->remove_after_min ?? 120) }}" class="field mt-1.5">
                                </div>
                                <label class="flex items-center gap-2 cursor-pointer self-end pb-2.5">
                                    <input type="checkbox" name="post_to_story" value="1" @checked(old('post_to_story', $setting->post_to_story)) class="accent-ig-pink w-4 h-4">
                                    <span class="text-[12px]">{{ __('Also share to Story') }}</span>
                                </label>
                            </div>
                        </div>

                        {{-- Caption --}}
                        <div class="hairline-t pt-4">
                            <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Caption / hashtags (applied to every repost)') }}</label>
                            <textarea name="hashtags" rows="2" maxlength="2000" placeholder="#reels #viral" class="field mt-1.5 resize-y">{{ old('hashtags', $setting->hashtags) }}</textarea>
                        </div>

                        <div class="text-[10.5px] text-ink-500 bg-paper-50 hairline rounded-xl p-3 leading-snug">
                            {{ __('Reels are published to Instagram for review via the official API. You are responsible for only reposting content you own or are licensed to use. Daily cap stays well under Instagram limits to keep the account safe.') }}
                        </div>

                        <button class="w-full rounded-full ig-grad-soft text-white py-2.5 text-[13px] font-semibold hover:opacity-90">{{ __('Save settings') }}</button>
                    </form>
                </div>

            </div>{{-- /settings panel --}}

            {{-- ══ Queue (overview) ══ --}}
            <div data-rp-panel="overview">
                <div class="bg-white hairline rounded-2xl p-5">
                    <h2 class="serif text-[20px] mb-3">{{ __('Queue') }}</h2>
                    <div class="ig-scroll max-h-[640px] overflow-y-auto -mx-1 px-1">
                        @forelse ($items as $it)
                            <div class="flex items-center gap-3 py-2.5 hairline-b last:border-0">
                                <span class="mono text-[9px] uppercase px-1.5 py-0.5 rounded-full {{ $it->source === 'youtube' ? 'bg-accent-coral/10 text-accent-coral' : 'bg-ig-pink/10 text-ig-pink' }}">{{ $it->source }}</span>
                                <a href="{{ route('instagram.reposter.show', $it->id) }}" class="min-w-0 flex-1 group" title="{{ __('View details') }}">
                                    <div class="text-[12px] truncate group-hover:text-ig-pink transition">{{ \Illuminate\Support\Str::limit($it->caption ?: $it->source_id, 48) }}</div>
                                    <div class="mono text-[10px] text-ink-500 truncate">
                                        {{ $it->source_handle ?: $it->source_id }}
                                        @if ($it->posted_at) · {{ $it->posted_at->diffForHumans() }}@endif
                                        @if ($it->last_error) · <span class="text-accent-coral">{{ \Illuminate\Support\Str::limit($it->last_error, 40) }}</span>@endif
                                    </div>
                                </a>
                                <span class="mono text-[9px] uppercase px-2 py-0.5 rounded-full
                                    {{ $it->status === 'posted' ? 'bg-wa-bubble text-wa-deep' : ($it->status === 'failed' ? 'bg-accent-coral/10 text-accent-coral' : 'bg-paper-100 text-ink-600') }}">{{ $it->status }}</span>
                                @if ($it->status === 'failed')
                                    <form method="POST" action="{{ route('instagram.reposter.retry', $it->id) }}" class="inline">@csrf
                                        <button type="submit" title="{{ __('Retry') }}" class="w-7 h-7 rounded-lg grid place-items-center text-ink-500 hover:bg-paper-100">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13 8a5 5 0 1 1-1.5-3.5M13 2v3h-3"/></svg>
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('instagram.reposter.destroy', $it->id) }}" class="inline" data-confirm="{{ __('Remove this clip from the queue?') }}">@csrf @method('DELETE')
                                    <button type="submit" title="{{ __('Remove') }}" class="w-7 h-7 rounded-lg grid place-items-center text-accent-coral hover:bg-accent-coral/10">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 4h11M6 4V2.5h4V4M4.3 4l.6 9.5h6.2l.6-9.5"/></svg>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="text-[12.5px] text-ink-500 py-10 text-center">{{ __('Nothing scraped yet. Add a source + enable autopilot, then the server pulls clips on the next scrape tick.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    @endif
</x-layouts.instagram>

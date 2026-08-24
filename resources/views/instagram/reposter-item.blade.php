{{--
    Reels Autopilot — single clip detail.

    The queue row only had room for a truncated caption + a status pill, so an
    operator couldn't see WHY a clip failed or edit its caption before it posts.
    This is the click-through: every field, the full failure reason, a preview
    of the source video, and an inline Edit panel (native <details>, no JS).
--}}
<x-layouts.instagram :title="__('Clip detail')" ig-active="reposter" page="instagram-reposter-item">

    @php
        $statusClass = $item->status === 'posted'
            ? 'bg-wa-bubble text-wa-deep'
            : ($item->status === 'failed' ? 'bg-accent-coral/10 text-accent-coral' : 'bg-paper-100 text-ink-600');
        $title = $item->caption ?: ($item->source_handle ?: $item->source_id ?: __('Reel clip'));
    @endphp

    <section class="max-w-[1000px] mx-auto px-6 pt-6 pb-16">

        {{-- Breadcrumb + back --}}
        <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
            <div class="flex items-center gap-2 text-[11px] mono uppercase tracking-[0.18em] text-ink-500">
                <a href="{{ route('instagram.reposter', ['account' => $item->instagram_account_id]) }}" class="hover:text-ig-pink">{{ __('Reels Autopilot') }}</a>
                <span>/</span>
                <span class="text-ink-700">{{ __('Clip') }} #{{ $item->id }}</span>
            </div>
            <a href="{{ route('instagram.reposter', ['account' => $item->instagram_account_id]) }}"
               class="px-4 py-2 hairline rounded-full bg-white text-[12px] font-medium hover:bg-paper-50 flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3 5 8l5 5"/></svg>
                {{ __('Back to queue') }}
            </a>
        </div>

        @if (session('status'))
            <div class="mb-5 rounded-2xl bg-wa-bubble text-wa-deep px-4 py-3 text-[13px]">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-5 rounded-2xl bg-accent-coral/10 text-accent-coral px-4 py-3 text-[13px]">{{ $errors->first() }}</div>
        @endif

        {{-- Title + status --}}
        <div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
            <div class="min-w-0">
                <div class="flex items-center gap-2 mb-2">
                    <span class="mono text-[9px] uppercase px-1.5 py-0.5 rounded-full {{ $item->source === 'youtube' ? 'bg-accent-coral/10 text-accent-coral' : 'bg-ig-pink/10 text-ig-pink' }}">{{ $item->source }}</span>
                    <span class="mono text-[9px] uppercase px-2 py-0.5 rounded-full {{ $statusClass }}">{{ $item->status }}</span>
                </div>
                <h1 class="serif text-[26px] leading-tight break-words">{{ \Illuminate\Support\Str::limit($title, 120) }}</h1>
                <p class="mono text-[11px] text-ink-500 mt-1.5">
                    {{ $account?->username ? '@'.$account->username : __('Account') . ' #' . $item->instagram_account_id }}
                    @if ($item->posted_at) · {{ __('posted') }} {{ $item->posted_at->diffForHumans() }}@endif
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">

            {{-- LEFT: details + failure + edit --}}
            <div class="space-y-6 min-w-0">

                {{-- What failed / what's happening --}}
                @if ($item->status === 'failed')
                    <div class="rounded-2xl border border-accent-coral/30 bg-accent-coral/5 p-4">
                        <div class="flex items-center gap-2 mb-1.5 text-accent-coral text-[12px] font-semibold">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M8 4.5v4M8 11h.01"/><circle cx="8" cy="8" r="6.4"/></svg>
                            {{ __('Why this failed') }}
                        </div>
                        <p class="text-[12.5px] text-ink-700 leading-relaxed break-words">{{ $item->last_error ?: __('No error message was recorded.') }}</p>
                    </div>
                @elseif ($item->status === 'queued')
                    <div class="rounded-2xl border border-paper-200 bg-paper-50 p-4">
                        <div class="flex items-center gap-2 mb-1.5 text-ink-700 text-[12px] font-semibold">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="8" cy="8" r="6.4"/><path d="M8 4.5V8l2.4 1.4"/></svg>
                            {{ __('Waiting to post') }}
                        </div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed">{{ __('This clip is in line. The server publishes the oldest queued clip on the next post tick (your posting interval), respecting the daily cap.') }}</p>
                    </div>
                @else
                    <div class="rounded-2xl border border-wa-deep/20 bg-wa-bubble/40 p-4">
                        <div class="flex items-center gap-2 mb-1.5 text-wa-deep text-[12px] font-semibold">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m3 8.5 3.2 3L13 5"/></svg>
                            {{ __('Published to Instagram') }}
                        </div>
                        <p class="text-[12.5px] text-ink-700 leading-relaxed">{{ __('This reel is live on the connected account.') }} @if ($item->media_id) {{ __('Media ID') }} <span class="mono">{{ $item->media_id }}</span>.@endif</p>
                    </div>
                @endif

                {{-- Field table --}}
                <div class="rounded-2xl border border-paper-200 bg-white overflow-hidden">
                    <div class="px-4 py-3 hairline-b"><h2 class="serif text-[16px]">{{ __('Details') }}</h2></div>
                    <dl class="divide-y divide-paper-100 text-[12.5px]">
                        @php
                            $rows = [
                                [__('Status'),      ucfirst($item->status)],
                                [__('Source'),      ucfirst($item->source)],
                                [__('Source handle'), $item->source_handle ?: '—'],
                                [__('Source ID'),   $item->source_id ?: '—'],
                                [__('Media ID'),    $item->media_id ?: '—'],
                                [__('Created'),     $item->created_at?->format('M j, Y g:i A') . ' · ' . $item->created_at?->diffForHumans()],
                                [__('Claimed'),     $item->claimed_at ? $item->claimed_at->format('M j, Y g:i A') : __('Not yet claimed')],
                                [__('Posted'),      $item->posted_at ? $item->posted_at->format('M j, Y g:i A') : __('Not posted')],
                            ];
                        @endphp
                        @foreach ($rows as [$k, $v])
                            <div class="flex items-start gap-4 px-4 py-2.5">
                                <dt class="mono text-[10px] uppercase tracking-wide text-ink-500 w-32 shrink-0 pt-0.5">{{ $k }}</dt>
                                <dd class="text-ink-800 break-words min-w-0">{{ $v }}</dd>
                            </div>
                        @endforeach
                        @if ($item->public_url)
                            <div class="flex items-start gap-4 px-4 py-2.5">
                                <dt class="mono text-[10px] uppercase tracking-wide text-ink-500 w-32 shrink-0 pt-0.5">{{ __('Video URL') }}</dt>
                                <dd class="min-w-0"><a href="{{ $item->public_url }}" target="_blank" rel="noopener" class="text-ig-pink hover:underline break-all">{{ $item->public_url }}</a></dd>
                            </div>
                        @endif
                        @if ($item->caption)
                            <div class="flex items-start gap-4 px-4 py-2.5">
                                <dt class="mono text-[10px] uppercase tracking-wide text-ink-500 w-32 shrink-0 pt-0.5">{{ __('Caption') }}</dt>
                                <dd class="text-ink-800 whitespace-pre-wrap break-words min-w-0">{{ $item->caption }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Edit panel — native <details>, no JS --}}
                <details class="rounded-2xl border border-paper-200 bg-white overflow-hidden group" @if($errors->any()) open @endif>
                    <summary class="px-4 py-3 flex items-center justify-between cursor-pointer select-none hover:bg-paper-50">
                        <span class="serif text-[16px] flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3.5 12.5 7 5.5 14H2v-3.5z"/><path d="M8 4.5 11.5 8"/></svg>
                            {{ __('Edit clip') }}
                        </span>
                        <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-400 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m4 6 4 4 4-4"/></svg>
                    </summary>
                    <form method="POST" action="{{ route('instagram.reposter.update', $item->id) }}" class="px-4 pb-4 pt-1 space-y-4">
                        @csrf @method('PUT')

                        <div>
                            <label class="mono text-[10px] uppercase tracking-wide text-ink-500 block mb-1.5">{{ __('Caption') }}</label>
                            <textarea name="caption" rows="4" maxlength="2200"
                                class="w-full hairline rounded-xl px-3 py-2.5 text-[13px] bg-paper-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-ig-pink/30 resize-y"
                                placeholder="{{ __('Caption text posted with the reel…') }}">{{ old('caption', $item->caption) }}</textarea>
                        </div>

                        @if ($item->status !== 'posted')
                            <div>
                                <label class="mono text-[10px] uppercase tracking-wide text-ink-500 block mb-1.5">{{ __('Source video URL (HTTPS .mp4 / .mov)') }}</label>
                                <input type="url" name="public_url" value="{{ old('public_url', $item->public_url) }}" maxlength="1024"
                                    class="w-full hairline rounded-xl px-3 py-2.5 text-[13px] bg-paper-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-ig-pink/30"
                                    placeholder="https://…/reel.mp4">
                            </div>
                            <label class="flex items-center gap-2.5 cursor-pointer hairline rounded-xl p-3 bg-paper-50">
                                <input type="checkbox" name="requeue" value="1" class="rounded accent-ig-pink">
                                <span class="text-[12.5px] text-ink-700">{{ __('Put this clip back in the queue to post on the next tick') }}</span>
                            </label>
                        @else
                            <p class="text-[11.5px] text-ink-500">{{ __('This clip is already published — its source URL is locked. Only the stored caption can be edited here.') }}</p>
                        @endif

                        <div class="flex items-center gap-2">
                            <button class="rounded-full ig-grad-soft text-white px-5 py-2.5 text-[13px] font-semibold hover:opacity-90">{{ __('Save changes') }}</button>
                        </div>
                    </form>
                </details>

            </div>

            {{-- RIGHT: preview + actions --}}
            <div class="space-y-4">
                @if ($item->public_url)
                    <div class="rounded-2xl border border-paper-200 bg-black/[0.02] overflow-hidden">
                        <video src="{{ $item->public_url }}" controls preload="metadata"
                            class="w-full aspect-[9/16] object-contain bg-black"></video>
                    </div>
                @else
                    <div class="rounded-2xl border border-paper-200 bg-paper-50 aspect-[9/16] grid place-items-center text-center px-4">
                        <div>
                            <svg viewBox="0 0 24 24" class="w-8 h-8 mx-auto text-ink-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9 5 3-5 3z"/></svg>
                            <p class="text-[12px] text-ink-500">{{ __('No preview — the source file was cleaned up or was never a direct URL.') }}</p>
                        </div>
                    </div>
                @endif

                <div class="rounded-2xl border border-paper-200 bg-white p-4 space-y-2.5">
                    <div class="mono text-[10px] uppercase tracking-wide text-ink-500 mb-1">{{ __('Actions') }}</div>

                    @if ($item->media_id && $account?->username)
                        <a href="https://www.instagram.com/{{ $account->username }}/reels/" target="_blank" rel="noopener"
                           class="w-full rounded-full ig-grad-soft text-white px-4 py-2.5 text-[12.5px] font-semibold hover:opacity-90 flex items-center justify-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="2.5" width="11" height="11" rx="3.4"/><circle cx="8" cy="8" r="2.6"/></svg>
                            {{ __('View on Instagram') }}
                        </a>
                    @endif

                    @if ($item->status === 'failed')
                        <form method="POST" action="{{ route('instagram.reposter.retry', $item->id) }}">@csrf
                            <button class="w-full rounded-full hairline bg-white px-4 py-2.5 text-[12.5px] font-medium hover:bg-paper-50 flex items-center justify-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13 8a5 5 0 1 1-1.5-3.5M13 2v3h-3"/></svg>
                                {{ __('Retry this clip') }}
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('instagram.reposter.destroy', $item->id) }}" data-confirm="{{ __('Delete this clip permanently?') }}">@csrf @method('DELETE')
                        <button class="w-full rounded-full px-4 py-2.5 text-[12.5px] font-medium text-accent-coral hover:bg-accent-coral/10 flex items-center justify-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 4h11M6 4V2.5h4V4M4.3 4l.6 9.5h6.2l.6-9.5"/></svg>
                            {{ __('Delete clip') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-layouts.instagram>

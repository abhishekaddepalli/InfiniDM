<x-layouts.instagram :title="__('Comment moderation')" ig-active="comments" page="instagram-comments">
    @php $accId = $account?->id; @endphp
    <div class="max-w-[1500px] mx-auto px-6 py-6">

        {{-- HEADER --}}
        <section class="pb-4">
            <div class="flex items-center gap-3 mb-1.5 text-[11px] mono uppercase tracking-[0.18em] text-ink-500"><span>{{ __('Studio') }}</span><span class="w-1 h-1 rounded-full bg-ink-400"></span><span>{{ __('Comment moderation') }}</span></div>
            <h1 class="serif text-[40px] leading-none">{{ __('Moderate') }} <span class="ig-text italic">{{ __('comments') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Pick a post to see its live comments — reply publicly, DM the commenter privately, hide or delete.') }}</p>
        </section>

        <x-admin.flash />

        @if ($accounts->isEmpty())
            <div class="bg-white hairline rounded-2xl p-12 text-center">
                <div class="serif text-[22px]">{{ __('No connected account') }}</div>
                <p class="text-[13px] text-ink-600 mt-1">{{ __('Connect an Instagram account first.') }}</p>
                <a href="{{ url('/instagram/connect') }}" class="inline-block mt-4 px-4 py-2 rounded-full ig-grad-soft text-white text-[13px] font-semibold">{{ __('Connect account') }}</a>
            </div>
        @else
            {{-- account picker --}}
            @if ($accounts->count() > 1)
                <form method="GET" class="mb-4 flex items-center gap-2">
                    <span class="text-[12px] text-ink-600">{{ __('Account') }}</span>
                    <select name="account" onchange="this.form.submit()" class="field !w-auto">
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}" @selected($accId == $a->id)>{{ '@'.($a->username ?: $a->ig_user_id) }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            <div class="grid lg:grid-cols-[340px_1fr] gap-5">
                {{-- POST PICKER --}}
                <div class="bg-white hairline rounded-2xl p-4">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('Recent posts') }}</div>
                    @if (empty($media))
                        <p class="text-[12px] text-ink-500">{{ __('No posts found, or the account lacks content permissions.') }}</p>
                    @else
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($media as $m)
                                @php $thumb = $m['thumbnail_url'] ?? ($m['media_url'] ?? ''); @endphp
                                <a href="{{ url('/instagram/comments?account='.$accId.'&media='.$m['id']) }}"
                                   class="relative block aspect-square rounded-xl overflow-hidden hairline {{ $selectedMediaId === $m['id'] ? 'ring-2 ring-ig-pink' : '' }}"
                                   title="{{ \Illuminate\Support\Str::limit($m['caption'] ?? '', 80) }}">
                                    @if ($thumb)
                                        <span class="block w-full h-full bg-center bg-cover" style="background-image:url('{{ $thumb }}')"></span>
                                    @else
                                        <span class="block w-full h-full ig-grad-soft"></span>
                                    @endif
                                    <span class="absolute bottom-1 right-1 text-[9px] mono px-1.5 py-0.5 rounded bg-ink-900/70 text-white">{{ (int)($m['comments_count'] ?? 0) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- COMMENTS --}}
                <div class="bg-white hairline rounded-2xl p-5">
                    @if ($selectedMediaId === '')
                        <div class="text-center py-16 text-ink-500 text-[13px]">{{ __('Select a post to load its comments.') }}</div>
                    @else
                        {{-- comment-on-own-post --}}
                        <form method="POST" action="{{ route('instagram.comments.create') }}" class="mb-5 pb-5 hairline-b flex items-end gap-2">
                            @csrf
                            <input type="hidden" name="instagram_account_id" value="{{ $accId }}">
                            <input type="hidden" name="media_id" value="{{ $selectedMediaId }}">
                            <div class="flex-1">
                                <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Add a comment to this post') }}</label>
                                <input name="message" required maxlength="2200" class="field mt-1" placeholder="{{ __('Write a comment…') }}">
                            </div>
                            <button class="px-4 py-2.5 rounded-xl bg-ink-900 text-white text-[12.5px] font-semibold">{{ __('Comment') }}</button>
                        </form>

                        @if (!empty($commentsError ?? null))
                            {{-- Instagram refused the request — say so. Showing the
                                 empty state here would read as "no comments". --}}
                            <div class="text-center py-12 text-accent-coral text-[13px] px-4">{{ $commentsError }}</div>
                        @elseif (empty($comments))
                            <div class="text-center py-12 text-ink-500 text-[13px]">{{ __('No comments on this post yet.') }}</div>
                        @else
                            <div class="space-y-3">
                                @foreach ($comments as $c)
                                    <div class="hairline rounded-xl p-3 {{ ($c['hidden'] ?? false) ? 'opacity-60' : '' }}">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="text-[13px] font-semibold">{{ '@'.($c['username'] ?? 'user') }}
                                                    @if ($c['hidden'] ?? false)<span class="pill bg-paper-100 text-ink-500 ml-1">{{ __('hidden') }}</span>@endif
                                                </div>
                                                <div class="text-[13px] text-ink-800 mt-0.5 break-words">{{ $c['text'] ?? '' }}</div>
                                                <div class="text-[10.5px] mono text-ink-400 mt-1">{{ isset($c['timestamp']) ? \Illuminate\Support\Carbon::parse($c['timestamp'])->diffForHumans() : '' }} · {{ (int)($c['like_count'] ?? 0) }} {{ __('likes') }}</div>
                                            </div>
                                        </div>
                                        {{-- items-START, not items-center: an open <details> grows taller than
                                             its neighbours, and centering re-anchored the whole row around it —
                                             "DM privately" jumped a line and shoved Hide/Delete sideways. Wrapping
                                             + a full-width open panel (below) keeps the action row fixed. --}}
                                        <div class="flex flex-wrap items-start gap-x-3 gap-y-2 mt-2 text-[11.5px]">
                                            {{-- public reply --}}
                                            {{-- inline-block, NOT inline: a display:inline <details> never lays
                                                 out its disclosure content, so Reply / DM privately toggled `open`
                                                 and rendered nothing — the link looked dead. Hide/Delete are plain
                                                 <form class="inline"> buttons, which is why only these two broke. --}}
                                            <details class="inline-block [&[open]]:w-full [&[open]]:order-last">
                                                <summary class="cursor-pointer text-ig-pink font-medium list-none">{{ __('Reply') }}</summary>
                                                <form method="POST" action="{{ route('instagram.comments.reply') }}" class="flex items-end gap-2 mt-2">
                                                    @csrf
                                                    <input type="hidden" name="instagram_account_id" value="{{ $accId }}">
                                                    <input type="hidden" name="comment_id" value="{{ $c['id'] }}">
                                                    <input name="message" required maxlength="2000" class="field" placeholder="{{ __('Public reply…') }}">
                                                    <button class="px-3 py-2 rounded-lg bg-ink-900 text-white text-[11.5px] font-semibold whitespace-nowrap">{{ __('Send') }}</button>
                                                </form>
                                            </details>
                                            {{-- private DM --}}
                                            {{-- inline-block, NOT inline: a display:inline <details> never lays
                                                 out its disclosure content, so Reply / DM privately toggled `open`
                                                 and rendered nothing — the link looked dead. Hide/Delete are plain
                                                 <form class="inline"> buttons, which is why only these two broke. --}}
                                            <details class="inline-block [&[open]]:w-full [&[open]]:order-last">
                                                <summary class="cursor-pointer text-ig-purple font-medium list-none">{{ __('DM privately') }}</summary>
                                                <form method="POST" action="{{ route('instagram.comments.private-reply') }}" class="flex items-end gap-2 mt-2">
                                                    @csrf
                                                    <input type="hidden" name="instagram_account_id" value="{{ $accId }}">
                                                    <input type="hidden" name="comment_id" value="{{ $c['id'] }}">
                                                    <input name="message" required maxlength="1000" class="field" placeholder="{{ __('Private DM…') }}">
                                                    <button class="px-3 py-2 rounded-lg ig-grad-soft text-white text-[11.5px] font-semibold whitespace-nowrap">{{ __('Send DM') }}</button>
                                                </form>
                                            </details>
                                            {{-- hide / unhide --}}
                                            <form method="POST" action="{{ route('instagram.comments.hide') }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="instagram_account_id" value="{{ $accId }}">
                                                <input type="hidden" name="comment_id" value="{{ $c['id'] }}">
                                                <input type="hidden" name="hide" value="{{ ($c['hidden'] ?? false) ? 0 : 1 }}">
                                                <button class="text-ink-600 hover:text-ink-900">{{ ($c['hidden'] ?? false) ? __('Unhide') : __('Hide') }}</button>
                                            </form>
                                            {{-- delete --}}
                                            <form method="POST" action="{{ route('instagram.comments.delete') }}" class="inline" data-confirm="{{ __('Delete this comment permanently?') }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="instagram_account_id" value="{{ $accId }}">
                                                <input type="hidden" name="comment_id" value="{{ $c['id'] }}">
                                                <button class="text-accent-coral hover:underline">{{ __('Delete') }}</button>
                                            </form>
                                        </div>

                                        {{-- existing replies --}}
                                        @if (!empty($c['replies']['data']))
                                            <div class="mt-2 ml-3 pl-3 hairline-l space-y-1">
                                                @foreach ($c['replies']['data'] as $rep)
                                                    <div class="text-[12px]"><span class="font-semibold">{{ '@'.($rep['username'] ?? 'you') }}</span> <span class="text-ink-700">{{ $rep['text'] ?? '' }}</span></div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-layouts.instagram>

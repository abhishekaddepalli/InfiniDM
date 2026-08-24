<x-layouts.instagram :title="__('Inbox')" ig-active="inbox" page="instagram-inbox">
    @php
        $accById = $accounts->keyBy('id');
        $contacts = $contacts ?? collect();
        // Label a thread by WHO messaged (the sender's @username/name), NOT by
        // our own connected-account handle — that made every thread look
        // identical/merged. Falls back to "Instagram user" until their profile
        // is fetched.
        $threadName = function ($accId, $igsid) use ($contacts) {
            $c = $contacts->get($accId.':'.$igsid);
            if ($c) return $c->displayName();
            return $igsid ? __('Instagram user') : '—';
        };
        $initials = fn ($s) => strtoupper(\Illuminate\Support\Str::of((string) $s)->replace(['@', '_', '.'], ' ')->trim()->limit(2, '')->__toString() ?: 'IG');
        // AI-agent model picker options. WaDesk sources these from
        // AdminAiKeyController::MODELS, a host-only class that does not exist in
        // the standalone build — referencing it 500'd the whole inbox the moment
        // a thread opened. Inlined here (same list) so the view is self-contained.
        $aiModels = [
            'openai' => [
                'gpt-5.5', 'gpt-5.5-pro', 'gpt-5.4', 'gpt-5.4-pro',
                'gpt-5.4-mini', 'gpt-5.4-nano', 'gpt-5-mini', 'gpt-5-nano',
                'gpt-4.1', 'gpt-4.1-mini', 'gpt-4o', 'gpt-4o-mini',
            ],
            'anthropic' => [
                'claude-opus-4-8', 'claude-fable-5', 'claude-sonnet-4-6', 'claude-haiku-4-5',
                'claude-opus-4-7', 'claude-opus-4-6',
            ],
            'gemini' => [
                'gemini-3.5-flash', 'gemini-3.1-pro-preview', 'gemini-3.1-flash-lite',
                'gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.5-flash-lite',
            ],
        ];
        // Instagram's own default avatar — a grey disc with a white silhouette.
        // Used wherever we have no profile_pic, instead of coloured initials:
        // initials invent an identity we don't actually know, and every thread
        // reading "IN" for "Instagram user" told the operator nothing.
        $silhouette = fn ($px = 48) => '<span class="block rounded-full bg-paper-200 grid place-items-center overflow-hidden" style="width:'.$px.'px;height:'.$px.'px">'
            .'<svg viewBox="0 0 24 24" class="text-ink-400" style="width:'.round($px * 0.92).'px;height:'.round($px * 0.92).'px" fill="currentColor" aria-hidden="true">'
            .'<circle cx="12" cy="9" r="3.6"/><path d="M12 13.4c-3.4 0-6.2 2.1-7.1 5 1.8 2 4.3 3.2 7.1 3.2s5.3-1.2 7.1-3.2c-.9-2.9-3.7-5-7.1-5z"/></svg></span>';
        // A thread's contact row (may not exist yet) — for the avatar.
        $threadContact = fn ($accId, $igsid) => $contacts->get($accId.':'.$igsid);
        // The SAME Instagram user gets a different IGSID per account they message,
        // and their avatar is only fetched for the account that stored it — so the
        // same person can show a photo on one account's thread and a silhouette on
        // another's. The avatar belongs to the @username, not the account, so build
        // a username→avatar map (first contact that has one wins) and fall back to
        // it when a thread's own contact has no avatar.
        $avatarByUser = [];
        foreach ($contacts as $c) {
            $u = strtolower(ltrim((string) ($c->username ?? ''), '@'));
            if ($u !== '' && ! isset($avatarByUser[$u]) && ! empty($c->avatar_proxy_url)) {
                $avatarByUser[$u] = $c->avatar_proxy_url;
            }
        }
        $avatarFor = function ($contact) use ($avatarByUser) {
            if ($contact && ! empty($contact->avatar_proxy_url)) return $contact->avatar_proxy_url;
            $u = strtolower(ltrim((string) ($contact->username ?? ''), '@'));
            return $u !== '' ? ($avatarByUser[$u] ?? null) : null;
        };
        $openAcc  = $accById[$openAccountId] ?? null;
        // Soft per-thread avatar gradients so the list looks like the mockup.
        $grads = [
            'from-ig-pink to-ig-orange', 'from-ig-blue to-ig-purple', 'from-ig-amber to-ig-orange',
            'from-ig-purple to-ig-magenta', 'from-ig-red to-ig-pink', 'from-ig-orange to-ig-amber',
            'from-ig-magenta to-ig-purple', 'from-ig-blue to-ig-magenta',
        ];
    @endphp

    <div class="flex h-full min-h-0">

        {{-- ===== THREAD LIST COLUMN ===== --}}
        <aside class="w-[360px] shrink-0 hairline-r bg-white flex flex-col min-h-0">
            {{-- account header --}}
            <div class="px-5 pt-5 pb-3 shrink-0">
                @php
                    $selectedAccountId = $selectedAccountId ?? 0;
                    $status = request()->query('status', '');
                    $curAcc = $selectedAccountId ? ($accById[$selectedAccountId] ?? null) : null;
                    $acctLabel = $curAcc && $curAcc->username ? '@'.$curAcc->username : __('All accounts');
                    // Preserve the status filter when switching account.
                    $acctLink = fn ($id) => url('/instagram/inbox?' . http_build_query(array_filter(['account' => $id ?: null, 'status' => $status ?: null])));
                @endphp
                <div class="flex items-center justify-between">
                    @if ($accounts->count() > 1)
                        {{-- account switcher — filter the inbox to one connected account --}}
                        <details class="relative min-w-0 group">
                            <summary class="flex items-center gap-1.5 text-[19px] font-bold min-w-0 cursor-pointer list-none select-none">
                                <span class="truncate">{{ $acctLabel }}</span>
                                <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-500 shrink-0 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 6l4 4 4-4"/></svg>
                            </summary>
                            <div class="absolute z-20 mt-2 w-60 bg-white rounded-xl shadow-lg ring-1 ring-black/5 py-1.5 text-[13px] font-normal">
                                <a href="{{ $acctLink(0) }}" class="flex items-center gap-2 px-4 py-2 hover:bg-paper-100 {{ !$selectedAccountId ? 'text-ig-pink font-semibold' : 'text-ink-700' }}">{{ __('All accounts') }}</a>
                                @foreach ($accounts as $acc)
                                    <a href="{{ $acctLink($acc->id) }}" class="flex items-center gap-2 px-4 py-2 hover:bg-paper-100 {{ $selectedAccountId === $acc->id ? 'text-ig-pink font-semibold' : 'text-ink-700' }}">
                                        <span class="w-6 h-6 rounded-full bg-gradient-to-br {{ $grads[$loop->index % count($grads)] }} text-white text-[10px] font-semibold grid place-items-center shrink-0">{{ $initials('@'.$acc->username) }}</span>
                                        <span class="truncate">{{ $acc->username ? '@'.$acc->username : __('Account #').$acc->id }}</span>
                                        @if ($acc->status !== 'connected')<span class="ml-auto text-[9px] text-ink-400 shrink-0">{{ __('offline') }}</span>@endif
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <div class="flex items-center gap-1.5 text-[19px] font-bold min-w-0">
                            <span class="truncate">{{ $curAcc && $curAcc->username ? '@'.$curAcc->username : ($accounts->first() && $accounts->first()->username ? '@'.$accounts->first()->username : __('Inbox')) }}</span>
                        </div>
                    @endif
                    {{-- One compose button, exactly like Instagram's rail — the old
                         Refresh/Templates/Automations/Bulk-DM cluster crowded the header
                         and duplicated the app nav rail + the inbox's Quick actions. --}}
                    <button type="button" id="ig-compose-btn" title="{{ __('New message') }}" aria-label="{{ __('New message') }}"
                            class="w-9 h-9 rounded-full grid place-items-center text-ink-700 hover:text-ink-900 hover:bg-paper-100 transition shrink-0">
                        <svg viewBox="0 0 24 24" class="w-[21px] h-[21px]" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.5 12.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5.5a2 2 0 0 1 2-2h6.5"/>
                            <path d="M17.7 2.9a1.9 1.9 0 0 1 2.7 2.7L12.4 13.6l-3.5.8.8-3.5z"/>
                        </svg>
                    </button>
                </div>
                {{-- search --}}
                <div class="mt-3 flex items-center gap-2 bg-paper-100 rounded-xl px-3 py-2.5">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5"/><path d="m11 11 3 3"/></svg>
                    <input id="ig-thread-search" type="text" class="bg-transparent outline-none text-[13px] flex-1 placeholder:text-ink-500" placeholder="{{ __('Search conversations') }}">
                </div>
                {{-- tabs (status filter — real, drive ?status=; preserve account) --}}
                @php $statusLink = fn ($s) => url('/instagram/inbox?' . http_build_query(array_filter(['account' => $selectedAccountId ?: null, 'status' => $s ?: null]))); @endphp
                <div class="mt-3 flex items-center gap-1 text-[12.5px]">
                    <a href="{{ $statusLink('') }}" class="px-3 py-1.5 rounded-full {{ $status === '' ? 'ig-grad-soft text-white font-medium' : 'text-ink-600 hover:bg-paper-100' }}">{{ __('All') }}</a>
                    <a href="{{ $statusLink('in') }}" class="px-3 py-1.5 rounded-full {{ $status === 'in' ? 'ig-grad-soft text-white font-medium' : 'text-ink-600 hover:bg-paper-100' }}">{{ __('Received') }}</a>
                    <a href="{{ $statusLink('out') }}" class="px-3 py-1.5 rounded-full {{ $status === 'out' ? 'ig-grad-soft text-white font-medium' : 'text-ink-600 hover:bg-paper-100' }}">{{ __('Auto-sent') }}</a>
                    {{-- Archived tab — ALWAYS visible so you can review archived chats
                         even when the count is 0 or the archived thread sits on a
                         different account than the current filter. Toggles ?view=archived. --}}
                    <a href="{{ url('/instagram/inbox?' . http_build_query(array_filter(['account' => $selectedAccountId ?: null, 'view' => ($view ?? '') === 'archived' ? null : 'archived']))) }}"
                       class="px-3 py-1.5 rounded-full {{ ($view ?? '') === 'archived' ? 'ig-grad-soft text-white font-medium' : 'text-ink-600 hover:bg-paper-100' }}">{{ ($view ?? '') === 'archived' ? __('← Inbox') : __('Archived') }}@if (($archivedCount ?? 0) > 0 && ($view ?? '') !== 'archived') <span class="mono opacity-70">{{ $archivedCount }}</span>@endif</a>
                </div>
            </div>

            {{-- threads --}}
            @if (session('status'))
                <div class="mx-3 mt-2 mb-1 rounded-xl bg-wa-bubble/60 border border-wa-deep/15 px-3 py-2 text-[11.5px] text-wa-deep leading-snug">{{ session('status') }}</div>
            @endif
            <div class="flex-1 min-h-0 overflow-y-auto scrollbar" id="ig-thread-list"
                 data-max-id="{{ (int) ($threads->max('id') ?? 0) }}"
                 data-account="{{ (int) ($selectedAccountId ?? 0) }}">
                @forelse ($threads as $i => $t)
                    @php
                        $key = $t->instagram_account_id.':'.$t->igsid;
                        $name = $threadName($t->instagram_account_id, $t->igsid);
                        $grad = $grads[$i % count($grads)];
                        $isAuto = $t->direction === 'out';
                        $tAvatar = $avatarFor($threadContact($t->instagram_account_id, $t->igsid));
                        $muted   = ($mutedKeys ?? collect())->has($key);
                        $archivedView = ($view ?? '') === 'archived';
                        // Unread = latest message is inbound AND newer than the read
                        // cursor (last_read_id), and it isn't the thread we have open.
                        $isUnread = $t->direction === 'in'
                            && (int) $t->id > (int) (optional($threadContact($t->instagram_account_id, $t->igsid))->last_read_id ?? 0)
                            && $openKey !== $key;
                    @endphp
                    <a href="{{ url('/instagram/inbox?' . http_build_query(array_filter(['thread' => $key, 'account' => $selectedAccountId ?: null, 'status' => $status ?: null, 'stage' => ($stageFilter ?? '') ?: null, 'view' => $archivedView ? 'archived' : null]))) }}"
                       class="ig-thread block px-5 py-3 relative group {{ $openKey === $key ? 'active' : '' }}"
                       data-search="{{ strtolower($name.' '.$t->body) }}"
                       data-acc="{{ $t->instagram_account_id }}" data-igsid="{{ $t->igsid }}">
                        <div class="flex items-center gap-3">
                            <span class="ig-ring shrink-0">
                                @if ($tAvatar)
                                    <img src="{{ $tAvatar }}" alt="" class="block w-12 h-12 rounded-full object-cover bg-paper-100" loading="lazy"
                                         onerror="this.style.display='none';this.nextElementSibling?.classList.remove('hidden')">
                                    <span class="hidden">{!! $silhouette(48) !!}</span>
                                @else
                                    {!! $silhouette(48) !!}
                                @endif
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="flex items-center gap-1 min-w-0">
                                        <span class="text-[14px] truncate {{ $isUnread ? 'font-bold text-ink-900' : 'font-semibold' }}" title="{{ $name }}">{{ \Illuminate\Support\Str::limit($name, 20, '…') }}</span>
                                        @if ($muted)<svg viewBox="0 0 16 16" class="w-3 h-3 text-ink-400 shrink-0" title="{{ __('Muted') }}" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M4 6H2v4h2l4 3V3z"/><path d="M11 6l3 4M14 6l-3 4" stroke-linecap="round"/></svg>@endif
                                        {{-- Which of YOUR accounts this DM came into. Only in the
                                             "All accounts" view with more than one account connected,
                                             so single-account users never see a redundant tag. --}}
                                        @php $rowAcc = $accById[$t->instagram_account_id] ?? null; @endphp
                                        @if ($accounts->count() > 1 && !$selectedAccountId && $rowAcc?->username)
                                            <span class="mono text-[9px] leading-none px-1.5 py-0.5 rounded-full bg-ig-pink/10 text-ig-pink shrink-0 max-w-[92px] truncate"
                                                  title="{{ __('Received on your account') }} {{ '@' . $rowAcc->username }}">{{ '@' . $rowAcc->username }}</span>
                                        @endif
                                    </span>
                                    <span class="mono text-[10px] {{ $openKey === $key ? 'text-ig-pink' : 'text-ink-400' }} shrink-0">{{ $t->created_at?->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true, 1) ?? '' }}</span>
                                </div>
                                <div class="text-[12.5px] truncate flex items-center gap-1 {{ $isUnread ? 'text-ink-900 font-semibold' : 'text-ink-600' }}">
                                    @if ($isAuto)
                                        <svg viewBox="0 0 12 12" class="w-3 h-3 text-ig-pink shrink-0" fill="currentColor"><path d="M6 1l1.5 3 3 .5-2 2 .5 3L6 11 3 9.5l.5-3-2-2 3-.5z"/></svg>
                                        <span class="shrink-0 text-ink-500">↳</span>
                                    @endif
                                    @php
                                        // Conversation list preview: the text if any, else a
                                        // media-type label like Instagram ("Shared a reel",
                                        // "Photo") instead of a bare "(no text)".
                                        $preview = \Illuminate\Support\Str::limit((string) $t->body, 40);
                                        if ($preview === '') {
                                            $mt = strtolower((string) ($t->attachment_type ?? ''));
                                            $preview = match (true) {
                                                in_array($mt, ['image', 'photo', 'story_mention'], true) => __('Photo'),
                                                $mt === 'video'                                          => __('Video'),
                                                in_array($mt, ['ig_reel', 'reel', 'share'], true)        => __('Shared a reel'),
                                                in_array($mt, ['audio', 'voice'], true)                  => __('Voice message'),
                                                $mt === 'story_reply'                                    => __('Replied to your story'),
                                                $mt === 'story_mention'                                  => __('Mentioned you in their story'),
                                                $mt === 'sticker'                                        => __('Sticker'),
                                                $mt !== '' && $mt !== 'text'                             => __('Attachment'),
                                                default                                                  => __('(no text)'),
                                            };
                                        }
                                    @endphp
                                    <span class="truncate">{{ $preview }}</span>
                                </div>
                                @php $stg = ($stageByIgsid ?? [])[$t->igsid] ?? null; @endphp
                                @if ($stg)
                                    <span class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded-full text-[9.5px] font-mono"
                                          style="background:{{ $stg['color'] }}1a;color:{{ $stg['color'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full" style="background:{{ $stg['color'] }}"></span>{{ $stg['label'] }}
                                    </span>
                                @endif
                            </div>
                            {{-- Unread dot — cleared the moment the thread is opened
                                 (controller advances last_read_id). --}}
                            @if ($isUnread)
                                <span class="w-2.5 h-2.5 rounded-full bg-ig-pink shrink-0 mt-1" title="{{ __('Unread') }}"></span>
                            @endif
                        </div>

                        {{-- Per-conversation menu (WaDesk team-inbox style): opens on RIGHT-CLICK
                             — no three-dot button. Positioned at the cursor by the JS. --}}
                        <div class="ig-th-menu hidden fixed z-40 w-44 bg-white rounded-xl shadow-lg ring-1 ring-black/5 py-1 text-[12.5px] text-ink-700">
                            <button type="button" class="ig-th-act w-full text-left px-3.5 py-2 hover:bg-paper-100 flex items-center gap-2.5" data-act="archive">
                                <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 4h12v2H2zM3 6v7h10V6M6.5 9h3"/></svg>{{ $archivedView ? __('Unarchive chat') : __('Archive chat') }}
                            </button>
                            <button type="button" class="ig-th-act w-full text-left px-3.5 py-2 hover:bg-paper-100 flex items-center gap-2.5" data-act="mute">
                                <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6H2v4h2l4 3V3z"/><path d="M11 6l3 4M14 6l-3 4" stroke-linecap="round"/></svg><span class="ig-th-mute-label">{{ $muted ? __('Unmute chat') : __('Mute chat') }}</span>
                            </button>
                            <button type="button" class="ig-th-act w-full text-left px-3.5 py-2 hover:bg-red-50 text-red-600 flex items-center gap-2.5" data-act="delete">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4h10M6 4V2.5h4V4M4.5 4l.5 9h6l.5-9" stroke-linecap="round"/></svg>{{ __('Delete chat') }}
                            </button>
                        </div>
                    </a>
                @empty
                    <div class="px-6 py-16 text-center">
                        <span class="ig-grad w-12 h-12 rounded-2xl grid place-items-center text-white mx-auto mb-3"><svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2.5 5.5h15v9H7l-4.5 3.5z"/></svg></span>
                        <div class="text-[13px] font-semibold">{{ __('No conversations yet') }}</div>
                        <p class="text-[12px] text-ink-500 mt-1">{{ __('Direct messages appear here when someone DMs you.') }}</p>
                    </div>
                @endforelse
                <div id="ig-thread-empty" class="hidden px-6 py-12 text-center text-[12px] text-ink-500">{{ __('No conversations match your search.') }}</div>
            </div>
        </aside>

        {{-- ===== NEW MESSAGE MODAL (compose) =====
             Instagram's own picker, same shape: To: search → pick one → Chat.
             The list is everyone who has DMed this workspace's accounts, which
             is also exactly who Meta lets us message — the Messaging API only
             allows a reply to an existing conversation, so there is no "search
             all of Instagram" to offer here. --}}
        <div id="ig-compose-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
            <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-[2px]" data-close-compose></div>
            <div class="relative w-full max-w-[430px] bg-paper-0 rounded-2xl shadow-soft hairline overflow-hidden flex flex-col max-h-[80vh]">
                <div class="px-4 py-3 hairline-b flex items-center justify-center relative shrink-0">
                    <div class="text-[14px] font-semibold">{{ __('New message') }}</div>
                    <button type="button" data-close-compose aria-label="{{ __('Close') }}" class="absolute right-2.5 w-8 h-8 rounded-full hover:bg-paper-100 grid place-items-center text-ink-500 transition">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                    </button>
                </div>
                <div class="px-4 py-2.5 hairline-b flex items-center gap-2 shrink-0">
                    <span class="text-[13px] font-semibold shrink-0">{{ __('To:') }}</span>
                    <input id="ig-compose-search" type="text" autocomplete="off" placeholder="{{ __('Search…') }}" class="flex-1 bg-transparent outline-none text-[13px] placeholder:text-ink-400">
                </div>
                <div id="ig-compose-list" class="flex-1 min-h-0 overflow-y-auto scrollbar py-1">
                    @forelse ($contacts as $key => $c)
                        @php
                            $cName   = $c->name ?: $c->displayName();
                            $cHandle = $c->displayName();
                            $cAcc    = $accById[$c->instagram_account_id] ?? null;
                        @endphp
                        <button type="button" class="ig-compose-row w-full px-4 py-2.5 flex items-center gap-3 text-left hover:bg-paper-50 transition"
                                data-key="{{ $key }}" data-search="{{ strtolower($cName.' '.$cHandle) }}">
                            <span class="ig-ring shrink-0">
                                @if ($c->avatar_proxy_url)
                                    <img src="{{ $c->avatar_proxy_url }}" alt="" class="block w-11 h-11 rounded-full object-cover bg-paper-100" loading="lazy"
                                         onerror="this.style.display='none';this.nextElementSibling?.classList.remove('hidden')">
                                    <span class="hidden">{!! $silhouette(44) !!}</span>
                                @else
                                    {!! $silhouette(44) !!}
                                @endif
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold truncate">{{ $cName }}</div>
                                <div class="text-[11.5px] text-ink-500 truncate">
                                    {{ $cHandle }}@if ($accounts->count() > 1 && $cAcc?->username) · {{ '@'.$cAcc->username }}@endif
                                </div>
                            </div>
                            {{-- Rounded square, not a circle: several people can be ticked, and a
                                 radio shape would promise single-select. --}}
                            <span class="ig-radio w-5 h-5 rounded-md border-2 border-paper-300 shrink-0 grid place-items-center transition">
                                <span class="dot w-3 h-3 rounded-sm ig-grad-soft scale-0 transition"></span>
                            </span>
                        </button>
                    @empty
                        <div class="px-6 py-12 text-center">
                            <div class="text-[13px] font-semibold">{{ __('No one to message yet') }}</div>
                            <p class="text-[12px] text-ink-500 mt-1.5 leading-relaxed">{{ __('Instagram only lets you DM people who messaged you first. They will show up here as soon as they do.') }}</p>
                        </div>
                    @endforelse
                    <div id="ig-compose-empty" class="hidden px-6 py-10 text-center text-[12px] text-ink-500">{{ __('No one matches that search.') }}</div>
                </div>
                <div class="p-3 shrink-0 hairline-t">
                    <button type="button" id="ig-compose-go" disabled
                            class="w-full py-2.5 rounded-xl ig-grad-soft text-white text-[13px] font-semibold transition disabled:opacity-40 disabled:cursor-not-allowed hover:opacity-90">{{ __('Chat') }}</button>
                </div>
            </div>
        </div>

        {{-- ===== CHAT PANE ===== --}}
        <main class="flex-1 flex flex-col min-w-0 min-h-0">
            @if ($openIgsid && $messages->isNotEmpty())
                @php
                    $openName = $threadName($openAccountId, $openIgsid);
                    $openAi = \App\Models\InstagramContact::where('instagram_account_id', $openAccountId)->where('igsid', $openIgsid)->value('ai_enabled');
                    $openAi = is_null($openAi) ? true : (bool) $openAi;
                    // Per-conversation AI agent picker (Team-Inbox style): the account's
                    // active ai_agent automations, plus which one (if any) is pinned to
                    // THIS conversation. No pin = the default (first active) agent.
                    $aiAgents = \App\Models\InstagramAutomation::where('instagram_account_id', $openAccountId)
                        ->where('type', 'ai_agent')->where('is_active', true)->orderBy('id')->get(['id', 'name']);
                    $hasAiAgent = $aiAgents->isNotEmpty();
                    $assignedAgentId = (int) (\App\Models\InstagramContact::where('instagram_account_id', $openAccountId)
                        ->where('igsid', $openIgsid)->value('ai_agent_id') ?? 0) ?: null;
                    $currentAgent = $assignedAgentId ? $aiAgents->firstWhere('id', $assignedAgentId) : null;
                @endphp
                {{-- chat header — conversation actions live here (Team-Inbox style) --}}
                <div class="px-5 py-2.5 hairline-b flex items-center gap-2.5 shrink-0">
                    @php $openAvatar = $avatarFor($threadContact($openAccountId, $openIgsid)); @endphp
                    <span class="ig-ring shrink-0">
                        @if ($openAvatar)
                            <img src="{{ $openAvatar }}" alt="" class="block w-9 h-9 rounded-full object-cover bg-paper-100" loading="lazy"
                                 onerror="this.style.display='none';this.nextElementSibling?.classList.remove('hidden')">
                            <span class="hidden">{!! $silhouette(36) !!}</span>
                        @else
                            {!! $silhouette(36) !!}
                        @endif
                    </span>
                    <div class="min-w-0">
                        <div class="text-[13.5px] font-semibold truncate max-w-[150px]">{{ $openName }}</div>
                        <div class="mono text-[9.5px] text-ink-500 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('via') }} {{ $openAcc && $openAcc->username ? '@'.$openAcc->username : __('Instagram') }}</div>
                    </div>

                    <div class="flex-1"></div>

                    {{-- AI Agent picker — EXACT Team-Inbox flow. The "Assign agent"
                         button is ALWAYS shown; clicking it opens the dropdown. When no
                         agents exist yet the menu shows a "Create agent" row that opens
                         the in-page modal. Once agents exist, pick one to assign, turn AI
                         off, or add/manage more — no page redirect anywhere. --}}
                    <div class="relative shrink-0" id="ig-agent-wrap"
                        data-account="{{ $openAccountId }}" data-igsid="{{ $openIgsid }}"
                        data-url="{{ route('instagram.inbox.assign-agent') }}">
                        <button type="button" id="ig-agent-btn"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[11.5px] font-medium {{ $openAi ? 'border-ig-pink/40 bg-ig-pink/5 text-ig-pink' : 'border-paper-200 text-ink-500' }}"
                            title="{{ __('Assign an AI agent to this conversation') }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="10" height="8" rx="2"/><path d="M6 5V3.5A2 2 0 0 1 10 3.5V5"/><circle cx="6" cy="9" r="1" fill="currentColor" stroke="none"/><circle cx="10" cy="9" r="1" fill="currentColor" stroke="none"/></svg>
                            <span id="ig-agent-label" class="truncate max-w-[90px]">{{ $openAi ? ($currentAgent->name ?? __('AI Agent')) : __('Assign agent') }}</span>
                            <svg viewBox="0 0 16 16" class="w-3 h-3 opacity-60 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 6l4 4 4-4"/></svg>
                        </button>
                        <div id="ig-agent-menu" class="hidden absolute top-full mt-2 right-0 w-64 bg-paper-0 border border-paper-200 rounded-xl shadow-lg z-30 p-1">
                            <div class="font-mono text-[9.5px] uppercase tracking-widest text-ink-400 px-2.5 pt-2 pb-1">{{ __('AI Agents') }}</div>
                            @forelse ($aiAgents as $ag)
                                @php $isSel = $openAi && ($assignedAgentId ? $assignedAgentId === $ag->id : $loop->first); @endphp
                                <button type="button" class="ig-agent-pick w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-paper-100 text-left" data-agent="{{ $ag->id }}">
                                    <span class="w-6 h-6 rounded-full ig-grad-soft text-white text-[9px] font-semibold grid place-items-center shrink-0">{{ strtoupper(mb_substr($ag->name ?: 'AI', 0, 2)) }}</span>
                                    <span class="flex-1 min-w-0 text-[12.5px] truncate">{{ $ag->name ?: __('AI agent') }}</span>
                                    <svg viewBox="0 0 16 16" class="ig-agent-check w-3.5 h-3.5 text-ig-pink shrink-0 {{ $isSel ? '' : 'hidden' }}" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5l3 3 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            @empty
                                <div class="px-2.5 py-2 text-[11.5px] text-ink-500">{{ __('No agents yet — create one below.') }}</div>
                            @endforelse
                            @if ($hasAiAgent)
                                <button type="button" class="ig-agent-pick w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-paper-100 text-left text-ink-600" data-agent="0">
                                    <span class="w-6 h-6 rounded-full bg-paper-100 text-ink-500 grid place-items-center shrink-0"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4l8 8M12 4l-8 8" stroke-linecap="round"/></svg></span>
                                    <span class="flex-1 text-[12.5px]">{{ __('Turn off AI') }}</span>
                                    <svg viewBox="0 0 16 16" class="ig-agent-check w-3.5 h-3.5 text-ink-500 shrink-0 {{ $openAi ? 'hidden' : '' }}" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5l3 3 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            @endif
                            <button type="button" class="ig-agents-open w-full text-left border-t border-paper-200 mt-1 px-2.5 py-2 text-[12px] text-ig-pink font-medium hover:bg-paper-50 rounded-b-lg">{{ $hasAiAgent ? __('+ Add / manage agents') : __('+ Create agent') }}</button>
                        </div>
                    </div>


                    {{-- Saved-template picker --}}
                    <div class="relative shrink-0">
                        <button type="button" id="ig-tpl-btn" class="w-8 h-8 rounded-full grid place-items-center text-ink-600 hover:text-ink-900 hover:bg-paper-100 transition" title="{{ __('Insert a saved template') }}">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="2.5" width="11" height="11" rx="1.6"/><path d="M2.5 6h11M6 6v7.5"/></svg>
                        </button>
                        <div id="ig-tpl-menu" class="hidden absolute top-full mt-2 right-0 w-72 max-h-72 overflow-y-auto bg-paper-0 border border-paper-200 rounded-xl shadow-lg z-30 p-1"
                             data-url="{{ route('instagram.templates.list') }}" data-manage="{{ url('/instagram/templates') }}">
                            <div class="text-[11px] text-ink-400 px-2 py-3 text-center" id="ig-tpl-empty">{{ __('Loading…') }}</div>
                        </div>
                    </div>

                    {{-- Details — toggle the info side-panel (was a confusing "?" icon). --}}
                    <button type="button" id="ig-detail-toggle" title="{{ __('Toggle details panel') }}" class="w-8 h-8 rounded-full grid place-items-center text-ink-600 hover:text-ink-900 hover:bg-paper-100 transition shrink-0">
                        <svg viewBox="0 0 20 20" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="14" height="12" rx="2.5"/><path d="M12.5 4v12"/></svg>
                    </button>
                </div>

                {{-- ===== AI Agents modal — create / manage agents WITHOUT leaving the
                     inbox (Team-Inbox style). Opened by "Set up AI" and "Add/manage
                     agents". ===== --}}
                <div id="ig-agents-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4"
                     data-create-url="{{ route('instagram.inbox.create-agent') }}" data-account="{{ $openAccountId }}">
                    <div class="absolute inset-0 bg-ink-900/40" data-close-agents></div>
                    <div class="relative bg-paper-0 rounded-2xl w-full max-w-lg p-5 shadow-2xl max-h-[90vh] flex flex-col">
                        <div class="flex items-start justify-between mb-1 shrink-0">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Workspace') }}</div>
                                <h3 class="serif text-[22px] leading-none mt-0.5">{{ __('AI Agents') }}</h3>
                            </div>
                            <button type="button" data-close-agents class="w-8 h-8 rounded-full hover:bg-paper-100 grid place-items-center text-ink-500 shrink-0"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4l8 8M12 4l-8 8" stroke-linecap="round"/></svg></button>
                        </div>
                        <p class="text-[12px] text-ink-500 mb-3 shrink-0">{{ __('AI agents auto-reply to Instagram DMs on behalf of your team.') }}</p>

                        <div class="flex-1 min-h-0 overflow-y-auto scrollbar -mx-1 px-1">
                            <div class="space-y-2">
                            @forelse ($aiAgents as $ag)
                                <div class="hairline rounded-xl p-3 flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-full ig-grad-soft text-white text-[11px] font-semibold grid place-items-center shrink-0">{{ strtoupper(mb_substr($ag->name ?: 'AI', 0, 2)) }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[13px] font-semibold truncate">{{ $ag->name ?: __('AI agent') }}</div>
                                        <div class="mono text-[10px] text-wa-deep">{{ __('active') }}</div>
                                    </div>
                                    <a href="{{ url('/instagram/automations') }}" class="text-[11px] text-ink-500 hover:text-ig-pink shrink-0">{{ __('Edit') }}</a>
                                </div>
                            @empty
                                <div class="text-[12px] text-ink-500 text-center py-4">{{ __('No AI agents yet — create your first below.') }}</div>
                            @endforelse
                            </div>

                        {{-- "How the AI agent will work" --}}
                        <details class="mt-3 mb-3 hairline rounded-xl bg-paper-50 overflow-hidden">
                            <summary class="px-3 py-2 text-[11px] font-mono uppercase tracking-[0.14em] text-ink-600 cursor-pointer hover:bg-paper-100 select-none">{{ __('› How the AI agent will work') }}</summary>
                            <div class="px-4 py-3 text-[11.5px] leading-relaxed text-ink-600 border-t border-paper-200 bg-paper-0">
                                {{ __('Give the agent a name + a personality prompt. In a DM, click "AI Agent" → pick this agent → it replies to every incoming message (reading recent context) with your chosen model, until you type a reply yourself.') }}
                            </div>
                        </details>

                        <form id="ig-agent-create" class="pt-3 border-t border-paper-200 space-y-3.5">
                            <div class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Add agent') }}</div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Agent name') }}</label>
                                <input type="text" name="name" required maxlength="120" placeholder="{{ __('e.g. Sales AI, Support AI') }}" class="w-full px-3 py-2 hairline rounded-lg bg-white text-[13px] focus:outline-none focus:border-ig-pink">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Provider') }}</label>
                                    <select name="provider" class="w-full px-3 py-2 hairline rounded-lg bg-white text-[13px] focus:outline-none focus:border-ig-pink">
                                        <option value="openai">{{ __('OpenAI (GPT)') }}</option>
                                        <option value="anthropic">{{ __('Anthropic (Claude)') }}</option>
                                        <option value="gemini">{{ __('Google (Gemini)') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Model') }}</label>
                                    <select name="model" class="w-full px-3 py-2 hairline rounded-lg bg-white text-[13px] focus:outline-none focus:border-ig-pink">
                                        @foreach ($aiModels as $pk => $ml)
                                            <optgroup label="{{ ucfirst($pk) }}">
                                                @foreach ($ml as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Tone') }}</label>
                                    <select name="tone" class="w-full px-3 py-2 hairline rounded-lg bg-white text-[13px] focus:outline-none focus:border-ig-pink">
                                        <option value="professional">{{ __('Professional') }}</option>
                                        <option value="friendly">{{ __('Friendly') }}</option>
                                        <option value="concise">{{ __('Concise') }}</option>
                                        <option value="empathetic">{{ __('Empathetic') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Avatar color') }}</label>
                                    <div class="flex items-center gap-2 flex-wrap pt-1.5" id="ig-agent-colors">
                                        <input type="hidden" name="avatar_color" value="#6366f1">
                                        @foreach (['#6366f1','#075E54','#0ea5e9','#f59e0b','#ef4444','#8b5cf6'] as $c)
                                            <button type="button" data-color="{{ $c }}" class="ig-color-swatch w-6 h-6 rounded-full {{ $loop->first ? 'ring-2 ring-offset-2 ring-ink-400' : '' }}" style="background: {{ $c }}"></button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('System prompt') }}</label>
                                <textarea name="system_prompt" rows="4" maxlength="4000" placeholder="{{ __('You are a helpful Instagram assistant for our brand. Answer product / order questions warmly and briefly, and offer to connect a human when unsure.') }}" class="w-full px-3 py-2 hairline rounded-lg bg-white text-[13px] resize-y focus:outline-none focus:border-ig-pink"></textarea>
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __("Describe the agent's role, knowledge, and behaviour. Leave blank for a generic assistant.") }}</div>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Max tokens') }}</label>
                                    <input type="number" name="max_tokens" min="64" max="4096" value="512" class="w-full px-3 py-2 hairline rounded-lg bg-white text-[13px] focus:outline-none focus:border-ig-pink">
                                </div>
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Temperature (0–10)') }}</label>
                                    <input type="number" name="temperature" min="0" max="10" value="7" class="w-full px-3 py-2 hairline rounded-lg bg-white text-[13px] focus:outline-none focus:border-ig-pink">
                                    <div class="text-[10px] text-ink-500 mt-1">{{ __('0=focused 10=creative') }}</div>
                                </div>
                                <div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Auto-respond') }}</label>
                                    <label class="flex items-center gap-2 mt-2 cursor-pointer"><input type="checkbox" name="auto_respond" checked class="w-4 h-4 rounded accent-ig-pink"><span class="text-[12px] text-ink-700">{{ __('Enabled') }}</span></label>
                                </div>
                            </div>
                            <div class="hairline rounded-xl p-3 bg-ig-pink/5">
                                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="use_saved_replies" class="w-4 h-4 rounded accent-ig-pink"><span class="text-[12px] text-ink-700 font-semibold">{{ __('Use saved templates as canned answers') }}</span></label>
                                <p class="text-[10.5px] text-ink-500 mt-1.5 ml-6">{{ __('The AI uses your Instagram reply templates verbatim when a DM matches.') }}</p>
                            </div>
                            <div class="hairline rounded-xl p-3 bg-amber-500/5 flex items-center gap-2">
                                <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Human handoff') }}</span>
                                <label class="ml-auto flex items-center gap-2 cursor-pointer"><input type="checkbox" name="handoff_enabled" checked class="w-4 h-4 rounded accent-ig-pink"><span class="text-[11.5px] text-ink-700 font-semibold">{{ __('Enabled') }}</span></label>
                            </div>
                            <button type="submit" class="w-full px-4 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('Create agent') }}</button>
                        </form>
                        </div>
                    </div>
                </div>

                {{-- messages --}}
                <div class="flex-1 min-h-0 overflow-y-auto scrollbar ig-chat-bg px-6 py-5 space-y-1" id="ig-message-scroll"
                    data-account-id="{{ $openAccountId }}" data-igsid="{{ $openIgsid }}" data-last-id="{{ optional($messages->last())->id ?? 0 }}"
                    data-oldest-id="{{ $oldestId ?? 0 }}" data-has-more="{{ ($hasMore ?? false) ? '1' : '0' }}" data-older-url="{{ route('instagram.inbox.older') }}">

                    {{-- Scroll-up "load older" sentinel — the JS shows it while a batch
                         loads and prepends older messages just below it. --}}
                    <div id="ig-older-sentinel" class="hidden flex justify-center py-2">
                        <span class="mono text-[10px] text-ink-400 tracking-wide flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2.5a5.5 5.5 0 1 0 5.5 5.5" stroke-linecap="round"/></svg>
                            {{ __('Loading older messages…') }}
                        </span>
                    </div>

                    {{-- profile intro --}}
                    <div class="flex flex-col items-center py-4">
                        <span class="ig-ring"><span class="block w-20 h-20 rounded-full bg-gradient-to-br from-ig-pink to-ig-orange text-white text-[26px] font-semibold grid place-items-center">{{ $initials($openName) }}</span></span>
                        <div class="text-[16px] font-bold mt-3">{{ $openName }}</div>
                        <div class="text-[12px] text-ink-500">{{ __('Instagram Direct') }}@if ($openAcc && $openAcc->followers_count) · {{ number_format($openAcc->followers_count) }} {{ __('followers') }}@endif</div>
                    </div>

                    @include('instagram.partials.thread-messages', ['messages' => $messages])
                </div>

                {{-- composer --}}
                <div class="px-5 py-4 shrink-0 hairline-t">
                    @error('instagram')<div class="mb-2.5 hairline rounded-xl bg-ig-pink/5 border-ig-pink/30 px-4 py-2 text-[12px] text-ig-pink">{{ $message }}</div>@enderror
                    <form method="POST" action="{{ url('/instagram/inbox/reply') }}" id="ig-reply-form" enctype="multipart/form-data">@csrf
                        <input type="hidden" name="instagram_account_id" value="{{ $openAccountId }}">
                        <input type="hidden" name="igsid" value="{{ $openIgsid }}">

                        {{-- Selected-template preview (Team-Inbox style): shows the full
                             template body + buttons as an outbound bubble. While shown, the
                             normal composer row is hidden; Send here fires the template. --}}
                        <div id="ig-tpl-preview" class="hidden mb-3">
                            <div class="flex items-center justify-between mb-2 px-1">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Selected template ·') }} <span id="ig-tp-name" class="text-ink-700"></span>
                                </div>
                                <button type="button" id="ig-tp-clear" class="text-[10.5px] text-ig-pink font-semibold hover:underline">{{ __('Clear · pick another') }}</button>
                            </div>
                            <div class="ig-grad-soft text-white rounded-2xl rounded-br-md px-3.5 py-2.5 max-w-[420px] ml-auto shadow-sm">
                                <div id="ig-tp-body" class="text-[13.5px] whitespace-pre-wrap leading-snug"></div>
                                <div class="text-[9px] text-white/70 text-right mt-1.5">10:30</div>
                            </div>
                            {{-- Buttons / quick-replies render as full-width action rows BELOW the bubble (WhatsApp/WaDesk style). --}}
                            <div id="ig-tp-buttons" class="hidden max-w-[420px] ml-auto space-y-1 mt-1"></div>
                            <div class="flex justify-end mt-2">
                                <button type="submit" class="px-6 py-2 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90 flex items-center gap-1.5">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7Z"/></svg>{{ __('Send') }}
                                </button>
                            </div>
                        </div>

                        {{-- Instagram-style composer: emoji (left), then when the field
                             is EMPTY the right shows voice / photo / GIF icons; the moment
                             you type (or stage media) they swap for the send button. --}}
                        <div id="ig-composer-row" class="hairline rounded-full flex items-center gap-1 pl-1.5 pr-1.5 py-1.5">
                            {{-- emoji picker --}}
                            <div class="relative shrink-0" id="ig-emoji-wrap">
                                <button type="button" id="ig-emoji-btn" class="w-8 h-8 rounded-full grid place-items-center text-ink-500 hover:bg-paper-100" title="{{ __('Emoji') }}">
                                    <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="7.5"/><path d="M7 8.3h.01M13 8.3h.01" stroke-linecap="round"/><path d="M6.8 12.2a4 4 0 0 0 6.4 0" stroke-linecap="round"/></svg>
                                </button>
                                {{-- emoji-picker-element web component is appended here on first open --}}
                                <div id="ig-emoji-menu" class="hidden absolute bottom-full mb-2 left-0 z-30 rounded-xl shadow-lg overflow-hidden border border-paper-200"></div>
                            </div>

                            {{-- hidden attach input + remote-media (GIF) fields --}}
                            <input type="file" name="media_file" id="ig-reply-file" class="hidden" accept="image/*,video/*,audio/*,.pdf">
                            <input type="hidden" name="media_url" id="ig-reply-media-url">
                            <input type="hidden" name="media_type" id="ig-reply-media-type">

                            <input name="body" id="ig-reply-body" maxlength="1000" autocomplete="off" class="flex-1 bg-transparent text-[13.5px] outline-none placeholder:text-ink-400 py-1.5 px-1" placeholder="{{ __('Message…') }}">

                            {{-- action icons — visible only while the field is empty --}}
                            <div id="ig-compose-actions" class="flex items-center gap-0.5 shrink-0">
                                <button type="button" id="ig-mic-btn" class="w-8 h-8 rounded-full grid place-items-center text-ink-500 hover:bg-paper-100" title="{{ __('Record voice note') }}">
                                    <svg viewBox="0 0 20 20" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="7.5" y="2.5" width="5" height="9" rx="2.5"/><path d="M5 9.5a5 5 0 0 0 10 0"/><path d="M10 14.5V17M7.5 17h5"/></svg>
                                </button>
                                <button type="button" id="ig-photo-btn" class="w-8 h-8 rounded-full grid place-items-center text-ink-500 hover:bg-paper-100" title="{{ __('Send photo or video') }}">
                                    <svg viewBox="0 0 20 20" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="3.5" width="15" height="13" rx="2.5"/><circle cx="7" cy="8" r="1.5"/><path d="M3 14l4-3.5 3.5 3 3-2.5L17 14"/></svg>
                                </button>
                                <div class="relative shrink-0" id="ig-gif-wrap">
                                    <button type="button" id="ig-sticker-btn" class="w-8 h-8 rounded-full grid place-items-center text-ink-500 hover:bg-paper-100" title="{{ __('Send a GIF') }}">
                                        <svg viewBox="0 0 20 20" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2.5" y="4.5" width="15" height="11" rx="2.5"/><path d="M6.5 8.4c-.6-.5-2-.4-2 1.1s1.4 1.6 2 1.1v-1H6M9 8.2v3.4M12 11.6V8.2h2.3M12 9.9h1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    <div id="ig-gif-menu" class="hidden absolute bottom-full mb-2 right-0 w-[300px] bg-paper-0 border border-paper-200 rounded-xl shadow-lg z-30 p-2" data-url="{{ route('instagram.inbox.gif-search') }}">
                                        <input type="text" id="ig-gif-search" placeholder="{{ __('Search GIPHY…') }}" class="w-full px-3 py-1.5 hairline rounded-lg bg-white text-[12.5px] mb-2 focus:outline-none focus:border-ig-pink">
                                        <div id="ig-gif-grid" class="max-h-64 overflow-y-auto scrollbar"></div>
                                        <div class="text-[9px] text-ink-400 text-center pt-1.5 uppercase tracking-widest">{{ __('Powered by GIPHY') }}</div>
                                    </div>
                                </div>
                            </div>

                            {{-- send button — visible while typing / media staged --}}
                            <button type="submit" id="ig-send-btn" class="hidden w-9 h-9 rounded-full ig-grad-soft text-white grid place-items-center shrink-0 disabled:opacity-100" title="{{ __('Send') }}">
                                <svg id="ig-send-plane" viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7Z"/></svg>
                                <svg id="ig-send-spin" viewBox="0 0 24 24" class="hidden w-[18px] h-[18px] animate-spin" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" stroke-opacity="0.3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
                            </button>
                        </div>

                        {{-- chosen-file chip + injected template fields. The AI / Templates /
                             Human-agent controls now live in the chat header (Team-Inbox style). --}}
                        <div class="flex items-center flex-wrap gap-2.5 mt-2 px-1 text-[11.5px] empty:hidden">
                            {{-- Staged attachment: image thumbnail (for images) + filename + remove (×). --}}
                            <span id="ig-reply-preview" class="hidden items-center gap-2 pl-1 pr-1.5 py-1 rounded-full bg-paper-100 text-ink-600">
                                <img id="ig-reply-thumb" alt="" class="hidden w-6 h-6 rounded-md object-cover shrink-0">
                                <span id="ig-reply-fname" class="max-w-[180px] truncate"></span>
                                <button type="button" id="ig-reply-remove" title="{{ __('Remove') }}" aria-label="{{ __('Remove attachment') }}"
                                        class="w-4.5 h-4.5 grid place-items-center rounded-full text-ink-500 hover:bg-paper-200 hover:text-ink-800 shrink-0">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                                </button>
                            </span>
                            {{-- A picked template's quick-reply / button fields are injected here (kept inside the form so they submit) --}}
                            <div id="ig-tpl-inject" class="hidden"></div>
                            <button type="button" id="ig-tpl-active" class="hidden items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-ig-purple/8 text-ig-purple" title="{{ __('Clear template') }}">
                                <span id="ig-tpl-active-name"></span>
                                <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                            </button>
                        </div>
                        <div id="ig-window-note" class="mono text-[10px] text-ink-400 mt-1.5 px-1 flex items-center gap-1.5">
                            <span id="ig-window-dot" class="w-1.5 h-1.5 rounded-full shrink-0" style="background:#c7c7c7"></span>
                            <span id="ig-window-text">{{ __('Replies are allowed only inside the 24-hour messaging window.') }}</span>
                        </div>
                    </form>
                </div>

                {{-- In-app reel player. A DM-shared reel carries only its PUBLIC
                     permalink (an HTML page, not a video file). We stream the real
                     .mp4 through our own origin (data-media-tpl → inboxMedia scrapes
                     the video url + proxies it) and play it in <video>. If that url
                     can't be resolved (private / login-walled), the <video> errors
                     and JS falls back to Instagram's framable embed player. --}}
                <div id="ig-reel-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-black/85 p-4" role="dialog" aria-modal="true"
                     data-media-tpl="{{ route('instagram.inbox.media', ['message' => 'MID']) }}">
                    <div class="relative w-full max-w-[400px]">
                        <button type="button" id="ig-reel-close" class="absolute -top-9 right-0 text-white/80 hover:text-white grid place-items-center" title="{{ __('Close') }}" aria-label="{{ __('Close') }}">
                            <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                        <div class="rounded-2xl overflow-hidden bg-black h-[80vh] grid place-items-center">
                            <video id="ig-reel-video" class="w-full h-full object-contain bg-black" controls autoplay playsinline></video>
                            <iframe id="ig-reel-frame" src="" title="{{ __('Reel') }}" class="w-full h-full border-0 hidden" scrolling="no" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture"></iframe>
                        </div>
                        <a id="ig-reel-ext" href="#" target="_blank" rel="noopener" class="block text-center text-white/60 hover:text-white text-[11.5px] mt-3 underline">{{ __('Open on Instagram') }}</a>
                    </div>
                </div>
            @else
                {{-- ===== EMPTY CHAT STATE =====
                     No thread open. Rather than a lone icon marooned in the
                     widest pane on the page, this doubles as the module's home:
                     live counts, every connected account with its own thread
                     tally (click = filter the rail to it), and the jobs an
                     operator actually opens this page to do. --}}
                @php
                    $byAccount   = $threads->groupBy('instagram_account_id');
                    $totalChats  = $threads->count();
                    $inCount     = $threads->where('direction', 'in')->count();
                    $outCount    = $threads->where('direction', 'out')->count();
                    $latestAt    = $threads->max('created_at');
                    $compact     = fn ($n) => $n >= 1000000 ? round($n / 1000000, 1) . 'M' : ($n >= 1000 ? round($n / 1000, 1) . 'K' : (string) $n);
                @endphp
                <div class="flex-1 overflow-y-auto ig-chat-bg scrollbar">
                    <div class="min-h-full grid place-items-center px-6 py-10">
                        <div class="w-full max-w-[600px]">

                            {{-- ── hero ── --}}
                            <div class="text-center">
                                {{-- Just the badge over its own glow. The two "floating chat
                                     bubbles" that used to flank it read as stray blobs — the
                                     white one looked like a render glitch on light paper. --}}
                                <div class="relative inline-flex items-center justify-center mb-5">
                                    <span class="absolute w-28 h-28 rounded-full ig-grad-soft opacity-20 blur-3xl"></span>
                                    <span class="relative w-[72px] h-[72px] rounded-[22px] ig-grad-soft grid place-items-center shadow-soft">
                                        <svg viewBox="0 0 24 24" class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-9z"/></svg>
                                    </span>
                                </div>
                                <div class="serif text-[28px] leading-tight">{{ __('Your') }} <span class="ig-text italic">{{ __('Direct') }}</span> {{ __('inbox') }}</div>
                                <p class="text-[12.5px] text-ink-500 mt-1.5">
                                    @if ($accounts->isEmpty())
                                        {{ __('Connect an Instagram account to start receiving DMs here.') }}
                                    @elseif ($threads->isEmpty())
                                        {{ __('Conversations land here as soon as someone DMs your connected account.') }}
                                    @else
                                        {{ __('Select a conversation on the left to read and reply.') }}
                                    @endif
                                </p>
                            </div>

                            @if ($accounts->isEmpty())
                                {{-- nothing connected — one obvious next step --}}
                                <div class="mt-6 flex justify-center">
                                    <a href="{{ route('instagram.connect') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold shadow-soft hover:opacity-90 transition">
                                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M8 3.5v9M3.5 8h9"/></svg>{{ __('Connect Instagram') }}
                                    </a>
                                </div>
                            @else
                                {{-- ── live counts ── --}}
                                @if ($totalChats)
                                    <div class="mt-6 grid grid-cols-3 gap-2.5">
                                        <div class="hairline rounded-2xl bg-paper-0 px-3 py-3 text-center">
                                            <div class="serif text-[22px] leading-none">{{ $compact($totalChats) }}</div>
                                            <div class="mono text-[9.5px] uppercase tracking-[0.14em] text-ink-400 mt-1.5">{{ __('Chats') }}</div>
                                        </div>
                                        <div class="hairline rounded-2xl bg-paper-0 px-3 py-3 text-center">
                                            <div class="serif text-[22px] leading-none ig-text">{{ $compact($inCount) }}</div>
                                            <div class="mono text-[9.5px] uppercase tracking-[0.14em] text-ink-400 mt-1.5">{{ __('Received') }}</div>
                                        </div>
                                        <div class="hairline rounded-2xl bg-paper-0 px-3 py-3 text-center">
                                            <div class="serif text-[22px] leading-none">{{ $compact($outCount) }}</div>
                                            <div class="mono text-[9.5px] uppercase tracking-[0.14em] text-ink-400 mt-1.5">{{ __('Auto-sent') }}</div>
                                        </div>
                                    </div>
                                @endif

                                {{-- ── connected accounts — click one to filter the rail ── --}}
                                <div class="mt-6">
                                    <div class="flex items-center gap-2.5 mb-2.5">
                                        <span class="mono text-[9.5px] uppercase tracking-[0.16em] text-ink-400 shrink-0">{{ __('Connected accounts') }}</span>
                                        <span class="h-px flex-1 bg-paper-200"></span>
                                        <a href="{{ route('instagram.connect') }}" class="mono text-[9.5px] uppercase tracking-[0.12em] ig-text hover:opacity-70 transition shrink-0">{{ __('Add') }}</a>
                                    </div>
                                    <div class="grid sm:grid-cols-2 gap-2.5">
                                        @foreach ($accounts as $i => $acc)
                                            @php
                                                $n        = ($byAccount[$acc->id] ?? collect())->count();
                                                $isOn     = ($acc->status ?? '') === 'connected';
                                                $isActive = (int) ($selectedAccountId ?? 0) === (int) $acc->id;
                                                $handle   = $acc->username ? '@'.$acc->username : ($acc->name ?: __('Instagram account'));
                                            @endphp
                                            <a href="{{ url('/instagram/inbox?' . http_build_query(array_filter(['account' => $acc->id, 'status' => $status ?: null]))) }}"
                                               class="group hairline rounded-2xl bg-paper-0 p-3 flex items-center gap-3 transition hover:shadow-soft {{ $isActive ? 'border-ig-pink/50' : 'hover:border-ig-pink/30' }}">
                                                <span class="ig-ring shrink-0">
                                                    @if ($acc->profile_pic_url)
                                                        <img src="{{ url('ig-avatar/'.$acc->id.'/self') }}" alt="" class="block w-11 h-11 rounded-full object-cover" loading="lazy"
                                                             onerror="this.style.display='none';this.nextElementSibling?.classList.remove('hidden')">
                                                        <span class="hidden block w-11 h-11 rounded-full bg-gradient-to-br {{ $grads[$i % count($grads)] }} text-white text-[12px] font-semibold grid place-items-center">{{ $initials($handle) }}</span>
                                                    @else
                                                        <span class="block w-11 h-11 rounded-full bg-gradient-to-br {{ $grads[$i % count($grads)] }} text-white text-[12px] font-semibold grid place-items-center">{{ $initials($handle) }}</span>
                                                    @endif
                                                </span>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <span class="text-[13px] font-semibold truncate">{{ $handle }}</span>
                                                        <span title="{{ $isOn ? __('Connected') : __('Needs attention') }}" class="w-1.5 h-1.5 rounded-full shrink-0 {{ $isOn ? 'bg-wa-green' : 'bg-accent-coral' }}"></span>
                                                    </div>
                                                    <div class="mono text-[10px] text-ink-400 mt-0.5 truncate">
                                                        {{ $n === 1 ? __(':n chat', ['n' => 1]) : __(':n chats', ['n' => $compact($n)]) }}@if ($acc->followers_count) · {{ $compact($acc->followers_count) }} {{ __('followers') }}@endif
                                                    </div>
                                                </div>
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-300 shrink-0 opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M6 4l4 4-4 4"/></svg>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- ── the jobs you open this page to do ── --}}
                                <div class="mt-6">
                                    <div class="flex items-center gap-2.5 mb-2.5">
                                        <span class="mono text-[9.5px] uppercase tracking-[0.16em] text-ink-400 shrink-0">{{ __('Quick actions') }}</span>
                                        <span class="h-px flex-1 bg-paper-200"></span>
                                    </div>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                        @php
                                            $tiles = [
                                                ['url' => url('/instagram/broadcast/create'), 'label' => __('Bulk DM'),      'path' => '<path d="M14 2 2 7.3l4.2 1.5L7.7 13 14 2z"/><path d="M14 2 6.2 8.8"/>'],
                                                ['url' => route('instagram.automations'),    'label' => __('Automations'), 'path' => '<path d="M8.6 2 4 8.6h3.1L6.4 14 11.6 7H8.3z"/>'],
                                                ['url' => url('/instagram/templates'),       'label' => __('Templates'),   'path' => '<path d="M2.5 3.5h11v9h-11z"/><path d="M2.5 6.5h11"/>'],
                                                ['url' => url('/instagram/inbox/sync'),      'label' => __('Sync now'),    'path' => '<path d="M13.5 8a5.5 5.5 0 1 1-1.6-3.9"/><path d="M13.5 2.5V6H10"/>'],
                                            ];
                                        @endphp
                                        @foreach ($tiles as $t)
                                            {{-- The gradient is a fading LAYER, not a group-hover: variant —
                                                 .ig-grad-soft is plain CSS (app.css), so Tailwind can't build a
                                                 variant for it. Icons use text-ig-pink, never .ig-text: that class
                                                 sets color:transparent for background-clip:text, which renders a
                                                 stroke="currentColor" SVG completely invisible. --}}
                                            <a href="{{ $t['url'] }}" class="group hairline rounded-2xl bg-paper-0 px-3 py-3.5 grid place-items-center gap-2 text-center transition hover:border-ig-pink/30 hover:shadow-soft">
                                                <span class="relative w-9 h-9 rounded-xl bg-paper-50 grid place-items-center overflow-hidden">
                                                    <span class="absolute inset-0 ig-grad-soft opacity-0 group-hover:opacity-100 transition"></span>
                                                    <svg viewBox="0 0 16 16" class="relative w-4 h-4 text-ig-pink group-hover:text-white transition" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $t['path'] !!}</svg>
                                                </span>
                                                <span class="text-[11.5px] font-medium">{{ $t['label'] }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>

                                @if ($latestAt)
                                    <p class="mono text-[10px] text-ink-400 text-center mt-5">{{ __('Last message') }} {{ $latestAt->diffForHumans() }}</p>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </main>

        {{-- ===== DETAIL PANEL ===== --}}
        @if ($openIgsid && $messages->isNotEmpty())
            @php
                $openName = $threadName($openAccountId, $openIgsid);
                $inCount  = $messages->where('direction', 'in')->count();
                $outCount = $messages->where('direction', 'out')->count();
                $autoMsg  = $messages->first(fn ($m) => $m->direction === 'out' && $m->source && $m->source !== 'manual');
                $firstAt  = $messages->first()?->created_at;
            @endphp
            <aside id="ig-detail-panel" class="w-[300px] shrink-0 hairline-l bg-white overflow-y-auto scrollbar min-h-0">
                <div class="p-5 hairline-b text-center">
                    <span class="ig-ring inline-block"><span class="block w-16 h-16 rounded-full bg-gradient-to-br from-ig-pink to-ig-orange text-white text-[20px] font-semibold grid place-items-center">{{ $initials($openName) }}</span></span>
                    <div class="text-[15px] font-semibold mt-2 truncate px-2">{{ $openName }}</div>
                    <div class="text-[11px] text-ink-500">{{ __('Instagram Direct') }}</div>
                    <div class="mt-3 flex items-center justify-center gap-4">
                        <div><div class="text-[14px] font-semibold tabular">{{ $inCount }}</div><div class="text-[9px] text-ink-500 mono uppercase">{{ __('received') }}</div></div>
                        <div class="hairline-l h-7"></div>
                        <div><div class="text-[14px] font-semibold tabular">{{ $outCount }}</div><div class="text-[9px] text-ink-500 mono uppercase">{{ __('sent') }}</div></div>
                        @if ($firstAt)
                            <div class="hairline-l h-7"></div>
                            <div><div class="text-[14px] font-semibold tabular">{{ $firstAt->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true, 1) }}</div><div class="text-[9px] text-ink-500 mono uppercase">{{ __('since') }}</div></div>
                        @endif
                    </div>
                </div>

                <div class="p-5 hairline-b">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('Account') }}</div>
                    <div class="space-y-2.5 text-[12px]">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-ink-600">{{ __('Connected as') }}</span>
                            <span class="font-medium truncate">{{ $openAcc && $openAcc->username ? '@'.$openAcc->username : '—' }}</span>
                        </div>
                        @if ($openAcc && $openAcc->followers_count)
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-ink-600">{{ __('Followers') }}</span>
                                <span class="font-medium tabular">{{ number_format($openAcc->followers_count) }}</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-ink-600">{{ __('Status') }}</span>
                            <span class="pill {{ $openAcc && $openAcc->status === 'connected' ? 'bg-wa-green/10 text-wa-deep' : 'bg-paper-100 text-ink-500' }}">{{ ucfirst($openAcc->status ?? 'unknown') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Lead / Deal — capture this person while chatting (WaDesk-style). --}}
                <div class="p-5 hairline-b">
                    <div class="flex items-center justify-between mb-3">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Lead / Deal') }}</div>
                        @if ($openLead && $openLead->deal_id)
                            <a href="{{ url('/deals/' . $openLead->deal_id) }}" class="text-[11px] text-ig-purple underline">{{ __('Deal') }} #{{ $openLead->deal_id }}</a>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('instagram.inbox.capture-lead') }}" class="space-y-2">
                        @csrf
                        <input type="hidden" name="instagram_account_id" value="{{ $openAccountId }}">
                        <input type="hidden" name="igsid" value="{{ $openIgsid }}">
                        <input type="text"  name="full_name" value="{{ old('full_name', $openLead->full_name ?? $openName) }}" placeholder="{{ __('Full name') }}" class="field w-full text-[12.5px] py-2">
                        <input type="email" name="email"     value="{{ old('email', $openLead->email ?? '') }}" placeholder="{{ __('Email') }}" class="field w-full text-[12.5px] py-2">
                        <input type="text"  name="phone"     value="{{ old('phone', $openLead->phone ?? '') }}" placeholder="{{ __('Phone') }}" class="field w-full text-[12.5px] py-2">
                        <textarea name="notes" rows="2" placeholder="{{ __('Notes') }}" class="field w-full text-[12.5px] py-2 resize-none">{{ old('notes', $openLead->notes ?? '') }}</textarea>
                        <label class="flex items-center gap-2 text-[11.5px] text-ink-600"><input type="checkbox" name="create_deal" value="1" checked> {{ __('Add to Sales Pipeline') }}</label>
                        <button class="w-full px-3 py-2 rounded-lg ig-grad-soft text-white text-[12px] font-semibold">{{ $openLead ? __('Update lead') : __('Save lead & create deal') }}</button>
                    </form>
                    @if ($openLead)
                        <div class="mt-2 text-[10.5px] text-ink-500">{{ __('Captured') }} {{ optional($openLead->created_at)->diffForHumans() }} · {{ ucfirst($openLead->status ?? 'new') }}</div>
                    @endif
                </div>

                @if ($autoMsg)
                    <div class="p-5 hairline-b">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('Last automation') }}</div>
                        <div class="hairline rounded-xl p-3 bg-paper-50">
                            <div class="flex items-center gap-2 text-[11px] font-medium"><span class="pill ig-grad-soft text-white !py-0.5">{{ $autoMsg->source }}</span></div>
                            <div class="text-[11px] text-ink-600 mt-1.5 line-clamp-2">{{ \Illuminate\Support\Str::limit((string) $autoMsg->body, 80) }}</div>
                            <div class="mono text-[9px] text-ink-400 mt-1">{{ optional($autoMsg->created_at)->format('M j · H:i') }}</div>
                        </div>
                    </div>
                @endif

                <div class="p-5">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('Quick actions') }}</div>
                    <div class="space-y-2">
                        <a href="{{ url('/instagram/automations') }}" class="flex items-center gap-2.5 hairline rounded-xl px-3 py-2.5 hover:bg-paper-50 text-[12.5px] font-medium">
                            <span class="w-7 h-7 rounded-lg bg-ig-pink/10 text-ig-pink grid place-items-center"><svg viewBox="0 0 14 14" class="w-3.5 h-3.5" fill="currentColor"><path d="M7 1l1.5 3.5L12 5l-2.5 2.5.5 3.5L7 9.5 4 11l.5-3.5L2 5l3.5-.5z"/></svg></span>
                            {{ __('Manage automations') }}
                        </a>
                        <a href="{{ url('/instagram/broadcast') }}" class="flex items-center gap-2.5 hairline rounded-xl px-3 py-2.5 hover:bg-paper-50 text-[12.5px] font-medium">
                            <span class="w-7 h-7 rounded-lg bg-ig-purple/10 text-ig-purple grid place-items-center"><svg viewBox="0 0 14 14" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 3.5h8l-1 1.5 1 1.5H2zM2 8.5h6l1 1.5-1 1.5H2z"/></svg></span>
                            {{ __('Send a bulk DM') }}
                        </a>
                    </div>
                </div>
            </aside>
        @endif
    </div>

    {{-- Scroll-up "load older messages" for the open thread. --}}
    @push('scripts')
        <script src="{{ asset('assets/ig-inbox-older.js') }}?v={{ @filemtime(public_path('assets/ig-inbox-older.js')) ?: 1 }}" defer></script>
        <script src="{{ asset('assets/ig-inbox-window.js') }}?v={{ @filemtime(public_path('assets/ig-inbox-window.js')) ?: 1 }}" defer></script>
    @endpush
</x-layouts.instagram>

<x-layouts.instagram :title="__('Notifications')" ig-active="notifications" page="instagram-notifications">
    @php
        // All KPI + count values are computed server-side (see
        // InstagramController::notifications) so they reflect the WHOLE dataset,
        // not just the visible page. $items is a paginator.
        $accFilter = $accFilter ?? 0;
    @endphp

    <div class="max-w-[1500px] mx-auto px-6 py-6">

        {{-- HEADER --}}
        <section class="pb-4">
            <div class="flex items-center gap-3 mb-1.5 text-[11px] mono uppercase tracking-[0.18em] text-ink-500"><span>{{ __('Activity') }}</span><span class="w-1 h-1 rounded-full bg-ink-400"></span><span>{{ __('Notifications') }}</span></div>
            <h1 class="serif text-[40px] leading-none">{{ __('Recent') }} <span class="ig-text italic">{{ __('activity') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Every direct message people send your connected Instagram accounts lands here. Tap any item to jump straight into the inbox thread.') }}</p>
        </section>

        <x-admin.flash />

        {{-- ===== KPI STRIP ===== --}}
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white hairline rounded-2xl p-5">
                <div class="flex items-center justify-between"><span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Recent DMs') }}</span><span class="pill bg-paper-100 text-ink-600">{{ __('inbound') }}</span></div>
                <div class="serif text-[42px] leading-none mt-2 tabular">{{ number_format($total) }}</div>
                <div class="text-[11px] text-ink-500 mt-3 mono">{{ $accFilter ? __('for this account') : __('across all accounts') }}</div>
            </div>
            <div class="bg-white hairline rounded-2xl p-5">
                <div class="flex items-center justify-between"><span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Today') }}</span><span class="pill bg-paper-100 text-ink-600">{{ now()->format('d M') }}</span></div>
                <div class="serif text-[42px] leading-none mt-2 tabular {{ $todayCount > 0 ? 'ig-text' : '' }}">{{ number_format($todayCount) }}</div>
                <div class="text-[11px] text-ink-500 mt-3 mono">{{ __('messages received today') }}</div>
            </div>
            <div class="bg-white hairline rounded-2xl p-5">
                <div class="flex items-center justify-between"><span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Distinct senders') }}</span><span class="pill bg-paper-100 text-ink-600">{{ __('people') }}</span></div>
                <div class="serif text-[42px] leading-none mt-2 tabular">{{ number_format($distinctSenders) }}</div>
                <div class="text-[11px] text-ink-500 mt-3 mono">{{ __('unique conversations') }}</div>
            </div>
            <div class="bg-white hairline rounded-2xl p-5">
                <div class="flex items-center justify-between"><span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Connected') }}</span><span class="pill bg-paper-100 text-ink-600">{{ __('accounts') }}</span></div>
                <div class="serif text-[42px] leading-none mt-2 tabular">{{ number_format($accounts->count()) }}</div>
                <div class="text-[11px] text-ink-500 mt-3 mono">{{ $latestAt ? __('last') . ' ' . $latestAt->diffForHumans() : __('awaiting first DM') }}</div>
            </div>
        </section>

        @if ($grandTotal === 0)
            <div class="bg-white hairline rounded-2xl p-12 text-center">
                <span class="ig-grad w-12 h-12 rounded-2xl grid place-items-center text-white mx-auto mb-3"><svg viewBox="0 0 20 20" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 8a6 6 0 0112 0v3l1.5 3h-15L4 11z"/><path d="M7.5 14a2.5 2.5 0 005 0"/></svg></span>
                <div class="serif text-[22px]">{{ __('Nothing yet') }}</div>
                <p class="text-[13px] text-ink-600 mt-1">{{ __('New DMs and replies will appear here as soon as they arrive.') }}</p>
            </div>
        @else
            {{-- ===== FEED + SIDE ===== --}}
            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">

                {{-- FEED --}}
                <div class="bg-white hairline rounded-2xl overflow-hidden">
                    {{-- account segment bar (only when more than one account) --}}
                    @if ($accounts->count() > 1)
                        <div class="px-4 py-3 hairline-b flex items-center gap-2 flex-wrap">
                            <a href="{{ url('/instagram/notifications') }}" class="pill {{ !$accFilter ? 'ig-grad-soft text-white' : 'bg-paper-100 text-ink-600 hover:bg-paper-200' }}">{{ __('All') }} <span class="mono ml-1 opacity-80">{{ $grandTotal }}</span></a>
                            @foreach ($accounts as $a)
                                @php $c = $perAccount[$a->id] ?? 0; @endphp
                                <a href="{{ url('/instagram/notifications?account=' . $a->id) }}" class="pill {{ (int) $accFilter === $a->id ? 'ig-grad-soft text-white' : 'bg-paper-100 text-ink-600 hover:bg-paper-200' }}">{{ '@' . ($a->username ?: $a->ig_user_id) }} <span class="mono ml-1 opacity-80">{{ $c }}</span></a>
                            @endforeach
                        </div>
                    @endif

                    @forelse ($items as $m)
                        @php
                            $acc = $acctById[$m->instagram_account_id] ?? null;
                            $thread = $m->instagram_account_id . ':' . $m->igsid;
                            $senderName = (string) ($nameBy[$thread] ?? '');
                            $senderLabel = $senderName !== '' ? '@' . $senderName : '@' . \Illuminate\Support\Str::limit((string) $m->igsid, 16, '');
                            $ini = strtoupper(substr($senderName !== '' ? $senderName : (string) $m->igsid, 0, 2));
                            $isToday = $m->created_at && $m->created_at->isToday();
                        @endphp
                        <a href="{{ url('/instagram/inbox?thread=' . urlencode($thread)) }}" class="flex items-center gap-3 px-4 py-3.5 hairline-b hover:bg-paper-50 transition">
                            <span class="block w-10 h-10 rounded-full ig-grad-soft text-white text-[12px] font-semibold grid place-items-center shrink-0">{{ $ini }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="text-[13px] text-ink-800">
                                    <span class="font-semibold">{{ $senderLabel }}</span> <span class="text-ink-600">{{ __('sent a message') }}</span>
                                    @if ($acc)<span class="text-ink-400">· {{ '@' . ($acc->username ?: $acc->ig_user_id) }}</span>@endif
                                </div>
                                <div class="text-[12.5px] text-ink-500 truncate mt-0.5">
                                    @if (trim((string) $m->body) !== ''){{ \Illuminate\Support\Str::limit($m->body, 90) }}@elseif ($m->attachment_type)<span class="italic">{{ ucfirst(str_replace('_', ' ', $m->attachment_type)) }}</span>@else<span class="text-ink-400">—</span>@endif
                                </div>
                            </div>
                            @if ($isToday)<span class="pill ig-grad-soft text-white shrink-0">{{ __('new') }}</span>@endif
                            <span class="mono text-[10.5px] text-ink-400 shrink-0">{{ $m->created_at?->diffForHumans() }}</span>
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-300 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 4l4 4-4 4"/></svg>
                        </a>
                    @empty
                        <div class="p-10 text-center text-[12.5px] text-ink-500 italic">{{ __('No activity for this account yet.') }}</div>
                    @endforelse

                    {{-- ===== PAGER ===== theme-matched: count summary + prev / numbered / next --}}
                    <div class="px-4 py-3 hairline-t flex items-center justify-between gap-3 flex-wrap text-[12px] text-ink-500">
                        <span>
                            {{ __('Showing') }}
                            <span class="mono text-ink-800">{{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }}</span>
                            {{ __('of') }} <span class="mono text-ink-800">{{ number_format($items->total()) }}</span>
                        </span>
                        @if ($items->hasPages())
                            <nav class="flex items-center gap-1">
                                @if ($items->onFirstPage())
                                    <span class="w-8 h-8 grid place-items-center rounded-lg bg-paper-100 text-ink-300 cursor-default"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M10 4L6 8l4 4"/></svg></span>
                                @else
                                    <a href="{{ $items->previousPageUrl() }}" class="w-8 h-8 grid place-items-center rounded-lg bg-paper-100 text-ink-700 hover:bg-paper-200 transition"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M10 4L6 8l4 4"/></svg></a>
                                @endif

                                @php
                                    $cur = $items->currentPage();
                                    $last = $items->lastPage();
                                    $start = max(1, $cur - 2);
                                    $end = min($last, $cur + 2);
                                @endphp
                                @if ($start > 1)
                                    <a href="{{ $items->url(1) }}" class="min-w-8 h-8 px-2 grid place-items-center rounded-lg bg-paper-100 text-ink-700 hover:bg-paper-200 text-[12px] transition">1</a>
                                    @if ($start > 2)<span class="px-1 text-ink-400">…</span>@endif
                                @endif
                                @foreach ($items->getUrlRange($start, $end) as $page => $url)
                                    @if ($page == $cur)
                                        <span class="min-w-8 h-8 px-2 grid place-items-center rounded-lg ig-grad-soft text-white text-[12px] font-semibold">{{ $page }}</span>
                                    @else
                                        <a href="{{ $url }}" class="min-w-8 h-8 px-2 grid place-items-center rounded-lg bg-paper-100 text-ink-700 hover:bg-paper-200 text-[12px] transition">{{ $page }}</a>
                                    @endif
                                @endforeach
                                @if ($end < $last)
                                    @if ($end < $last - 1)<span class="px-1 text-ink-400">…</span>@endif
                                    <a href="{{ $items->url($last) }}" class="min-w-8 h-8 px-2 grid place-items-center rounded-lg bg-paper-100 text-ink-700 hover:bg-paper-200 text-[12px] transition">{{ $last }}</a>
                                @endif

                                @if ($items->hasMorePages())
                                    <a href="{{ $items->nextPageUrl() }}" class="w-8 h-8 grid place-items-center rounded-lg bg-paper-100 text-ink-700 hover:bg-paper-200 transition"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 4l4 4-4 4"/></svg></a>
                                @else
                                    <span class="w-8 h-8 grid place-items-center rounded-lg bg-paper-100 text-ink-300 cursor-default"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 4l4 4-4 4"/></svg></span>
                                @endif
                            </nav>
                        @endif
                        <a href="{{ url('/instagram/inbox') }}" class="text-[11.5px] text-ig-pink font-semibold">{{ __('Open inbox →') }}</a>
                    </div>
                </div>

                {{-- SIDE --}}
                <aside class="space-y-4">
                    <div class="ig-grad rounded-2xl p-5 text-white">
                        <div class="mono text-[10px] uppercase tracking-widest text-white/70">{{ __('Heads up') }}</div>
                        <div class="serif text-[22px] leading-tight mt-1">{{ __('Auto-captured') }}</div>
                        <p class="mt-2 text-[12px] text-white/85 leading-relaxed">{{ __('Inbound DMs flow in automatically as soon as Instagram delivers the webhook. Tap a row to reply in the unified inbox.') }}</p>
                    </div>
                    <div class="bg-white hairline rounded-2xl p-5">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('By account') }}</div>
                        <div class="space-y-2.5">
                            @foreach ($accounts as $a)
                                @php $c = $perAccount[$a->id] ?? 0; @endphp
                                <div class="flex items-center justify-between text-[12px]">
                                    <span class="text-ink-700 truncate">{{ '@' . ($a->username ?: $a->ig_user_id) }}</span>
                                    <span class="mono text-ink-900 shrink-0 ml-2">{{ number_format($c) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <p class="text-[11.5px] text-ink-400 px-1">{{ __('Tip: comment & mention activity shows on the') }} <a href="{{ url('/instagram/comments') }}" class="text-ig-pink">{{ __('Moderate') }}</a> {{ __('page.') }}</p>
                </aside>

            </section>
        @endif
    </div>
</x-layouts.instagram>

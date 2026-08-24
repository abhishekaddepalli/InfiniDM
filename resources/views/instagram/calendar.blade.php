<x-layouts.instagram :title="__('Calendar')" ig-active="calendar" page="instagram-calendar">
    @php
        $acctById = $accounts->keyBy('id');
        $byDay = $posts->groupBy(fn ($p) => optional($p->scheduled_at)->format('Y-m-d'));

        // Build the Sun→Sat grid cells.
        $cells = [];
        $d = $gridStart->copy();
        while ($d <= $gridEnd) { $cells[] = $d->copy(); $d->addDay(); }
        $weeks = array_chunk($cells, 7);

        $month = $anchor->copy()->startOfMonth(); // month-view reference
        $todayStr = now()->format('Y-m-d');

        // The zone the reschedule UIs interpret wall-clock times in. Used by BOTH
        // the inline "Upcoming" form and the manage-drawer reschedule, so they can
        // never disagree and silently shift a post's fire time on re-save.
        $schedTz = optional(auth()->user()?->currentWorkspace)->timezone
            ?: (setting('default_timezone') ?: (config('app.timezone') ?: 'UTC'));

        // View-aware navigation + title. Prev/Next/Today move by the active unit.
        $base   = url('/instagram/calendar');
        $accParam = ($currentAccount ?? null) ? '&account=' . $currentAccount->id : '';
        $navUrl = fn ($v, $dt) => $base . '?view=' . $v . '&date=' . $dt->format('Y-m-d') . $accParam;
        if ($view === 'week') {
            $prevUrl = $navUrl('week', $anchor->copy()->subWeek());
            $nextUrl = $navUrl('week', $anchor->copy()->addWeek());
            $title   = $gridStart->isoFormat('MMM D') . ' – ' . $gridEnd->isoFormat('MMM D, YYYY');
        } elseif ($view === 'day') {
            $prevUrl = $navUrl('day', $anchor->copy()->subDay());
            $nextUrl = $navUrl('day', $anchor->copy()->addDay());
            $title   = $anchor->isoFormat('dddd, MMM D');
        } else {
            $prevUrl = $navUrl('month', $month->copy()->subMonth());
            $nextUrl = $navUrl('month', $month->copy()->addMonth());
            $title   = $month->isoFormat('MMMM') . ' ' . $month->format('Y');
        }
        $todayUrl = $navUrl($view, now());
        $viewUrl  = fn ($v) => $navUrl($v, $anchor);

        // Per media-type chip styling. Literal class strings so Tailwind's
        // build-time scanner picks them up (dynamic bg-{$x}/10 would be missed).
        $typeStyle = [
            'image'    => ['chip' => 'bg-ig-pink/10 text-ig-pink',     'g' => 'from-ig-pink to-ig-orange'],
            'carousel' => ['chip' => 'bg-ig-pink/10 text-ig-pink',     'g' => 'from-ig-purple to-ig-magenta'],
            'story'    => ['chip' => 'bg-ig-purple/10 text-ig-purple', 'g' => 'from-ig-purple to-ig-magenta'],
            'reels'    => ['chip' => 'bg-ig-orange/10 text-ig-orange', 'g' => 'from-ig-amber to-ig-red'],
        ];

        // Data attributes so a chip click can open the manage drawer (send now /
        // reschedule / delete) pre-filled with that post.
        $chipData = fn ($p) => 'data-post-id="' . $p->id . '"'
            . ' data-post-caption="' . e($p->caption ?: ucfirst($p->media_type)) . '"'
            . ' data-post-type="' . e(ucfirst($p->media_type)) . '"'
            . ' data-post-status="' . e($p->status) . '"'
            . ' data-post-when="' . ($p->scheduled_at ? $p->scheduled_at->copy()->setTimezone($schedTz)->format('Y-m-d\TH:i') : '') . '"'
            . ' data-post-when-label="' . e($p->scheduled_at ? $p->scheduled_at->copy()->setTimezone($schedTz)->isoFormat('ddd, MMM D · h:mm A') : '') . '"'
            . ' data-post-img="' . e($p->image_url ?: '') . '"';

        // Real counts for the filter strip + summary.
        $cnt = ['image' => 0, 'story' => 0, 'reels' => 0, 'carousel' => 0];
        foreach ($posts as $p) { $cnt[$p->media_type] = ($cnt[$p->media_type] ?? 0) + 1; }
        $pending   = $posts->where('status', 'pending')->count();
        $published = $posts->where('status', 'published')->count();
        $failed    = $posts->where('status', 'failed')->count();
    @endphp

    <div class="max-w-[1500px] mx-auto px-6 py-6">

        {{-- ===== HEADER ===== --}}
        <section class="flex items-center justify-between pb-4 gap-4 flex-wrap">
            <div>
                <div class="flex items-center gap-3 mb-1.5 text-[11px] mono uppercase tracking-[0.18em] text-ink-500"><span>{{ __('Studio') }}</span><span class="w-1 h-1 rounded-full bg-ink-400"></span><span>{{ __('Content calendar') }}</span></div>
                <h1 class="serif text-[40px] leading-none">{{ $title }}</h1>
            </div>
            {{-- stats — centered in the same row --}}
            <div class="flex-1 flex items-center justify-center gap-3 flex-wrap">
                @foreach ([['Scheduled', $pending, ''], ['Published', $published, 'ig-text'], ['Failed', $failed, $failed ? 'text-accent-coral' : ''], ['This month', $posts->count(), '']] as [$lbl, $num, $cls])
                    <div class="bg-white hairline rounded-2xl px-6 py-4 flex items-baseline gap-3">
                        <span class="serif text-[38px] leading-none tabular {{ $cls }}">{{ $num }}</span>
                        <span class="mono text-[11px] uppercase tracking-widest text-ink-500">{{ __($lbl) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                {{-- view switcher --}}
                <div class="flex items-center gap-1 hairline rounded-full p-1 text-[11px] bg-white">
                    @foreach (['month' => __('Month'), 'week' => __('Week'), 'day' => __('Day')] as $v => $lbl)
                        <a href="{{ $viewUrl($v) }}" class="px-3 py-1 rounded-full {{ $view === $v ? 'bg-ink-900 text-white' : 'text-ink-600 hover:bg-paper-50' }}">{{ $lbl }}</a>
                    @endforeach
                </div>
                {{-- prev / today / next (moves by the active view's unit) --}}
                <a href="{{ $prevUrl }}" class="w-9 h-9 rounded-full hairline bg-white grid place-items-center hover:bg-paper-50" title="{{ __('Previous') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 4L6 8l4 4"/></svg></a>
                <a href="{{ $todayUrl }}" class="px-3 h-9 rounded-full hairline bg-white grid place-items-center text-[12px] font-medium hover:bg-paper-50">{{ __('Today') }}</a>
                <a href="{{ $nextUrl }}" class="w-9 h-9 rounded-full hairline bg-white grid place-items-center hover:bg-paper-50" title="{{ __('Next') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 4l4 4-4 4"/></svg></a>
                <a href="{{ url('/instagram/composer') }}" class="px-4 h-9 rounded-full ig-grad-soft text-white text-[12px] font-semibold grid place-items-center hover:opacity-90">{{ __('Compose') }}</a>
            </div>
        </section>

        <x-admin.flash />

        {{-- ===== FILTER STRIP ===== --}}
        <section class="flex items-center gap-3 pb-4">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="pill bg-white hairline text-ink-700"><span class="w-2 h-2 rounded-sm bg-ig-pink"></span>{{ __('Posts') }} · {{ $cnt['image'] + $cnt['carousel'] }}</span>
                <span class="pill bg-white hairline text-ink-700"><span class="w-2 h-2 rounded-sm bg-ig-purple"></span>{{ __('Stories') }} · {{ $cnt['story'] }}</span>
                <span class="pill bg-white hairline text-ink-700"><span class="w-2 h-2 rounded-sm bg-ig-orange"></span>{{ __('Reels') }} · {{ $cnt['reels'] }}</span>
            </div>
            <div class="flex-1"></div>
            @if ($accounts->count())
                @php
                    $sel = $currentAccount ?? null;
                    $qs  = fn ($aid) => url()->current() . '?view=' . $view . '&date=' . $anchor->format('Y-m-d') . ($aid ? '&account=' . $aid : '');
                    $avatar = function ($a) {
                        $u = $a->username ?: ($a->ig_user_id ?? 'IG');
                        $init = strtoupper(substr($u, 0, 2));
                        $img = $a->profile_pic_url ? '<img src="' . e($a->profile_pic_url) . '" alt="" loading="lazy" referrerpolicy="no-referrer" class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">' : '';
                        return '<span class="relative block w-6 h-6 rounded-full ig-grad-soft text-white text-[9px] font-semibold grid place-items-center overflow-hidden shrink-0">' . $init . $img . '</span>';
                    };
                @endphp
                <details class="relative">
                    <summary style="list-style:none" class="cursor-pointer select-none flex items-center gap-2 hairline rounded-full pl-1 pr-2 py-1 bg-white hover:border-wa-deep">
                        @if ($sel)
                            {!! $avatar($sel) !!}
                            <span class="text-[12px] font-semibold">{{ '@' . ($sel->username ?: $sel->ig_user_id) }}</span>
                        @else
                            <span class="w-6 h-6 rounded-full ig-grad-soft text-white text-[9px] font-semibold grid place-items-center shrink-0">IG</span>
                            <span class="text-[12px] font-semibold">{{ __('All accounts') }}</span>
                        @endif
                        <svg viewBox="0 0 16 16" class="w-3 h-3 text-ink-500" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
                    </summary>
                    <div class="absolute right-0 mt-2 w-56 bg-white hairline rounded-xl shadow-card py-1 z-30">
                        <a href="{{ $qs(0) }}" class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] hover:bg-paper-50 {{ ! $sel ? 'text-wa-deep font-semibold' : 'text-ink-700' }}">
                            <span class="w-6 h-6 rounded-full ig-grad-soft text-white text-[9px] font-semibold grid place-items-center shrink-0">IG</span>{{ __('All accounts') }}
                        </a>
                        @foreach ($accounts as $a)
                            <a href="{{ $qs($a->id) }}" class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] hover:bg-paper-50 {{ $sel && $sel->id === $a->id ? 'text-wa-deep font-semibold' : 'text-ink-700' }}">
                                {!! $avatar($a) !!}<span class="truncate">{{ '@' . ($a->username ?: $a->ig_user_id) }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endif
        </section>

        {{-- ===== CALENDAR GRID ===== --}}
        @if ($view === 'month')
        <section class="bg-white hairline rounded-2xl overflow-hidden">
            {{-- weekday header --}}
            <div class="grid grid-cols-7 hairline-b">
                @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $i => $wd)
                    <div class="px-3 py-2.5 mono text-[10px] uppercase tracking-widest text-ink-500 {{ $i ? 'hairline-l' : '' }}">{{ __($wd) }}</div>
                @endforeach
            </div>

            @foreach ($weeks as $week)
                <div class="grid grid-cols-7 {{ !$loop->last ? 'hairline-b' : '' }}">
                    @foreach ($week as $i => $cell)
                        @php
                            $key = $cell->format('Y-m-d');
                            $inMonth = $cell->month === $month->month;
                            $dayPosts = $byDay[$key] ?? collect();
                            $isToday = $key === $todayStr;
                        @endphp
                        <div class="cal-cell p-2 relative cursor-pointer {{ $i ? 'hairline-l' : '' }} {{ $isToday ? 'bg-ig-pink/5' : '' }}"
                             data-cal-day="{{ $key }}" data-cal-label="{{ $cell->isoFormat('ddd, MMM D') }}">
                            <div class="flex items-center justify-between">
                                <span class="text-[12px] mono {{ $inMonth ? ($isToday ? 'font-bold ig-text' : 'text-ink-600') : 'text-ink-300' }}">{{ $cell->day }}</span>
                                <button type="button" data-cal-add class="add-btn w-5 h-5 rounded-full hover:bg-paper-100 grid place-items-center text-ink-400" title="{{ __('Schedule a post') }}">
                                    <svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2.5v7M2.5 6h7"/></svg>
                                </button>
                            </div>
                            @if ($dayPosts->count())
                                <div class="mt-1.5 space-y-1">
                                    @foreach ($dayPosts as $p)
                                        @php $ts = $typeStyle[$p->media_type] ?? $typeStyle['image']; @endphp
                                        <div {!! $chipData($p) !!} class="post-chip cursor-pointer {{ $ts['chip'] }} {{ $p->status === 'failed' ? 'ring-1 ring-accent-coral/40' : '' }}" title="{{ ($p->caption ?: ucfirst($p->media_type)).' · '.optional($p->scheduled_at)->format('g:i A') }}">
                                            @if ($p->image_url)
                                                <span class="w-5 h-5 rounded bg-center bg-cover shrink-0" style="background-image:url('{{ $p->image_url }}')"></span>
                                            @else
                                                <span class="w-5 h-5 rounded bg-gradient-to-br {{ $ts['g'] }} shrink-0"></span>
                                            @endif
                                            <span class="truncate">{{ $p->caption ?: ucfirst($p->media_type) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </section>

        @elseif ($view === 'week')
        {{-- WEEK VIEW: 7 tall day columns --}}
        <section class="bg-white hairline rounded-2xl overflow-hidden">
            <div class="grid grid-cols-7">
                @foreach ($cells as $i => $cell)
                    @php
                        $key = $cell->format('Y-m-d');
                        $dayPosts = ($byDay[$key] ?? collect())->sortBy('scheduled_at');
                        $isToday = $key === $todayStr;
                    @endphp
                    <div class="relative cursor-pointer flex flex-col min-h-[440px] {{ $i ? 'hairline-l' : '' }} {{ $isToday ? 'bg-ig-pink/5' : '' }}"
                         data-cal-day="{{ $key }}" data-cal-label="{{ $cell->isoFormat('ddd, MMM D') }}">
                        <div class="px-2.5 py-2 hairline-b flex items-center justify-between">
                            <div>
                                <div class="mono text-[9px] uppercase tracking-widest text-ink-400">{{ $cell->isoFormat('ddd') }}</div>
                                <div class="text-[15px] leading-none mt-0.5 {{ $isToday ? 'font-bold ig-text' : 'text-ink-700' }}">{{ $cell->day }}</div>
                            </div>
                            <button type="button" data-cal-add class="w-5 h-5 rounded-full hover:bg-paper-100 grid place-items-center text-ink-400" title="{{ __('Schedule a post') }}"><svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2.5v7M2.5 6h7"/></svg></button>
                        </div>
                        <div class="p-2 space-y-1.5 flex-1">
                            @forelse ($dayPosts as $p)
                                @php $ts = $typeStyle[$p->media_type] ?? $typeStyle['image']; @endphp
                                <div {!! $chipData($p) !!} class="post-chip cursor-pointer {{ $ts['chip'] }} {{ $p->status === 'failed' ? 'ring-1 ring-accent-coral/40' : '' }} !items-start" title="{{ ($p->caption ?: ucfirst($p->media_type)).' · '.optional($p->scheduled_at)->format('g:i A') }}">
                                    @if ($p->image_url)
                                        <span class="w-6 h-6 rounded bg-center bg-cover shrink-0" style="background-image:url('{{ $p->image_url }}')"></span>
                                    @else
                                        <span class="w-6 h-6 rounded bg-gradient-to-br {{ $ts['g'] }} shrink-0"></span>
                                    @endif
                                    <span class="min-w-0">
                                        <span class="block mono text-[9px] text-ink-400 leading-tight">{{ optional($p->scheduled_at)->format('g:i A') }}</span>
                                        <span class="block truncate leading-tight">{{ $p->caption ?: ucfirst($p->media_type) }}</span>
                                    </span>
                                </div>
                            @empty
                                <div class="text-center text-[10px] text-ink-300 pt-8 select-none">{{ __('Tap to schedule') }}</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        @else
        {{-- DAY VIEW: single-day agenda --}}
        @php
            $key = $anchor->format('Y-m-d');
            $dayPosts = ($byDay[$key] ?? collect())->sortBy('scheduled_at');
        @endphp
        <section class="bg-white hairline rounded-2xl overflow-hidden">
            <div class="px-5 py-4 hairline-b flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ $anchor->isoFormat('dddd') }}</div>
                    <div class="serif text-[26px] leading-none mt-0.5">{{ $anchor->isoFormat('MMMM D') }}</div>
                </div>
                <span data-cal-day="{{ $key }}" data-cal-label="{{ $anchor->isoFormat('ddd, MMM D') }}">
                    <button type="button" data-cal-add class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold flex items-center gap-2 hover:opacity-90"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 3v10M3 8h10"/></svg>{{ __('Schedule a post') }}</button>
                </span>
            </div>
            <div class="p-5">
                @if ($dayPosts->count())
                    <div class="space-y-2.5">
                        @foreach ($dayPosts as $p)
                            @php $ts = $typeStyle[$p->media_type] ?? $typeStyle['image']; @endphp
                            <div {!! $chipData($p) !!} class="flex items-center gap-3 hairline rounded-xl p-3 cursor-pointer hover:bg-paper-50">
                                <span class="mono text-[11px] text-ink-500 w-16 shrink-0">{{ optional($p->scheduled_at)->format('g:i A') }}</span>
                                @if ($p->image_url)
                                    <span class="w-10 h-10 rounded-lg bg-center bg-cover shrink-0" style="background-image:url('{{ $p->image_url }}')"></span>
                                @else
                                    <span class="w-10 h-10 rounded-lg bg-gradient-to-br {{ $ts['g'] }} shrink-0"></span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="text-[13px] font-medium truncate">{{ $p->caption ?: ucfirst($p->media_type) }}</div>
                                    <div class="mono text-[10.5px] text-ink-500">{{ ucfirst($p->media_type) }} · {{ ucfirst($p->status) }}</div>
                                </div>
                                <span class="pill {{ $ts['chip'] }} shrink-0">{{ ucfirst($p->media_type) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-16">
                        <div class="serif text-[20px] text-ink-700">{{ __('Nothing scheduled') }}</div>
                        <p class="text-[12.5px] text-ink-500 mt-1">{{ __('Click “Schedule a post” to add content for this day.') }}</p>
                    </div>
                @endif
            </div>
        </section>
        @endif

        {{-- ===== MANAGE PENDING ===== --}}
        @php $upcoming = $posts->where('status', 'pending')->sortBy('scheduled_at'); @endphp
        @if ($upcoming->count())
            <section class="bg-white hairline rounded-2xl p-5 mt-3">
                <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('Upcoming scheduled posts') }}</div>
                <div class="space-y-2">
                    @foreach ($upcoming as $p)
                        @php $ts = $typeStyle[$p->media_type] ?? $typeStyle['image']; @endphp
                        <div class="hairline rounded-xl p-3 flex flex-wrap items-center gap-3">
                            <span class="w-8 h-8 rounded-lg bg-gradient-to-br {{ $ts['g'] }} shrink-0"></span>
                            <div class="min-w-0 flex-1">
                                <div class="text-[13px] font-medium truncate">{{ $p->caption ?: ucfirst($p->media_type) }}</div>
                                <div class="mono text-[10.5px] text-ink-500">{{ ucfirst($p->media_type) }} · {{ optional($p->scheduled_at)->format('D, M j · g:i A') }}</div>
                            </div>
                            {{-- reschedule --}}
                            @php $schedTz = optional(auth()->user()?->currentWorkspace)->timezone ?: (setting('default_timezone') ?: (config('app.timezone') ?: 'Asia/Kolkata')); @endphp
                            <form method="POST" action="{{ route('instagram.scheduled.update', $p->id) }}" class="flex items-center gap-2">
                                @csrf
                                <input type="hidden" name="timezone" value="{{ $schedTz }}">
                                <input type="datetime-local" name="schedule_at" value="{{ $p->scheduled_at ? $p->scheduled_at->copy()->setTimezone($schedTz)->format('Y-m-d\TH:i') : '' }}" class="field !py-1.5 !text-[12px] !w-auto">
                                <button class="px-3 py-1.5 rounded-lg bg-ink-900 text-white text-[12px] font-semibold">{{ __('Reschedule') }}</button>
                            </form>
                            {{-- delete --}}
                            <form method="POST" action="{{ route('instagram.scheduled.destroy', $p->id) }}" data-confirm="{{ __('Remove this scheduled post?') }}">
                                @csrf @method('DELETE')
                                <button class="px-3 py-1.5 rounded-lg hairline text-accent-coral text-[12px] font-semibold hover:bg-paper-50">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- ===== RIGHT-SIDE SCHEDULER DRAWER (opens on day-cell click) ===== --}}
    <div id="cal-overlay" class="fixed inset-0 bg-ink-900/40 backdrop-blur-sm z-40 hidden opacity-0 transition-opacity duration-200"></div>
    <aside id="cal-drawer"
        class="fixed top-0 right-0 h-full w-full max-w-[420px] bg-paper-0 z-50 shadow-2xl translate-x-full transition-transform duration-300 flex flex-col"
        aria-hidden="true">
        <form method="POST" action="{{ url('/instagram/composer') }}" enctype="multipart/form-data" id="cal-form" class="flex flex-col h-full min-h-0">
            @csrf
            <input type="hidden" name="media_type" id="cal-media-type" value="image">
            <input type="hidden" name="send_date" id="cal-send-date" value="">

            {{-- header --}}
            <div class="px-5 py-4 hairline-b flex items-center justify-between shrink-0">
                <div>
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Schedule a post') }}</div>
                    <div class="serif text-[20px] leading-none mt-1" id="cal-date-label">{{ __('Pick a date') }}</div>
                </div>
                <button type="button" id="cal-close" class="w-8 h-8 rounded-full hairline grid place-items-center text-ink-500 hover:bg-paper-50" title="{{ __('Close') }}">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                </button>
            </div>

            {{-- scrollable body --}}
            <div class="flex-1 min-h-0 overflow-y-auto px-5 py-4 space-y-5">
                @if ($accounts->count())
                    {{-- account --}}
                    <div>
                        <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Post to') }}</label>
                        <div class="mt-2 space-y-1.5">
                            @foreach ($accounts as $a)
                                <label class="flex items-center gap-2.5 hairline rounded-xl px-3 py-2.5 cursor-pointer hover:bg-paper-50 has-[:checked]:border-ig-pink has-[:checked]:bg-ig-pink/5">
                                    <input type="radio" name="instagram_account_id" value="{{ $a->id }}" class="accent-ig-pink" @checked($loop->first)>
                                    <span class="relative block w-7 h-7 rounded-full ig-grad-soft text-white text-[9px] font-semibold grid place-items-center overflow-hidden shrink-0">{{ strtoupper(substr($a->username ?: 'IG', 0, 2)) }}@if ($a->profile_pic_url)<img src="{{ $a->profile_pic_url }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">@endif</span>
                                    <span class="text-[13px] font-medium">{{ '@'.($a->username ?: $a->ig_user_id) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- media type --}}
                    <div>
                        <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Type') }}</label>
                        <div class="mt-2 grid grid-cols-4 gap-1.5" id="cal-type-tabs">
                            @foreach (['image' => __('Post'), 'reels' => __('Reel'), 'story' => __('Story'), 'carousel' => __('Carousel')] as $mt => $lbl)
                                <button type="button" data-cal-type="{{ $mt }}" class="hairline rounded-lg py-2 text-[11.5px] font-semibold hover:bg-paper-50 {{ $loop->first ? 'ig-grad-soft text-white' : 'text-ink-600' }}">{{ $lbl }}</button>
                            @endforeach
                        </div>
                    </div>

                    {{-- media upload --}}
                    <div>
                        <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Media') }}</label>
                        <div data-cal-media="single" class="mt-2">
                            <label class="hairline rounded-xl border-dashed border-2 border-paper-200 px-4 py-6 flex flex-col items-center justify-center gap-1.5 cursor-pointer hover:border-ig-pink hover:bg-paper-50 text-center">
                                <svg viewBox="0 0 20 20" class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 13V4M6 8l4-4 4 4M4 15h12"/></svg>
                                <span class="text-[12px] text-ink-500" id="cal-file-name">{{ __('Upload image or video') }}</span>
                                <input type="file" name="media_file" id="cal-file" accept=".jpg,.jpeg,.png,.webp,.mp4,.mov,.m4v" class="hidden">
                            </label>
                        </div>
                        <div data-cal-media="multi" class="mt-2 hidden">
                            <label class="hairline rounded-xl border-dashed border-2 border-paper-200 px-4 py-6 flex flex-col items-center justify-center gap-1.5 cursor-pointer hover:border-ig-pink hover:bg-paper-50 text-center">
                                <svg viewBox="0 0 20 20" class="w-6 h-6 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="14" height="14" rx="2"/><path d="M3 13l4-4 3 3 3-4 4 5"/></svg>
                                <span class="text-[12px] text-ink-500" id="cal-files-name">{{ __('Upload 2–10 images/videos') }}</span>
                                <input type="file" name="media_files[]" id="cal-files" accept=".jpg,.jpeg,.png,.webp,.mp4,.mov,.m4v" multiple class="hidden">
                            </label>
                        </div>
                    </div>

                    {{-- caption --}}
                    <div>
                        <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Caption') }}</label>
                        <textarea name="caption" id="cal-caption" rows="3" maxlength="2200" placeholder="{{ __('Write a caption…') }}" class="field mt-2 resize-none"></textarea>
                    </div>

                    {{-- time + timezone --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Send time') }}</label>
                            <input type="time" name="send_time" id="cal-send-time" value="09:00" class="field mt-2">
                        </div>
                        <div>
                            <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Timezone') }}</label>
                            <select name="timezone" id="cal-timezone" class="field mt-2">
                                @php
                                    $userTz = optional(auth()->user()?->currentWorkspace)->timezone ?: (setting('default_timezone') ?: (config('app.timezone') ?: 'Asia/Kolkata'));
                                    try { $tzList = \DateTimeZone::listIdentifiers(); } catch (\Throwable $e) { $tzList = ['UTC', 'Asia/Kolkata', 'Asia/Dubai', 'Europe/London', 'America/New_York']; }
                                @endphp
                                @foreach ($tzList as $tz)
                                    <option value="{{ $tz }}" {{ $tz === $userTz ? 'selected' : '' }}>{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @else
                    <div class="text-center py-10">
                        <div class="text-[13px] text-ink-500">{{ __('Connect an Instagram account first to schedule posts.') }}</div>
                        <a href="{{ url('/instagram/connect') }}" class="inline-flex mt-3 px-4 py-2 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold">{{ __('Connect account') }}</a>
                    </div>
                @endif
            </div>

            {{-- footer actions --}}
            @if ($accounts->count())
                <div class="px-5 py-4 hairline-t shrink-0 flex items-center gap-2.5">
                    <button type="button" id="cal-post-now" class="flex-1 py-2.5 rounded-full hairline text-[13px] font-semibold hover:bg-paper-50 flex items-center justify-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 8l4 4 6-8"/></svg>{{ __('Publish now') }}
                    </button>
                    <button type="button" id="cal-schedule" class="flex-1 py-2.5 rounded-full ig-grad-soft text-white text-[13px] font-semibold hover:opacity-90 flex items-center justify-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 1"/></svg>{{ __('Schedule') }}
                    </button>
                </div>
            @endif
        </form>
    </aside>

    {{-- ===== MANAGE DRAWER (opens on clicking an existing post chip) ===== --}}
    <div id="cm-overlay" class="fixed inset-0 bg-ink-900/40 backdrop-blur-sm z-40 hidden opacity-0 transition-opacity duration-200"></div>
    <aside id="cal-manage" data-sched-base="{{ url('/instagram/scheduled') }}"
        class="fixed top-0 right-0 h-full w-full max-w-[420px] bg-paper-0 z-50 shadow-2xl translate-x-full transition-transform duration-300 flex flex-col" aria-hidden="true">
        <div class="px-5 py-4 hairline-b flex items-center justify-between shrink-0">
            <div>
                <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Scheduled post') }}</div>
                <div class="serif text-[20px] leading-none mt-1" id="cm-title">—</div>
            </div>
            <button type="button" id="cm-close" class="w-8 h-8 rounded-full hairline grid place-items-center text-ink-500 hover:bg-paper-50" title="{{ __('Close') }}">
                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4l8 8M12 4l-8 8"/></svg>
            </button>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto px-5 py-4 space-y-5">
            {{-- preview --}}
            <div class="flex items-center gap-3">
                <span id="cm-thumb" class="w-14 h-14 rounded-xl bg-center bg-cover ig-grad-soft shrink-0"></span>
                <div class="min-w-0">
                    <div id="cm-caption" class="text-[13px] font-medium break-words">—</div>
                    <div class="mono text-[10.5px] text-ink-500"><span id="cm-type">—</span> · <span id="cm-when">—</span></div>
                </div>
            </div>

            {{-- reschedule (pending only) --}}
            <div id="cm-reschedule">
                <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Reschedule') }}</label>
                <div class="grid grid-cols-2 gap-3 mt-2">
                    <input type="date" id="cm-date" class="field">
                    <input type="time" id="cm-time" class="field">
                </div>
                <button type="button" id="cm-save" class="mt-3 w-full py-2.5 rounded-full ig-grad-soft text-white text-[13px] font-semibold hover:opacity-90">{{ __('Save new time') }}</button>
            </div>

            {{-- non-pending note --}}
            <div id="cm-note" class="hidden text-[12.5px] text-ink-500 text-center py-4">{{ __('This post is no longer pending.') }}</div>
        </div>

        {{-- footer: delete + publish now (pending only) --}}
        <div id="cm-footer" class="px-5 py-4 hairline-t shrink-0 flex items-center gap-2.5">
            <button type="button" id="cm-delete" class="px-4 py-2.5 rounded-full hairline text-accent-coral text-[13px] font-semibold hover:bg-accent-coral/5">{{ __('Delete') }}</button>
            <button type="button" id="cm-send-now" class="flex-1 py-2.5 rounded-full bg-ink-900 text-white text-[13px] font-semibold hover:opacity-90 flex items-center justify-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2.5 8l11-5-4 11-2.5-4.5L2.5 8z"/></svg>{{ __('Publish now') }}
            </button>
        </div>

        {{-- hidden forms — JS sets the action (post id) then submits --}}
        <form id="cm-form-update" method="POST" class="hidden">@csrf<input type="hidden" name="timezone" value="{{ $schedTz }}"><input type="hidden" name="schedule_at" id="cm-schedule-at"></form>
        <form id="cm-form-publish" method="POST" class="hidden">@csrf</form>
        <form id="cm-form-delete" method="POST" class="hidden">@csrf @method('DELETE')</form>
    </aside>
@push('scripts')
    <script src="{{ url('assets/ig-schedule-tz.js') }}?v=1" defer></script>
@endpush
</x-layouts.instagram>

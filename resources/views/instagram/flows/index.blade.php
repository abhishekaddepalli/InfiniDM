{{--
    Flows list — STANDALONE Instagram product only.

    Never rendered inside WaDesk: the routes that reach this view are skipped
    when InstagramGate::isWaDesk(), because WaDesk's own /flows owns that URL
    there. See routes/instagram.php.

    Structure follows WaDesk's user/flows/index.blade.php so the two products
    read as one family. Four of its controls are absent rather than restyled —
    import, export, duplicate and the starter-template gallery — because this
    build registers no endpoint behind any of them, and a route() to a name that
    does not exist throws before a single byte of the page is sent.
--}}
<x-layouts.instagram :title="__('Flows')" ig-active="flows" page="instagram-flows">

    @php
        // index() passes ONE variable, the paginated list. Filter state, counts
        // and the featured pick are resolved here so the page is whole without a
        // controller change, and every one is `??`-guarded: the moment index()
        // starts supplying real ones (filtered server-side, with a ?partial=1
        // branch for the data-fl-* hooks below) these derivations stand down.
        $loaded = collect(method_exists($flows, 'items') ? $flows->items() : $flows);

        $currentStatus = $currentStatus ?? (string) request()->query('status', 'all');
        $currentCategory = $currentCategory ?? (string) request()->query('category', 'all');
        $currentQuery = $currentQuery ?? trim((string) request()->query('q', ''));

        // Counting $loaded would only ever count the current page. One
        // lightweight whole-workspace read gives honest totals — the same
        // in-view, try/catch-guarded shape instagram/_rail.blade.php uses for
        // its own counts — and it falls back to the page rather than throwing.
        try {
            $ws = auth()->user()?->current_workspace_id;
            $census = \App\Instagram\Models\InstagramFlow::query()
                ->when($ws, fn ($q) => $q->where('workspace_id', $ws))
                ->get(['id', 'flow_name', 'category', 'is_published', 'is_active']);
        } catch (\Throwable $e) {
            $census = $loaded;
        }

        $stateOf = fn ($f) => $f->is_published ? ($f->is_active ? 'live' : 'paused') : 'draft';
        $catOf = fn ($f) => $f->category ?: 'uncategorized';

        $statusCounts = $statusCounts ?? [
            'all' => $census->count(),
            'live' => $census->filter(fn ($f) => $stateOf($f) === 'live')->count(),
            'paused' => $census->filter(fn ($f) => $stateOf($f) === 'paused')->count(),
            'draft' => $census->filter(fn ($f) => $stateOf($f) === 'draft')->count(),
        ];
        $categoryCounts = $categoryCounts ?? $census->groupBy($catOf)->map->count()->put('all', $census->count())->all();

        // The four tiles below index directly; a controller-supplied array need
        // not carry every key.
        $statusCounts = array_merge(['all' => 0, 'live' => 0, 'paused' => 0, 'draft' => 0], $statusCounts);

        // Filtering is page-local: the controller returns an unfiltered page, so
        // a filter can only narrow what already arrived. Exact for a workspace
        // that fits on one page; past that the pager still walks the whole list
        // and the footer reports the true match total from the census.
        $matches = function ($f) use ($currentStatus, $currentCategory, $currentQuery, $stateOf, $catOf) {
            if ($currentStatus !== 'all' && $stateOf($f) !== $currentStatus) return false;
            if ($currentCategory !== 'all' && $catOf($f) !== $currentCategory) return false;
            if ($currentQuery !== '' && !str_contains(mb_strtolower((string) $f->flow_name), mb_strtolower($currentQuery))) return false;
            return true;
        };
        $filtering = $currentStatus !== 'all' || $currentCategory !== 'all' || $currentQuery !== '';
        $visible = $filtering ? $loaded->filter($matches)->values() : $loaded;
        $matchTotal = $filtering ? $census->filter($matches)->count() : $statusCounts['all'];

        // Newest-first, so the first published row is the flow being worked on.
        // Suppressed while filtering — a card pinned above results it is not
        // part of reads as a bug.
        $onFirstPage = !method_exists($flows, 'currentPage') || $flows->currentPage() === 1;
        $featured = $featured ?? ($filtering || !$onFirstPage ? null : ($loaded->firstWhere('is_published', true) ?? $loaded->first()));

        // Without this, page 2 silently drops the filter.
        if (method_exists($flows, 'appends')) {
            $flows->appends(request()->query());
        }

        // Defaults are stripped so an unfiltered view is a clean /flows.
        $filterUrl = fn (array $over) => route(
            'instagram.flows.index',
            array_filter(
                array_merge(['status' => $currentStatus, 'category' => $currentCategory, 'q' => $currentQuery], $over),
                fn ($v) => $v !== null && $v !== '' && $v !== 'all',
            ),
        );

        $libraryEntries = [
            'all' => ['label' => __('All flows'), 'icon' => 'M3 4h10M3 8h10M3 12h6'],
            'welcome' => ['label' => __('Welcome'), 'icon' => 'M3 11s2-1 5-1 5 1 5 1M5 6.5h.01M11 6.5h.01M8 9.5s1 .8 0 1.5'],
            'cart' => ['label' => __('Cart abandonment'), 'icon' => 'M3 5h8l1 6H4z'],
            'post-purchase' => ['label' => __('Post-purchase'), 'icon' => 'M4 13V7l4-4 4 4v6M3 13h10'],
            're-engagement' => ['label' => __('Re-engagement'), 'icon' => 'M11 4H5a3 3 0 0 0 0 6h6a3 3 0 0 1 0 6H5M5 4l-2 2 2 2M11 16l2-2-2-2'],
            'event' => ['label' => __('Special events'), 'icon' => 'M2 3h12v11H2zM2 6h12M5 1v3M11 1v3'],
            'lead' => ['label' => __('Lead nurture'), 'icon' => 'M5 6a3 3 0 1 0 0-0.01M2 14c0-3 3-4 6-4s6 1 6 4'],
        ];
    @endphp

    <div data-fl-state data-fl-status="{{ $currentStatus }}" data-fl-category="{{ $currentCategory }}"
        data-fl-search="{{ $currentQuery }}"
        data-fl-page="{{ method_exists($flows, 'currentPage') ? $flows->currentPage() : 1 }}">


        <div class="px-4 sm:px-7 py-7">
            <div class="grid grid-cols-1 gap-6">

                

                {{-- ===== MAIN COLUMN ===== --}}
                <main>
                    <div class="flex items-end justify-between mb-5 flex-wrap gap-3">
                        <div>
                            {{-- Ported from WaDesk, this read
                                 "Workspace · {current_workspace->name}". This product
                                 is single-tenant and its User model deliberately has
                                 no current_workspace relation, so the name resolved
                                 to null and the eyebrow rendered "WORKSPACE ·
                                 WORKSPACE". Matches the other Instagram screens
                                 ("COMMENT AUTOMATION") instead. --}}
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                                {{ __('Automation') }}
                            </div>
                            <h1 class="serif font-serif font-normal text-[32px] sm:text-[38px] lg:text-[44px] leading-[1.05] tracking-tight">
                                {{ __('Automated') }} <span class="ig-text italic">{{ __('flows') }}</span>.
                            </h1>
                            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                                {{ __('Build reusable Instagram DM workflows — triggered by a keyword, branched by replies, and measured end-to-end.') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('instagram.flows.create') }}"
                                class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold flex items-center gap-2 hover:opacity-90">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M8 3v10M3 8h10" />
                                </svg>
                                {{ __('Create flow') }}
                            </a>
                        </div>
                    </div>

                    {{-- Start from a template — admin-published FlowTemplates the
                         user can clone into an editable flow. Hidden when none
                         are published. --}}
                    @php $flowTemplates = ($templates ?? collect()); @endphp
                    @if ($flowTemplates->isNotEmpty())
                        @if (session('status'))
                            <div class="mb-4 rounded-2xl bg-wa-bubble text-wa-deep px-4 py-3 text-[13px]">{{ session('status') }}</div>
                        @endif
                        @error('template')
                            <div class="mb-4 rounded-2xl bg-accent-coral/10 text-accent-coral px-4 py-3 text-[13px]">{{ $message }}</div>
                        @enderror
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2.5">
                                <h2 class="font-serif text-[18px]">{{ __('Start from a template') }}</h2>
                                <span class="mono text-[10px] uppercase tracking-wide text-ink-400">{{ trans_choice('{1}:count template|[2,*]:count templates', $flowTemplates->count(), ['count' => $flowTemplates->count()]) }}</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                                @foreach ($flowTemplates as $tpl)
                                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card flex flex-col hover:border-wa-deep hover:shadow-soft transition">
                                        <div class="flex items-start gap-2.5 mb-2">
                                            <span class="w-9 h-9 rounded-xl bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                                <svg viewBox="0 0 20 20" class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="5" r="2.2"/><circle cx="15" cy="5" r="2.2"/><circle cx="10" cy="15" r="2.2"/><path d="M5 7.2v2.3a1.6 1.6 0 0 0 1.6 1.6h6.8A1.6 1.6 0 0 0 15 9.5V7.2M10 11.1v1.7"/></svg>
                                            </span>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-[13px] text-ink-900 truncate">{{ $tpl->name }}</div>
                                                <div class="mono text-[9px] uppercase tracking-wide text-ink-400 mt-0.5">{{ $tpl->category }}</div>
                                            </div>
                                        </div>
                                        <p class="text-[11.5px] text-ink-500 leading-snug flex-1 mb-3">{{ \Illuminate\Support\Str::limit($tpl->description, 90) ?: __('A ready-made flow you can customise.') }}</p>
                                        <form method="POST" action="{{ route('instagram.flows.clone-template', $tpl->id) }}">
                                            @csrf
                                            <button type="submit" class="w-full rounded-full ig-grad-soft text-white px-4 py-2 text-[12px] font-semibold hover:opacity-90 flex items-center justify-center gap-1.5">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v10M3 8h10"/></svg>
                                                {{ __('Use template') }}
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Stat row --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                        <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Published flows') }}</div>
                            <div class="font-serif text-[34px] leading-none mt-1" data-fl-stat="live">{{ $statusCounts['live'] }}</div>
                            <div class="text-[11px] text-ink-500 mt-2">{{ __('answering DMs now') }}</div>
                        </div>
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Total flows') }}</div>
                            <div class="font-serif text-[34px] leading-none mt-1" data-fl-stat="all">{{ $statusCounts['all'] }}</div>
                            <div class="text-[11px] text-ink-500 mt-2">{{ __('in this workspace') }}</div>
                        </div>
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Drafts') }}</div>
                            <div class="font-serif text-[34px] leading-none mt-1" data-fl-stat="draft">{{ $statusCounts['draft'] }}</div>
                            <div class="text-[11px] text-ink-500 mt-2">{{ __('never run until published') }}</div>
                        </div>
                        <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Paused') }}</div>
                            <div class="font-serif text-[34px] leading-none mt-1" data-fl-stat="paused">{{ $statusCounts['paused'] }}</div>
                            <div class="text-[11px] text-ink-500 mt-2">{{ __('temporarily off') }}</div>
                        </div>
                    </div>

                    {{-- Tabs + search. The search is a real GET form so it works
                         with JavaScript off; #fl-search stays for the debounced
                         version to take over. --}}
                    <div class="border-b border-paper-200 flex items-center gap-x-6 gap-y-2 flex-wrap px-2 mb-5">
                        @foreach (['all' => __('All flows'), 'live' => __('Published'), 'paused' => __('Paused'), 'draft' => __('Drafts')] as $key => $label)
                            <a href="{{ $filterUrl(['status' => $key]) }}" data-fl-filter="status" data-fl-value="{{ $key }}"
                                class="inline-flex items-center gap-2 py-3.5 text-[14px] border-b-2 transition whitespace-nowrap {{ $currentStatus === $key ? 'ig-tab-active font-semibold' : 'text-ink-600 hover:text-ink-900 border-transparent' }}">
                                {{ $label }}
                                <span class="text-[10px] px-1.5 py-px rounded-full bg-paper-100 text-ink-600 font-mono"
                                    data-fl-status-count="{{ $key }}">{{ $statusCounts[$key] ?? 0 }}</span>
                            </a>
                        @endforeach
                        <div class="flex-1"></div>
                        <form method="GET" action="{{ route('instagram.flows.index') }}" class="relative w-full sm:w-auto py-2">
                            @if ($currentStatus !== 'all')
                                <input type="hidden" name="status" value="{{ $currentStatus }}">
                            @endif
                            @if ($currentCategory !== 'all')
                                <input type="hidden" name="category" value="{{ $currentCategory }}">
                            @endif
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                                stroke="currentColor" stroke-width="1.5">
                                <circle cx="7" cy="7" r="5" />
                                <path d="m11 11 3 3" />
                            </svg>
                            <input id="fl-search" type="search" name="q" value="{{ $currentQuery }}"
                                placeholder="{{ __('Search flows...') }}"
                                class="border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full sm:w-64 focus:outline-none focus:border-wa-deep" />
                        </form>
                    </div>

                    {{-- ===== FEATURED FLOW ===== --}}
                    <div id="fl-featured">
                        @if ($featured)
                            <x-instagram.flow-featured :flow="$featured" />
                        @endif
                    </div>

                    {{-- ===== FLOW GRID ===== --}}
                    <div id="fl-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        @forelse ($visible as $flow)
                            <x-instagram.flow-card :flow="$flow" />
                        @empty
                            <div data-list-grid-empty
                                class="col-span-full bg-paper-0 border border-dashed border-paper-200 rounded-2xl p-8 md:p-10 shadow-card text-center">
                                <div class="font-serif text-[22px] md:text-[24px] leading-tight text-ink-900">
                                    {{ $filtering ? __('No flows match these filters') : __('No flows yet') }}
                                </div>
                                <p class="mt-2 text-[13px] text-ink-500 max-w-2xl mx-auto">
                                    {{ $filtering
                                        ? __('Nothing here matches the current status, category, or search. Clear the filters to see everything.')
                                        : __('A flow answers a DM automatically — matched on a keyword, or on every message. Build one and publish it to put it to work.') }}
                                </p>
                                <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                                    @if ($filtering)
                                        <a href="{{ route('instagram.flows.index') }}"
                                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Clear filters') }}</a>
                                    @endif
                                    <a href="{{ route('instagram.flows.create') }}"
                                        class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ __('Build your first flow') }}</a>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    <div id="fl-pagination">
                        <x-instagram.pager :paginator="$flows" :label="__('flows')" />
                    </div>

                    {{-- ===== HELP ===== --}}
                    <div class="mt-7 grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div class="border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Help · 01') }}</div>
                            <div class="serif font-serif font-normal text-[20px] mb-1">{{ __('What is a flow?') }}</div>
                            <p class="text-[12.5px] text-ink-600 leading-relaxed">
                                {{ __('A sequence of DMs, waits, and decision branches that starts when someone messages you — on a keyword, on a comment reply, or on every message.') }}
                            </p>
                        </div>
                        <div class="border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Help · 02') }}</div>
                            <div class="serif font-serif font-normal text-[20px] mb-1">{{ __('Why is my flow not running?') }}</div>
                            <p class="text-[12.5px] text-ink-600 leading-relaxed">
                                {{ __('A flow runs only when it is published, has a trigger keyword set, and its Trigger step points at a connected Instagram account.') }}
                            </p>
                        </div>
                        <div class="border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Help · 03') }}</div>
                            <div class="serif font-serif font-normal text-[20px] mb-1">{{ __('Which flow should I build first?') }}</div>
                            <p class="text-[12.5px] text-ink-600 leading-relaxed">
                                {{ __('Welcome, then product questions, then post-purchase. That covers the first reply, the sale, and the repeat order with the least upkeep.') }}
                            </p>
                        </div>
                    </div>

                    <div id="fl-results-footer"
                        class="mt-6 text-[11px] text-ink-500 mono font-mono text-center {{ $statusCounts['all'] > 0 ? '' : 'hidden' }}">
                        {{ __('Showing') }} <span data-fl-shown>{{ $visible->count() }}</span>
                        {{ __('of') }} <span data-fl-total>{{ number_format($matchTotal) }}</span>
                        {{ $filtering ? __('matching flows') : __('flows') }}
                    </div>
                </main>
            </div>
        </div>
    </div>

    {{-- Delete posts to the same /flows/api/{id} endpoint the builder uses, so
         there is one deletion path rather than a second one to keep in step. --}}
    @push('scripts')
        <script type="module" src="{{ asset('extensions/instagram/instagram-flows-index.js') }}"></script>
    @endpush
</x-layouts.instagram>

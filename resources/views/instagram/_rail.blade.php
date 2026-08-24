@props(['active' => 'dashboard'])
@php
    // IgDesk left rail — same SHAPE as the WaDesk /wa-campaigns rail
    // (tip → filter lists with counts → health card) but the FILTER CONTENT
    // is page-specific (driven by $active), so every page's rail is different.
    $ws = auth()->user()?->current_workspace;
    $wsId = (int) ($ws->id ?? 0);
    $cnt = function (string $table, ?callable $extra = null) use ($wsId): int {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable($table)) return 0;
            $q = \Illuminate\Support\Facades\DB::table($table)->where('workspace_id', $wsId);
            if ($extra) $extra($q);
            return (int) $q->count();
        } catch (\Throwable $e) { return 0; }
    };
    $igConnected = $cnt('instagram_accounts', fn ($q) => $q->where('status', 'connected'));

    // Per-page tip (top card).
    $tip = match ($active) {
        'inbox'       => __('Reply within the 24-hour window. Outside it, Instagram only allows a template-style re-open.'),
        'automations' => __('Start with one keyword → DM rule. Add comment → DM on your best post next.'),
        'composer'    => __('Publish a photo, then attach a comment → DM rule right on the post.'),
        'broadcast'   => __('Bulk DM only reaches people who messaged you in the last 24h. Ban-safe, capped at 200/hr.'),
        'analytics'   => __('Reach + profile views come straight from the official Instagram Insights API.'),
        'reposter'    => __('Auto-repost reels from YouTube/IG on a schedule. Posting runs on the server via the official API — even when you are offline.'),
        default       => __('DM + comment automation on the official Instagram Graph API.'),
    };

    // Per-page filter groups: [ Heading => [ [label, href, count|null], ... ] ].
    $q = request()->query();
    $groups = match ($active) {
        'automations' => [
            __('Status') => [
                [__('All'),        url('/instagram/automations'),                 $cnt('instagram_automations'), empty($q['status'])],
                [__('Active'),     url('/instagram/automations?status=active'),   $cnt('instagram_automations', fn ($x) => $x->where('is_active', 1)), ($q['status'] ?? '') === 'active'],
                [__('Paused'),     url('/instagram/automations?status=paused'),   $cnt('instagram_automations', fn ($x) => $x->where('is_active', 0)), ($q['status'] ?? '') === 'paused'],
            ],
            __('Trigger') => [
                [__('Keyword → DM'),  url('/instagram/automations?type=dm_keyword'),    $cnt('instagram_automations', fn ($x) => $x->where('type', 'dm_keyword')), ($q['type'] ?? '') === 'dm_keyword'],
                [__('Comment → DM'),  url('/instagram/automations?type=comment_to_dm'), $cnt('instagram_automations', fn ($x) => $x->where('type', 'comment_to_dm')), ($q['type'] ?? '') === 'comment_to_dm'],
                [__('AI agent'),      url('/instagram/automations?type=ai_agent'),      $cnt('instagram_automations', fn ($x) => $x->where('type', 'ai_agent')), ($q['type'] ?? '') === 'ai_agent'],
                [__('Run a flow'),    url('/instagram/automations?type=flow'),          $cnt('instagram_automations', fn ($x) => $x->where('type', 'flow')), ($q['type'] ?? '') === 'flow'],
            ],
        ],
        'inbox' => [
            __('Conversations') => [
                [__('All'),      url('/instagram/inbox'),               $cnt('instagram_messages', fn ($x) => $x->where('direction', 'in')->distinct('igsid')), empty($q['status']) && empty($q['stage'])],
                [__('Received'), url('/instagram/inbox?status=in'),     $cnt('instagram_messages', fn ($x) => $x->where('direction', 'in')), ($q['status'] ?? '') === 'in'],
                [__('Auto-sent'),url('/instagram/inbox?status=out'),    $cnt('instagram_messages', fn ($x) => $x->where('direction', 'out')), ($q['status'] ?? '') === 'out'],
            ],
            __('Funnel') => [
                [__('Lead'),       url('/instagram/inbox?stage=lead'),       $cnt('instagram_leads',  fn ($x) => $x->whereNotNull('igsid')), ($q['stage'] ?? '') === 'lead'],
                [__('Ordered'),    url('/instagram/inbox?stage=ordered'),    $cnt('instagram_orders', fn ($x) => $x->where('status', 'placed')), ($q['stage'] ?? '') === 'ordered'],
                [__('Paid'),       url('/instagram/inbox?stage=paid'),       $cnt('instagram_orders', fn ($x) => $x->where('status', 'paid')), ($q['stage'] ?? '') === 'paid'],
                [__('Dispatched'), url('/instagram/inbox?stage=dispatched'), $cnt('instagram_orders', fn ($x) => $x->where('status', 'dispatched')), ($q['stage'] ?? '') === 'dispatched'],
            ],
        ],
        'broadcast' => [
            __('Bulk DMs') => [
                [__('All'),    url('/instagram/broadcast'),             $cnt('instagram_broadcasts'), empty($q['status'])],
                [__('Done'),   url('/instagram/broadcast?status=done'), $cnt('instagram_broadcasts', fn ($x) => $x->where('status', 'done')), ($q['status'] ?? '') === 'done'],
                [__('Running'),url('/instagram/broadcast?status=running'), $cnt('instagram_broadcasts', fn ($x) => $x->whereIn('status', ['running','queued'])), ($q['status'] ?? '') === 'running'],
            ],
        ],
        'composer' => [
            __('Posts') => [
                [__('Compose'),   url('/instagram/composer'),               null, empty($q['view'])],
                [__('Scheduled'), url('/instagram/composer?view=scheduled'), $cnt('instagram_scheduled_posts', fn ($x) => $x->whereNull('posted_at')), ($q['view'] ?? '') === 'scheduled'],
            ],
        ],
        'analytics' => [
            __('Range') => [
                [__('Last 7 days'),  url('/instagram/analytics?range=7'),  null, ($q['range'] ?? '14') === '7'],
                [__('Last 14 days'), url('/instagram/analytics?range=14'), null, ($q['range'] ?? '14') === '14'],
                [__('Last 30 days'), url('/instagram/analytics?range=30'), null, ($q['range'] ?? '') === '30'],
            ],
        ],
        default => [],
    };
@endphp

<aside class="space-y-3">
    {{-- Tip --}}
    <div class="rounded-2xl p-4 ig-grad-soft text-white shadow-card">
        <div class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-[0.16em] text-white/80">
            <span class="w-1.5 h-1.5 rounded-full bg-white"></span>{{ __('Tip') }}
        </div>
        <p class="text-[12px] text-white/90 mt-2 leading-snug">{{ $tip }}</p>
    </div>

    {{-- Identity --}}
    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
        <div class="flex items-start justify-between gap-2">
            <span class="w-11 h-11 rounded-xl shrink-0 grid place-items-center text-white ig-grad-soft">
                <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
            </span>
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono {{ $igConnected ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-100 text-ink-500 border border-paper-200' }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $igConnected ? 'bg-wa-green' : 'bg-ink-300' }}"></span>{{ $igConnected ? __('Live') : __('Offline') }}
            </span>
        </div>
        <div class="font-serif text-[18px] leading-tight mt-3">{{ brand_name() }}</div>
        <div class="font-mono text-[10.5px] text-ink-500 mt-0.5 truncate">{{ $ws->name ?? __('Workspace') }}</div>
        <div class="mt-2 font-mono text-[10px] text-ink-500">{{ $igConnected }} {{ __('account(s) connected') }}</div>
    </div>

    {{-- Per-page filter groups (the part that differs per page) --}}
    @foreach ($groups as $heading => $rows)
        <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card space-y-0.5">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">{{ $heading }}</div>
            @foreach ($rows as $row)
                @php [$label, $href, $count, $isOn] = array_pad($row, 4, null); @endphp
                <a href="{{ $href }}" @class([
                    'flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] transition',
                    'ig-grad-soft text-white font-semibold' => $isOn,
                    'text-ink-700 hover:bg-paper-50' => !$isOn,
                ])>
                    <span class="flex-1">{{ $label }}</span>
                    @if ($count !== null)
                        <span class="font-mono text-[10px] px-1.5 py-0.5 rounded-full {{ $isOn ? 'bg-white/25 text-white' : 'bg-paper-100 text-ink-600' }}">{{ $count }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endforeach

    {{-- Reels Autopilot — cross-page quick link (visible on every IgDesk page) --}}
    <a href="{{ url('/instagram/reposter') }}" @class([
        'flex items-center gap-2 px-3 py-2.5 rounded-2xl border text-[13px] font-medium transition shadow-card',
        'ig-grad-soft text-white border-transparent' => $active === 'reposter',
        'bg-paper-0 border-paper-200 text-ink-700 hover:bg-paper-50' => $active !== 'reposter',
    ])>
        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5l14 7-14 7V5Z"/></svg>
        <span class="flex-1">{{ __('Reels Autopilot') }}</span>
    </a>

    {{-- Health card (bottom) --}}
    <div class="rounded-2xl p-4 {{ $igConnected ? 'bg-wa-mint border border-wa-green/30' : 'border border-paper-200 bg-paper-0' }} shadow-card">
        <div class="flex items-center gap-2 text-[12px] font-semibold {{ $igConnected ? 'text-wa-deep' : 'text-ink-700' }}">
            <span class="w-2 h-2 rounded-full {{ $igConnected ? 'bg-wa-green' : 'bg-ink-300' }}"></span>{{ $igConnected ? __('Channel healthy') : __('No account yet') }}
        </div>
        <p class="text-[11px] text-ink-500 mt-1">{{ $igConnected ? __('Official Graph API · ban-safe.') : __('Connect a Professional / Creator account to start.') }}</p>
        <a href="{{ $igConnected ? url('/instagram') : url('/instagram/connect') }}" class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-white text-[12px] font-semibold ig-grad-soft hover:opacity-90">
            {{ $igConnected ? __('Dashboard') : __('Connect Instagram') }}
        </a>
    </div>
</aside>

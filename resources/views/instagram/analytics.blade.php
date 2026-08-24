<x-layouts.instagram :title="__('Analytics')" ig-active="analytics" page="instagram-analytics">
    @php
        // ── Build an SVG path "d" from a numeric series across a fixed box. ──
        // $area=true closes the path down to the baseline for the gradient fill.
        $sparkPath = function (array $vals, int $w = 760, int $h = 240, bool $area = false) {
            $vals = array_values($vals);
            $n = count($vals);
            if ($n === 0) return '';
            $max = max(1, max($vals));
            $top = 30; $bottom = $h - 30;          // inner plot band
            $step = $n > 1 ? $w / ($n - 1) : 0;
            $pts = [];
            foreach ($vals as $i => $v) {
                $x = round($i * $step, 1);
                $y = round($bottom - ($v / $max) * ($bottom - $top), 1);
                $pts[] = "$x $y";
            }
            $d = 'M' . implode(' L', $pts);
            if ($area) $d .= " L{$w} {$bottom} L0 {$bottom}Z";
            return $d;
        };

        $reachSeries = array_column($insights['reach'] ?? [], 'v');
        $hasReach    = count(array_filter($reachSeries)) > 0;

        $totalIn   = array_sum($inSeries ?? []);
        $totalOut  = array_sum($outSeries ?? []);
        $hasDm     = ($totalIn + $totalOut) > 0;
        // Auto-deflection = share of inbound DMs we answered automatically.
        $deflect   = $totalIn > 0 ? min(100, (int) round($totalOut / max(1, $totalIn) * 100)) : 0;
    @endphp

    {{-- ===== HEADER ===== --}}
    <section class="w-full px-6 pt-6 pb-4 flex items-end justify-between">
        <div>
            <div class="flex items-center gap-3 mb-1.5 text-[11px] mono uppercase tracking-[0.18em] text-ink-500">
                <span>{{ __('Insights') }}</span>
                <span class="w-1 h-1 rounded-full bg-ink-400"></span>
                <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>{{ $accounts->count() }} {{ trans_choice('account|accounts', $accounts->count()) }}</span>
            </div>
            <h1 class="serif text-[40px] leading-none">{{ __('Performance') }} <span class="ig-text italic">{{ __('analytics') }}</span></h1>
        </div>
        <div class="flex items-center gap-2">
            @if ($accounts->count() > 1)
                <form method="GET" action="{{ url('/instagram/analytics') }}">
                    <select name="account" onchange="this.form.submit()" class="px-4 py-2 hairline rounded-full bg-white text-[12px] font-medium hover:bg-paper-50 focus:outline-none focus:border-ig-pink">
                        @foreach ($accounts as $acc)
                            <option value="{{ $acc->id }}" @selected($account && $account->id === $acc->id)>{{ '@'.($acc->username ?: $acc->ig_user_id) }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <span class="px-4 py-2 hairline rounded-full bg-white text-[12px] font-medium text-ink-600">{{ __('Last 14 days') }}</span>
        </div>
    </section>

    <div class="w-full px-6"><x-admin.flash /></div>

    @if (!$account)
        {{-- ===== EMPTY: no connected account ===== --}}
        <section class="w-full px-6 pb-8">
            <div class="bg-white hairline rounded-2xl p-12 text-center">
                <span class="ig-grad w-14 h-14 rounded-2xl grid place-items-center text-white mx-auto mb-3"><svg viewBox="0 0 20 20" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 13l4-5 3 3 6-7"/><path d="M3 3v14h14"/></svg></span>
                <div class="serif text-[24px]">{{ __('Connect an account to see analytics') }}</div>
                <p class="text-[12.5px] text-ink-500 mt-1">{{ __('Reach & engagement come straight from the official Instagram Insights API once a Professional account is linked.') }}</p>
                <a href="{{ url('/instagram/connect') }}" class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('Connect Instagram') }}</a>
            </div>
        </section>
    @else

    {{-- ===== KPI ROW ===== --}}
    <section class="w-full px-6 pb-4 grid grid-cols-4 gap-3">
        {{-- Reach (real, official Insights) --}}
        <div class="bg-white hairline rounded-2xl p-5">
            <div class="flex items-center justify-between"><span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Reach · 14d') }}</span><span class="pill bg-paper-100 text-ink-600">{{ __('Insights API') }}</span></div>
            <div class="serif text-[42px] leading-none mt-2 tabular">{{ number_format($reach) }}</div>
            @if ($hasReach)
                <svg viewBox="0 0 200 36" class="w-full h-9 mt-2" preserveAspectRatio="none"><path d="{{ $sparkPath($reachSeries, 200, 36) }}" fill="none" stroke="#833AB4" stroke-width="2"/></svg>
            @else
                <div class="h-9 mt-2 flex items-center text-[10.5px] text-ink-400 mono">{{ __('no daily reach yet') }}</div>
            @endif
        </div>

        {{-- Auto-deflection (derived from our DM log: out ÷ in) --}}
        <div class="bg-white hairline rounded-2xl p-5">
            <div class="flex items-center justify-between"><span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Auto-deflection') }}</span><span class="pill bg-paper-100 text-ink-600">{{ __('out ÷ in') }}</span></div>
            <div class="serif text-[42px] leading-none mt-2 tabular {{ $hasDm ? 'ig-text' : '' }}">{{ $hasDm ? $deflect.'%' : '—' }}</div>
            <div class="mt-3 h-1.5 rounded-full bg-paper-100 overflow-hidden"><div class="h-full ig-grad-soft" style="width:{{ $hasDm ? $deflect : 0 }}%"></div></div>
        </div>

        {{-- DMs received (real) --}}
        <div class="bg-white hairline rounded-2xl p-5">
            <div class="flex items-center justify-between"><span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('DMs received') }}</span><span class="pill bg-paper-100 text-ink-600">{{ __('14d') }}</span></div>
            <div class="serif text-[42px] leading-none mt-2 tabular">{{ number_format($totalIn) }}</div>
            @if (count(array_filter($inSeries)))
                <svg viewBox="0 0 200 36" class="w-full h-9 mt-2" preserveAspectRatio="none"><path d="{{ $sparkPath($inSeries, 200, 36) }}" fill="none" stroke="#E1306C" stroke-width="2"/></svg>
            @else
                <div class="h-9 mt-2 flex items-center text-[10.5px] text-ink-400 mono">{{ __('no inbound DMs yet') }}</div>
            @endif
        </div>

        {{-- Auto-sent (real) --}}
        <div class="bg-white hairline rounded-2xl p-5">
            <div class="flex items-center justify-between"><span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Auto-sent DMs') }}</span><span class="pill bg-paper-100 text-ink-600">{{ __('14d') }}</span></div>
            <div class="serif text-[42px] leading-none mt-2 tabular">{{ number_format($totalOut) }}</div>
            <div class="text-[11px] text-ink-500 mt-3 mono">{{ __('accounts engaged') }} · {{ number_format($profile) }}</div>
        </div>
    </section>

    {{-- ===== MAIN CHARTS ===== --}}
    <section class="w-full px-6 pb-4 grid grid-cols-12 gap-3">
        {{-- big area chart: DM volume in vs out --}}
        <div class="col-span-8 bg-white hairline rounded-2xl p-5">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h2 class="serif text-[22px] leading-tight">{{ __('DM volume over time') }}</h2>
                    <p class="text-[11px] text-ink-500 mono">{{ __('received vs auto-sent · 14 days') }}</p>
                </div>
                <div class="flex items-center gap-4 text-[11px]">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-ig-purple"></span>{{ __('Received') }}</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-ig-pink"></span>{{ __('Auto-sent') }}</span>
                </div>
            </div>
            @if ($hasDm)
                @php
                    $combinedMax = max(1, max(array_merge($inSeries ?: [0], $outSeries ?: [0])));
                @endphp
                <div class="relative" data-dm-chart data-in='@json($inSeries)' data-out='@json($outSeries)' data-labels='@json(array_values($labels))'>
                <svg viewBox="0 0 760 240" class="w-full h-[240px]">
                    <defs>
                        <linearGradient id="gp" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#833AB4" stop-opacity=".22"/><stop offset="1" stop-color="#833AB4" stop-opacity="0"/></linearGradient>
                        <linearGradient id="gk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#E1306C" stop-opacity=".28"/><stop offset="1" stop-color="#E1306C" stop-opacity="0"/></linearGradient>
                    </defs>
                    <g stroke="#EBE3EE" stroke-dasharray="2 4"><line x1="0" x2="760" y1="40" y2="40"/><line x1="0" x2="760" y1="100" y2="100"/><line x1="0" x2="760" y1="160" y2="160"/><line x1="0" x2="760" y1="210" y2="210"/></g>
                    <g font-family="JetBrains Mono" font-size="9" fill="#928699"><text x="2" y="38">{{ number_format($combinedMax) }}</text><text x="2" y="158">{{ number_format((int) round($combinedMax / 2)) }}</text></g>
                    <path d="{{ $sparkPath($inSeries, 760, 240, true) }}" fill="url(#gp)"/>
                    <path d="{{ $sparkPath($inSeries, 760, 240) }}" fill="none" stroke="#833AB4" stroke-width="2.5"/>
                    <path d="{{ $sparkPath($outSeries, 760, 240, true) }}" fill="url(#gk)"/>
                    <path d="{{ $sparkPath($outSeries, 760, 240) }}" fill="none" stroke="#E1306C" stroke-width="2.5"/>
                </svg>
                    <div data-dm-guide class="absolute top-0 bottom-0 w-px bg-ink-400/30 pointer-events-none opacity-0 transition-opacity"></div>
                    <div data-dm-tip class="absolute z-10 pointer-events-none opacity-0 -translate-x-1/2 -translate-y-full px-2.5 py-1.5 rounded-lg bg-ink-900 text-white text-[11px] leading-tight shadow-lg whitespace-nowrap transition-opacity"></div>
                </div>
                <div class="flex justify-between mt-1 mono text-[9px] text-ink-400">
                    @foreach (array_values($labels) as $i => $d)
                        @if ($i % 2 === 0)<span>{{ \Illuminate\Support\Carbon::parse($d)->format('d M') }}</span>@endif
                    @endforeach
                </div>
            @else
                <div class="h-[240px] grid place-items-center text-center">
                    <div>
                        <svg viewBox="0 0 24 24" class="w-8 h-8 mx-auto text-ink-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 13l4-5 3 3 6-7"/><path d="M3 3v14h14"/></svg>
                        <p class="text-[12px] text-ink-500">{{ __('No DM activity in the last 14 days.') }}</p>
                        <p class="text-[11px] text-ink-400 mt-1 mono">{{ __('volume appears as messages flow through your automations') }}</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- account-engagement panel (real Insights totals) --}}
        <div class="col-span-4 bg-white hairline rounded-2xl p-5">
            <h2 class="serif text-[22px] mb-4">{{ __('Engagement') }}</h2>
            <div class="flex items-center justify-center my-4 relative">
                @php
                    $engaged = (int) ($insights['_totals']['accounts_engaged'] ?? 0);
                    $reaches = (int) $reach;
                    // Engaged ÷ reached → fraction of the ring to paint.
                    $pct = $reaches > 0 ? min(1, $engaged / $reaches) : 0;
                    $circ = 2 * M_PI * 48;          // r=48
                    $dash = round($circ * $pct, 1);
                @endphp
                <svg viewBox="0 0 120 120" class="w-40 h-40 -rotate-90">
                    <circle cx="60" cy="60" r="48" fill="none" stroke="#EBE3EE" stroke-width="16"/>
                    @if ($pct > 0)
                        <circle cx="60" cy="60" r="48" fill="none" stroke="#E1306C" stroke-width="16" stroke-dasharray="{{ $dash }} {{ round($circ - $dash, 1) }}" stroke-dashoffset="0" stroke-linecap="round"/>
                    @endif
                </svg>
                <div class="absolute inset-0 grid place-items-center text-center">
                    <div>
                        <div class="serif text-[28px] tabular leading-none">{{ $reaches > 0 ? round($pct * 100).'%' : '—' }}</div>
                        <div class="mono text-[9px] text-ink-500 uppercase">{{ __('engaged') }}</div>
                    </div>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex items-center justify-between text-[12px]"><span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm bg-ig-pink"></span>{{ __('Accounts engaged') }}</span><span class="mono font-semibold tabular">{{ number_format($engaged) }}</span></div>
                <div class="flex items-center justify-between text-[12px]"><span class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm bg-paper-200"></span>{{ __('Accounts reached') }}</span><span class="mono font-semibold tabular">{{ number_format($reaches) }}</span></div>
            </div>
            @if ($reaches === 0 && $engaged === 0)
                <p class="text-[11px] text-ink-400 mt-3 mono">{{ __('totals populate once the Insights API returns activity') }}</p>
            @endif
        </div>
    </section>

    {{-- ===== BOTTOM: accounts + DM split + automations note ===== --}}
    <section class="w-full px-6 pb-8 grid grid-cols-12 gap-3">
        {{-- connected accounts (real list; per-account analytics via the picker above) --}}
        <div class="col-span-5 bg-white hairline rounded-2xl p-5">
            <h2 class="serif text-[20px] mb-4">{{ __('Connected accounts') }}</h2>
            <table class="w-full text-[12px]">
                <thead><tr class="text-left mono text-[9.5px] uppercase tracking-widest text-ink-500 hairline-b"><th class="py-2 font-normal">{{ __('Account') }}</th><th class="py-2 font-normal text-right">{{ __('Status') }}</th><th class="py-2 font-normal text-right">{{ __('View') }}</th></tr></thead>
                <tbody>
                    @foreach ($accounts as $acc)
                        @php $ini = strtoupper(substr($acc->username ?: 'IG', 0, 2)); @endphp
                        <tr class="{{ !$loop->last ? 'hairline-b' : '' }}">
                            <td class="py-3"><div class="flex items-center gap-2.5"><span class="ig-ring"><span class="block w-7 h-7 rounded-full ig-grad-soft text-white text-[10px] font-semibold grid place-items-center">{{ $ini }}</span></span><div><div class="font-semibold">{{ '@'.($acc->username ?: $acc->ig_user_id) }}</div><div class="mono text-[9px] text-ink-500">{{ ucfirst($acc->status ?? 'connected') }}</div></div></div></td>
                            <td class="text-right">
                                @if (($acc->status ?? '') === 'connected')
                                    <span class="pill bg-wa-bubble text-wa-deep !py-0">{{ __('Live') }}</span>
                                @else
                                    <span class="pill bg-amber-100 text-amber-700 !py-0">{{ ucfirst($acc->status ?? 'idle') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ url('/instagram/analytics') }}?account={{ $acc->id }}" class="mono text-[11px] {{ $account && $account->id === $acc->id ? 'ig-text font-semibold' : 'text-ink-500 hover:text-ig-pink' }}">{{ $account && $account->id === $acc->id ? __('viewing') : __('open →') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- DM split bars (real in/out daily, top buckets) --}}
        <div class="col-span-4 bg-white hairline rounded-2xl p-5">
            <h2 class="serif text-[20px] mb-4">{{ __('Busiest days') }}</h2>
            @if ($hasDm)
                @php
                    // Pair each label with its in+out total, take the 5 busiest days.
                    $rows = [];
                    foreach (array_values($labels) as $i => $d) {
                        $rows[] = ['d' => $d, 'in' => $inSeries[$i] ?? 0, 'out' => $outSeries[$i] ?? 0, 't' => ($inSeries[$i] ?? 0) + ($outSeries[$i] ?? 0)];
                    }
                    usort($rows, fn ($a, $b) => $b['t'] <=> $a['t']);
                    $rows = array_slice($rows, 0, 5);
                    $peak = max(1, ($rows[0]['t'] ?? 1));
                @endphp
                <div class="space-y-3">
                    @foreach ($rows as $r)
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1"><span class="font-medium">{{ \Illuminate\Support\Carbon::parse($r['d'])->format('D, d M') }}</span><span class="mono tabular">{{ number_format($r['t']) }}</span></div>
                            <div class="h-1.5 rounded-full bg-paper-100 overflow-hidden"><div class="h-full ig-grad-soft" style="width:{{ max(4, (int) round($r['t'] / $peak * 100)) }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center text-[12px] text-ink-500">{{ __('No DM traffic to rank yet.') }}</div>
            @endif
        </div>

        {{-- DM split funnel (real: received → auto-sent) --}}
        <div class="col-span-3 bg-white hairline rounded-2xl p-5">
            <h2 class="serif text-[20px] mb-4">{{ __('DM split') }}</h2>
            @if ($hasDm)
                @php $totMax = max(1, max($totalIn, $totalOut)); @endphp
                <div class="space-y-1.5">
                    <div class="ig-grad-soft text-white rounded-lg p-2.5" style="width:{{ max(40, (int) round($totalIn / $totMax * 100)) }}%"><div class="flex items-center justify-between"><span class="text-[11px]">{{ __('Received') }}</span><span class="serif text-[16px] tabular">{{ number_format($totalIn) }}</span></div></div>
                    <div class="text-center mono text-[9px] text-ink-400">{{ $totalIn > 0 ? round($deflect).'% '.__('auto-handled') : '' }}</div>
                    <div class="bg-ig-purple/80 text-white rounded-lg p-2.5" style="width:{{ max(40, (int) round($totalOut / $totMax * 100)) }}%"><div class="flex items-center justify-between"><span class="text-[11px]">{{ __('Auto-sent') }}</span><span class="serif text-[16px] tabular">{{ number_format($totalOut) }}</span></div></div>
                </div>
            @else
                <div class="py-8 text-center text-[12px] text-ink-500">{{ __('No DMs to split yet.') }}</div>
            @endif
        </div>
    </section>

    {{-- ===== MORE ANALYTICS ===== --}}
    <section class="max-w-[1500px] mx-auto px-6 pb-6 grid grid-cols-12 gap-3 items-start">
        @php
            // Post engagement from the recent media (real like + comment counts).
            $mediaC = collect($media ?? []);
            $ranked = $mediaC->map(fn ($m) => [
                't'        => $m['thumbnail_url'] ?? ($m['media_url'] ?? ''),
                'likes'    => (int) ($m['like_count'] ?? 0),
                'comments' => (int) ($m['comments_count'] ?? 0),
                'perma'    => $m['permalink'] ?? '#',
                'type'     => $m['media_type'] ?? 'IMAGE',
            ])->map(fn ($m) => $m + ['eng' => $m['likes'] + $m['comments']])
              ->sortByDesc('eng')->values();
            $engPeak    = max(1, (int) ($ranked[0]['eng'] ?? 1));
            $totLikes   = (int) $mediaC->sum(fn ($m) => (int) ($m['like_count'] ?? 0));
            $totComments = (int) $mediaC->sum(fn ($m) => (int) ($m['comments_count'] ?? 0));
            $totEng     = max(1, $totLikes + $totComments);
            // Content mix by type.
            $mix = ['IMAGE' => 0, 'VIDEO' => 0, 'CAROUSEL_ALBUM' => 0];
            foreach ($mediaC as $m) { $tk = $m['media_type'] ?? 'IMAGE'; $mix[$tk] = ($mix[$tk] ?? 0) + 1; }
            $mixLabels = ['IMAGE' => __('Posts'), 'VIDEO' => __('Reels / video'), 'CAROUSEL_ALBUM' => __('Carousels')];
            $hMax = max(1, max($hourly ?? [0]));
            $peakHour = $hourly ? array_search(max($hourly), $hourly) : null;
        @endphp

        {{-- Busiest hours (24-slot) --}}
        <div class="col-span-12 lg:col-span-5 bg-white hairline rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="serif text-[20px]">{{ __('Busiest hours') }}</h2>
                @if (!is_null($peakHour) && ($hourly[$peakHour] ?? 0) > 0)
                    <span class="pill bg-ig-pink/10 text-ig-pink mono">{{ __('Peak') }} {{ sprintf('%02d:00', $peakHour) }}</span>
                @endif
            </div>
            @if (array_sum($hourly ?? []) > 0)
                <div class="flex items-end gap-[3px] h-32">
                    @foreach ($hourly as $h => $c)
                        <div class="flex-1 rounded-t ig-grad-soft min-h-[2px]" style="height:{{ max(2, (int) round($c / $hMax * 100)) }}%" title="{{ sprintf('%02d:00', $h) }} · {{ $c }} {{ __('DMs') }}"></div>
                    @endforeach
                </div>
                <div class="flex justify-between mono text-[9px] text-ink-400 mt-1.5"><span>12a</span><span>6a</span><span>12p</span><span>6p</span><span>11p</span></div>
                <p class="text-[10.5px] text-ink-500 mt-2">{{ __('When your DMs land (last 14 days) — post + reply around the peak for the best response.') }}</p>
            @else
                <div class="py-10 text-center text-[12px] text-ink-500">{{ __('No DM activity to chart yet.') }}</div>
            @endif
        </div>

        {{-- Top posts by engagement --}}
        <div class="col-span-12 lg:col-span-4 bg-white hairline rounded-2xl p-5">
            <h2 class="serif text-[20px] mb-4">{{ __('Top posts') }}</h2>
            @if ($ranked->where('eng', '>', 0)->count())
                <div class="space-y-2.5">
                    @foreach ($ranked->take(5) as $r)
                        <a href="{{ $r['perma'] }}" target="_blank" rel="noopener" class="flex items-center gap-3 group">
                            <span class="w-10 h-10 rounded-lg bg-center bg-cover hairline shrink-0" style="background-image:url('{{ $r['t'] }}')"></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between text-[11px] mb-1">
                                    <span class="mono text-ink-500 flex items-center gap-1">
                                        <svg viewBox="0 0 16 16" class="w-3 h-3 text-ig-pink" fill="currentColor"><path d="M8 14s-5-3.3-5-7a3 3 0 0 1 5-2 3 3 0 0 1 5 2c0 3.7-5 7-5 7z"/></svg>{{ number_format($r['likes']) }}
                                        <svg viewBox="0 0 16 16" class="w-3 h-3 ml-1 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2.5 4.5h11v6h-6l-3 2.5v-2.5h-2z"/></svg>{{ number_format($r['comments']) }}
                                    </span>
                                    <span class="mono tabular font-semibold text-ink-700">{{ number_format($r['eng']) }}</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-paper-100 overflow-hidden"><div class="h-full ig-grad-soft" style="width:{{ max(4, (int) round($r['eng'] / $engPeak * 100)) }}%"></div></div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="py-10 text-center text-[12px] text-ink-500">{{ __('No post engagement yet.') }}</div>
            @endif
        </div>

        {{-- Content mix + likes vs comments --}}
        <div class="col-span-12 lg:col-span-3 bg-white hairline rounded-2xl p-5">
            <h2 class="serif text-[20px] mb-4">{{ __('Content mix') }}</h2>
            @if ($mediaC->count())
                <div class="space-y-2.5 mb-4">
                    @foreach ($mix as $k => $n)
                        @if ($n > 0)
                            <div>
                                <div class="flex items-center justify-between text-[11.5px] mb-1"><span>{{ $mixLabels[$k] ?? $k }}</span><span class="mono tabular">{{ $n }}</span></div>
                                <div class="h-1.5 rounded-full bg-paper-100 overflow-hidden"><div class="h-full ig-grad-soft" style="width:{{ max(6, (int) round($n / max(1, $mediaC->count()) * 100)) }}%"></div></div>
                            </div>
                        @endif
                    @endforeach
                </div>
                <div class="hairline-t pt-3">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-2">{{ __('Likes vs comments') }}</div>
                    <div class="flex h-3 rounded-full overflow-hidden bg-paper-100">
                        <div class="ig-grad-soft" style="width:{{ (int) round($totLikes / $totEng * 100) }}%" title="{{ __('Likes') }} {{ $totLikes }}"></div>
                        <div class="bg-ig-purple/80" style="width:{{ (int) round($totComments / $totEng * 100) }}%" title="{{ __('Comments') }} {{ $totComments }}"></div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] mt-2">
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm ig-grad-soft"></span>{{ __('Likes') }} <b class="tabular">{{ number_format($totLikes) }}</b></span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-ig-purple/80"></span>{{ __('Comments') }} <b class="tabular">{{ number_format($totComments) }}</b></span>
                    </div>
                </div>
            @else
                <div class="py-10 text-center text-[12px] text-ink-500">{{ __('No posts to analyse yet.') }}</div>
            @endif
        </div>
    </section>

    {{-- ===== PER-POST INSIGHTS ===== --}}
    @if (!empty($media))
        <section class="max-w-[1500px] mx-auto px-6 pb-10">
            <div class="bg-white hairline rounded-2xl p-5">
                <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('Recent posts — tap for insights') }}</div>
                <div class="grid grid-cols-4 lg:grid-cols-6 gap-2" data-acct="{{ $account?->id }}">
                    @foreach ($media as $m)
                        @php $t = $m['thumbnail_url'] ?? ($m['media_url'] ?? ''); @endphp
                        <button type="button" class="relative block aspect-square rounded-lg overflow-hidden hairline text-left"
                                data-post-insights data-media="{{ $m['id'] }}" data-type="{{ $m['media_type'] ?? '' }}">
                            @if ($t)<span class="block w-full h-full bg-center bg-cover" style="background-image:url('{{ $t }}')"></span>@else<span class="block w-full h-full bg-paper-100"></span>@endif
                            <span class="absolute bottom-1 right-1 text-[9px] mono px-1 py-0.5 rounded bg-ink-900/70 text-white">♥ {{ (int)($m['like_count'] ?? 0) }}</span>
                        </button>
                    @endforeach
                </div>
                <div id="ig-post-insights" class="hidden mt-4 hairline rounded-xl p-4">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-2" data-pi-title>{{ __('Post insights') }}</div>
                    <div class="grid grid-cols-3 lg:grid-cols-6 gap-3" data-pi-grid></div>
                </div>
            </div>
        </section>
    @endif
    @endif
</x-layouts.instagram>

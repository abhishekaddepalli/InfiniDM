{{-- AI Dashboard tab body — shared by /admin/ai-dashboard and /admin/insights.
     Inline SVG only (no JS chart lib). --}}
@php
    $fmt = function ($n) {
        $n = (int) $n;
        if ($n >= 1000000000) return number_format($n / 1000000000, 2) . 'B';
        if ($n >= 1000000) return number_format($n / 1000000, 2) . 'M';
        if ($n >= 1000) return number_format($n / 1000, 1) . 'k';
        return number_format($n);
    };
    $igStops = ['#833AB4', '#E1306C', '#F77737', '#FCAF45', '#405DE6', '#5851DB', '#C13584'];
    $typeColor = fn ($i) => $igStops[$i % count($igStops)];
    $typeLabel = fn ($t) => ucfirst(str_replace('_', ' ', (string) $t));

    $splitTotal = max(1, ($split['ai'] ?? 0) + ($split['manual'] ?? 0));
    $aiPct = round((($split['ai'] ?? 0) / $splitTotal) * 100);
    $manualPct = 100 - $aiPct;

    $vals = array_values($daily);
    $maxDay = max(1, count($vals) ? max($vals) : 1);
    $n = max(1, count($vals));
    $W = 1000; $H = 200;
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $n > 1 ? ($i / ($n - 1)) * $W : 0;
        $y = $H - ($v / $maxDay) * ($H - 12) - 4;
        $pts[] = round($x, 1) . ',' . round($y, 1);
    }
    $line = implode(' ', $pts);
    $area = $n > 1 ? "0,{$H} " . $line . " {$W},{$H}" : '';
@endphp

<main class="px-4 sm:px-7 py-5 space-y-5">

    {{-- Hero + window switch --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-serif text-[26px] leading-tight">{{ __('AI automation') }} <span class="italic ig-text">{{ __('activity') }}</span></h1>
            <p class="text-[12.5px] text-ink-500 mt-1 max-w-2xl">{{ __('Every AI agent, keyword and comment automation across the platform — how many are live, how often they fire, and which Instagram conversations are handled by AI.') }}</p>
        </div>
        <div class="flex items-center gap-1 bg-paper-0 border border-paper-200 rounded-xl p-1 shadow-card">
            @foreach ($windows as $key => $label)
                <a href="#" onclick="var u=new URL(location);u.searchParams.set('window','{{ $key }}');location=u;return false"
                    class="px-3 py-1.5 rounded-lg text-[12px] font-medium {{ $window === $key ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    {{-- KPI strip --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @php
            $cards = [
                ['label' => __('AI agents live'), 'value' => $fmt($kpis['aiAgents']), 'sub' => __('answering DMs'), 'accent' => 'text-wa-deep'],
                ['label' => __('AI-handled chats'), 'value' => $fmt($kpis['aiContacts']), 'sub' => __('conversations'), 'accent' => 'text-ink-900'],
                ['label' => __('Active automations'), 'value' => $fmt($kpis['automations']), 'sub' => __('rules enabled'), 'accent' => 'text-ink-900'],
                ['label' => __('Total triggers'), 'value' => $fmt($kpis['fired']), 'sub' => __('all-time fires'), 'accent' => 'text-ink-900'],
            ];
        @endphp
        @foreach ($cards as $c)
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ $c['label'] }}</div>
                <div class="font-serif text-[28px] leading-none mt-2 {{ $c['accent'] }}">{{ $c['value'] }}</div>
                <div class="text-[11px] text-ink-400 mt-1.5">{{ $c['sub'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Daily trend --}}
    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Automated replies per day') }}</div>
            <div class="text-[11px] text-ink-400">{{ __('peak') }} {{ $fmt($maxDay) }}/{{ __('day') }}</div>
        </div>
        @if (array_sum($vals) > 0)
            <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none" class="w-full h-44">
                <defs>
                    <linearGradient id="aiStroke" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stop-color="#833AB4" />
                        <stop offset="55%" stop-color="#E1306C" />
                        <stop offset="100%" stop-color="#F77737" />
                    </linearGradient>
                    <linearGradient id="aiFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#E1306C" stop-opacity="0.22" />
                        <stop offset="100%" stop-color="#E1306C" stop-opacity="0" />
                    </linearGradient>
                </defs>
                @if ($area)<polygon points="{{ $area }}" fill="url(#aiFill)" />@endif
                <polyline points="{{ $line }}" fill="none" stroke="url(#aiStroke)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
            </svg>
        @else
            <div class="h-44 grid place-items-center text-[12.5px] text-ink-400">{{ __('No automated replies recorded in this window yet.') }}</div>
        @endif
    </div>

    <div class="grid lg:grid-cols-[1.4fr_1fr] gap-5">
        {{-- Automations by type --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-4">{{ __('Automations by type') }}</div>
            @forelse ($byType as $i => $t)
                <div class="mb-3.5 last:mb-0">
                    <div class="flex items-center justify-between text-[12.5px] mb-1">
                        <span class="font-medium text-ink-900 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-sm" style="background: {{ $typeColor($i) }}"></span>
                            {{ $typeLabel($t['type']) }}
                        </span>
                        <span class="text-ink-500 tabular-nums">{{ $fmt($t['fired']) }} {{ __('fires') }} · {{ $fmt($t['count']) }} {{ __('rules') }}</span>
                    </div>
                    <div class="h-2.5 rounded-full bg-paper-100 overflow-hidden">
                        <div class="h-full rounded-full" style="width: {{ max(2, round(($t['fired'] / $maxFired) * 100)) }}%; background: {{ $typeColor($i) }}"></div>
                    </div>
                </div>
            @empty
                <div class="text-[12.5px] text-ink-400 py-6 text-center">{{ __('No automations configured yet.') }}</div>
            @endforelse
        </div>

        {{-- AI vs manual conversations --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-4">{{ __('Who replies — AI vs human') }}</div>
            <div class="flex h-4 rounded-full overflow-hidden bg-paper-100 mb-4">
                <div class="h-full bg-wa-deep" style="width: {{ $aiPct }}%"></div>
                <div class="h-full bg-paper-300" style="width: {{ $manualPct }}%"></div>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[12.5px] text-ink-700 flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm bg-wa-deep"></span>{{ __('AI-handled') }}</span>
                    <span class="text-[12.5px] tabular-nums text-ink-900 font-medium">{{ $fmt($split['ai']) }} <span class="text-ink-400">({{ $aiPct }}%)</span></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[12.5px] text-ink-700 flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm bg-paper-300"></span>{{ __('Human / manual') }}</span>
                    <span class="text-[12.5px] tabular-nums text-ink-900 font-medium">{{ $fmt($split['manual']) }} <span class="text-ink-400">({{ $manualPct }}%)</span></span>
                </div>
                <div class="pt-3 mt-1 border-t border-paper-200 text-[11.5px] text-ink-500 leading-relaxed">
                    {{ __('AI-handled conversations are answered automatically by an assigned AI agent. Everything else waits for a human in the inbox.') }}
                </div>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-5">
        {{-- Top AI agents --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Busiest AI agents') }}</div>
            <div class="divide-y divide-paper-100">
                @forelse ($topAgents as $a)
                    <div class="flex items-center justify-between px-5 py-3">
                        <div class="min-w-0 flex items-center gap-2.5">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $a['active'] ? 'bg-wa-green' : 'bg-ink-300' }}"></span>
                            <div class="min-w-0">
                                <div class="text-[13px] font-medium text-ink-900 truncate">{{ $a['name'] }}</div>
                                <div class="text-[11px] text-ink-400 mt-0.5">{{ $a['active'] ? __('Live') : __('Paused') }}</div>
                            </div>
                        </div>
                        <div class="text-[14px] font-serif text-wa-deep tabular-nums shrink-0 ml-3">{{ $fmt($a['fired']) }}</div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-[12.5px] text-ink-400">{{ __('No AI agents yet.') }}</div>
                @endforelse
            </div>
        </div>

        {{-- Top automations --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Top automations') }}</div>
            <div class="divide-y divide-paper-100">
                @forelse ($topAutomations as $m)
                    <div class="flex items-center justify-between px-5 py-3">
                        <div class="min-w-0">
                            <div class="text-[13px] font-medium text-ink-900 truncate">{{ $m['name'] }}</div>
                            <div class="text-[11px] text-ink-400 mt-0.5">{{ $typeLabel($m['type']) }} · {{ $fmt($m['fired']) }} {{ __('fires') }}</div>
                        </div>
                        <div class="text-[13.5px] font-serif text-ink-900 tabular-nums shrink-0 ml-3">{{ $fmt($m['fired']) }}</div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-[12.5px] text-ink-400">{{ __('No automations yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Per-account AI status --}}
    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
        <div class="flex items-center justify-between mb-4">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('AI coverage by account') }}</div>
            <a href="{{ route('admin.overview') }}" class="text-[11.5px] text-wa-deep font-semibold hover:underline">{{ __('All accounts') }}</a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @forelse ($accounts as $ac)
                <div class="flex items-center gap-3 border border-paper-200 rounded-xl px-3.5 py-3">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $ac['active'] ? 'bg-wa-green' : 'bg-ink-300' }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[13px] font-medium text-ink-900 truncate">{{ '@' . ltrim($ac['name'], '@') }}</div>
                        <div class="text-[11px] text-ink-400 truncate">{{ $fmt($ac['autos']) }} {{ __('automations') }} · {{ $fmt($ac['ai']) }} {{ __('AI chats') }}</div>
                    </div>
                    <span class="text-[10px] font-mono uppercase tracking-wide px-2 py-0.5 rounded {{ $ac['active'] ? 'bg-wa-mint text-wa-deep' : 'bg-paper-100 text-ink-500' }}">{{ $ac['active'] ? __('On') : __('Off') }}</span>
                </div>
            @empty
                <div class="text-[12.5px] text-ink-400 col-span-full py-2">{{ __('No Instagram accounts connected yet.') }}</div>
            @endforelse
        </div>
        <div class="mt-4 pt-4 border-t border-paper-200 flex flex-wrap items-center gap-x-8 gap-y-2 text-[12.5px]">
            <div><span class="text-ink-500">{{ __('AI-handled chats') }}:</span> <span class="font-semibold text-ink-900">{{ $fmt($split['ai']) }}</span></div>
            <div><span class="text-ink-500">{{ __('Total conversations') }}:</span> <span class="font-semibold text-ink-900">{{ $fmt($totalCon) }}</span></div>
            <div><span class="text-ink-500">{{ __('AI agents live') }}:</span> <span class="font-semibold text-ink-900">{{ $fmt($kpis['aiAgents']) }}</span></div>
        </div>
    </div>

</main>

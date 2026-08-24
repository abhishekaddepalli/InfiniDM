<x-layouts.instagram :title="__('Automation analytics')" ig-active="automations" page="instagram-automations-analytics">

    @php
        // Visual styling per trigger type — mirrors the index typeMeta so the
        // bars / pills read identically across the suite.
        $typeMeta = [
            'dm_keyword'    => ['label' => __('DM keyword'),   'pill' => 'bg-ig-pink/10 text-ig-pink',      'bar' => '#E1306C'],
            'comment_to_dm' => ['label' => __('Comment → DM'), 'pill' => 'bg-ig-magenta/10 text-ig-magenta','bar' => '#C13584'],
            'story_reply'   => ['label' => __('Story reply'),  'pill' => 'bg-ig-purple/10 text-ig-purple',  'bar' => '#833AB4'],
            'mention'       => ['label' => __('Mention'),      'pill' => 'bg-ig-orange/10 text-ig-orange',  'bar' => '#F77737'],
            'ai_agent'      => ['label' => __('AI agent'),     'pill' => 'bg-ig-blue/10 text-ig-blue',      'bar' => '#3897F0'],
            'flow'          => ['label' => __('Visual flow'),  'pill' => 'bg-ig-blue/10 text-ig-blue',      'bar' => '#3897F0'],
        ];

        $totalRules = $automations->count();
        $avgFired   = $totalRules ? $totalFired / $totalRules : 0;
        // Bar scale = the busiest trigger type, so the widest bar fills the track.
        $maxFired   = (int) (collect($byType)->max('fired') ?: 0);
    @endphp

    {{-- ===== HEADER ===== --}}
    <section class="w-full px-7 pt-7 pb-4 flex items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 mb-1.5 text-[11px] mono uppercase tracking-[0.18em] text-ink-500">
                <a href="{{ route('instagram.automations') }}" class="flex items-center gap-1.5 hover:text-ig-pink">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 3 5 8l5 5"/></svg>{{ __('Automations') }}
                </a>
                <span class="w-1 h-1 rounded-full bg-ink-400"></span>
                <span>{{ __('Analytics') }}</span>
            </div>
            <h1 class="serif text-[40px] leading-none">{{ __('Automation') }} <span class="ig-text italic">{{ __('analytics') }}</span></h1>
        </div>
        <a href="{{ route('instagram.automations') }}" class="px-5 py-2.5 rounded-full hairline bg-white text-[12.5px] font-semibold flex items-center gap-2 hover:bg-paper-50 shrink-0">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 3 5 8l5 5"/></svg>{{ __('Back to rules') }}
        </a>
    </section>

    @if ($automations->isEmpty())
        {{-- ===== EMPTY STATE ===== --}}
        <section class="w-full px-7 pb-8">
            <div class="bg-white hairline rounded-2xl p-12 text-center">
                <span class="ig-grad-soft w-14 h-14 rounded-2xl grid place-items-center text-white mx-auto mb-3"><svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19V5M4 19h16M8 16v-4M12 16V8M16 16v-6"/></svg></span>
                <div class="serif text-[24px]">{{ __('Nothing to measure yet') }}</div>
                <p class="text-[12.5px] text-ink-500 mt-1">{{ __('Build a few auto-reply rules — once they start firing, this page charts which triggers convert best.') }}</p>
                <a href="{{ route('instagram.automations') }}" class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('Create a rule') }}
                </a>
            </div>
        </section>
    @else

    {{-- ===== KPI STRIP ===== --}}
    <section class="w-full px-7 pb-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white hairline rounded-2xl p-4">
            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Total fired') }}</div>
            <div class="serif text-[32px] leading-none mt-1.5 tabular ig-text">{{ number_format($totalFired) }}</div>
            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('DMs auto-sent across all rules') }}</div>
        </div>
        <div class="bg-white hairline rounded-2xl p-4">
            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Active rules') }}</div>
            <div class="serif text-[32px] leading-none mt-1.5 tabular">{{ number_format($activeCount) }}</div>
            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('currently live and listening') }}</div>
        </div>
        <div class="bg-white hairline rounded-2xl p-4">
            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Total rules') }}</div>
            <div class="serif text-[32px] leading-none mt-1.5 tabular">{{ number_format($totalRules) }}</div>
            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('including paused rules') }}</div>
        </div>
        <div class="bg-white hairline rounded-2xl p-4">
            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Avg fired per rule') }}</div>
            <div class="serif text-[32px] leading-none mt-1.5 tabular">{{ number_format($avgFired, $avgFired >= 10 ? 0 : 1) }}</div>
            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('mean sends per configured rule') }}</div>
        </div>
    </section>

    {{-- ===== BY TRIGGER TYPE ===== --}}
    <section class="w-full px-7 pb-4">
        <div class="bg-white hairline rounded-2xl overflow-hidden">
            <div class="px-5 py-3 hairline-b flex items-center justify-between">
                <span class="text-[14px] font-semibold">{{ __('By trigger type') }}</span>
                <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('fired count') }}</span>
            </div>
            <div class="p-5 space-y-3.5">
                @foreach ($byType as $type => $stat)
                    @php
                        $m     = $typeMeta[$type] ?? $typeMeta['dm_keyword'];
                        $fired = (int) $stat['fired'];
                        // Bar fill is relative to the busiest type; min 2% so a
                        // non-zero value is always visible, 0 stays empty.
                        $pct   = $maxFired > 0 ? max($fired > 0 ? 2 : 0, round($fired / $maxFired * 100)) : 0;
                        $share = $totalFired > 0 ? round($fired / $totalFired * 100) : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between gap-3 mb-1.5">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="pill {{ $m['pill'] }}">{{ $m['label'] }}</span>
                                <span class="text-[11px] text-ink-500 truncate">{{ trans_choice('{1}:count rule|[2,*]:count rules', $stat['count'], ['count' => $stat['count']]) }} · {{ $stat['active'] }} {{ __('active') }}</span>
                            </div>
                            <div class="flex items-baseline gap-2 shrink-0">
                                <span class="tabular text-[13px] font-semibold">{{ number_format($fired) }}</span>
                                <span class="mono text-[10px] text-ink-500">{{ $share }}%</span>
                            </div>
                        </div>
                        <div class="w-full h-2 rounded-full bg-paper-100 overflow-hidden" role="img" aria-label="{{ $m['label'] }}: {{ $fired }}">
                            @if ($pct > 0)
                                <div class="h-full rounded-full transition-all" style="width: {{ $pct }}%; background: {{ $m['bar'] }};"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== TOP PERFORMING RULES ===== --}}
    <section class="w-full px-7 pb-8">
        <div class="bg-white hairline rounded-2xl overflow-hidden">
            <div class="px-5 py-3 hairline-b flex items-center justify-between">
                <span class="text-[14px] font-semibold">{{ __('Top performing rules') }}</span>
                <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('top 10 by fired') }}</span>
            </div>

            @if ($top->where('fired_count', '>', 0)->isEmpty())
                <div class="px-5 py-14 text-center">
                    <div class="serif text-[20px] leading-none">{{ __('No rule has fired yet') }}</div>
                    <p class="text-[12.5px] text-ink-500 mt-1.5">{{ __('Once a DM, comment or story reply matches one of your rules, it ranks here.') }}</p>
                </div>
            @else
                <table class="w-full text-[12.5px]">
                    <thead>
                        <tr class="text-left mono text-[9.5px] uppercase tracking-widest text-ink-500 hairline-b">
                            <th class="pl-5 py-2.5 font-normal w-8">#</th>
                            <th class="py-2.5 font-normal">{{ __('Rule') }}</th>
                            <th class="py-2.5 font-normal">{{ __('Trigger') }}</th>
                            <th class="py-2.5 font-normal">{{ __('Account') }}</th>
                            <th class="py-2.5 font-normal text-right">{{ __('Fired') }}</th>
                            <th class="py-2.5 font-normal text-center pr-5">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @foreach ($top as $i => $a)
                            @php
                                $m        = $typeMeta[$a->type] ?? $typeMeta['dm_keyword'];
                                $acc      = $a->account;
                                $accName  = $acc ? '@'.($acc->username ?: $acc->ig_user_id) : __('account removed');
                                $ruleName = $a->name ?: ($a->trigger_keyword ?: $m['label']);
                            @endphp
                            <tr class="{{ $a->is_active ? '' : 'opacity-65' }}">
                                <td class="pl-5 py-3.5 mono text-[11px] text-ink-400 tabular">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                                <td class="py-3.5"><span class="font-semibold truncate max-w-[260px] inline-block align-middle">{{ $ruleName }}</span></td>
                                <td><span class="pill {{ $m['pill'] }}">{{ $m['label'] }}</span></td>
                                <td class="text-ink-600 truncate max-w-[170px]">{{ $accName }}</td>
                                <td class="text-right tabular font-semibold ig-text">{{ number_format((int) $a->fired_count) }}</td>
                                <td class="text-center pr-5">
                                    @if ($a->is_active)
                                        <span class="pill bg-wa-green/10 text-wa-deep inline-flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('Active') }}
                                        </span>
                                    @else
                                        <span class="pill bg-ink-900/5 text-ink-500">{{ __('Paused') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    @endif
</x-layouts.instagram>

{{-- Analytics tab body — shared by /admin/analytics and /admin/insights.
     NOTE: the plan donut id is #chart-plans-analytics (Overview owns #chart-plans;
     both live on the merged page, so the ids must differ). --}}
<main class="px-4 sm:px-7 py-7 space-y-5">

    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Platform') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Admin') }}
                <span class="italic ig-text">{{ __('analytics') }}</span>.</h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('A control-room view of growth, connected accounts, DM volume, automations, and commerce.') }}</p>
        </div>
        <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
            <select onchange="var u=new URL(location);u.searchParams.set('days',this.value);location=u"
                class="px-3.5 py-2 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                <option value="7" @selected($days == 7)>{{ __('Last 7 days') }}</option>
                <option value="30" @selected($days == 30)>{{ __('Last 30 days') }}</option>
                <option value="90" @selected($days == 90)>{{ __('Last 90 days') }}</option>
                <option value="365" @selected($days == 365)>{{ __('This year') }}</option>
            </select>
        </div>
    </div>

    {{-- Sub-tab bar (Overview / Messaging / Accounts / Commerce) --}}
    <section class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex items-center gap-1 shadow-card overflow-x-auto" data-wa-tabs>
        @foreach (['overview' => __('Overview'), 'messaging' => __('Messaging'), 'accounts' => __('Accounts'), 'commerce' => __('Commerce')] as $k => $label)
            <button data-wa-tab="{{ $k }}" class="inline-flex shrink-0 whitespace-nowrap items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] font-semibold {{ $loop->first ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">{{ $label }}</button>
        @endforeach
        <div class="flex-1"></div>
        <span class="inline-flex shrink-0 whitespace-nowrap items-center gap-1.5 px-3 py-1.5 rounded-full bg-wa-mint text-wa-deep text-[11px] font-mono border border-wa-green/40">
            <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('refreshed just now') }}
        </span>
    </section>

    {{-- KPI row (6) --}}
    <section class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3" data-wa-tab-panel="overview">
        @php
            $kpiCards = [
                ['label' => __('Customers'),   'value' => $kpi['customers'],   'pill' => '+' . $kpi['customers_new'], 'sub' => number_format($kpi['users_total']) . ' ' . __('total users'), 'accent' => 'wa-green'],
                ['label' => __('Accounts'),    'value' => $kpi['accounts'],    'pill' => ($kpi['accounts'] ? round($kpi['accounts_online'] / max(1, $kpi['accounts']) * 100) . '%' : '—'), 'sub' => number_format($kpi['accounts_online']) . ' ' . __('connected'), 'accent' => 'wa-green'],
                ['label' => __('DMs · :d d', ['d' => $days]), 'value' => $kpi['dms_window'], 'pill' => $days . 'd', 'sub' => number_format($kpi['dms_total']) . ' ' . __('all time'), 'accent' => 'paper'],
                ['label' => __('Automations'), 'value' => $kpi['automations'], 'pill' => __('live'), 'sub' => __('keyword & comment'), 'accent' => 'paper'],
                ['label' => __('Flows'),       'value' => $kpi['flows'],       'pill' => __('built'), 'sub' => __('across platform'), 'accent' => 'paper'],
                ['label' => __('Leads'),       'value' => $kpi['leads'],       'pill' => __(':n orders', ['n' => $kpi['orders']]), 'sub' => __('captured'), 'accent' => 'paper'],
            ];
        @endphp
        @foreach ($kpiCards as $c)
            <div class="bg-paper-0 border {{ $c['accent'] === 'wa-green' ? 'border-wa-green/40' : 'border-paper-200' }} rounded-2xl p-4 shadow-card">
                <div class="flex items-start justify-between">
                    <div class="text-[11px] text-ink-600 font-medium">{{ $c['label'] }}</div>
                    <span class="rounded-full bg-wa-bubble text-wa-deep px-2 py-0.5 text-[10px] font-mono">{{ $c['pill'] }}</span>
                </div>
                <div class="font-serif text-[31px] leading-none mt-2">{{ number_format($c['value']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $c['sub'] }}</div>
            </div>
        @endforeach
    </section>

    {{-- Growth + pulse --}}
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview messaging accounts">
        <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-start justify-between gap-4 mb-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Growth command center') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('New signups, last :d days', ['d' => $days]) }}</h2>
                    <div class="flex items-center gap-4 mt-2 text-[11px] text-ink-500">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-wa-deep"></span>{{ __('Signups') }}</span>
                    </div>
                </div>
            </div>
            <div id="chart-platform-growth" data-height="315"></div>
        </div>

        <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-start justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Live health') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Platform pulse') }}</h2>
                </div>
                <span class="inline-flex items-center gap-1 rounded-full bg-wa-mint text-wa-deep px-2 py-0.5 text-[10px] font-mono border border-wa-green/40"><span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('normal') }}</span>
            </div>
            @php
                $pulse = [
                    [__('Customers'), $kpi['customers'], min(100, $kpi['customers']), 'bg-wa-deep'],
                    [__('Accounts connected'), ($kpi['accounts'] ? round($kpi['accounts_online'] / max(1, $kpi['accounts']) * 100) : 0) . '%', ($kpi['accounts'] ? round($kpi['accounts_online'] / max(1, $kpi['accounts']) * 100) : 0), 'bg-wa-deep'],
                    [__('New signups · :d d', ['d' => $days]), $kpi['users_new'], min(100, $kpi['users_new'] * 5), 'bg-wa-teal'],
                    [__('Automations live'), $kpi['automations'], min(100, $kpi['automations'] * 10), 'bg-accent-amber'],
                ];
            @endphp
            <div class="mt-4 space-y-3">
                @foreach ($pulse as [$lbl, $val, $pct, $bar])
                    <div class="rounded-xl border border-paper-200 p-3">
                        <div class="flex items-center justify-between text-[12.5px]"><span class="font-semibold">{{ $lbl }}</span><span class="font-mono text-wa-deep">{{ $val }}</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden mt-2"><div class="h-full {{ $bar }}" style="width: {{ $pct }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Donut + volume + top accounts --}}
    <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview messaging accounts commerce">
        <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-start justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Plan distribution') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Customers by plan') }}</h2>
                </div>
                <span class="font-mono text-[11px] text-ink-500">{{ __('live') }}</span>
            </div>
            <div id="chart-plans-analytics" class="mt-1"></div>
        </div>

        <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-start justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('DM traffic') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Message volume') }}</h2>
                </div>
                <div class="flex items-center gap-3 text-[11px] font-mono text-ink-500">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-wa-deep"></span>{{ __('in') }}</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-ink-300"></span>{{ __('out') }}</span>
                </div>
            </div>
            <div id="chart-volume" class="mt-1"></div>
        </div>

        <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-start justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Reach') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Top accounts') }}</h2>
                </div>
                <a href="{{ route('admin.overview') }}" class="text-[12px] font-semibold text-wa-deep hover:underline">{{ __('All') }}</a>
            </div>
            <div id="chart-top-accounts" class="mt-1"></div>
        </div>
    </section>

    {{-- Leaderboard + acquisition --}}
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview accounts">
        <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Account leaderboard') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Top connected accounts by followers') }}</h2>
                </div>
                <a href="{{ route('admin.overview') }}" class="rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 py-1.5 text-[11.5px] font-semibold">{{ __('View all') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[560px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-3">{{ __('Account') }}</th>
                            <th class="text-left px-3 py-3 w-[120px]">{{ __('Login') }}</th>
                            <th class="text-right px-3 py-3 w-[130px]">{{ __('Followers') }}</th>
                            <th class="text-center px-4 py-3 w-[110px]">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($topAccounts as $a)
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="w-8 h-8 rounded-full ig-grad text-white grid place-items-center text-[11px] font-bold shrink-0">{{ mb_strtoupper(mb_substr($a->username ?: $a->ig_user_id, 0, 2)) }}</span>
                                        <div class="min-w-0">
                                            <div class="font-semibold truncate">@ {{ $a->username ?: $a->ig_user_id }}</div>
                                            <div class="text-[10.5px] text-ink-500 truncate">{{ $a->name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-[11.5px]">{{ ucfirst($a->login_type ?: '—') }}</td>
                                <td class="px-3 py-3 text-right font-mono text-wa-deep">{{ number_format((int) ($a->followers_count ?? 0)) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $a->status === 'connected' ? 'bg-wa-mint text-wa-deep' : 'bg-paper-100 text-ink-500' }}">{{ ucfirst($a->status ?: '—') }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-ink-500 text-[13px]">{{ __('No accounts connected yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Acquisition') }}</div>
            <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Users → Accounts') }}</h2>
            @php
                $uTot = max(0, (int) $kpi['users_total']);
                $uNew = max(0, (int) $kpi['users_new']);
                $accTot = max(0, (int) $kpi['accounts']);
                $denom = max(1, $uTot);
            @endphp
            <div class="space-y-3 text-[12px]">
                <div>
                    <div class="flex items-center justify-between mb-1"><span>{{ __('Total users') }}</span><span class="font-mono">{{ number_format($uTot) }}</span></div>
                    <div class="h-2 bg-paper-100 rounded-full overflow-hidden"><div class="h-full bg-wa-deep w-full"></div></div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1"><span>{{ __('New users · :d d', ['d' => $days]) }}</span><span class="font-mono">{{ number_format($uNew) }} · {{ round(($uNew / $denom) * 100, 1) }}%</span></div>
                    <div class="h-2 bg-paper-100 rounded-full overflow-hidden"><div class="h-full bg-wa-deep" style="width: {{ min(100, ($uNew / $denom) * 100) }}%"></div></div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1"><span>{{ __('Accounts connected') }}</span><span class="font-mono">{{ number_format($accTot) }}</span></div>
                    <div class="h-2 bg-paper-100 rounded-full overflow-hidden"><div class="h-full bg-wa-teal" style="width: {{ min(100, ($accTot / $denom) * 100) }}%"></div></div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1"><span>{{ __('Leads captured') }}</span><span class="font-mono">{{ number_format($kpi['leads']) }}</span></div>
                    <div class="h-2 bg-paper-100 rounded-full overflow-hidden"><div class="h-full bg-accent-amber" style="width: {{ min(100, $kpi['leads']) }}%"></div></div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1"><span>{{ __('Orders') }}</span><span class="font-mono text-wa-deep">{{ number_format($kpi['orders']) }}</span></div>
                    <div class="h-2 bg-paper-100 rounded-full overflow-hidden"><div class="h-full bg-accent-coral" style="width: {{ min(100, $kpi['orders'] * 5) }}%"></div></div>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-paper-200 grid grid-cols-2 gap-3 text-[12px]">
                <div><div class="font-serif text-[24px] leading-none">{{ number_format($kpi['users_new']) }}</div><div class="text-[10.5px] text-ink-500 mt-1">{{ __('new users · :d d', ['d' => $days]) }}</div></div>
                <div><div class="font-serif text-[24px] leading-none">{{ number_format($kpi['accounts']) }}</div><div class="text-[10.5px] text-ink-500 mt-1">{{ __('accounts total') }}</div></div>
            </div>
        </div>
    </section>

    {{-- Chart data --}}
    <script>
        window.adminAnalytics = {
            growth: {
                labels: @json($signupSeries->pluck('d')),
                values: @json($signupSeries->pluck('n')->map(fn ($n) => (int) $n)),
            },
            volume: {
                labels: @json(collect($series)->pluck('day')),
                in: @json(collect($series)->pluck('in')->map(fn ($n) => (int) $n)),
                out: @json(collect($series)->pluck('out')->map(fn ($n) => (int) $n)),
            },
            plans: {
                labels: @json($byPlan->pluck('name')),
                series: @json($byPlan->pluck('users_count')->map(fn ($n) => (int) $n)),
            },
            topAccounts: {
                labels: @json($topAccounts->map(fn ($a) => '@' . ($a->username ?: $a->ig_user_id))),
                values: @json($topAccounts->map(fn ($a) => (int) ($a->followers_count ?? 0))),
            },
        };
    </script>
</main>

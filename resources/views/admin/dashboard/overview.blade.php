{{-- Overview tab body — shared by /admin (standalone) and /admin/insights (tab). --}}
<main class="px-4 sm:px-7 py-7 space-y-5">

    {{-- Heading --}}
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Workspace · Admin') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[40px] leading-[1.0]">{{ __('Admin') }}
                <span class="italic ig-text">{{ __('dashboard') }}</span>.</h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Platform health, growth, usage, and account activity in one place.') }}</p>
        </div>
        <div class="flex items-center gap-2 shrink-0 pb-1 flex-wrap">
            <a href="{{ route('admin.packages.index') }}" class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Packages') }}</a>
            <a href="{{ route('admin.settings') }}" class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-medium hover:opacity-90 flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="8" cy="8" r="2"/><path d="M8 1v2M8 13v2M1 8h2M13 8h2"/></svg>{{ __('Settings') }}
            </a>
        </div>
    </div>

    {{-- KPI row — 4 cards with signed % delta pills --}}
    @php
        $kpiCards = [
            ['key' => 'customers', 'label' => __('Customers')],
            ['key' => 'accounts',  'label' => __('Connected accounts')],
            ['key' => 'leads',     'label' => __('Leads captured')],
            ['key' => 'orders',    'label' => __('Orders')],
        ];
    @endphp
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($kpiCards as $card)
            @php $k = $kpis[$card['key']]; @endphp
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-500">{{ $card['label'] }}</div>
                <div class="font-semibold text-[24px] mt-2">{{ number_format($k['value']) }}</div>
                <div class="mt-2 inline-flex items-center gap-1 rounded-full {{ $k['positive'] ? 'bg-wa-bubble text-wa-deep' : 'bg-accent-coral/10 text-accent-coral' }} px-2 py-0.5 text-[10px] font-mono">
                    {{ ($k['positive'] ? '+' : '') . number_format($k['delta'], 1) }}%
                </div>
                <span class="text-[10.5px] text-ink-400 ml-1">{{ __('vs last month') }}</span>
            </div>
        @endforeach
    </section>

    {{-- Growth + plan mix --}}
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-8 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-start justify-between gap-4 mb-3">
                <div>
                    <h2 class="font-semibold text-[14px]">{{ __('Signups over time') }}</h2>
                    <div class="flex items-center gap-5 mt-2 text-[11px]">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full ig-grad-soft"></span>{{ __('New accounts') }}
                            <b>{{ number_format(collect($signups)->sum('n')) }}</b></span>
                        <span class="text-ink-500">{{ __('last 14 days') }}</span>
                    </div>
                </div>
            </div>
            <div id="chart-signups" class="mt-1"></div>
        </div>

        <div class="lg:col-span-4 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h2 class="font-semibold text-[14px]">{{ __('Customers by plan') }}</h2>
                    <p class="text-[11px] text-ink-500 mt-1">{{ __('Active subscriptions') }}</p>
                </div>
            </div>
            <div id="chart-plans"></div>
        </div>
    </section>

    {{-- Chart data --}}
    <script>
        window.adminOverview = {
            signups: {
                labels: @json(collect($signups)->pluck('day')),
                values: @json(collect($signups)->pluck('n')),
            },
            plans: {
                labels: @json($plans->pluck('name')),
                series: @json($plans->pluck('users_count')->map(fn ($n) => (int) $n)),
            },
        };
    </script>

    {{-- Three secondary tiles --}}
    <section class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        @foreach ($secondary as $s)
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="font-semibold text-[14px]">{{ $s['label'] }}</h2>
                        <p class="text-[11px] text-ink-500 mt-1">{{ $s['hint'] }}</p>
                    </div>
                </div>
                <div class="font-serif text-[40px] leading-none mt-4">{{ number_format($s['value']) }}</div>
            </div>
        @endforeach
    </section>

    {{-- Activity table + alerts --}}
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-8 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-[14px]">{{ __('Recent signups') }}</h2>
                    <p class="text-[11px] text-ink-500 mt-1">{{ __('Newest accounts on the platform') }}</p>
                </div>
                <a href="{{ route('admin.users') }}" class="rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 py-1.5 text-[11.5px] font-semibold">{{ __('View all') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[640px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-3">{{ __('Name & email') }}</th>
                            <th class="text-left px-3 py-3 w-[160px]">{{ __('Plan') }}</th>
                            <th class="text-left px-3 py-3 w-[140px]">{{ __('Joined') }}</th>
                            <th class="text-center px-3 py-3 w-[90px]">{{ __('Role') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($recent as $u)
                            @php
                                $initials = collect(explode(' ', trim((string) $u->name)))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
                                $initials = $initials !== '' ? mb_strtoupper($initials) : '?';
                            @endphp
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="w-8 h-8 rounded-full ig-grad text-white grid place-items-center text-[11px] font-bold shrink-0">{{ $initials }}</span>
                                        <div class="min-w-0">
                                            <div class="font-semibold leading-tight truncate">{{ $u->name }}</div>
                                            <div class="text-[10.5px] text-ink-500 font-mono truncate">{{ $u->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3"><span class="px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-semibold">{{ $u->package?->name ?? __('No plan') }}</span></td>
                                <td class="px-3 py-3 font-mono text-[10.5px] text-ink-600">{{ $u->created_at?->diffForHumans() }}</td>
                                <td class="px-3 py-3 text-center">
                                    @if ($u->is_admin)
                                        <span class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Admin') }}</span>
                                    @else
                                        <span class="text-[11px] text-ink-500">{{ __('Customer') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-ink-500">{{ __('No accounts yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="lg:col-span-4 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-[14px]">{{ __('Admin alerts') }}</h2>
                    <p class="text-[11px] text-ink-500 mt-1">{{ __('Priority queue') }}</p>
                </div>
                <span class="rounded-full bg-accent-coral/10 text-accent-coral border border-accent-coral/30 px-2 py-0.5 text-[10px] font-semibold">{{ count($alerts) }} {{ __('open') }}</span>
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($alerts as $a)
                    @php $sev = $a['severity'] ?? 'low'; @endphp
                    <div class="rounded-xl p-3 {{ $sev === 'high' ? 'border border-accent-coral/30 bg-accent-coral/10' : 'border border-paper-200' }}">
                        <div class="text-[13px] font-semibold">{{ $a['title'] }}</div>
                        <div class="text-[11.5px] text-ink-600 mt-1">{{ $a['detail'] }}</div>
                    </div>
                @empty
                    <div class="text-[12px] text-ink-500 px-1 py-3">{{ __('All systems healthy — no alerts.') }}</div>
                @endforelse
            </div>
        </div>
    </section>

</main>

{{-- Premium tab body — shared by /admin/premium and /admin/insights. --}}
<main class="px-4 sm:px-7 py-7 space-y-5">

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Plans & upsell') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[40px] leading-[1.0]">{{ __('Premium') }}
                <span class="italic ig-text">{{ __('plans') }}</span>.</h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Subscriber mix, revenue per plan, and new paid signups over time.') }}</p>
        </div>
        <div class="flex items-center gap-2 shrink-0 pb-1">
            <a href="{{ route('admin.packages.index') }}" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-medium hover:bg-wa-teal">{{ __('Manage packages') }}</a>
        </div>
    </div>

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
            <div class="text-[11px] text-ink-500">{{ __('Premium subscribers') }}</div>
            <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($kpis['subscribers']) }}</div>
            <div class="text-[11px] text-wa-deep mt-2">{{ __('on featured plans') }}</div>
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
            <div class="text-[11px] text-ink-500">{{ __('Featured plans') }}</div>
            <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($kpis['featured']) }}</div>
            <div class="text-[11px] text-ink-500 mt-2">{{ __('highlighted') }}</div>
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
            <div class="text-[11px] text-ink-500">{{ __('Paid plans') }}</div>
            <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($kpis['paidPlans']) }}</div>
            <div class="text-[11px] text-ink-500 mt-2">{{ __('in catalog') }}</div>
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
            <div class="text-[11px] text-ink-500">{{ __('Premium share') }}</div>
            <div class="font-serif text-[34px] leading-none mt-1">{{ $kpis['conversion'] }}</div>
            <div class="text-[11px] text-ink-500 mt-2">{{ __('of all subscribers') }}</div>
        </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-5 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <h2 class="font-semibold text-[14px]">{{ __('Subscriber mix') }}</h2>
            <p class="text-[11px] text-ink-500 mt-1">{{ __('Customers by plan') }}</p>
            <div id="premium-mix-chart"></div>
        </div>
        <div class="lg:col-span-7 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <h2 class="font-semibold text-[14px]">{{ __('Revenue per plan') }}</h2>
            <p class="text-[11px] text-ink-500 mt-1">{{ __('Price × active subscribers') }}</p>
            <div id="premium-revenue-chart"></div>
        </div>
    </section>

    <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
        <h2 class="font-semibold text-[14px]">{{ __('New paid signups') }}</h2>
        <p class="text-[11px] text-ink-500 mt-1">{{ __('Last 14 days') }}</p>
        <div id="premium-upgrades-chart"></div>
    </section>

    <section>
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold text-[14px]">{{ __('Featured plans') }}</h2>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @forelse ($featured as $p)
                <div class="bg-paper-0 border-2 border-wa-deep rounded-2xl p-5 shadow-card relative">
                    <span class="absolute -top-3 left-5 px-2.5 py-0.5 rounded-full bg-wa-deep text-paper-0 text-[10px] font-semibold uppercase tracking-wider">{{ __('Most popular') }}</span>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">#{{ $p->id }}</div>
                    <h3 class="font-serif text-[24px] leading-none">{{ $p->name }}</h3>
                    <div class="mt-3 flex items-baseline gap-1">
                        <span class="font-serif text-[28px]">{{ (float) $p->price <= 0 ? __('Free') : $p->currency . ' ' . number_format((float) $p->price, 2) }}</span>
                        @if ((float) $p->price > 0)<span class="text-[12px] text-ink-500">/ {{ $p->interval }}</span>@endif
                    </div>
                    <div class="mt-5 pt-4 border-t border-paper-200 flex items-center justify-between text-[11px] font-mono text-ink-500">
                        <span class="{{ $p->users_count > 0 ? 'text-wa-deep font-semibold' : '' }}">{{ $p->users_count }} {{ $p->users_count === 1 ? __('subscriber') : __('subscribers') }}</span>
                        <a href="{{ route('admin.packages.edit', $p->id) }}" class="ig-text font-semibold">{{ __('Edit') }} →</a>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center text-ink-500 py-10">
                    {{ __('No featured plans yet.') }} <a href="{{ route('admin.packages.index') }}" class="ig-text font-semibold">{{ __('Mark a plan as featured →') }}</a>
                </div>
            @endforelse
        </div>
    </section>

    <script>
        window.IG_CURRENCY = @json($cur);
        window.adminPremium = {
            planMix: @json($planMix),
            planRevenue: @json($planRevenue),
            upgradesDaily: @json($upgradesDaily),
        };
    </script>
</main>

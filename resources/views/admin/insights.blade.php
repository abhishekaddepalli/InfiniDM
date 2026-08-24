{{--
 /admin/insights — unified admin dashboard. Overview, Financial, Premium,
 Analytics and AI on one page with client-side tabs. Each panel's data is
 passed scoped into its shared partial (the tabs reuse $kpis/$plans/$window,
 so scopes must stay isolated). admin-charts.js lazy-renders a tab's charts
 the first time it is shown (hidden panels have 0 width otherwise).
--}}
<x-layouts.admin :title="__('Dashboard')" admin-key="dashboard" page="admin-charts">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <span class="uppercase tracking-[0.16em]">{{ __('Admin') }}</span>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Dashboard') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    {{-- Tab bar --}}
    <div class="px-4 sm:px-7 pt-5">
        <div data-dash-tabs="{{ $tab }}" class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex items-center gap-1 shadow-card overflow-x-auto">
            @php
                $tabList = [
                    'overview'  => __('Overview'),
                    'financial' => __('Financial'),
                    'premium'   => __('Premium'),
                    'ai'        => __('AI Dashboard'),
                ];
            @endphp
            @foreach ($tabList as $key => $label)
                <button type="button" data-dash-tab="{{ $key }}"
                    class="inline-flex shrink-0 whitespace-nowrap items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] font-semibold transition-colors {{ $tab === $key ? 'ig-grad-soft text-white' : 'text-ink-600 hover:bg-paper-50' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div data-dash-panel="overview" @if ($tab !== 'overview') hidden @endif>
        @include('admin.dashboard.overview', $ov)
    </div>
    <div data-dash-panel="financial" @if ($tab !== 'financial') hidden @endif>
        @include('admin.dashboard.financial', $fin)
    </div>
    <div data-dash-panel="premium" @if ($tab !== 'premium') hidden @endif>
        @include('admin.dashboard.premium', $prem)
    </div>
    <div data-dash-panel="ai" @if ($tab !== 'ai') hidden @endif>
        @include('admin.dashboard.ai', $ai)
    </div>

</x-layouts.admin>

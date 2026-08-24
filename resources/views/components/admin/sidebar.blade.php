@props(['active' => 'overview'])

{{--
    Admin sidebar — WaDesk's admin sidebar structure (grouped platform-admin
    sections), scoped to the pages this product ships and pointed at the
    standalone routes. Same class vocabulary, so it recolours to Instagram from
    the shared tokens; the active pill uses .ig-grad-soft — the one flourish.
--}}
@php
    $fmtCount = function (int $n): string {
        if ($n >= 1000000) return number_format($n / 1000000, $n >= 10000000 ? 0 : 1) . 'M';
        if ($n >= 1000) return number_format($n / 1000, $n >= 10000 ? 0 : 1) . 'k';
        return (string) $n;
    };
    $count = function (string $table, ?callable $scope = null) {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) return 0;
            $q = \Illuminate\Support\Facades\DB::table($table);
            if ($scope) $scope($q);
            return (int) $q->count();
        } catch (\Throwable $e) { return 0; }
    };

    $b = [
        'users'         => $count('users'),
        'packages'      => $count('packages'),
        'coupons'       => $count('coupons'),
        'announcements' => $count('announcements'),
        'blog'          => $count('blog_posts'),
        'currencies'    => $count('currencies'),
        'languages'     => $count('languages'),
        'orders'        => $count('instagram_orders'),
        'invoices'      => $count('instagram_orders', fn ($q) => $q->where('payment_status', 'paid')),
        'flowtpl'       => $count('flow_templates'),
    ];

    // type: leaf | group. Leaves render a link; groups render a collapsible list.
    $nav = [
        ['type' => 'leaf', 'key' => 'dashboard', 'href' => route('admin.insights'), 'label' => __('Dashboard'), 'sw' => 1.6,
            'icon' => '<rect x="2" y="2" width="5" height="6" rx="1"/><rect x="9" y="2" width="5" height="3" rx="1"/><rect x="9" y="7" width="5" height="7" rx="1"/><rect x="2" y="10" width="5" height="4" rx="1"/>'],
        ['type' => 'leaf', 'key' => 'analytics', 'href' => route('admin.analytics'), 'label' => __('Analytics'), 'sw' => 1.6,
            'icon' => '<path d="M2 12h12M4 10l2.2-3 3 2 3.2-5"/>'],
        ['type' => 'leaf', 'key' => 'instagram', 'href' => route('admin.settings.instagram'), 'label' => __('Instagram'), 'sw' => 1.6,
            'icon' => '<rect x="2.5" y="2.5" width="11" height="11" rx="3.4"/><circle cx="8" cy="8" r="2.6"/><circle cx="11.3" cy="4.7" r="0.7" fill="currentColor" stroke="none"/>'],
        ['type' => 'leaf', 'key' => 'wadesk-connection', 'href' => route('admin.wadesk-connection'), 'label' => __('Connect WaDesk'), 'sw' => 1.6,
            'icon' => '<path d="M6.5 9.5a2.7 2.7 0 0 0 4 .3l1.6-1.6a2.7 2.7 0 0 0-3.8-3.8l-.9.9"/><path d="M9.5 6.5a2.7 2.7 0 0 0-4-.3L3.9 7.8a2.7 2.7 0 0 0 3.8 3.8l.9-.9"/>'],
        ['type' => 'leaf', 'key' => 'health', 'href' => route('admin.health'), 'label' => __('System Health'), 'sw' => 1.6,
            'icon' => '<path d="M1.5 8h2.5l1.5-4 2 8 1.5-4h3.5"/>'],
        ['type' => 'group', 'key' => 'access', 'label' => __('Users & access'), 'sw' => 1.5,
            'icon' => '<circle cx="6" cy="6" r="3"/><path d="M2 14c0-3 2.5-5 4-5s4 2 4 5"/>',
            'children' => [
                ['key' => 'users', 'href' => route('admin.users'), 'label' => __('Users'), 'badge' => $b['users'] ? $fmtCount($b['users']) : null],
                ['key' => 'roles', 'href' => route('admin.roles.index'), 'label' => __('Roles & Permissions')],
            ]],
        ['type' => 'group', 'key' => 'billing', 'label' => __('Billing & plans'), 'sw' => 1.6,
            'icon' => '<rect x="2" y="4" width="12" height="9" rx="1.5"/><path d="M2 7h12"/>',
            'children' => [
                ['key' => 'packages', 'href' => route('admin.packages.index'), 'label' => __('Packages'), 'badge' => $b['packages'] ? (string) $b['packages'] : null],
                ['key' => 'pricing-faqs', 'href' => route('admin.pricing-faqs'), 'label' => __('Pricing FAQ')],
                ['key' => 'coupons', 'href' => route('admin.coupons.index'), 'label' => __('Coupons'), 'badge' => $b['coupons'] ? (string) $b['coupons'] : null],
                ['key' => 'plan-orders', 'href' => route('admin.plan-orders'), 'label' => __('Plan orders'), 'badge' => ($pc = \App\Models\Order::where('status', 'pending')->count()) ? (string) $pc : null],
                ['key' => 'orders', 'href' => route('admin.orders'), 'label' => __('Order history'), 'badge' => $b['orders'] ? $fmtCount($b['orders']) : null],
                ['key' => 'invoices', 'href' => route('admin.invoices'), 'label' => __('Invoices'), 'badge' => $b['invoices'] ? $fmtCount($b['invoices']) : null],
                ['key' => 'payment-gateways', 'href' => route('admin.payment-gateways.index'), 'label' => __('Payment gateways')],
                ['key' => 'billing', 'href' => route('admin.billing'), 'label' => __('Billing settings')],
            ]],
        ['type' => 'group', 'key' => 'localization', 'label' => __('Localization'), 'sw' => 1.5,
            'icon' => '<circle cx="8" cy="8" r="6"/><path d="M2 8h12"/><path d="M8 2c1.7 1.8 1.7 10.2 0 12M8 2c-1.7 1.8-1.7 10.2 0 12"/>',
            'children' => [
                ['key' => 'currencies', 'href' => route('admin.currencies.index'), 'label' => __('Currencies'), 'badge' => $b['currencies'] ? (string) $b['currencies'] : null],
                ['key' => 'languages', 'href' => route('admin.languages.index'), 'label' => __('Languages'), 'badge' => $b['languages'] ? (string) $b['languages'] : null],
            ]],
        ['type' => 'group', 'key' => 'settingsgrp', 'label' => __('Settings'), 'sw' => 1.5,
            'icon' => '<circle cx="8" cy="8" r="2"/><path d="M13 8a5 5 0 0 0-.1-1.1l1.4-1-1.5-2.6-1.6.7a5 5 0 0 0-1.9-1.1L9 1H7l-.3 1.9a5 5 0 0 0-1.9 1.1l-1.6-.7-1.5 2.6 1.4 1A5 5 0 0 0 3 8c0 .4 0 .7.1 1.1l-1.4 1 1.5 2.6 1.6-.7a5 5 0 0 0 1.9 1.1L7 15h2l.3-1.9a5 5 0 0 0 1.9-1.1l1.6.7 1.5-2.6-1.4-1c.1-.4.1-.7.1-1.1Z"/>',
            'children' => [
                ['key' => 'settings', 'href' => route('admin.settings'), 'label' => __('General')],
                ['key' => 'api-keys', 'href' => route('admin.api-keys.index'), 'label' => __('AI keys')],
                ['key' => 'site', 'href' => route('admin.site'), 'label' => __('Site settings')],
                ['key' => 'front', 'href' => route('admin.front'), 'label' => __('Front pages')],
                ['key' => 'seo', 'href' => route('admin.settings.seo'), 'label' => __('SEO')],
                ['key' => 'tracking', 'href' => route('admin.settings.analytics'), 'label' => __('Tracking')],
                ['key' => 'social', 'href' => route('admin.settings.social'), 'label' => __('Social login')],
                ['key' => 'privacy', 'href' => route('admin.settings.privacy'), 'label' => __('Privacy')],
                ['key' => 'mail', 'href' => route('admin.settings.mail'), 'label' => __('Mail')],
            ]],
    ];

    $sys = [
        ['key' => 'security', 'href' => route('admin.security'), 'label' => __('Security'), 'sw' => 1.6,
            'icon' => '<path d="M8 2l5 2v4c0 3.2-2 5.4-5 6-3-.6-5-2.8-5-6V4z"/><path d="M6 8l1.5 1.5L10.5 6"/>'],
        ['key' => 'flow-templates', 'href' => route('admin.flow-templates.index'), 'label' => __('Flow templates'), 'sw' => 1.5,
            'icon' => '<circle cx="3.5" cy="8" r="1.6"/><circle cx="12.5" cy="3.5" r="1.6"/><circle cx="12.5" cy="12.5" r="1.6"/><path d="M5 7.2l6-3M5 8.8l6 3"/>'],
        ['key' => 'audit', 'href' => route('admin.audit'), 'label' => __('Audit log'), 'sw' => 1.6,
            'icon' => '<path d="M3 2.5h7l3 3v8H3z"/><path d="M9.5 2.5v3.5H13"/><path d="M5.5 8.5h5M5.5 11h3"/>'],
        ['key' => 'storage', 'href' => route('admin.storage'), 'label' => __('Storage'), 'sw' => 1.6,
            'icon' => '<ellipse cx="8" cy="4" rx="5.5" ry="2"/><path d="M2.5 4v8c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2V4"/><path d="M2.5 8c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2"/>'],
        ['key' => 'update', 'href' => route('admin.update'), 'label' => __('Update'), 'sw' => 1.6,
            'icon' => '<path d="M14 8a6 6 0 1 1-1.76-4.24"/><path d="M14 2v3.5h-3.5"/>'],
    ];
@endphp

<div class="bg-paper-0 border-r border-paper-200 flex flex-col sticky top-0 h-screen">
    <div class="h-16 px-5 flex items-center justify-between border-b border-paper-200 shrink-0">
        <a href="{{ url('/dashboard') }}" class="flex items-center gap-2.5">
            @php $lgL = site_logo(); $lgD = site_logo_dark(); $lgBoth = $lgL && $lgD; @endphp
            @if ($lgL || $lgD)
                @if ($lgL)<img src="{{ $lgL }}" alt="{{ site_name() }}" class="h-9 w-auto max-w-[150px] object-contain {{ $lgBoth ? 'brand-logo-light' : '' }}">@endif
                @if ($lgD)<img src="{{ $lgD }}" alt="{{ site_name() }}" class="h-9 w-auto max-w-[150px] object-contain {{ $lgBoth ? 'brand-logo-dark' : '' }}">@endif
            @else
                <span class="relative inline-flex items-center justify-center w-9 h-9 rounded-xl ig-grad text-white">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg>
                </span>
                <span class="leading-none">
                    <span class="block font-serif font-normal text-[20px] tracking-[-0.01em]">{{ site_name() }}</span>
                    <span class="block text-[9.5px] font-mono uppercase tracking-[0.18em] text-ink-500 mt-1">{{ __('Admin console') }}</span>
                </span>
            @endif
        </a>
        <button type="button" aria-label="{{ __('Close') }}" onclick="window.__adminToggleSidebar(false)"
            class="md:hidden shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg text-ink-500 hover:text-ink-900 hover:bg-paper-50 transition">
            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4l8 8M12 4l-8 8"/></svg>
        </button>
    </div>

    <nav class="px-3 py-4 flex-1 overflow-y-auto ig-scroll">
        <div class="px-3 pb-1.5 text-[9.5px] font-mono uppercase tracking-[0.18em] text-ink-500">{{ __('Main menu') }}</div>
        <div class="space-y-0.5">
            @foreach ($nav as $item)
                @if ($item['type'] === 'group')
                    @php
                        $childActive = collect($item['children'])->contains(fn ($c) => $c['key'] === $active);
                        $isOpen = $childActive;
                    @endphp
                    <div class="admin-nav-group">
                        <button type="button" data-admin-toggle @class([
                            'flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px] w-full text-left',
                            'text-ink-900 font-semibold' => $childActive,
                            'text-ink-700 hover:bg-paper-50 font-medium' => !$childActive,
                        ])>
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="{{ $item['sw'] }}">{!! $item['icon'] !!}</svg>
                            <span class="flex-1 truncate">{{ $item['label'] }}</span>
                            <svg data-admin-chevron class="w-3 h-3 text-ink-500 shrink-0 transition-transform {{ $isOpen ? 'rotate-180' : '' }}" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 5l3 3 3-3"/></svg>
                        </button>
                        <div @class(['admin-nav-children space-y-0.5 mt-0.5 ml-3 pl-3 border-l border-paper-200', 'hidden' => !$isOpen])>
                            @foreach ($item['children'] as $child)
                                @php $cActive = $child['key'] === $active; @endphp
                                <a href="{{ $child['href'] }}" @class([
                                    'flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12px]',
                                    'ig-grad-soft text-white font-semibold' => $cActive,
                                    'text-ink-700 hover:bg-paper-50' => !$cActive,
                                ])>
                                    <span @class(['w-1 h-1 rounded-full ml-1 shrink-0', 'bg-white' => $cActive, 'bg-paper-300' => !$cActive])></span>
                                    <span class="flex-1 truncate">{{ $child['label'] }}</span>
                                    @if (!empty($child['badge']))
                                        <span @class(['ml-auto font-mono text-[10px]', 'text-white/90' => $cActive, 'text-ink-500' => !$cActive])>{{ $child['badge'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    @php $isActive = $item['key'] === $active; @endphp
                    <a href="{{ $item['href'] }}" @class([
                        'flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px]',
                        'ig-grad-soft text-white font-semibold' => $isActive,
                        'text-ink-700 hover:bg-paper-50' => !$isActive,
                    ])>
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="{{ $item['sw'] }}">{!! $item['icon'] !!}</svg>
                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>

        <div class="mt-1 pt-4 border-t border-paper-200">
            <div class="px-3 pb-1.5 text-[9.5px] font-mono uppercase tracking-[0.18em] text-ink-500">{{ __('System') }}</div>
            <div class="space-y-0.5">
                @foreach ($sys as $item)
                    @php $isActive = $item['key'] === $active; @endphp
                    <a href="{{ $item['href'] }}" @class([
                        'flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px]',
                        'ig-grad-soft text-white font-semibold' => $isActive,
                        'text-ink-700 hover:bg-paper-50' => !$isActive,
                    ])>
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="{{ $item['sw'] }}">{!! $item['icon'] !!}</svg>
                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </nav>

    <div class="px-3 py-3 border-t border-paper-200 shrink-0">
        <a href="{{ url('/dashboard') }}" class="flex items-center justify-between px-3 py-2 rounded-xl bg-paper-50 hover:bg-paper-100 transition">
            <span class="flex items-center gap-2 text-[11.5px] font-medium text-ink-700">
                <svg viewBox="0 0 16 16" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 4 5 8l4 4M5 8h8"/></svg>
                {{ __('Back to app') }}
            </span>
            <span class="inline-flex items-center gap-1 text-[10px] font-mono text-wa-deep">
                <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('online') }}
            </span>
        </a>
    </div>

    {{-- Collapsible groups — tiny self-contained toggle so the sidebar needs no build. --}}
    <script>
        (function () {
            document.querySelectorAll('#admin-sidebar [data-admin-toggle]').forEach(function (btn) {
                if (btn.__wired) return; btn.__wired = true;
                btn.addEventListener('click', function () {
                    var kids = btn.parentElement.querySelector('.admin-nav-children');
                    var chev = btn.querySelector('[data-admin-chevron]');
                    if (kids) kids.classList.toggle('hidden');
                    if (chev) chev.classList.toggle('rotate-180');
                });
            });
        })();
    </script>
</div>

@props(['active' => 'dashboard'])
@php
    $nav = [
        ['key' => 'dashboard',   'href' => url('/instagram'),             'label' => __('Dashboard'),  'icon' => '<rect x="2" y="2" width="5" height="6" rx="1"/><rect x="9" y="2" width="5" height="3" rx="1"/><rect x="9" y="7" width="5" height="7" rx="1"/><rect x="2" y="10" width="5" height="4" rx="1"/>'],
        ['key' => 'inbox',       'href' => url('/instagram/inbox'),       'label' => __('Inbox'),      'icon' => '<path d="M2 4h12v8H6l-4 3z"/>'],
        ['key' => 'automations', 'href' => url('/instagram/automations'), 'label' => __('Automations'),'icon' => '<path d="M3 5a4 4 0 018 0v3l1 2H2l1-2z"/><path d="M6 12a1.5 1.5 0 003 0"/>'],
        ['key' => 'composer',    'href' => url('/instagram/composer'),    'label' => __('Compose'),    'icon' => '<rect x="2.5" y="2.5" width="11" height="11" rx="2.5"/><path d="M5.5 10l2-2 2 2 1.5-1.5"/><circle cx="6" cy="6" r="1"/>'],
        ['key' => 'broadcast',   'href' => url('/instagram/broadcast'),   'label' => __('Bulk DM'),    'icon' => '<path d="M2 8l12-5-4 12-3-5z"/>'],
        ['key' => 'analytics',   'href' => url('/instagram/analytics'),   'label' => __('Analytics'),  'icon' => '<path d="M2 11l3-5 3 3 3-6 3 4"/>'],
        ['key' => 'flows',       'href' => url('/flows'),                 'label' => __('Flows'),      'icon' => '<circle cx="3.5" cy="8" r="1.8"/><circle cx="12.5" cy="3.5" r="1.8"/><circle cx="12.5" cy="12.5" r="1.8"/><path d="M5 7l6-3M5 9l6 3"/>'],
    ];
    $authUser = auth()->user();
    $currentWs = $authUser?->current_workspace;
    $initials = \Illuminate\Support\Str::of($authUser?->name ?? 'U')->trim()->limit(2, '')->upper();
@endphp

<header class="relative z-40 bg-paper-0 border-b border-paper-200">
    <div class="max-w-none mx-auto px-3 sm:px-5 h-16 flex items-center gap-4">

        {{-- Brand logo — uploaded logo (admin → General settings) if set, else the mark. --}}
        <a href="{{ url('/instagram') }}" class="flex items-center gap-2.5 shrink-0">
            @php $lgL = site_logo(); $lgD = site_logo_dark(); $lgBoth = $lgL && $lgD; @endphp
            @if ($lgL || $lgD)
                @if ($lgL)<img src="{{ $lgL }}" alt="{{ brand_name() }}" class="h-9 max-w-[150px] object-contain {{ $lgBoth ? 'brand-logo-light' : '' }}">@endif
                @if ($lgD)<img src="{{ $lgD }}" alt="{{ brand_name() }}" class="h-9 max-w-[150px] object-contain {{ $lgBoth ? 'brand-logo-dark' : '' }}">@endif
            @else
                <span class="ig-grad-soft w-9 h-9 rounded-[11px] grid place-items-center text-white">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
                </span>
                <span class="font-serif text-[22px] leading-none">{{ brand_name() }}</span>
            @endif
        </a>

        {{-- Channel switcher — WaDesk ⇆ IgDesk (the header toggle) --}}
        <div class="inline-flex items-center gap-1 p-1 rounded-full border border-paper-200 bg-paper-50 shrink-0">
            <a href="{{ url('/dashboard') }}" class="px-3 py-1.5 rounded-full text-[12px] font-semibold text-ink-600 hover:bg-paper-0 flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 5a4 4 0 018 0v3l1 2H2l1-2z"/></svg><span class="hidden sm:inline">{{ brand_name() }}</span>
            </a>
            <span class="px-3 py-1.5 rounded-full text-[12px] font-semibold text-white ig-grad-soft flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="2.5" width="11" height="11" rx="3.5"/><circle cx="8" cy="8" r="2.6"/></svg><span class="hidden sm:inline">{{ brand_name() }}</span>
            </span>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 min-w-0 hidden lg:flex items-center justify-center overflow-x-auto">
            <div class="flex items-center gap-0.5">
                @foreach ($nav as $item)
                    @php $isActive = $item['key'] === $active; @endphp
                    <a href="{{ $item['href'] }}" @class([
                        'inline-flex items-center gap-2 px-3 py-[7px] rounded-full text-[12.5px] font-medium transition whitespace-nowrap',
                        'ig-grad-soft text-white' => $isActive,
                        'text-ink-600 hover:bg-paper-50' => !$isActive,
                    ])>
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6">{!! $item['icon'] !!}</svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>

        {{-- Right side --}}
        <div class="ml-auto flex items-center gap-2 shrink-0">
            @if ($currentWs)
                <span class="hidden md:flex items-center gap-1.5 px-2.5 py-1.5 rounded-full border border-paper-200 bg-paper-50 text-[12px] font-semibold text-ink-700 max-w-[160px]">
                    <span class="w-5 h-5 rounded-full grid place-items-center text-white text-[9px] font-bold" style="background:{{ workspace_brand_color($currentWs ?? null, '#C13584') }}">{{ strtoupper(substr($currentWs->name ?? 'W', 0, 2)) }}</span>
                    <span class="truncate">{{ $currentWs->name }}</span>
                </span>
            @endif
            <a href="{{ url('/account') }}" class="w-9 h-9 rounded-full ig-grad-soft text-white grid place-items-center text-[12px] font-semibold" title="{{ $authUser?->name }}">{{ $initials }}</a>
        </div>

        {{-- Mobile nav --}}
        <details class="lg:hidden ml-auto relative">
            <summary class="list-none w-9 h-9 rounded-full border border-paper-200 grid place-items-center cursor-pointer">
                <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4h12M2 8h12M2 12h12"/></svg>
            </summary>
            <div class="absolute right-0 mt-2 w-56 bg-paper-0 border border-paper-200 rounded-2xl shadow-soft p-1.5 z-50">
                @foreach ($nav as $item)
                    <a href="{{ $item['href'] }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px] {{ $item['key'] === $active ? 'ig-grad-soft text-white' : 'text-ink-700 hover:bg-paper-50' }}">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6">{!! $item['icon'] !!}</svg>{{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </details>
    </div>
</header>

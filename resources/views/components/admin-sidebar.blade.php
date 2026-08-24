@props(['active' => ''])

@php
    // key => [label, route, svg-inner]. A lean set — the operator's daily needs,
    // not WaDesk's 29 sections.
    $items = [
        'overview'  => [__('Overview'),  route('admin.overview'),        '<rect x="3" y="3" width="6" height="6" rx="1.6"/><rect x="11" y="3" width="6" height="6" rx="1.6"/><rect x="3" y="11" width="6" height="6" rx="1.6"/><rect x="11" y="11" width="6" height="6" rx="1.6"/>'],
        'users'     => [__('Customers'), route('admin.users'),           '<circle cx="7" cy="6.5" r="2.6"/><path d="M2.5 15.5c0-2.6 2-4 4.5-4s4.5 1.4 4.5 4"/><circle cx="14" cy="7" r="2"/><path d="M12.5 11.6c2 .2 3.5 1.3 3.5 3.4"/>'],
        'packages'  => [__('Packages'),  route('admin.packages.index'),  '<path d="M10 2.5 16.5 6v8L10 17.5 3.5 14V6z"/><path d="M3.5 6 10 9.5 16.5 6M10 9.5v8"/>'],
        'analytics' => [__('Analytics'), route('admin.analytics'),       '<path d="M3 3v13.5h13.5"/><path d="M6.5 13V9M10 13V6.5M13.5 13v-2.5"/>'],
        'settings'  => [__('Settings'),  route('admin.settings'),        '<circle cx="10" cy="10" r="2.6"/><path d="M10 2.5v2M10 15.5v2M17.5 10h-2M4.5 10h-2M15 5l-1.4 1.4M6.4 13.6 5 15M15 15l-1.4-1.4M6.4 6.4 5 5"/>'],
    ];
@endphp

<aside class="w-[228px] shrink-0 bg-paper-0 border-r border-paper-200 flex flex-col">
    {{-- Brand --}}
    <a href="{{ url('/instagram') }}" class="h-14 shrink-0 flex items-center gap-2.5 px-5 border-b border-paper-200">
        @if ($logo = site_logo())
            <img src="{{ $logo }}" alt="{{ site_name() }}" class="h-7 max-w-[130px] object-contain">
        @else
            <span class="ig-grad w-8 h-8 rounded-[10px] grid place-items-center text-white shrink-0">
                <svg viewBox="0 0 24 24" class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
            </span>
            <span class="font-bold text-[15px] tracking-tight truncate">{{ site_name() }}</span>
        @endif
    </a>

    <nav class="flex-1 overflow-y-auto ig-scroll p-3 space-y-1">
        <div class="px-2 pb-1.5 text-[10px] font-mono uppercase tracking-[0.16em] text-ink-400">{{ __('Manage') }}</div>
        @foreach ($items as $key => [$label, $href, $svg])
            <a href="{{ $href }}"
                class="flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] font-medium transition
                    {{ $active === $key ? 'ig-grad-soft text-white' : 'text-ink-700 hover:bg-paper-50' }}">
                <svg viewBox="0 0 20 20" class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $svg !!}</svg>
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <div class="p-3 border-t border-paper-200">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] font-medium text-ink-600 hover:bg-paper-50">
                <svg viewBox="0 0 20 20" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 3H4.4A1.9 1.9 0 0 0 2.5 4.9v10.2A1.9 1.9 0 0 0 4.4 17h3.1"/><path d="m13 13.5 3.5-3.5L13 6.5"/><path d="M16.5 10h-9"/></svg>
                {{ __('Sign out') }}
            </button>
        </form>
    </div>
</aside>

{{-- Admin header right-side controls — WaDesk's <x-admin.header-right>, trimmed
     to what standalone has: health pill, back-to-app, theme toggle, and the
     account menu. The notification bell + locale switcher are dropped (no
     standalone endpoints). The layout renders this once and an inline script
     moves it into every page's [data-admin-header-right] slot. --}}
@php
    $au = auth()->user();
    $base = $au ? ($au->name ?: $au->email) : '';
    $parts = preg_split('/\s+/', trim((string) $base));
    $initials = $au ? strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1)) : 'IF';
    $roleLabel = ($au && $au->is_admin) ? __('Administrator') : __('Member');

    // Health pill — DB ping only, no remote calls. ok | down.
    $sysStatus = 'ok';
    $sysLabel = __('All systems normal');
    try {
        \DB::connection()->getPdo();
    } catch (\Throwable $e) {
        $sysStatus = 'down';
        $sysLabel = __('Database unreachable');
    }
    $sysDot = $sysStatus === 'ok' ? 'bg-wa-green' : 'bg-accent-coral';
@endphp

<div class="flex items-center gap-2" data-admin-header-controls>
    {{-- Platform health pill --}}
    <a href="{{ route('admin.overview') }}" title="{{ __('Platform health') }}"
        class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium">
        <span class="w-1.5 h-1.5 rounded-full {{ $sysDot }}"></span>
        <span>{{ $sysLabel }}</span>
    </a>

    {{-- Back-to-app shortcut --}}
    <a href="{{ url('/dashboard') }}"
        class="w-9 h-9 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
        title="{{ __('Back to app') }}">
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 4 5 8l4 4M5 8h8" /></svg>
    </a>

    {{-- Language dropdown --}}
    <x-language-switcher align="right" />

    {{-- Theme toggle --}}
    <button id="wa-theme-btn" type="button"
        class="w-9 h-9 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
        title="{{ __('Theme') }}">
        <svg id="wa-theme-icon" viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1a7 7 0 1 0 7 7 5.5 5.5 0 0 1-7-7z" /></svg>
    </button>

    {{-- User avatar + menu --}}
    <div class="relative ml-1" data-user-menu>
        <button type="button" data-user-toggle
            class="flex items-center gap-2 pl-1 pr-3 py-1 rounded-full hover:bg-paper-50">
            <span class="w-9 h-9 rounded-full ig-grad text-white text-[12px] font-semibold flex items-center justify-center">{{ $initials ?: 'IF' }}</span>
            @if ($au)
                <span class="text-left hidden md:block">
                    <span class="block text-[13px] font-semibold leading-tight">{{ $au->name ?: explode('@', (string) $au->email)[0] }}</span>
                    <span class="block text-[11px] text-ink-500 leading-tight">{{ $roleLabel }}</span>
                </span>
            @endif
            <svg class="w-3 h-3 text-ink-500" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 5l3 3 3-3" /></svg>
        </button>
        @if ($au)
            <div data-user-pane
                class="hidden absolute right-0 mt-2 w-[240px] bg-paper-0 border border-paper-200 rounded-2xl shadow-soft p-2 z-30">
                <div class="px-2 py-1.5 text-[12px] text-ink-500 truncate">{{ $au->email }}</div>
                <a href="{{ url('/dashboard') }}" class="block px-2 py-2 rounded-xl hover:bg-paper-50 text-[13px]">{{ __('Back to app') }}</a>
                <a href="{{ route('admin.overview') }}" class="block px-2 py-2 rounded-xl hover:bg-paper-50 text-[13px]">{{ __('Admin overview') }}</a>
                <a href="{{ route('admin.settings') }}" class="block px-2 py-2 rounded-xl hover:bg-paper-50 text-[13px]">{{ __('Settings') }}</a>
                <div class="border-t border-paper-200 my-1"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-2 py-2 rounded-xl hover:bg-accent-coral/10 text-[13px] text-accent-coral font-semibold">{{ __('Sign out') }}</button>
                </form>
            </div>
        @endif
    </div>
</div>

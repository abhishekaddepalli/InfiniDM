{{-- Public header — WaDesk fixed centered nav, Instaflow colours + routes.
     Home is included and every item lights up on its own page. --}}
@php
    $nav = [
        ['label' => __('Home'), 'href' => url('/'), 'active' => request()->routeIs('home')],
        ['label' => front('nav_features'), 'href' => route('page.features'), 'active' => request()->routeIs('page.features')],
        ['label' => front('nav_pricing'), 'href' => route('page.pricing'), 'active' => request()->routeIs('page.pricing')],
        ['label' => front('nav_blog'), 'href' => route('blog.index'), 'active' => request()->routeIs('blog.*')],
    ];
    if (front_bool('about_show'))   $nav[] = ['label' => front('nav_about'), 'href' => route('page.about'), 'active' => request()->routeIs('page.about')];
    if (front_bool('contact_show')) $nav[] = ['label' => front('nav_contact'), 'href' => route('page.contact'), 'active' => request()->routeIs('page.contact')];
@endphp
<header
    x-data="{ scrolled: false, mobileMenuOpen: false }"
    @scroll.window="scrolled = (window.pageYOffset > 20)"
    :class="scrolled ? 'bg-background/85 backdrop-blur-xl border-border' : 'bg-transparent border-transparent'"
    class="fixed top-0 z-50 w-full border-b transition-all duration-300">

    <div class="container mx-auto px-4 sm:px-6 flex h-20 items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5 shrink-0">
            @if($logo = site_logo())
                <img src="{{ $logo }}" alt="{{ brand_name() }}" class="h-9 max-w-[160px] object-contain">
            @else
                <span class="ig-grad w-9 h-9 rounded-xl grid place-items-center"><svg viewBox="0 0 24 24" class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg></span>
                <span class="text-[19px] font-semibold tracking-tight text-foreground">{{ brand_name() }}</span>
            @endif
        </a>

        {{-- Desktop nav (absolute-centered) --}}
        <nav class="hidden lg:flex items-center gap-9 text-xs font-bold uppercase tracking-widest absolute left-1/2 -translate-x-1/2">
            @foreach($nav as $item)
                <a href="{{ $item['href'] }}" class="transition-colors {{ $item['active'] ? 'ig-text' : 'text-muted-foreground hover:text-primary' }}">{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            @auth
                <a href="{{ url('/instagram') }}" class="hidden sm:inline-flex h-11 items-center justify-center rounded-xl ig-grad-soft btn-grad px-6 text-sm font-bold text-white shadow-lg shadow-primary/20 transition-opacity hover:opacity-90">{{ __('Dashboard') }}</a>
            @else
                <a href="{{ route('login') }}" class="text-sm font-bold text-foreground hover:text-primary transition-colors px-3">{{ front('nav_signin') }}</a>
                <a href="{{ route('register') }}" class="hidden sm:inline-flex h-11 items-center justify-center rounded-xl ig-grad-soft btn-grad px-6 text-sm font-bold text-white shadow-lg shadow-primary/20 transition-opacity hover:opacity-90">
                    {{ front('nav_cta') }}
                    <svg viewBox="0 0 24 24" class="ml-2 h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            @endauth
            <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden p-2 text-muted-foreground hover:text-foreground" aria-label="Menu">
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
            </button>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div x-show="mobileMenuOpen" x-cloak
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
        @click.away="mobileMenuOpen = false"
        class="absolute top-20 left-0 w-full bg-background border-b border-border shadow-lg lg:hidden p-5 flex flex-col gap-4">
        @foreach($nav as $item)
            <a href="{{ $item['href'] }}" class="text-sm font-bold uppercase tracking-widest {{ $item['active'] ? 'text-primary' : 'text-foreground hover:text-primary' }}">{{ $item['label'] }}</a>
        @endforeach
        <hr class="border-border">
        @auth
            <a href="{{ url('/instagram') }}" class="w-full text-center h-11 rounded-xl ig-grad-soft text-white font-bold flex items-center justify-center">{{ __('Dashboard') }}</a>
        @else
            <a href="{{ route('login') }}" class="w-full text-center h-11 rounded-xl border border-border text-foreground font-bold flex items-center justify-center">{{ front('nav_signin') }}</a>
            <a href="{{ route('register') }}" class="w-full text-center h-11 rounded-xl ig-grad-soft text-white font-bold flex items-center justify-center">{{ front('nav_cta') }}</a>
        @endauth
    </div>
</header>

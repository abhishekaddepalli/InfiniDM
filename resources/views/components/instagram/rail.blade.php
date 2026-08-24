@props(['active' => 'dashboard'])
@php
    // Slim left icon rail. Each item = a clean 20×20 line-icon body (stroke 1.6,
    // rounded caps) + the ig-active key its page passes. Kept visually uniform.
    $items = [
        // Ordered by how often an operator actually reaches for it, not by when
        // it was built. Inbox first — it is the job; Ads/Autopilot last — they
        // are set up once and left alone.
        ['key' => 'dashboard',    'href' => url('/instagram'),               'label' => __('Dashboard'),   'icon' => '<rect x="3" y="3" width="6" height="6" rx="1.7"/><rect x="11" y="3" width="6" height="6" rx="1.7"/><rect x="3" y="11" width="6" height="6" rx="1.7"/><rect x="11" y="11" width="6" height="6" rx="1.7"/>'],
        ['key' => 'inbox',        'href' => url('/instagram/inbox'),         'label' => __('Inbox'),       'icon' => '<path d="M17.5 9.7c0 3.5-3.4 6.3-7.5 6.3-.9 0-1.8-.1-2.6-.4L3.5 17l1.3-3.6A6 6 0 0 1 2.5 9.7C2.5 6.2 5.9 3.4 10 3.4s7.5 2.8 7.5 6.3Z"/>'],
        ['key' => 'composer',     'href' => url('/instagram/composer'),      'label' => __('Create'),      'icon' => '<rect x="3" y="3" width="14" height="14" rx="4.2"/><path d="M10 7.3v5.4M7.3 10h5.4"/>'],
        ['key' => 'automations',  'href' => url('/instagram/automations'),   'label' => __('Auto-reply'),  'icon' => '<path d="M10.8 2.6 4.7 11h4.2l-1 6.4 6.4-8.7h-4.3z"/>'],
        ['key' => 'auto-comments','href' => url('/instagram/auto-comments'), 'label' => __('Comment rules'),'icon' => '<path d="M17 9.4c0 3.1-3.1 5.7-7 5.7-.9 0-1.8-.1-2.6-.4L3.7 16l1.2-3A5.4 5.4 0 0 1 3 9.4c0-3.1 3.1-5.7 7-5.7s7 2.6 7 5.7Z"/><path d="M7.4 9.4h.01M10 9.4h.01M12.6 9.4h.01"/>'],
        ['key' => 'flows',        'href' => url('/flows?type=instagram'), 'label' => __('Flows'),       'icon' => '<circle cx="5" cy="5" r="2.2"/><circle cx="15" cy="5" r="2.2"/><circle cx="10" cy="15" r="2.2"/><path d="M5 7.2v2.3a1.6 1.6 0 0 0 1.6 1.6h6.8A1.6 1.6 0 0 0 15 9.5V7.2"/><path d="M10 11.1v1.7"/>'],
        ['key' => 'analytics',    'href' => url('/instagram/analytics'),     'label' => __('Analytics'),   'icon' => '<path d="M3.4 3v13.6h13.2"/><path d="M7.2 13.4V9.6M10.6 13.4V6.8M14 13.4v-2.6"/>'],
    ];
    $authUser = auth()->user();
    $initials = \Illuminate\Support\Str::of($authUser?->name ?? 'U')->trim()->limit(2, '')->upper();

    // The connect page picks its own middleware at boot (routes/instagram.php):
    // admin-gated when the host exposes an 'admin' alias (addon build), plain
    // auth when standalone. Mirror that same branch so the rail never offers a
    // link that would 403 — method_exists because the standalone User model has
    // no isAdmin(). Route::has() keeps a build without the route from 500ing the
    // whole shell rather than just hiding one icon.
    // Moved to the ADMIN panel (Settings → Connect WaDesk) — never in the user
    // Instagram shell. Kept false so the rail icon is gone for everyone.
    $showWadeskConn = false;

    // Plan gating for the rail. Each rail key maps to an InstagramGate feature;
    // when the current plan doesn't include it we mark the icon PREMIUM (crown)
    // and point it at the pricing page instead of the feature — clicking a locked
    // item takes the user to upgrade, exactly like WaDesk. Dashboard has no
    // mapping (baseline, never locked). Admins bypass inside allows().
    $railFeat = [
        'inbox'         => 'inbox',
        'composer'      => 'composer',
        'automations'   => 'automations',
        'auto-comments' => 'auto_comments',
        'flows'         => 'flows',
        'analytics'     => 'analytics',
    ];
    $isLocked = function ($key) use ($railFeat) {
        $f = $railFeat[$key] ?? null;
        return $f !== null && ! \App\Services\Instagram\InstagramGate::allows($f);
    };
    $plansHref = \Illuminate\Support\Facades\Route::has('instagram.plans')
        ? route('instagram.plans')
        : url('/instagram/plans');
    // Crown mark for a premium/locked rail icon — small "gold" glyph.
    $railCrown = '<svg viewBox="0 0 20 20" class="w-2.5 h-2.5" fill="currentColor" aria-hidden="true"><path d="M2.5 6.4l3 2.3 3.2-4.6a1.6 1.6 0 0 1 2.6 0l3.2 4.6 3-2.3c.8-.6 1.9.1 1.7 1.1l-1.5 7.2a1.3 1.3 0 0 1-1.3 1H3.6a1.3 1.3 0 0 1-1.3-1L.8 7.5C.6 6.5 1.7 5.8 2.5 6.4z"/></svg>';
@endphp

<div class="ig-rail-wrap h-full">
{{-- RTL: under an Arabic/Hebrew/Urdu layout the whole shell mirrors and the
     rail sits on the RIGHT. The rail expands 72→236px on hover; anchored left:0
     it would grow off the right screen edge, so anchor it right:0 (grows LEFT
     into the content) and move the collapsed tooltip to the left of each icon.
     The expanded inline labels already flip via CSS-grid direction. --}}
<style>
    [dir="rtl"] .ig-rail { left: auto; right: 0; }
    [dir="rtl"] .ig-rail:hover { box-shadow: -12px 0 30px -18px rgba(26, 19, 32, 0.35); }
    [dir="rtl"] .ig-rail-ic .ig-tip { left: auto; right: calc(100% + 10px); }
    [dir="rtl"] .ig-rail:hover .ig-rail-brand,
    [dir="rtl"] .ig-rail:hover .ig-rail-user { padding-left: 0; padding-right: 13px; }
    [dir="rtl"] .ig-rail .ig-rail-brand-label,
    [dir="rtl"] .ig-rail .ig-rail-user-label { margin-left: 0; margin-right: 0.75rem; }
</style>
<nav class="ig-rail h-full border-r border-paper-200 bg-paper-0 flex flex-col items-center py-4 gap-1 ig-scroll">

    {{-- Brand. COLLAPSED rail (72px) → the IG icon mark, which fits the narrow
         rail. EXPANDED (hover, 236px) → the uploaded logo (or brand name if none).
         The mark uses .ig-rail-brandmark (hidden on hover via the rule below) and
         the logo/name uses .ig-rail-brand-label (shown only on hover). --}}
    @php $railFav = site_favicon(); $railLgL = site_logo(); $railLgD = site_logo_dark(); $railBoth = $railLgL && $railLgD; @endphp
    <a href="{{ url('/instagram') }}" class="ig-rail-brand flex items-center mb-3 shrink-0" title="{{ brand_name() }}">
        {{-- Collapsed mark: the uploaded favicon if set, else the built-in IG glyph. --}}
        <span class="ig-rail-brandmark w-11 h-11 rounded-2xl grid place-items-center shrink-0 overflow-hidden {{ $railFav ? 'bg-paper-0 border border-paper-200' : 'ig-grad' }}">
            @if ($railFav)
                <img src="{{ $railFav }}" alt="{{ brand_name() }}" class="w-full h-full object-contain p-1.5">
            @else
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
            @endif
        </span>
        @if ($railLgL || $railLgD)
            @if ($railLgL)<img src="{{ $railLgL }}" alt="{{ brand_name() }}" class="ig-rail-brand-label ml-3 h-9 max-w-[150px] object-contain {{ $railBoth ? 'brand-logo-light' : '' }}">@endif
            @if ($railLgD)<img src="{{ $railLgD }}" alt="{{ brand_name() }}" class="ig-rail-brand-label ml-3 h-9 max-w-[150px] object-contain {{ $railBoth ? 'brand-logo-dark' : '' }}">@endif
        @else
            <span class="ig-rail-brand-label ml-3">{{ brand_name() }}</span>
        @endif
    </a>
    @if (site_logo())
        {{-- With a logo, drop the icon once the rail expands so only the logo shows. --}}
        <style>.ig-rail:hover .ig-rail-brandmark{display:none!important}</style>
    @endif

    @foreach ($items as $it)
        @php $locked = $isLocked($it['key']); @endphp
        <a href="{{ $locked ? $plansHref : $it['href'] }}" class="ig-rail-ic {{ $it['key'] === $active ? 'active' : '' }}" aria-label="{{ $it['label'] }}{{ $locked ? ' — ' . __('Upgrade to unlock') : '' }}">
            <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $it['icon'] !!}</svg>
            <span class="ig-tip">{{ $it['label'] }}</span>
            @if ($locked)
                {{-- Premium GOLD crown pinned to the icon's top-right. The icon
                     sits in a fixed 46px column in BOTH the collapsed and expanded
                     rail, so a fixed left/top offset keeps the crown on the icon
                     whether the rail is narrow or hovered open. Colour is an inline
                     style (an arbitrary text-[#hex] class is not in the prebuilt
                     CSS, which is why it rendered black). White halo for contrast. --}}
                <span class="absolute" style="left:29px;top:8px;color:#E8A62E;filter:drop-shadow(0 0 1.2px #fff) drop-shadow(0 0 1px #fff)" title="{{ __('Premium — upgrade to unlock') }}">{!! $railCrown !!}</span>
            @endif
            @if ($it['key'] === 'inbox')
                {{-- Hidden until the poller finds something. It used to be
                     unconditional markup, so the dot was on every page forever
                     and meant nothing. data-ibx-dot is what instaflow-inbox-bell
                     toggles. --}}
                <span data-ibx-dot class="hidden absolute top-2 right-2 w-2 h-2 rounded-full bg-ig-pink ring-2 ring-paper-0"></span>
            @endif
        </a>
    @endforeach

    {{-- Plans / pricing. A destination the operator reaches for occasionally
         (to upgrade), so it sits just above More rather than down with the
         utilities. Route::has keeps a build without the route from 500ing the
         whole shell — it just hides the icon. --}}
    @if (\Illuminate\Support\Facades\Route::has('instagram.plans'))
        <a href="{{ route('instagram.plans') }}" class="ig-rail-ic {{ $active === 'plans' ? 'active' : '' }}" aria-label="{{ __('Plans') }}">
            <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 2.6H4.2a1.6 1.6 0 0 0-1.6 1.6v6.1c0 .43.17.83.47 1.13l5.8 5.8a1.6 1.6 0 0 0 2.26 0l5.5-5.5a1.6 1.6 0 0 0 0-2.26l-5.8-5.8a1.6 1.6 0 0 0-1.13-.47Z"/><circle cx="6.3" cy="6.3" r="1"/></svg>
            <span class="ig-tip">{{ __('Plans') }}</span>
        </a>
    @endif

    {{-- Everything the rail no longer carries. Sits with the seven primary
         items rather than down with Theme/Notifications, because it is a
         destination, not a utility. --}}
    <a href="{{ url('/instagram/more') }}" class="ig-rail-ic {{ $active === 'more' ? 'active' : '' }}" aria-label="{{ __('More') }}">
        <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="4.5" cy="10" r="1.4"/><circle cx="10" cy="10" r="1.4"/><circle cx="15.5" cy="10" r="1.4"/></svg>
        <span class="ig-tip">{{ __('More') }}</span>
    </a>

    <div class="flex-1"></div>

    {{-- Language. A PAGE, not a rail flyout: a flyout from the slim rail kept
         landing off-screen / clipped, so — exactly like Theme below — this is a
         plain link to a full picker page. --}}
    <a href="{{ url('/instagram/language') }}" class="ig-rail-ic {{ $active === 'language' ? 'active' : '' }}" aria-label="{{ __('Language') }}">
        <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="7.4"/><path d="M2.6 10h14.8M10 2.6c2 2 3 4.6 3 7.4s-1 5.4-3 7.4c-2-2-3-4.6-3-7.4s1-5.4 3-7.4z"/></svg>
        <span class="ig-tip">{{ __('Language') }}</span>
    </a>

    {{-- Theme. A page, not an in-place toggle: the toggle wrote the preference
         from JS, and a JS-written cookie is plaintext, so Laravel's cookie
         encryption rejected it on the next request and every navigation snapped
         back to light. Saving server-side round-trips correctly. --}}
    <a href="{{ url('/instagram/theme') }}" class="ig-rail-ic {{ $active === 'theme' ? 'active' : '' }}" aria-label="{{ __('Theme') }}">
        <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5a7.5 7.5 0 1 0 0 15c.9 0 1.6-.7 1.6-1.6 0-.4-.2-.8-.4-1.1-.3-.3-.4-.7-.4-1.1 0-.9.7-1.6 1.6-1.6h1.9a3.2 3.2 0 0 0 3.2-3.2C17.5 5.4 14.1 2.5 10 2.5Z"/><circle cx="6.2" cy="8.1" r=".9" fill="currentColor" stroke="none"/><circle cx="9.4" cy="5.7" r=".9" fill="currentColor" stroke="none"/><circle cx="13.1" cy="6.9" r=".9" fill="currentColor" stroke="none"/></svg>
        <span class="ig-tip">{{ __('Theme') }}</span>
    </a>

    {{-- Notifications --}}
    <a href="{{ url('/instagram/notifications') }}" class="ig-rail-ic {{ $active === 'notifications' ? 'active' : '' }}" aria-label="{{ __('Notifications') }}">
        <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8.5a5 5 0 0 1 10 0v2.8l1.3 2.6a.4.4 0 0 1-.4.6H4.1a.4.4 0 0 1-.4-.6L5 11.3z"/><path d="M8 15.6a2 2 0 0 0 4 0"/></svg>
        <span class="ig-tip">{{ __('Notifications') }}</span>
    </a>

    {{-- Connect to WaDesk. The page shipped with no entry point anywhere in the
         product, so the only way to reach the shared key was to type the URL.
         Key is 'settings' because that is what the view already passes as
         ig-active. --}}
    @if ($showWadeskConn)
        <a href="{{ route('instaflow.wadesk-connection') }}" class="ig-rail-ic {{ $active === 'settings' ? 'active' : '' }}" aria-label="{{ __('Connect to WaDesk') }}">
            <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8.6 11.4a3.4 3.4 0 0 0 5.1.4l2-2a3.4 3.4 0 0 0-4.8-4.8l-1.1 1.1"/><path d="M11.4 8.6a3.4 3.4 0 0 0-5.1-.4l-2 2a3.4 3.4 0 0 0 4.8 4.8l1.1-1.1"/></svg>
            <span class="ig-tip">{{ __('Connect to WaDesk') }}</span>
        </a>
    @endif

    {{-- Back to admin. Only for platform admins — lets an operator who dropped
         into the customer UI hop straight back to the console without retyping
         the URL. Sits just above Sign out. --}}
    @if ($authUser && ($authUser->is_admin ?? false) && \Illuminate\Support\Facades\Route::has('admin.overview'))
        <a href="{{ route('admin.overview') }}" class="ig-rail-ic" aria-label="{{ __('Back to admin') }}">
            <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5 16 4.7v4.1c0 3.6-2.5 6.2-6 7.2-3.5-1-6-3.6-6-7.2V4.7z"/><path d="m7.6 9.8 1.8 1.8L13 8"/></svg>
            <span class="ig-tip">{{ __('Back to admin') }}</span>
        </a>
    @endif

    {{-- Sign out. Submits the hidden #logoutForm the layout renders, so this is
         a real POST with a CSRF token and needs no JS. It replaces the floating
         pill that used to sit over page content on every screen — and, on the
         builder, over the canvas controls. --}}
    <button type="submit" form="logoutForm" class="ig-rail-ic" aria-label="{{ __('Sign out') }}">
        <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 3H4.4A1.9 1.9 0 0 0 2.5 4.9v10.2A1.9 1.9 0 0 0 4.4 17h3.1"/><path d="m13 13.5 3.5-3.5L13 6.5"/><path d="M16.5 10h-9"/></svg>
        <span class="ig-tip">{{ __('Sign out') }}</span>
    </button>

    {{-- User --}}
    <a href="{{ url('/account') }}" class="ig-rail-user flex items-center mt-1 shrink-0" title="{{ $authUser?->name }}">
        <span class="ig-ring shrink-0">
            <span class="block w-9 h-9 rounded-full ig-grad-soft text-white text-[12px] font-semibold grid place-items-center">{{ $initials }}</span>
        </span>
        <span class="ig-rail-user-label ml-3 truncate">{{ $authUser?->name ?: __('Account') }}</span>
    </a>
</nav>
</div>

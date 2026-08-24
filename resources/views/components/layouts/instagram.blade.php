@props([
    'title' => null,
    'igActive' => 'dashboard',
    'page' => null,
    // Full-bleed shell: no icon rail, no chrome — the page owns the whole
    // viewport. The flow builder needs this; it is an infinite canvas with its
    // own toolbar, like WaDesk's full-page builder. builder.blade.php already
    // passed :bare="true", but nothing here READ the prop, so the rail rendered
    // regardless and ate a strip of canvas down the left edge.
    'bare' => false,
])

@php
    // Everything resolved here is host-app (WaDesk) surface the standalone
    // edition never ships: two App\Support classes, three global helpers, plus
    // the host build's shell JS and plan chrome further down. class_exists /
    // function_exists is TRUE under WaDesk (behaviour unchanged) and FALSE in
    // standalone, where each falls back to a plain default instead of a fatal on
    // every page. isWaDesk() is the single switch for the host chrome that can
    // only be resolved at COMPILE time, where a runtime @if cannot rescue it.
    $isWaDesk   = \App\Services\Instagram\InstagramGate::isWaDesk();
    $htmlDir    = class_exists(\App\Support\LocaleSettings::class)
        ? \App\Support\LocaleSettings::directionFor(app()->getLocale())
        : 'ltr';
    $appBase    = function_exists('wd_base') ? wd_base() : url('/');
    $defCountry = function_exists('app_default_country')
        ? app_default_country()
        : ['code' => '+1', 'iso' => 'US'];
    $appName    = function_exists('brand_name') ? brand_name() : 'IgDesk';
    // Uploaded favicon (admin General settings). Brand:: is a WaDesk-only class
    // absent in standalone, so read the setting directly via the helper.
    $faviconUrl = function_exists('site_favicon') ? site_favicon() : null;

    // Per-device appearance (mode / accent / typeface / size). One read here,
    // used by <body data-theme>, the font import and the override block below.
    $appearance = \App\Support\InstaflowAppearance::current();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $htmlDir }}" data-theme="{{ $appearance['theme'] }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base" content="{{ $appBase }}">
    <meta name="default-country-code" content="{{ $defCountry['code'] }}">
    <meta name="default-country-iso"  content="{{ $defCountry['iso'] }}">
    {{-- Subfolder base-path shim. On a subfolder install
         (templatecookies.com/instaflow/public) the pre-built inbox/composer JS
         still fetches ROOT-absolute paths like /instagram/inbox/sync — which drop
         the /instaflow/public prefix and 404. WaDesk gets away with the same
         hardcoded paths only because it runs at a subdomain ROOT. Rather than
         re-touch every fetch() in the shipped bundle, prefix any same-origin,
         root-absolute app path with the base ONCE here, before any module loads.
         No-op when installed at the domain root (basePath empty). --}}
    <script>
        (function () {
            var meta = document.querySelector('meta[name="app-base"]');
            if (!meta) return;
            var basePath;
            try { basePath = new URL(meta.content, location.origin).pathname.replace(/\/+$/, ''); }
            catch (e) { return; }
            if (!basePath || basePath === '') return;      // root install → nothing to do
            var withBase = function (u) {
                // Only rewrite our own root-absolute paths that don't already
                // carry the base. Leave //cdn, http(s):// and #anchors alone.
                if (typeof u !== 'string') return u;
                if (u[0] !== '/' || u[1] === '/') return u;
                if (u === basePath || u.indexOf(basePath + '/') === 0) return u;
                return basePath + u;
            };
            var origFetch = window.fetch;
            if (typeof origFetch === 'function') {
                window.fetch = function (input, init) {
                    if (typeof input === 'string') input = withBase(input);
                    return origFetch.call(this, input, init);
                };
            }
            var origOpen = window.XMLHttpRequest && window.XMLHttpRequest.prototype.open;
            if (origOpen) {
                window.XMLHttpRequest.prototype.open = function (method, url) {
                    if (typeof url === 'string') { url = withBase(url); arguments[1] = url; }
                    return origOpen.apply(this, arguments);
                };
            }
            window.wdBase = basePath;   // helper for any code that wants it explicitly
        })();
    </script>

    {{-- Toast notifications — the real Toastify npm package (same library WaDesk
         uses), bundled by the Instagram Vite build into instaflow-toast.js and
         loaded on EVERY page so window.toast() exists everywhere. WaDesk defines
         it in its core app.js bundle; the standalone build shipped none, so every
         window.toast?.() call was a silent no-op — send failures, saves and copy
         confirmations all vanished. --}}
    @if (is_file(public_path('extensions/instagram/instaflow-toast.css')))
        <link rel="stylesheet" href="{{ asset('extensions/instagram/instaflow-toast.css') }}?v={{ filemtime(public_path('extensions/instagram/instaflow-toast.css')) }}">
    @endif
    @if (is_file(public_path('extensions/instagram/instaflow-toast.js')))
        <script type="module" src="{{ asset('extensions/instagram/instaflow-toast.js') }}?v={{ filemtime(public_path('extensions/instagram/instaflow-toast.js')) }}"></script>
    @endif
    @if ($faviconUrl)
        <link rel="icon" type="image/x-icon" href="{{ $faviconUrl }}">
    @endif
    @php
        $flashVariant = session('error') ? 'error' : (session('warning') ? 'warn' : 'success');
        $flashMessage = session('error') ?? (session('warning') ?? (session('success') ?? session('status')));
    @endphp
    @if ($flashMessage)
        <meta name="wa-flash" content='@json(['variant' => $flashVariant, 'message' => $flashMessage])'>
    @endif
    <title>{{ $title }} — {{ brand_name() }}</title>
    @if ($isWaDesk)
        {{-- Host-only chrome. Rendered through <x-dynamic-component> so the name
             resolves at RENDER time: a bare <x-theme-bootstrap /> is resolved by
             ComponentTagCompiler at COMPILE time and throws when the class is
             absent — which is every standalone build — before any @if runs. --}}
        <x-dynamic-component component="theme-bootstrap" />
    @endif
    @auth
        <script>
            window.WADESK_USER = {
                name: @json(auth()->user()->name),
                initials: @json(\Illuminate\Support\Str::of(auth()->user()->name)->trim()->limit(2, '')->upper()->__toString()),
                appName: @json($appName),
            };
        </script>
    @endauth
    {{-- Core shell JS still comes from the host app's build, so it exists only
         under WaDesk. @vite reads a content-hashed manifest a standalone install
         never generates (it runs no npm build) and THROWS rather than degrading,
         so it must not run there. --}}
    @if ($isWaDesk)
        @vite(['resources/js/app.js'])
    @endif

    {{-- This extension's OWN assets are pre-built and shipped inside the
         package, loaded by plain URL rather than through @vite. Laravel's vite
         helper reads a content-hashed manifest that only the core build writes,
         so an installed extension could never appear in it — the client would
         have to run `npm run build`, which they will not. Stable filenames plus
         a ?v= stamp give cache-busting without a manifest. --}}
    @php
        $igAsset = function (string $file) {
            $rel = 'extensions/instagram/' . $file;
            $abs = public_path($rel);
            // filemtime, not the extension version: a re-install of the same
            // version with rebuilt assets must still bust the cache.
            $v = is_file($abs) ? filemtime($abs) : 0;
            return asset($rel) . '?v=' . $v;
        };
    @endphp
    <link rel="stylesheet" href="{{ $igAsset('instaflow.css') }}">

    {{-- Chosen typeface. Skipped for the system stack, which needs no request. --}}
    @if ($appearance['font_import'])
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family={{ $appearance['font_import'] }}&display=swap">
    @endif

    {{-- Appearance overrides. AFTER the stylesheet so they win, and inline so
         they land before first paint — a fetched file would flash the defaults.
         Only ever CSS custom properties, all validated server-side against the
         preset lists, so nothing user-supplied reaches the page verbatim. --}}
    <style>{!! \App\Support\InstaflowAppearance::css() !!}</style>
    @include('partials.analytics-head')
</head>

<body data-nav="instagram" @if ($page) data-page="{{ $page }}" @endif
    data-theme="{{ $appearance['theme'] }}"
    class="h-screen overflow-hidden font-sans antialiased bg-paper-50 text-ink-900 flex flex-col">

    @include('partials.analytics-body')
    @include('partials.preloader')

    @if (!empty($impersonation) && ($impersonation['active'] ?? false))
        <div class="z-[60] bg-accent-amber text-ink-900 border-b border-accent-amber/60 shadow-sm shrink-0">
            <div class="max-w-screen-2xl mx-auto px-4 py-2 flex items-center gap-3 text-[12.5px]">
                <span class="font-mono uppercase tracking-[0.16em] text-[10px]">{{ __('Impersonating') }}</span>
                <span class="font-semibold">{{ $impersonation['target_workspace_name'] ?? 'workspace' }}</span>
                <form method="POST" action="{{ url('/admin/impersonate/stop') }}" class="ml-auto">@csrf
                    <button type="submit" class="px-3 py-1 rounded-full bg-ink-900 text-paper-0 text-[11.5px] font-semibold hover:bg-ink-700">{{ __('Stop impersonating') }}</button>
                </form>
            </div>
        </div>
    @endif

    {{-- Plan / free-trial banner. The component self-hides unless the signed-in
         non-admin user is on a live trial or has just run out — so it is safe to
         render in BOTH standalone Instaflow and WaDesk-embedded mode. --}}
    <div class="shrink-0"><x-dynamic-component component="trial-bar" /></div>

    {{-- New IgDesk shell: slim left icon rail + scrolling main. --}}
    @if ($bare)
        {{-- No rail, and no overflow-y on main: the flow builder manages its own
             scrolling per pane (palette scrolls, canvas pans). An outer scroll
             container would double-scroll the canvas. --}}
        {{-- Full native RTL: the shell inherits <html dir>, so under an RTL
             language the whole layout mirrors — rail moves to the RIGHT, content
             to the left. Under LTR it stays rail-left as usual. --}}
        <div class="flex-1 flex min-h-0 overflow-hidden">
            <main class="flex-1 min-w-0 min-h-0 overflow-hidden">
                {{ $slot }}
            </main>
        </div>
    @else
        <div class="flex-1 flex min-h-0 overflow-hidden">
            <x-instagram.rail :active="$igActive" />
            <main class="flex-1 min-w-0 overflow-y-auto ig-scroll">
                {{ $slot }}
            </main>
        </div>
    @endif

    @if ($isWaDesk)
        <x-dynamic-component component="plan-paywall" />
    @endif
    @includeIf('partials.cookie-consent')
    {{-- Page module for this screen. Type=module so the shared chunks Vite
         split out resolve as relative imports, with no load-order for the
         blade to know about. Missing file = no JS, never a fatal page. --}}
    @if ($page && is_file(public_path('extensions/instagram/' . $page . '.js')))
        <script type="module" src="{{ $igAsset($page . '.js') }}"></script>
    @endif

    @auth
        {{-- New-message pill. Hidden until the poller finds unread inbound mail;
             the module itself no-ops on the inbox page. Sits above the content
             like WaDesk's, but bottom-LEFT of centre so it never covers the
             flow builder's undo/redo cluster in the corner. --}}
        <div id="ibx-bell" class="fixed bottom-5 right-5 z-[55] hidden"
            data-route="{{ url('/instagram/inbox') }}">
            <div class="rounded-2xl overflow-hidden shadow-soft border border-paper-200 bg-paper-0 w-[288px]">
                <button type="button" id="ibx-bell-btn"
                    class="w-full ig-grad-soft text-white flex items-center gap-2.5 px-3 py-2.5 text-left hover:opacity-95 transition">
                    <span class="relative grid place-items-center w-8 h-8 rounded-full bg-white/15 shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z"/></svg>
                        <span id="ibx-bell-count" class="absolute -top-1 -right-1 min-w-[17px] h-[17px] px-1 rounded-full bg-white text-[10px] font-mono font-semibold grid place-items-center" style="color:#C13584">0</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span id="ibx-bell-label" class="block text-[12.5px] font-semibold leading-tight">{{ __('New messages') }}</span>
                        <span class="block text-[10px] font-mono uppercase tracking-[0.14em] opacity-85">{{ __('tap to view') }}</span>
                    </span>
                    <span id="ibx-bell-x" role="button" tabindex="0" aria-label="{{ __('Dismiss') }}"
                        class="w-6 h-6 rounded-full grid place-items-center hover:bg-white/20 shrink-0">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                    </span>
                </button>
                <div id="ibx-bell-list" class="max-h-[188px] overflow-y-auto ig-scroll"></div>
            </div>
        </div>

        @if (is_file(public_path('extensions/instagram/instaflow-inbox-bell.js')))
            <script type="module" src="{{ $igAsset('instaflow-inbox-bell.js') }}"></script>
        @endif
    @endauth

    @stack('scripts')

    @auth
        {{-- WaDesk's shell JS (the command palette) submits this hidden form.
             Standalone ships no such JS and the rail carries no sign-out link,
             so there it becomes a real, JS-free submit button — without it a
             signed-in user has no way out of the app. --}}
        {{-- Always hidden now. The rail carries a real sign-out button that
             submits this form, so the floating pill it used to render is gone —
             it sat over page content on every screen, and on the flow builder it
             covered the canvas controls. --}}
        <form id="logoutForm" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
    @endauth
</body>

</html>

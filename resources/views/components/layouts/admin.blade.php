{{--
    Admin shell — the WaDesk admin layout, standalone + Instagram-themed.

    Same grid (fixed 260px sidebar + fluid main) and the same class vocabulary as
    WaDesk's <x-layouts.admin>, so admin pages ported from it drop in almost
    unchanged. Colours come free: wa-deep / wa-mint / paper / ink are remapped to
    Instagram in instaflow.css.

    The host chrome WaDesk's version carries — its @vite app bundle, the
    notification poller, the injected header-right cluster — is dropped: those
    need host endpoints and JS this build doesn't ship. Each page keeps its own
    <header>, exactly as the WaDesk pages do.
--}}
@props(['title' => 'Admin', 'adminKey' => '', 'page' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ class_exists(\App\Support\LocaleSettings::class) ? \App\Support\LocaleSettings::directionFor(app()->getLocale()) : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if ($fav = (function_exists('site_favicon') ? site_favicon() : null))
        <link rel="icon" href="{{ $fav }}">
    @endif
    <title>{{ $title }} — {{ site_name() }}</title>
    @php
        $igAsset = function (string $file) {
            $rel = 'extensions/instagram/' . $file;
            $abs = public_path($rel);
            $v = is_file($abs) ? filemtime($abs) : 0;
            return asset($rel) . '?v=' . $v;
        };
    @endphp
    <link rel="stylesheet" href="{{ $igAsset('instaflow.css') }}">
</head>

<body data-admin="{{ $adminKey }}" @if ($page) data-page="{{ $page }}" @endif data-theme="paper"
    class="min-h-screen font-sans antialiased bg-paper-50 text-ink-900 overflow-x-clip">

    @include('partials.preloader')

    <div class="admin-shell min-h-screen flex flex-col md:grid md:grid-cols-[260px_minmax(0,1fr)] relative">
        <div id="admin-sidebar-backdrop" class="fixed inset-0 bg-ink-900/50 z-40 hidden transition-opacity opacity-0"></div>
        <aside id="admin-sidebar"
            class="fixed inset-y-0 left-0 z-50 w-[260px] bg-paper-50 md:bg-transparent transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out md:transition-none flex flex-col">
            <x-admin.sidebar :active="$adminKey" />
        </aside>
        <div class="min-w-0 flex flex-col flex-1 w-full relative">
            {{ $slot }}
        </div>
    </div>

    {{-- Mobile sidebar toggle — a tiny inline handler so the shell needs no
         build. The page headers call window.__adminToggleSidebar(true). --}}
    <script>
        (function () {
            var sb = document.getElementById('admin-sidebar');
            var bd = document.getElementById('admin-sidebar-backdrop');
            window.__adminToggleSidebar = function (open) {
                if (!sb || !bd) return;
                sb.classList.toggle('-translate-x-full', !open);
                bd.classList.toggle('hidden', !open);
                requestAnimationFrame(function () { bd.classList.toggle('opacity-0', !open); });
                document.body.style.overflow = open ? 'hidden' : '';
            };
            bd && bd.addEventListener('click', function () { window.__adminToggleSidebar(false); });
        })();
    </script>

    {{-- Shared row kebab-menu handler. The panels live inside overflow-x-auto
         table wrappers, so an absolutely-positioned menu gets clipped and adds a
         stray scrollbar. Positioning it fixed (viewport-relative) escapes the
         clip. Delegated on document so it covers every admin table at once. --}}
    <script>
        (function () {
            function closeAll() {
                document.querySelectorAll('[data-row-menu-panel]:not(.hidden)').forEach(function (p) { p.classList.add('hidden'); });
            }
            document.addEventListener('click', function (e) {
                var toggle = e.target.closest('[data-row-menu-toggle]');
                if (toggle) {
                    e.stopPropagation();
                    var wrap = toggle.closest('[data-row-menu]') || toggle.parentElement;
                    var panel = wrap.querySelector('[data-row-menu-panel]');
                    var willOpen = panel && panel.classList.contains('hidden');
                    closeAll();
                    if (willOpen) {
                        panel.classList.remove('hidden');
                        panel.style.position = 'fixed';
                        panel.style.right = 'auto';
                        panel.style.marginTop = '0';
                        var r = toggle.getBoundingClientRect();
                        var pw = panel.offsetWidth || 180;
                        var ph = panel.offsetHeight || 80;
                        var left = Math.max(8, r.right - pw);
                        var top = r.bottom + 4;
                        if (top + ph > window.innerHeight - 8) top = Math.max(8, r.top - ph - 4);
                        panel.style.left = left + 'px';
                        panel.style.top = top + 'px';
                    }
                    return;
                }
                if (!e.target.closest('[data-row-menu-panel]')) closeAll();
            });
            window.addEventListener('scroll', closeAll, true);
            window.addEventListener('resize', closeAll);
        })();
    </script>

    {{-- Global flash toast — same as WaDesk's admin layout. --}}
    @php $flashMsg = session('success') ?: session('status') ?: null; $flashErr = session('error'); @endphp
    @if ($flashMsg || $flashErr)
        <div id="admin-flash-toast"
            class="fixed top-5 right-5 z-[60] max-w-md rounded-xl shadow-lg border px-4 py-3 text-[13px] font-medium flex items-start gap-3 {{ $flashErr ? 'bg-paper-0 border-accent-coral/40 text-accent-coral' : 'bg-paper-0 border-wa-green/40 text-wa-deep' }}"
            role="status" aria-live="polite">
            <svg viewBox="0 0 16 16" class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6">
                @if ($flashErr)<circle cx="8" cy="8" r="6" /><path d="M8 5v4M8 11h.01" />@else<circle cx="8" cy="8" r="6" /><path d="M5.5 8.5l1.8 1.8L10.5 6.5" />@endif
            </svg>
            <span class="flex-1">{{ $flashErr ?: $flashMsg }}</span>
            <button type="button" onclick="this.closest('#admin-flash-toast').remove()" class="text-ink-500 hover:text-ink-900 leading-none" aria-label="Dismiss">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4l8 8M12 4l-8 8" /></svg>
            </button>
        </div>
        <script>setTimeout(function(){var el=document.getElementById('admin-flash-toast');if(el){el.style.transition='opacity .3s';el.style.opacity='0';setTimeout(function(){el.remove();},320);}},4000);</script>
    @endif

    @stack('scripts')

    {{-- Header-right control set rendered once, then MOVED into each page's
         [data-admin-header-right] slot by the script below — exactly how WaDesk's
         admin layout does it. Also injects the search bar + mobile toggle. --}}
    @php
        $adminNav = [
            ['label' => __('Overview'), 'url' => route('admin.overview'), 'group' => __('Main')],
            ['label' => __('Financial'), 'url' => route('admin.financial'), 'group' => __('Main')],
            ['label' => __('Premium'), 'url' => route('admin.premium'), 'group' => __('Main')],
            ['label' => __('Analytics'), 'url' => route('admin.analytics'), 'group' => __('Main')],
            ['label' => __('AI Dashboard'), 'url' => route('admin.ai'), 'group' => __('Main')],
            ['label' => __('System Health'), 'url' => route('admin.health'), 'group' => __('Main')],
            ['label' => __('Users'), 'url' => route('admin.users'), 'group' => __('Users & access')],
            ['label' => __('Packages'), 'url' => route('admin.packages.index'), 'group' => __('Billing & plans')],
            ['label' => __('New package'), 'url' => route('admin.packages.create'), 'group' => __('Billing & plans')],
            ['label' => __('Coupons'), 'url' => route('admin.coupons.index'), 'group' => __('Billing & plans')],
            ['label' => __('New coupon'), 'url' => route('admin.coupons.create'), 'group' => __('Billing & plans')],
            ['label' => __('Order history'), 'url' => route('admin.orders'), 'group' => __('Billing & plans')],
            ['label' => __('Invoices'), 'url' => route('admin.invoices'), 'group' => __('Billing & plans')],
            ['label' => __('Billing settings'), 'url' => route('admin.billing'), 'group' => __('Billing & plans')],
            ['label' => __('Flow templates'), 'url' => route('admin.flow-templates.index'), 'group' => __('Automation')],
            ['label' => __('Currencies'), 'url' => route('admin.currencies.index'), 'group' => __('Localization')],
            ['label' => __('Languages'), 'url' => route('admin.languages.index'), 'group' => __('Localization')],
            ['label' => __('General settings'), 'url' => route('admin.settings'), 'group' => __('Settings')],
            ['label' => __('Site settings'), 'url' => route('admin.site'), 'group' => __('Settings')],
            ['label' => __('SEO'), 'url' => route('admin.settings.seo'), 'group' => __('Settings')],
            ['label' => __('Tracking / Analytics'), 'url' => route('admin.settings.analytics'), 'group' => __('Settings')],
            ['label' => __('Social login'), 'url' => route('admin.settings.social'), 'group' => __('Settings')],
            ['label' => __('Privacy'), 'url' => route('admin.settings.privacy'), 'group' => __('Settings')],
            ['label' => __('Mail settings'), 'url' => route('admin.settings.mail'), 'group' => __('Settings')],
            ['label' => __('Security'), 'url' => route('admin.security'), 'group' => __('System')],
            ['label' => __('Storage'), 'url' => route('admin.storage'), 'group' => __('System')],
            ['label' => __('Audit log'), 'url' => route('admin.audit'), 'group' => __('System')],
            // Updater hidden for now — route still exists at /admin/update.
        ];
    @endphp
    <script>window.__adminNav = @json($adminNav);</script>

    <div id="admin-header-right-source" style="display:none">
        <x-admin.header-right />
    </div>
    <script>
        (function () {
            // Apply saved admin theme early.
            var TKEY = 'instaflow_admin_theme';
            try { var t = localStorage.getItem(TKEY); if (t) document.body.setAttribute('data-theme', t === 'dark' ? 'dark' : 'paper'); } catch (e) {}

            function injectSearchBar() {
                var slot = document.querySelector('[data-admin-header-right]');
                if (!slot) return;
                var header = slot.closest('header');
                if (header && !header.querySelector('.admin-mobile-toggle')) {
                    var breadcrumb = header.firstElementChild;
                    if (breadcrumb && breadcrumb !== slot && !breadcrumb.classList.contains('admin-mobile-toggle')) {
                        breadcrumb.classList.add('hidden', 'md:flex');
                    }
                    var toggle = document.createElement('button');
                    toggle.type = 'button';
                    toggle.className = 'admin-mobile-toggle md:hidden w-9 h-9 flex items-center justify-center rounded-lg bg-paper-50 text-ink-700 hover:bg-paper-100 shrink-0 mr-2 -ml-2';
                    toggle.innerHTML = '<svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>';
                    toggle.addEventListener('click', function (e) { e.preventDefault(); window.__adminToggleSidebar && window.__adminToggleSidebar(true); });
                    header.insertBefore(toggle, header.firstChild);
                }
                if (!header || header.dataset.adminSearchFilled === '1') return;
                var existing = header.querySelector('input[type="search"]');
                if (existing) { existing.closest('div') && existing.closest('div').classList.add('hidden', 'md:block'); header.dataset.adminSearchFilled = '1'; return; }
                var wrap = document.createElement('div');
                wrap.className = 'hidden md:block relative flex-1 max-w-[520px] ml-1 md:ml-4';
                wrap.innerHTML = '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-[18px] -translate-y-1/2 text-ink-500 pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="7" cy="7" r="5"/><path d="m11 11 3 3"/></svg><input type="search" id="admin-search-input" autocomplete="off" class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-12 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition" placeholder="Search admin..." /><kbd class="hidden md:block absolute right-3 top-[18px] -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-paper-0 border border-paper-200 text-[10px] font-mono text-ink-500">⌘K</kbd><div id="admin-search-results" class="hidden absolute left-0 right-0 top-full mt-2 bg-paper-0 border border-paper-200 rounded-2xl shadow-soft overflow-hidden z-50 max-h-[360px] overflow-y-auto"></div>';
                header.insertBefore(wrap, slot);
                header.dataset.adminSearchFilled = '1';
                wireSearch(wrap);
            }

            function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

            function wireSearch(wrap) {
                var input = wrap.querySelector('#admin-search-input');
                var box = wrap.querySelector('#admin-search-results');
                var nav = window.__adminNav || [];
                var active = -1, shown = [];
                function paint() { Array.prototype.forEach.call(box.children, function (el, i) { el.classList.toggle('bg-paper-50', i === active); }); }
                function render(q) {
                    q = (q || '').trim().toLowerCase();
                    shown = q ? nav.filter(function (n) { return (n.label + ' ' + n.group).toLowerCase().indexOf(q) >= 0; }) : nav.slice();
                    active = shown.length ? 0 : -1;
                    if (!shown.length) {
                        box.innerHTML = '<div class="px-4 py-6 text-center text-[12px] text-ink-500">No matches.</div>';
                    } else {
                        box.innerHTML = shown.map(function (n, i) {
                            return '<a href="' + esc(n.url) + '" class="admin-search-row flex items-center justify-between gap-3 px-4 py-2.5 text-[13px] ' + (i === active ? 'bg-paper-50' : '') + ' hover:bg-paper-50"><span class="font-medium text-ink-900">' + esc(n.label) + '</span><span class="text-[10.5px] font-mono text-ink-500">' + esc(n.group) + '</span></a>';
                        }).join('');
                    }
                    box.classList.remove('hidden');
                }
                function go() { if (active >= 0 && shown[active]) window.location.href = shown[active].url; }
                input.addEventListener('focus', function () { render(input.value); });
                input.addEventListener('input', function () { render(input.value); });
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, shown.length - 1); paint(); }
                    else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); paint(); }
                    else if (e.key === 'Enter') { e.preventDefault(); go(); }
                    else if (e.key === 'Escape') { box.classList.add('hidden'); input.blur(); }
                });
                document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) box.classList.add('hidden'); });
                document.addEventListener('keydown', function (e) {
                    if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); input.focus(); render(''); }
                });
            }

            function wireControls() {
                document.querySelectorAll('[data-user-toggle]').forEach(function (btn) {
                    if (btn.__wired) return; btn.__wired = true;
                    var pane = btn.parentElement && btn.parentElement.querySelector('[data-user-pane]');
                    btn.addEventListener('click', function (e) { e.stopPropagation(); pane && pane.classList.toggle('hidden'); });
                });
                var themeBtn = document.getElementById('wa-theme-btn');
                if (themeBtn && !themeBtn.__wired) {
                    themeBtn.__wired = true;
                    themeBtn.addEventListener('click', function () {
                        var cur = document.body.getAttribute('data-theme') === 'dark' ? 'dark' : 'paper';
                        var nxt = cur === 'dark' ? 'paper' : 'dark';
                        document.body.setAttribute('data-theme', nxt);
                        try { localStorage.setItem(TKEY, nxt); } catch (e) {}
                    });
                }
                document.addEventListener('click', function () {
                    document.querySelectorAll('[data-user-pane]:not(.hidden)').forEach(function (p) { p.classList.add('hidden'); });
                });
            }

            function injectHeaderRight() {
                injectSearchBar();
                var src = document.getElementById('admin-header-right-source');
                var slot = document.querySelector('[data-admin-header-right]');
                if (src && slot && slot.dataset.adminHeaderFilled !== '1') {
                    var node = src.firstElementChild;
                    if (node) { slot.appendChild(node); slot.dataset.adminHeaderFilled = '1'; src.remove(); }
                }
                wireControls();
            }

            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', injectHeaderRight);
            else injectHeaderRight();
        })();
    </script>

    @auth
        <form id="logoutForm" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
    @endauth

    {{-- Page module, when a built bundle exists for this screen (e.g. the
         package stepper). Guarded on is_file so a missing bundle is simply no
         JS, never a fatal page. --}}
    @if ($page && is_file(public_path('extensions/instagram/' . $page . '.js')))
        <script type="module" src="{{ $igAsset($page . '.js') }}"></script>
    @endif
</body>

</html>

@php
    // Same cache-busted plain-URL asset loader the IgDesk layouts use — the
    // installer runs before any build/manifest exists, so never @vite here.
    $igAsset = function (string $file) {
        $rel = 'extensions/instagram/' . $file;
        $abs = public_path($rel);
        return asset($rel) . '?v=' . (is_file($abs) ? filemtime($abs) : 0);
    };
    $steps = ['Welcome', 'Requirements', 'Database', 'Application', 'Admin', 'Node', 'Install'];
    $licenseConfigured = false;
    // Reusable SVG snippets.
    $tick = '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l3 3 7-8"/></svg>';
    $cross = '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4l8 8M12 4l-8 8"/></svg>';
    $arrow = '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10M9 4l4 4-4 4"/></svg>';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Install · {{ site_name() }}</title>
    <link rel="stylesheet" href="{{ $igAsset('instaflow.css') }}">
</head>
<body class="bg-paper-50 text-ink-900 antialiased">
    <div class="min-h-screen w-full grid place-items-center p-4 sm:p-6 lg:p-8 install-grid relative overflow-hidden">
        {{-- ambient glows --}}
        <div class="absolute -top-40 -left-32 w-[30rem] h-[30rem] rounded-full bg-ig-purple/12 blur-[130px] pointer-events-none"></div>
        <div class="absolute -bottom-40 -right-32 w-[30rem] h-[30rem] rounded-full bg-ig-orange/12 blur-[130px] pointer-events-none"></div>

        <div class="iw-card-in w-full max-w-[1040px] flex bg-paper-0 border border-paper-200 rounded-[26px] shadow-soft overflow-hidden relative z-10">

            {{-- LEFT rail --}}
            <aside class="hidden lg:flex lg:w-[300px] shrink-0 flex-col justify-between bg-paper-50 border-r border-paper-200 relative overflow-hidden">
                <div class="absolute -top-16 -right-16 w-52 h-52 rounded-full bg-ig-pink/12 blur-3xl pointer-events-none"></div>
                <div class="relative z-10 px-8 pt-8">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-xl ig-grad-ring grid place-items-center shrink-0 shadow-card">
                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="#fff" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="#fff" stroke="none"/>
                            </svg>
                        </span>
                        <span class="font-serif text-[23px] leading-none tracking-[-0.01em]">Insta<span class="ig-text">Magic</span></span>
                    </div>
                    <div class="mt-7 space-y-2">
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ig-magenta">Setup wizard</div>
                        <h1 class="font-serif text-[21px] leading-[1.18] tracking-[-0.01em]">
                            Your <span class="italic ig-text">Instagram suite</span>, ready in minutes.
                        </h1>
                    </div>
                </div>

                <div class="relative z-10 px-6 py-6 flex-1 flex items-center min-h-0">
                    <div class="w-full space-y-0.5">
                        @foreach ($steps as $i => $label)
                            <div data-iw-stepitem="{{ $i }}" class="flex items-center gap-2.5 py-1.5 px-2.5 rounded-xl transition-all {{ $i === 0 ? 'bg-ig-pink/10' : '' }}">
                                <span data-iw-dot="{{ $i }}" class="w-7 h-7 rounded-full grid place-items-center shrink-0 font-mono text-[12px] font-semibold transition-all
                                    {{ $i === 0 ? 'ig-grad-btn shadow-card' : 'bg-paper-100 border border-paper-200 text-ink-500' }}">
                                    <span data-iw-num>{{ $i + 1 }}</span>
                                    <svg data-iw-tick class="w-3.5 h-3.5 hidden" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l3 3 7-8"/></svg>
                                </span>
                                <span data-iw-label class="text-[12px] font-mono uppercase tracking-[0.14em] transition-colors {{ $i === 0 ? 'text-ink-900 font-semibold' : 'text-ink-500' }}">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="relative z-10 px-8 pb-7">
                    <p class="text-[10px] font-mono uppercase tracking-[0.18em] text-ink-500 flex items-center gap-2.5">
                        <span class="h-px w-6 bg-paper-300"></span> InstaMagic installer · v1.0
                    </p>
                </div>
            </aside>

            {{-- RIGHT body --}}
            <main class="flex-1 min-w-0 flex flex-col max-h-[calc(100dvh-2rem)]">
                <div class="lg:hidden px-6 pt-6 pb-4 border-b border-paper-200">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-serif text-[17px]">Insta<span class="ig-text">Magic</span></span>
                        <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-ig-magenta"><span data-iw-mobstep>1</span>/8 · <span data-iw-mobname>Welcome</span></span>
                    </div>
                    <div class="w-full bg-paper-200 rounded-full h-1 mt-3"><div data-iw-mobbar class="ig-grad-btn h-1 rounded-full transition-all duration-500" style="width:16.6%"></div></div>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto install-scroll px-7 md:px-9 py-8 bg-paper-0">
                    <form id="iw-form" autocomplete="off" onsubmit="return false">
                        @csrf

                        {{-- 1 · WELCOME --}}
                        <section data-iw-panel="0" class="iw-step-enter space-y-5">
                            <div class="space-y-2">
                                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ig-magenta">Welcome</div>
                                <h2 class="font-serif text-[30px] leading-[1.05] tracking-[-0.01em]">Let's set up <span class="italic ig-text">{{ site_name() }}</span>.</h2>
                                <p class="text-[12.5px] text-ink-600 leading-relaxed max-w-[520px]">Six quick steps, about a minute. Everything is already built and bundled — no commands to run. We'll verify your server, create the database, seed every default, and create your first admin login.</p>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5">
                                @php
                                    $tiles = [
                                        ['Database', '<rect x="3" y="3" width="10" height="3" rx="1.2"/><rect x="3" y="6.5" width="10" height="3" rx="1.2"/><rect x="3" y="10" width="10" height="3" rx="1.2"/>'],
                                        ['Migrations', '<path d="M8 2v9M4.5 7.5L8 11l3.5-3.5"/><rect x="3" y="12.5" width="10" height="1.5" rx=".7"/>'],
                                        ['Node engine', '<circle cx="8" cy="8" r="2"/><path d="M8 2v2.5M8 11.5V14M2 8h2.5M11.5 8H14M4 4l1.8 1.8M10.2 10.2L12 12"/>'],
                                        ['Admin', '<circle cx="6" cy="6" r="2.5"/><path d="M2 14c0-3 1.7-5 4-5s4 2 4 5"/><circle cx="11.5" cy="7" r="2"/><path d="M9 14c0-2 1.5-3.5 3-3.5s3 1.5 3 3.5"/>'],
                                    ];
                                @endphp
                                @foreach ($tiles as [$label, $svg])
                                    <div class="bg-paper-0 border border-paper-200 rounded-2xl px-4 py-3.5 shadow-card">
                                        <span class="w-7 h-7 rounded-lg bg-ig-pink/12 text-ig-magenta grid place-items-center mb-2">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">{!! $svg !!}</svg>
                                        </span>
                                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ $label }}</div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">Before you start</div>
                                <ul class="text-[12.5px] text-ink-700 leading-relaxed space-y-1.5 list-disc pl-5">
                                    <li>A MySQL database and a user with full privileges (we create the database if it's missing).</li>
                                    <li>The public HTTPS URL this site will run on.</li>
                        </section>

                        {{-- 2 · REQUIREMENTS --}}
                        <section data-iw-panel="1" class="hidden space-y-4">
                            <div class="space-y-1.5">
                                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ig-magenta">Step 2 of 7</div>
                                <h2 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">Server <span class="italic ig-text">requirements</span>.</h2>
                                <p class="text-[12px] text-ink-600">We're checking the host meets the minimum bar before installing.</p>
                            </div>
                            @if ($php['ok'])
                                <div class="rounded-xl border border-ig-pink/40 bg-ig-pink/8 px-4 py-2.5 flex items-center gap-2.5 text-[12.5px] font-semibold text-ig-magenta">
                                    {!! $tick !!} PHP {{ $php['version'] }} <span class="text-ink-600 font-mono text-[11px] font-medium">— meets the 8.2+ requirement</span>
                                </div>
                            @else
                                <div class="rounded-xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-2.5 flex items-center gap-2.5 text-[12.5px] font-semibold text-accent-coral">
                                    {!! $cross !!} PHP {{ $php['version'] }} <span class="font-mono text-[11px] font-medium">— InstaMagic needs 8.2 or newer.</span>
                                </div>
                            @endif
                            <div class="grid lg:grid-cols-2 gap-3.5">
                                <div class="bg-paper-50 border border-paper-200 rounded-2xl p-3.5">
                                    <div class="flex items-center justify-between mb-2.5">
                                        <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">PHP extensions</span>
                                        <span class="font-mono text-[10.5px] font-semibold {{ !in_array(false, $ext, true) ? 'text-ig-magenta' : 'text-accent-coral' }}">{{ count(array_filter($ext)) }}/{{ count($ext) }}</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-1.5">
                                        @foreach ($ext as $name => $loaded)
                                            <div class="flex items-center justify-between rounded-lg border border-paper-200 bg-paper-0 pl-2.5 pr-2 py-1">
                                                <span class="text-[11.5px] font-mono text-ink-700">{{ $name }}</span>
                                                <span class="{{ $loaded ? 'text-ig-magenta' : 'text-accent-coral' }}">{!! $loaded ? $tick : $cross !!}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="space-y-3.5">
                                    <div class="bg-paper-50 border border-paper-200 rounded-2xl p-3.5">
                                        <div class="flex items-center justify-between mb-2.5">
                                            <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Writable</span>
                                            <span class="font-mono text-[10.5px] font-semibold {{ !in_array(false, $writable, true) ? 'text-ig-magenta' : 'text-accent-coral' }}">{{ count(array_filter($writable)) }}/{{ count($writable) }}</span>
                                        </div>
                                        <div class="space-y-1.5">
                                            @foreach ($writable as $path => $ok)
                                                <div class="flex items-center justify-between rounded-lg border border-paper-200 bg-paper-0 pl-2.5 pr-2 py-1">
                                                    <span class="text-[11.5px] font-mono text-ink-700 truncate">{{ $path }}</span>
                                                    <span class="{{ $ok ? 'text-ig-magenta' : 'text-accent-coral' }}">{!! $ok ? $tick : $cross !!}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="bg-paper-50 border border-paper-200 rounded-2xl p-3.5">
                                        <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">PDO drivers</span>
                                        <div class="flex flex-wrap gap-1.5 mt-2.5">
                                            @forelse ($pdo as $driver)
                                                <div class="inline-flex items-center gap-1.5 rounded-lg border border-paper-200 bg-paper-0 px-2.5 py-1">
                                                    <span class="text-ig-magenta">{!! $tick !!}</span><span class="text-[11.5px] font-mono text-ink-700">{{ $driver }}</span>
                                                </div>
                                            @empty
                                                <div class="text-[12px] text-ink-500 italic">No PDO drivers detected.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @unless ($allOk)
                                <div class="rounded-xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-2.5 text-[12px] font-medium text-accent-coral">Some checks failed. Install the missing extensions / fix folder permissions, then reload.</div>
                            @endunless
                        </section>

                        {{-- 3 · DATABASE --}}
                        <section data-iw-panel="2" class="hidden space-y-4">
                            <div class="space-y-1.5">
                                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ig-magenta">Step 3 of 7</div>
                                <h2 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">Database <span class="italic ig-text">connection</span>.</h2>
                                <p class="text-[12px] text-ink-600">Point InstaMagic at a MySQL database. We create it automatically if it doesn't exist, and verify the connection first.</p>
                            </div>
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4 space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Database driver</div>
                                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-ig-pink/8 border border-ig-pink/40 text-ig-magenta text-[12px] font-semibold">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><ellipse cx="8" cy="3.5" rx="5.5" ry="2"/><path d="M2.5 3.5v9c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2v-9"/><path d="M2.5 8c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2"/></svg>
                                        MySQL
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-3">
                                    <div class="col-span-2">@include('install._field', ['label' => 'Host', 'name' => 'db_host', 'value' => $defaults['db_host']])</div>
                                    <div>@include('install._field', ['label' => 'Port', 'name' => 'db_port', 'value' => $defaults['db_port']])</div>
                                </div>
                                @include('install._field', ['label' => 'Database name', 'name' => 'db_name', 'value' => $defaults['db_name']])
                                <div class="grid grid-cols-2 gap-3">
                                    @include('install._field', ['label' => 'Username', 'name' => 'db_user', 'value' => $defaults['db_user']])
                                    @include('install._field', ['label' => 'Password', 'name' => 'db_pass', 'value' => '', 'type' => 'password'])
                                </div>
                                <button type="button" data-iw-testdb class="w-full h-10 rounded-full border border-paper-200 bg-paper-50 hover:bg-paper-100 text-[12.5px] font-semibold inline-flex items-center justify-center gap-2">
                                    <svg data-iw-dbspin class="w-3.5 h-3.5 iw-spin hidden" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 8a6 6 0 1 1-6-6" stroke-linecap="round"/></svg>
                                    <span data-iw-dbtext>Test connection</span>
                                </button>
                                <div data-iw-dbresult class="hidden rounded-xl border px-3 py-2 text-[12px] font-medium"></div>
                            </div>
                        </section>

                        {{-- 4 · APPLICATION --}}
                        <section data-iw-panel="3" class="hidden space-y-4">
                            <div class="space-y-1.5">
                                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ig-magenta">Step 4 of 7</div>
                                <h2 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">Application <span class="italic ig-text">basics</span>.</h2>
                                <p class="text-[12px] text-ink-600">Name your install, set the public URL, and pick a timezone + default language.</p>
                            </div>
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4 space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div data-iw-fieldwrap>
                                        <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Application name</label>
                                        <input type="text" name="app_name" data-iw-input value="{{ $defaults['app_name'] }}" placeholder="{{ site_name() }}" autocomplete="off"
                                            class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] text-ink-900 focus:outline-none focus:border-ig-magenta focus:bg-paper-0 transition-colors">
                                        <p data-iw-fieldmsg class="hidden mt-1.5 text-[11px] font-medium text-accent-coral"></p>
                                    </div>
                                    <div data-iw-fieldwrap>
                                        <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Application URL</label>
                                        <input type="url" name="app_url" data-iw-input value="{{ $defaults['app_url'] }}" placeholder="https://app.example.com" autocomplete="off"
                                            class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono text-ink-900 focus:outline-none focus:border-ig-magenta focus:bg-paper-0 transition-colors">
                                        <p data-iw-fieldmsg class="hidden mt-1.5 text-[11px] font-medium text-accent-coral"></p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="relative">
                                        <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Timezone</label>
                                        <select name="app_timezone"
                                            class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] text-ink-900 focus:outline-none focus:border-ig-magenta focus:bg-paper-0 transition-colors">
                                            @foreach (timezone_identifiers_list() as $tz)
                                                <option value="{{ $tz }}" @selected($tz === ($defaults['timezone'] ?? 'UTC'))>{{ $tz }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Default language</label>
                                        @php
                                            $locales = ['en'=>'English','es'=>'Español (Spanish)','hi'=>'हिन्दी (Hindi)','ar'=>'العربية (Arabic)','pt'=>'Português','ru'=>'Русский (Russian)','ja'=>'日本語 (Japanese)','de'=>'Deutsch (German)','fr'=>'Français (French)','it'=>'Italiano','ko'=>'한국어 (Korean)','zh-CN'=>'简体中文 (Chinese)','tr'=>'Türkçe','id'=>'Bahasa Indonesia','vi'=>'Tiếng Việt','th'=>'ไทย (Thai)','pl'=>'Polski','nl'=>'Nederlands','ur'=>'اردو (Urdu)','he'=>'עברית (Hebrew)','bn'=>'বাংলা (Bengali)'];
                                        @endphp
                                        <select name="app_locale"
                                            class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] text-ink-900 focus:outline-none focus:border-ig-magenta focus:bg-paper-0 transition-colors">
                                            @foreach ($locales as $code => $label)
                                                <option value="{{ $code }}" @selected($defaults['locale'] === $code)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {{-- 5 · ADMIN --}}
                        <section data-iw-panel="4" class="hidden space-y-4">
                            <div class="space-y-1.5">
                                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ig-magenta">Step 5 of 7</div>
                                <h2 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">Your <span class="italic ig-text">admin</span> account.</h2>
                                <p class="text-[12px] text-ink-600">The first administrator. Password must be at least 8 characters.</p>
                            </div>
                            <div class="bg-paper-50 border border-paper-200 rounded-2xl p-5 shadow-card">
                                @include('install._field', ['label' => 'Full name', 'name' => 'admin_name', 'value' => 'Site Admin'])
                                @include('install._field', ['label' => 'Email', 'name' => 'admin_email', 'value' => '', 'type' => 'email'])
                                @include('install._field', ['label' => 'Password', 'name' => 'admin_password', 'value' => '', 'type' => 'password'])
                            </div>
                        </section>

                        {{-- 6 · NODE ENGINE --}}
                        <section data-iw-panel="5" class="hidden space-y-4">
                            <div class="space-y-1.5">
                                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ig-magenta">Step 6 of 7</div>
                                <h2 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">Node <span class="italic ig-text">engine</span>.</h2>
                                <p class="text-[12px] text-ink-600">InstaMagic runs flows, the scheduler and reels through a small Node service. Point Laravel at it and set the shared token — the installer writes both env files.</p>
                            </div>
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4 space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div data-iw-fieldwrap>
                                        <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Node server URL</label>
                                        <input type="url" name="server_url" data-iw-input value="{{ $defaults['server_url'] }}" placeholder="http://localhost:{{ $defaults['node_port'] }}" autocomplete="off"
                                            class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono text-ink-900 focus:outline-none focus:border-ig-magenta focus:bg-paper-0 transition-colors">
                                        <p class="mt-1 text-[10.5px] text-ink-500 leading-relaxed">Where your Node service listens. Laravel calls this to run flows.</p>
                                        <p data-iw-fieldmsg class="hidden mt-1.5 text-[11px] font-medium text-accent-coral"></p>
                                    </div>
                                    <div data-iw-fieldwrap>
                                        <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Node port</label>
                                        <input type="number" name="node_port" data-iw-input min="1" max="65535" value="{{ $defaults['node_port'] }}" placeholder="{{ $defaults['node_port'] }}" autocomplete="off"
                                            class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono text-ink-900 focus:outline-none focus:border-ig-magenta focus:bg-paper-0 transition-colors">
                                        <p class="mt-1 text-[10.5px] text-ink-500 leading-relaxed">The port the Node service binds to (written to <span class="font-mono">node/.env</span>).</p>
                                        <p data-iw-fieldmsg class="hidden mt-1.5 text-[11px] font-medium text-accent-coral"></p>
                                    </div>
                                </div>
                                <div data-iw-fieldwrap>
                                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Node shared token</label>
                                    <div class="relative mt-1">
                                        <input type="text" name="node_token" data-iw-input value="{{ $defaults['node_token'] }}" autocomplete="off"
                                            class="w-full h-10 pl-3 pr-28 rounded-xl border border-paper-200 bg-paper-50 text-[12px] font-mono text-ink-900 tracking-tight focus:outline-none focus:border-ig-magenta focus:bg-paper-0 transition-colors">
                                        <button type="button" data-iw-gentoken class="absolute right-1.5 top-1/2 -translate-y-1/2 px-3 h-7 inline-flex items-center gap-1.5 rounded-lg border border-paper-200 bg-paper-0 hover:bg-ig-pink/8 text-[11px] font-semibold text-ig-magenta">
                                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 8a5.5 5.5 0 1 1-1.6-3.9M13.5 2.5V5H11"/></svg>
                                            Generate
                                        </button>
                                    </div>
                                    <p class="mt-1 text-[10.5px] text-ink-500 leading-relaxed">Auto-filled. Must match in Laravel and the Node service — the installer writes both.</p>
                                    <p data-iw-fieldmsg class="hidden mt-1.5 text-[11px] font-medium text-accent-coral"></p>
                                </div>
                                <div class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2 text-[10.5px] text-ink-500 font-mono leading-relaxed flex items-start gap-2">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-ig-magenta" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6.5"/><path stroke-linecap="round" d="M8 7.5v4M8 5h.01"/></svg>
                                    <span>These write to <span class="text-ink-700">.env</span> and <span class="text-ink-700">node/.env</span> automatically. The token is shared as <span class="text-ink-700">NODE_WEBHOOK_TOKEN</span> across both.</span>
                                </div>
                            </div>
                        </section>

                        {{-- 7 · INSTALL — stepped progress, auto-runs on arrival --}}
                        <section data-iw-panel="6" class="hidden space-y-5">
                            <div class="space-y-1.5">
                                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ig-magenta">Step 7 of 7</div>
                                <h2 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">Installing <span class="italic ig-text">{{ site_name() }}</span>.</h2>
                                <p class="text-[12px] text-ink-600">Sit tight — we're configuring everything. This usually takes 20–40 seconds.</p>
                            </div>

                            {{-- Overall progress bar --}}
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
                                <div class="flex items-center justify-between mb-2">
                                    <span data-iw-prog-label class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Preparing…</span>
                                    <span data-iw-prog-pct class="font-mono text-[10.5px] font-semibold text-ig-magenta tabular-nums">0%</span>
                                </div>
                                <div class="w-full h-1.5 bg-paper-200 rounded-full overflow-hidden">
                                    <div data-iw-prog-bar class="h-full rounded-full ig-grad-btn transition-all duration-700 ease-out" style="width:0%"></div>
                                </div>

                                {{-- Substep list --}}
                                <div class="mt-5 space-y-1" data-iw-steps>
                                    @php
                                        $substeps = [
                                            ['Environment written', 'Writing .env configuration…'],
                                            ['Database tables created', 'Running database migrations…'],
                                            ['Essential data seeded', 'Seeding plans, currencies, gateways, guidebook…'],
                                            ['Admin account ready', 'Creating your administrator account…'],
                                            ['File permissions set', 'Linking storage directories…'],
                                            ['Installation finalized', 'Clearing caches and writing install marker…'],
                                        ];
                                    @endphp
                                    @foreach ($substeps as $i => [$label, $active])
                                        <div data-iw-step="{{ $i }}" data-iw-label="{{ $label }}" data-iw-active="{{ $active }}"
                                            class="flex items-center gap-3 py-2 px-2.5 rounded-xl transition-colors">
                                            <div class="w-5 h-5 grid place-items-center shrink-0">
                                                <svg data-iw-ic-pending viewBox="0 0 16 16" class="w-4 h-4 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6.5"/></svg>
                                                <svg data-iw-ic-run viewBox="0 0 16 16" class="w-4 h-4 hidden text-ig-magenta iw-spin" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M14 8a6 6 0 1 1-6-6"/></svg>
                                                <svg data-iw-ic-done viewBox="0 0 16 16" class="w-4 h-4 hidden text-ig-magenta" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l3 3 7-8"/></svg>
                                                <svg data-iw-ic-fail viewBox="0 0 16 16" class="w-4 h-4 hidden text-accent-coral" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4l8 8M12 4l-8 8"/></svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <span data-iw-step-text class="text-[12.5px] text-ink-500 transition-colors">{{ $label }}</span>
                                            </div>
                                            <div class="shrink-0 font-mono text-[10.5px] text-ink-500 tabular-nums">
                                                <span data-iw-step-time></span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Error block --}}
                            <div data-iw-err-block class="hidden rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-3">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-accent-coral mb-1">Installation paused</div>
                                <p data-iw-installerr class="text-[12.5px] text-ink-700 font-medium"></p>
                            </div>
                            <button type="button" data-iw-retry class="hidden w-full px-6 h-11 rounded-full ig-grad-btn text-[13px] font-semibold shadow-card">Retry from failed step</button>

                            {{-- Success block --}}
                            <div data-iw-done-block class="hidden rounded-2xl border border-ig-pink/40 bg-ig-pink/8 px-4 py-3 flex items-center gap-3">
                                <svg class="w-5 h-5 text-ig-magenta shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="8" cy="8" r="6.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5.5 8l2 2 3.5-4"/></svg>
                                <span class="text-[13px] font-semibold text-ig-magenta">Installation complete — redirecting…</span>
                            </div>
                        </section>

                        {{-- footer nav --}}
                        <div class="flex items-center justify-between gap-3 mt-8 pt-5 border-t border-paper-100">
                            <button type="button" data-iw-back class="px-5 h-10 inline-flex items-center gap-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12.5px] font-semibold text-ink-700 disabled:opacity-0 disabled:pointer-events-none" disabled>
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 8H3M7 4L3 8l4 4"/></svg>
                                Back
                            </button>
                            <div class="flex items-center gap-2.5">
                                <button type="button" data-iw-next class="px-6 h-10 inline-flex items-center gap-2 rounded-full ig-grad-btn text-[13px] font-semibold shadow-card">
                                    <span data-iw-nexttext>Continue</span> {!! $arrow !!}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script type="module" src="{{ $igAsset('instaflow-install.js') }}"></script>
</body>
</html>

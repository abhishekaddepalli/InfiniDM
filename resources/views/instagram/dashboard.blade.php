<x-layouts.instagram :title="__(':brand', ['brand' => brand_name()])" ig-active="dashboard" page="instagram-dashboard">
    @php
        $hour = (int) now()->format('G');
        $greet = $hour < 12 ? __('Good morning') : ($hour < 17 ? __('Good afternoon') : __('Good evening'));
        $firstName = \Illuminate\Support\Str::of(auth()->user()->name ?? '')->trim()->before(' ')->__toString() ?: __('there');
        $maxFired = max(1, (int) $automations->max('fired_count'));
    @endphp

    <div class="max-w-[1440px] mx-auto px-5 sm:px-6 py-6 space-y-4">

        {{-- ===== HERO ===== --}}
        <section class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5 mb-1.5 font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                    <span>{{ __('Overview') }}</span>
                    <span class="w-1 h-1 rounded-full bg-ink-400"></span>
                    <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-wa-green ig-pulse"></span>{{ $stats['live'] }} {{ __('accounts live') }}</span>
                </div>
                <h1 class="font-serif text-[36px] sm:text-[44px] leading-none">{{ $greet }}, {{ $firstName }}<span class="ig-text">.</span></h1>
                <p class="text-[13px] text-ink-600 mt-2">
                    @if ($stats['automations'])
                        {{ trans_choice('{1}:count automation is live across your accounts.|[2,*]:count automations are live across your accounts.', $stats['automations'], ['count' => $stats['automations']]) }}
                        <b>{{ number_format($stats['fired']) }}</b> {{ __('messages sent so far.') }}
                    @else
                        {{ __('Connect an account and set up your first DM / comment automation on the official Instagram Graph API.') }}
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ url('/instagram/composer') }}" class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium hover:bg-paper-50 inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2.5" y="2.5" width="11" height="11" rx="2.5"/><path d="M8 5.5v5M5.5 8h5"/></svg>{{ __('Create') }}
                </a>
                <a href="{{ url('/instagram/automations') }}" class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold inline-flex items-center gap-2 hover:opacity-90">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('New automation') }}
                </a>
            </div>
        </section>

        <x-admin.flash />

        {{-- ===== WEBHOOK DIAGNOSTICS PANEL (only after "Check webhook") ===== --}}
        @if ($diag = session('ig_webhook_diag'))
            @php
                // Verdict logic: the webhook only DELIVERS if (a) Meta has this
                // account subscribed to the `messages` field AND (b) the App-level
                // callback URL + verify token are set in the Meta App dashboard AND
                // (c) that URL is actually reachable + verifies (the self-test).
                $st     = $diag['selftest'] ?? ['ran' => false, 'ok' => false];
                $subOk  = $diag['subscribe_ok'] && $diag['has_messages'];
                $urlOk  = !empty($st['ran']) && !empty($st['ok']);
                $ok     = $subOk && $diag['verify_set'] && $diag['secret_set'] && $urlOk;
            @endphp
            <section class="bg-paper-0 border {{ $ok ? 'border-wa-green/40' : 'border-amber-300' }} rounded-2xl p-5">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Webhook diagnostics') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ '@'.$diag['username'] }}
                            <span class="font-mono text-[10px] text-ink-400">· {{ $diag['login_type'] === 'instagram' ? 'Instagram Login' : 'Facebook Login' }}</span>
                        </h3>
                    </div>
                    <span class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $ok ? 'bg-wa-bubble text-wa-deep' : 'bg-amber-100 text-amber-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $ok ? 'bg-wa-green' : 'bg-amber-500' }}"></span>
                        {{ $ok ? __('Ready to deliver') : __('Not delivering') }}
                    </span>
                </div>

                <div class="grid sm:grid-cols-2 gap-3 text-[12px]">
                    {{-- 1. Account subscription (live from Meta) --}}
                    <div class="border border-paper-200 rounded-xl p-3">
                        <div class="flex items-center gap-1.5 font-semibold mb-1.5">
                            @if ($diag['subscribe_ok'] && $diag['has_messages'])
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5l3 3 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            @else
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-red-600" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4l8 8M12 4l-8 8" stroke-linecap="round"/></svg>
                            @endif
                            {{ __('Account subscription (live from Meta)') }}
                        </div>
                        @if (!$diag['subscribe_ok'])
                            <div class="text-red-600">{{ __('Could not read subscription:') }} {{ $diag['subscribe_err'] ?: __('unknown error') }}</div>
                        @elseif (empty($diag['fields']))
                            <div class="text-red-600">{{ __('This account is subscribed to NOTHING. Click “Fix inbound” to re-subscribe.') }}</div>
                        @else
                            <div class="text-ink-600 mb-1">{{ __('Subscribed fields:') }}</div>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($diag['fields'] as $f)
                                    <span class="px-1.5 py-0.5 rounded font-mono text-[10px] {{ $f === 'messages' ? 'bg-wa-bubble text-wa-deep' : 'bg-paper-100 text-ink-600' }}">{{ $f }}</span>
                                @endforeach
                            </div>
                            @unless ($diag['has_messages'])
                                <div class="text-red-600 mt-1.5">{{ __('“messages” is NOT subscribed → DMs will not arrive. Click “Fix inbound”.') }}</div>
                            @endunless
                        @endif
                    </div>

                    {{-- 2. App-level webhook config (must be set in Meta App dashboard) --}}
                    <div class="border border-paper-200 rounded-xl p-3">
                        <div class="font-semibold mb-1.5">{{ __('Paste these in Meta App dashboard') }}</div>
                        <div class="text-ink-500 text-[10.5px] mb-0.5">{{ __('Callback URL') }}</div>
                        <div class="font-mono text-[11px] bg-paper-100 rounded px-2 py-1 break-all select-all mb-2">{{ $diag['callback_url'] }}</div>
                        <div class="text-ink-500 text-[10.5px] mb-0.5">{{ __('Verify token') }}</div>
                        @if ($diag['verify_set'])
                            <div class="font-mono text-[11px] bg-paper-100 rounded px-2 py-1 break-all select-all">{{ $diag['verify_token'] }}</div>
                        @else
                            <div class="text-red-600 text-[11px]">{{ __('Not set — add it in Admin → Instagram settings (instagram_webhook_verify_token).') }}</div>
                        @endif
                        <div class="mt-2 flex items-center gap-1.5 text-[11px] {{ $diag['secret_set'] ? 'text-wa-deep' : 'text-red-600' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $diag['secret_set'] ? 'bg-wa-green' : 'bg-red-500' }}"></span>
                            {{ $diag['secret_set'] ? __('App Secret configured') : __('App Secret NOT set — signed webhooks are refused') }}
                        </div>
                    </div>
                </div>

                {{-- 3. Self-reachability probe: did OUR server reach the callback URL like Meta would? --}}
                @if (!empty($st['ran']))
                    <div class="mt-3 border {{ $urlOk ? 'border-wa-green/30 bg-wa-bubble/40' : 'border-red-200 bg-red-50/40' }} rounded-xl p-3 text-[12px]">
                        <div class="flex items-center gap-1.5 font-semibold {{ $urlOk ? 'text-wa-deep' : 'text-red-700' }}">
                            @if ($urlOk)
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5l3 3 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ __('Callback URL is reachable and verifies (HTTP 200 + challenge echoed)') }}
                            @else
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 4v5M8 11.5v.5" stroke-linecap="round"/><circle cx="8" cy="8" r="6.5"/></svg>
                                {{ __('Callback URL self-test FAILED') }}
                            @endif
                        </div>
                        @unless ($urlOk)
                            <div class="text-red-600 mt-1">{{ $st['note'] ?? __('Meta will not be able to verify this URL.') }}</div>
                        @endunless
                    </div>
                @endif

                {{-- Verdict + next step --}}
                <div class="mt-3 pt-3 border-t border-paper-200 text-[12px] text-ink-700">
                    @if ($ok)
                        {{ __('Everything checks out — subscription, config, AND the callback URL is reachable + verifies. If webhooks STILL don’t log, the only remaining cause is App Mode: the app is in Development mode, so switch it to Live and get Advanced Access for instagram_business_manage_messages. Also make sure the Callback URL saved in the dashboard is EXACTLY the one above (with /public), then click Verify & Save + Test.') }}
                    @elseif (!$subOk)
                        {{ __('Fix the account subscription first — click “Fix inbound”, then re-check.') }}
                    @elseif (!$urlOk)
                        {{ __('Your account is subscribed correctly, but this server could NOT verify its own callback URL — so Meta can’t either. Fix the URL (note the /public path) and make sure the Callback URL saved in the Meta dashboard matches the one above EXACTLY, then Verify & Save.') }}
                    @else
                        {{ __('Account is subscribed, but the App-level webhook is not fully set. In Meta App dashboard → Instagram → Webhooks: paste the Callback URL + verify token above, click Verify & Save, then subscribe the “messages” field. Clicking Meta’s “Test” button should then produce an [IG-HOOK] POST received line in your log.') }}
                    @endif
                </div>
            </section>
        @endif

        {{-- ===== CONNECTED ACCOUNTS ===== --}}
        <section>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-[13px] font-semibold flex items-center gap-2">
                    <span class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Connected accounts') }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-paper-100 text-ink-600 font-mono text-[10px]">{{ $stats['accounts'] }}</span>
                </h2>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($accounts as $acc)
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 ig-card-hover">
                        <div class="flex items-center gap-3">
                            <span class="ig-ring shrink-0"><span class="relative block w-10 h-10 rounded-full ig-grad-soft text-white text-[12px] font-semibold grid place-items-center overflow-hidden">{{ strtoupper(substr($acc->username ?: 'IG', 0, 2)) }}@if ($acc->profile_pic_url)<img src="{{ $acc->profile_pic_url }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">@endif</span></span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold truncate">{{ '@'.($acc->username ?: $acc->ig_user_id) }}</div>
                                <div class="font-mono text-[10px] text-ink-500">{{ $acc->followers_count ? number_format($acc->followers_count).' '.__('followers') : __('Professional account') }}</div>
                            </div>
                            <span class="w-2 h-2 rounded-full {{ $acc->isLive() ? 'bg-wa-green ig-pulse' : 'bg-amber-500' }}"></span>
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="text-[10.5px] {{ $acc->isLive() ? 'text-wa-deep' : 'text-amber-600' }} font-medium">{{ $acc->isLive() ? __('Connected') : __('Needs re-auth') }}</span>
                            <div class="flex items-center gap-1">
                                {{-- Icon-only actions; the full label shows on hover (title) and is
                                     read by screen readers (aria-label). Check webhook = read back the
                                     LIVE Meta subscription; Fix inbound = re-subscribe so real-time DMs
                                     + auto-reply fire; Disconnect = remove the account. --}}
                                <a href="{{ url('/instagram/account/'.$acc->id.'/webhook-status') }}"
                                    class="grid place-items-center w-7 h-7 rounded-lg text-ink-500 transition hover:bg-paper-100 hover:text-ink-900"
                                    title="{{ __('Check webhook — what Meta has subscribed') }}" aria-label="{{ __('Check webhook') }}">
                                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="7" cy="7" r="5"/><path d="m11 11 3 3" stroke-linecap="round"/></svg>
                                </a>
                                <form method="POST" action="{{ url('/instagram/account/'.$acc->id.'/resubscribe') }}"
                                    onsubmit="return confirm('{{ __('Re-subscribe Instagram webhooks for this account? Use this if auto-reply / automations are not firing.') }}')">@csrf
                                    <button type="submit"
                                        class="grid place-items-center w-7 h-7 rounded-lg text-ig-purple transition hover:bg-ig-purple/10"
                                        title="{{ __('Fix inbound — re-subscribe webhooks so auto-reply works') }}" aria-label="{{ __('Fix inbound') }}">
                                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M13.5 8a5.5 5.5 0 1 1-1.6-3.9M13.5 2v3h-3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                </form>
                                <form method="POST" action="{{ url('/instagram/account/'.$acc->id) }}" onsubmit="return confirm('{{ __('Disconnect this account?') }}')">@csrf @method('DELETE')
                                    <button type="submit"
                                        class="grid place-items-center w-7 h-7 rounded-lg text-accent-coral transition hover:bg-accent-coral/10"
                                        title="{{ __('Disconnect this account') }}" aria-label="{{ __('Disconnect') }}">
                                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6.6 9.4 4.3 11.7a2.3 2.3 0 0 1-3.3-3.3L3.3 6.1M9.4 6.6l2.3-2.3a2.3 2.3 0 0 1 3.3 3.3l-2.3 2.3"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach

                @php
                    $igLoginType  = (string) \App\Services\Instagram\InstagramGate::setting('instagram_login_type', 'facebook');
                    $igAppId      = (string) \App\Services\Instagram\InstagramGate::setting('instagram_app_id', '');
                    $igConfigId   = (string) \App\Services\Instagram\InstagramGate::setting('instagram_config_id', '');
                    $igGraphV     = (string) \App\Services\Instagram\InstagramGate::setting('instagram_graph_version', 'v25.0');
                    $igEmbeddable = $igLoginType === 'facebook' && $igAppId !== '' && $igConfigId !== '';
                @endphp

                @if ($igEmbeddable)
                    {{-- Embedded signup (Facebook-Login-for-Business SDK popup) — the
                         ManyChat-style business-portfolio + Instagram asset picker. --}}
                    <div id="ig-fb-embed" class="hidden"
                        data-app-id="{{ $igAppId }}"
                        data-config-id="{{ $igConfigId }}"
                        data-graph-version="{{ $igGraphV }}"
                        data-endpoint="{{ route('instagram.connect.embedded') }}"></div>
                    <button type="button" data-ig-embedded-connect
                        class="ig-grad text-white rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:opacity-95 transition group min-h-[120px]">
                        <span class="w-10 h-10 rounded-full bg-white/20 grid place-items-center group-hover:scale-105 transition">
                            <svg viewBox="0 0 16 16" class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M8 3v10M3 8h10"/></svg>
                        </span>
                        <div class="text-[12px] font-semibold">{{ __('Connect via Meta') }}</div>
                        <div class="text-[10px] text-white/80" data-ig-embed-status>{{ __('Business portfolio picker') }}</div>
                    </button>
                @endif

                @unless ($igEmbeddable)
                    {{-- Redirect OAuth — the ONLY path for Instagram-Login (and the
                         fallback when Facebook login has no Business config_id).
                         When Facebook Login-for-Business IS configured we hide this
                         entirely and let "Connect via Meta" (the SDK popup) own it. --}}
                    <a href="{{ url('/instagram/connect') }}" class="border border-dashed border-paper-300 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:border-ig-pink hover:bg-paper-50 transition group min-h-[120px]">
                        <span class="ig-grad w-10 h-10 rounded-full grid place-items-center group-hover:scale-105 transition">
                            <svg viewBox="0 0 16 16" class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M8 3v10M3 8h10"/></svg>
                        </span>
                        <div class="text-[12px] font-semibold">{{ __('Connect account') }}</div>
                        <div class="text-[10px] text-ink-500">{{ __('Professional / Creator') }}</div>
                    </a>
                @endunless
            </div>
        </section>

        {{-- ===== DM VOLUME — the one number that moves daily =====
             Real rows from instagram_messages, grouped by day in SQL. Received vs
             auto-sent are separated on purpose: a spike in one means something
             very different from a spike in the other. --}}
        <section class="grid grid-cols-12 gap-3 mb-3">
            <div class="col-span-12 lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5">
                <div class="flex items-start justify-between gap-3 flex-wrap mb-1">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('DM volume') }} · {{ __('last 14 days') }}</div>
                        <div class="font-serif text-[30px] leading-none mt-1.5 tabular-nums">{{ number_format($stats['dms_14d']) }}</div>
                    </div>
                    <div class="flex items-center gap-4 text-[11px] pt-1">
                        <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full" style="background:#E1306C"></span>{{ __('Received') }} <b class="tabular-nums">{{ number_format($stats['received']) }}</b></span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full" style="background:#833AB4"></span>{{ __('Auto-sent') }} <b class="tabular-nums">{{ number_format($stats['auto_sent']) }}</b></span>
                    </div>
                </div>
                <div id="ig-dm-chart" class="h-[210px] -mx-2"
                     data-labels='@json($series['labels'])'
                     data-in='@json($series['in'])'
                     data-out='@json($series['out'])'></div>
                @if ($stats['dms_14d'] === 0)
                    <p class="text-[11.5px] text-ink-400 text-center -mt-6">{{ __('No DMs in the last 14 days — the chart fills in as conversations arrive.') }}</p>
                @endif
            </div>

            {{-- reach + audience --}}
            <div class="col-span-12 lg:col-span-4 grid grid-cols-2 lg:grid-cols-1 gap-3">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 ig-card-hover">
                    <div class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Followers') }}</div>
                    <div class="font-serif text-[30px] leading-none mt-1.5 tabular-nums">{{ number_format($stats['followers']) }}</div>
                    <div class="text-[11px] text-ink-400 mt-1.5">{{ trans_choice('across :n account|across :n accounts', $stats['accounts'], ['n' => $stats['accounts']]) }}</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 ig-card-hover">
                    <div class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('People in your inbox') }}</div>
                    <div class="font-serif text-[30px] leading-none mt-1.5 tabular-nums">{{ number_format($stats['contacts']) }}</div>
                    <div class="text-[11px] text-ink-400 mt-1.5">{{ __('everyone who has ever DMed you') }}</div>
                </div>
            </div>
        </section>

        {{-- ===== TOP AUTOMATIONS ===== --}}
        @if ($topAutomations->isNotEmpty() && $stats['fired'] > 0)
            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 mb-3">
                <div class="font-mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('Hardest-working automations') }}</div>
                @php $max = max(1, (int) $topAutomations->max('fired_count')); @endphp
                <div class="space-y-2.5">
                    @foreach ($topAutomations as $a)
                        <div class="flex items-center gap-3">
                            <span class="text-[12.5px] font-medium truncate w-40 shrink-0">{{ $a->name ?: __('Untitled') }}</span>
                            <span class="flex-1 h-2 rounded-full bg-paper-100 overflow-hidden">
                                <span class="block h-full ig-grad-soft rounded-full" style="width: {{ max(3, round(((int) $a->fired_count / $max) * 100)) }}%"></span>
                            </span>
                            <span class="mono text-[11px] tabular-nums text-ink-500 w-14 text-right shrink-0">{{ number_format((int) $a->fired_count) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ===== KPI ROW ===== --}}
        <section class="grid grid-cols-12 gap-3">
            <div class="col-span-12 lg:col-span-4 ig-grad-soft text-white rounded-2xl p-5 relative overflow-hidden">
                <div class="absolute inset-0 ig-dots opacity-25"></div>
                <div class="relative">
                    <div class="font-mono text-[10px] uppercase tracking-widest text-white/70">{{ __('Messages automated') }}</div>
                    <div class="font-serif text-[52px] leading-none mt-2 tabular-nums">{{ number_format($stats['fired']) }}</div>
                    <div class="text-[11px] text-white/80 mt-2">{{ __('handled by your automations with zero human touch') }}</div>
                    <div class="mt-3 flex items-center gap-2 text-[11px] text-white/80"><span class="w-1.5 h-1.5 rounded-full bg-white"></span>{{ __('Official Graph API · ban-safe') }}</div>
                </div>
            </div>
            <div class="col-span-6 lg:col-span-2 bg-paper-0 border border-paper-200 rounded-2xl p-4 ig-card-hover flex flex-col">
                <div class="flex items-start justify-between">
                    <div class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Connected') }}</div>
                    <span class="w-7 h-7 rounded-lg bg-ig-pink/10 text-ig-pink grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6.5 9.5 4.4 11.6a2.3 2.3 0 0 1-3.3-3.3L3.2 6.2M9.5 6.5l2.1-2.1a2.3 2.3 0 0 1 3.3 3.3L13 8.9M6 10l4-4"/></svg>
                    </span>
                </div>
                <div class="font-serif text-[34px] leading-none mt-2 tabular-nums">{{ $stats['accounts'] }}</div>
                <div class="text-[11px] text-ink-500 mt-1 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full {{ $stats['live'] ? 'bg-wa-green' : 'bg-ink-300' }}"></span>{{ $stats['live'] }} {{ __('live now') }}</div>
                {{-- Filler: the actual connected handles + a connect CTA pinned to the bottom. --}}
                <div class="mt-auto pt-3 space-y-1.5 border-t border-paper-100">
                    @forelse ($accounts->take(3) as $acc)
                        <div class="flex items-center gap-1.5 text-[11px] text-ink-600">
                            <span class="w-1.5 h-1.5 rounded-full {{ $acc->status === 'connected' ? 'bg-wa-green' : 'bg-ink-300' }} shrink-0"></span>
                            <span class="truncate">{{ '@' . ltrim($acc->username ?? $acc->name ?? ('#' . $acc->id), '@') }}</span>
                        </div>
                    @empty
                        <div class="text-[11px] text-ink-400">{{ __('No accounts yet') }}</div>
                    @endforelse
                    @if ($accounts->count() > 3)<div class="text-[10.5px] text-ink-400">+{{ $accounts->count() - 3 }} {{ __('more') }}</div>@endif
                    <a href="{{ url('/instagram/connect') }}" class="inline-flex items-center gap-1 text-[11px] text-ig-pink font-medium hover:underline pt-0.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('Connect more') }}
                    </a>
                </div>
            </div>
            <div class="col-span-6 lg:col-span-2 bg-paper-0 border border-paper-200 rounded-2xl p-4 ig-card-hover flex flex-col">
                <div class="flex items-start justify-between">
                    <div class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Automations') }}</div>
                    <span class="w-7 h-7 rounded-lg bg-ig-purple/10 text-ig-purple grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8.6 2 4 8.6h3.1L6.4 14 11.6 7H8.3z"/></svg>
                    </span>
                </div>
                <div class="font-serif text-[34px] leading-none mt-2 tabular-nums">{{ $stats['automations'] }}</div>
                <div class="text-[11px] text-ink-500 mt-1">{{ __('active rules') }}</div>
                {{-- Filler: live breakdown by trigger type + a manage CTA at the bottom. --}}
                @php
                    $byType = [
                        __('DM keyword')   => $automations->where('type', 'dm_keyword')->count(),
                        __('Story reply')  => $automations->where('type', 'story_reply')->count(),
                        __('Comment → DM') => $automations->where('type', 'comment_to_dm')->count(),
                    ];
                @endphp
                <div class="mt-auto pt-3 space-y-1 border-t border-paper-100 text-[11px]">
                    @foreach ($byType as $label => $n)
                        <div class="flex items-center justify-between text-ink-600"><span>{{ $label }}</span><span class="tabular-nums font-medium">{{ $n }}</span></div>
                    @endforeach
                    <a href="{{ route('instagram.automations') }}" class="inline-flex items-center gap-1 text-ig-pink font-medium hover:underline pt-1">
                        {{ __('Manage rules') }}<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3l5 5-5 5"/></svg>
                    </a>
                </div>
            </div>
            <div class="col-span-12 lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5">
                <div class="font-mono text-[10px] uppercase tracking-widest text-ink-500 mb-2.5">{{ __('Quick actions') }}</div>
                @php
                    $quick = [
                        ['Inbox','/instagram/inbox','<path d="M2 4.5h12v7H2z"/><path d="M2 8.5h3l1 1.8h4l1-1.8h3"/>'],
                        ['Bulk DM','/instagram/broadcast','<path d="M14 2 2 7.3l4.2 1.5L7.7 13 14 2z"/><path d="M14 2 6.2 8.8"/>'],
                        ['Calendar','/instagram/calendar','<rect x="2.5" y="3" width="11" height="10.5" rx="2"/><path d="M2.5 6.2h11M5.6 2v2.2M10.4 2v2.2"/>'],
                        ['Comments','/instagram/auto-comments','<path d="M14 8.3c0 2.6-2.7 4.7-6 4.7-.8 0-1.6-.1-2.3-.4L2.5 13.5l1-2.6A4.7 4.7 0 0 1 2 8.3C2 5.7 4.7 3.6 8 3.6s6 2.1 6 4.7Z"/>'],
                        ['Reels','/instagram/reposter','<rect x="2.5" y="3" width="11" height="10" rx="2.5"/><path d="M6.8 6.3 10.4 8 6.8 9.7z"/>'],
                        ['Analytics','/instagram/analytics','<path d="M2.6 2.6v10.8h10.8"/><path d="M5.5 11V8.5M8 11V5.5M10.6 11V7.5"/>'],
                        ['Ads','/instagram/ads','<circle cx="8" cy="8" r="5.4"/><circle cx="8" cy="8" r="2.4"/><circle cx="8" cy="8" r=".4" fill="currentColor" stroke="none"/>'],
                    ];
                @endphp
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($quick as $qa)
                        <a href="{{ url($qa[1]) }}" class="group flex items-center gap-2.5 border border-paper-200 rounded-xl px-3 py-2.5 hover:border-ig-pink hover:bg-paper-50 transition">
                            <span class="w-7 h-7 rounded-lg bg-paper-100 text-ink-600 grid place-items-center shrink-0 transition group-hover:bg-ig-pink/10 group-hover:text-ig-pink">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">{!! $qa[2] !!}</svg>
                            </span>
                            <span class="text-[12px] font-medium truncate">{{ __($qa[0]) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ===== ACTIVE AUTOMATIONS + ACTIVITY ===== --}}
        <section class="grid grid-cols-12 gap-3">
            <div class="col-span-12 lg:col-span-7 bg-paper-0 border border-paper-200 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-serif text-[20px]">{{ __('Active automations') }}</h2>
                    <a href="{{ url('/instagram/automations') }}" class="text-[12px] text-ig-pink font-semibold">{{ __('View all →') }}</a>
                </div>
                <div class="space-y-2">
                    @forelse ($automations->take(6) as $a)
                        <div class="border border-paper-200 rounded-xl p-3 ig-card-hover flex items-center gap-3 {{ $a->is_active ? '' : 'opacity-70' }}">
                            <span class="w-9 h-9 rounded-lg grid place-items-center text-white ig-grad-soft shrink-0">
                                @if ($a->type === 'comment_to_dm')
                                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M13 6.3c0 2.1-2.2 3.9-5 3.9-.6 0-1.2-.1-1.8-.3L3.6 11l.7-2.1A3.7 3.7 0 0 1 3 6.3C3 4.2 5.2 2.4 8 2.4s5 1.8 5 3.9Z"/><path d="m6.8 11.8 2.2 1.7.3-2.3"/></svg>
                                @elseif ($a->type === 'dm_keyword')
                                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 4h11v6.5H7l-3 2.4V10.5H2.5z"/><path d="M5.5 7.2h5"/></svg>
                                @else
                                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8.6 2 4 8.6h3.1L6.4 14 11.6 7H8.3z"/></svg>
                                @endif
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-[12.5px] font-medium truncate">{{ $a->name ?: ($a->trigger_keyword ?: ucfirst(str_replace('_',' ',$a->type))) }}</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full {{ $a->is_active ? 'bg-wa-bubble text-wa-deep' : 'bg-paper-100 text-ink-500' }}">{{ $a->is_active ? __('on') : __('paused') }}</span>
                                </div>
                                <div class="font-mono text-[10px] text-ink-500 truncate">{{ str_replace('_',' ',$a->type) }} · {{ $a->trigger_keyword ?: '—' }}</div>
                            </div>
                            <div class="w-20 shrink-0">
                                <div class="h-1.5 rounded-full bg-paper-100 overflow-hidden"><div class="h-full ig-grad-soft" style="width:{{ min(100, round($a->fired_count / $maxFired * 100)) }}%"></div></div>
                                <div class="text-[9px] text-ink-500 font-mono mt-1 text-right">{{ number_format($a->fired_count) }} {{ __('sent') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-[12.5px] text-ink-500 text-center py-8">{{ __('No automations yet. Create a keyword→DM or comment→DM rule to get started.') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="col-span-12 lg:col-span-5 bg-paper-0 border border-paper-200 rounded-2xl p-5">
                <h2 class="font-serif text-[20px] mb-3">{{ __('Get the most out of :brand', ['brand' => brand_name()]) }}</h2>
                <div class="space-y-2.5">
                    @foreach ([
                        ['Comment-to-DM','Auto-DM anyone who comments a keyword on your post or reel.','/instagram/auto-comments','<path d="M13 6.3c0 2.1-2.2 3.9-5 3.9-.6 0-1.2-.1-1.8-.3L3.6 11l.7-2.1A3.7 3.7 0 0 1 3 6.3C3 4.2 5.2 2.4 8 2.4s5 1.8 5 3.9Z"/><path d="m6.8 11.8 2.2 1.7.3-2.3"/>'],
                        ['Schedule posts & reels','Plan your feed on the content calendar.','/instagram/calendar','<rect x="2.5" y="3" width="11" height="10.5" rx="2"/><path d="M2.5 6.2h11M5.6 2v2.2M10.4 2v2.2M8 8.4v2l1.4 .9"/>'],
                        ['Bulk DM campaigns','Message segments of followers at once.','/instagram/broadcast','<path d="M2.5 6.3v3.4l8 3.3V3l-8 3.3z"/><path d="M10.6 5.2a2.8 2.8 0 0 1 0 5.6"/><path d="M4.2 9.7V12"/>'],
                        ['Connect your AI brain','Let the AI agent answer DMs in your tone.','/instagram/automations','<path d="M8 2.2 9.1 5.6 12.6 6.7 9.1 7.8 8 11.2 6.9 7.8 3.4 6.7 6.9 5.6z"/>'],
                    ] as $tip)
                        <a href="{{ url($tip[2]) }}" class="flex items-start gap-3 border border-paper-200 rounded-xl p-3 hover:border-ig-pink hover:bg-paper-50 transition">
                            <span class="w-8 h-8 rounded-lg bg-ig-pink/10 text-ig-pink grid place-items-center shrink-0"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $tip[3] !!}</svg></span>
                            <div class="min-w-0">
                                <div class="text-[12.5px] font-semibold">{{ __($tip[0]) }}</div>
                                <div class="text-[11px] text-ink-500">{{ __($tip[1]) }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

    </div>

    {{-- Embedded signup — Facebook-Login-for-Business SDK popup (ManyChat-style).
         Page-scoped: loads the Meta JS SDK, runs FB.login() with the business
         config_id + IG_API_ONBOARDING, then POSTs the code to the native endpoint. --}}
    @push('scripts')
    <script>
    (function () {
        var box = document.getElementById('ig-fb-embed');
        var btn = document.querySelector('[data-ig-embedded-connect]');
        if (!box || !btn) return;

        var appId    = box.dataset.appId;
        var configId = box.dataset.configId;
        var graphV   = box.dataset.graphVersion || 'v25.0';
        var endpoint = box.dataset.endpoint;
        var statusEl = document.querySelector('[data-ig-embed-status]');
        var csrf     = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

        function setStatus(t) { if (statusEl) statusEl.textContent = t; }

        // Load the Meta JS SDK once.
        window.fbAsyncInit = function () {
            FB.init({ appId: appId, cookie: true, xfbml: false, version: graphV });
        };
        (function (d, s, id) {
            if (d.getElementById(id)) return;
            var js = d.createElement(s); js.id = id;
            js.src = 'https://connect.facebook.net/en_US/sdk.js';
            var fjs = d.getElementsByTagName(s)[0];
            fjs.parentNode.insertBefore(js, fjs);
        })(document, 'script', 'facebook-jssdk');

        btn.addEventListener('click', function () {
            if (typeof FB === 'undefined') { setStatus('Loading Meta…'); return; }
            setStatus('Opening Meta…');
            FB.login(function (response) {
                var code = response && response.authResponse && response.authResponse.code;
                if (!code) { setStatus('Cancelled — try again'); return; }
                setStatus('Connecting…');
                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ code: code })
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.ok) {
                        setStatus('Connected — reloading…');
                        window.location = res.j.redirect || '/instagram';
                    } else {
                        setStatus((res.j && res.j.message) ? res.j.message : 'Connect failed');
                    }
                })
                .catch(function () { setStatus('Network error — try again'); });
            }, {
                config_id: configId,
                response_type: 'code',
                override_default_response_type: true,
                extras: { setup: { channel: 'IG_API_ONBOARDING' } }
            });
        });
    })();
    </script>
    @endpush
</x-layouts.instagram>

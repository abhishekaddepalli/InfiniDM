<x-layouts.admin :title="__('Instagram')" admin-key="instagram" page="settings-instagram">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Instagram') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin - Instagram automation') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">
                    {{ __('Instagram') }} <span class="italic text-wa-deep">{{ __('automation') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Fill your Meta App credentials here once. Workspaces then connect their Instagram Professional / Creator accounts via OAuth and build DM + comment automations — all through the official Instagram Graph API.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ url('/admin/settings') }}" class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                <button type="submit" form="ig-settings-form" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
            </div>
        </div>

        {{-- Inlined rather than <x-admin.flash />: that component is owned by the host
             app, so referencing it would throw at compile time in standalone. --}}
        @php($igFlashStatus = session('status') ?: session('success'))
        @if ($igFlashStatus)
            <div class="rounded-xl px-4 py-3 text-[12.5px] font-mono flex items-center gap-2.5 mb-4 bg-wa-mint border border-wa-green/30 text-wa-deep">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="8" cy="8" r="6" />
                    <path d="M5.5 8.5l2 2 3-4" />
                </svg>
                <span>{{ $igFlashStatus }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl px-4 py-3 text-[12.5px] font-mono flex items-center gap-2.5 mb-4 bg-accent-coral/10 border border-accent-coral/30 text-accent-coral">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="8" cy="8" r="6" />
                    <path d="M8 5v3.5M8 11v.5" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if (session('warning'))
            <div class="rounded-xl px-4 py-3 text-[12.5px] font-mono flex items-center gap-2.5 mb-4 bg-accent-amber/15 border border-accent-amber/40 text-[#7B5A14]">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M8 2.5L14 13H2L8 2.5z" />
                    <path d="M8 7v3M8 11.5v.5" />
                </svg>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        <form id="ig-settings-form" method="POST" action="{{ route('admin.settings.instagram.update') }}" class="space-y-5">@csrf

            {{-- Enable + status --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5">
                <label class="inline-flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" name="instagram_enabled" value="1" @checked($settings['instagram_enabled'])
                        class="mt-0.5 w-4 h-4 rounded border-paper-300 text-wa-deep focus:ring-wa-deep/20">
                    <span class="text-[12.5px] text-ink-700 leading-relaxed">
                        <span class="font-semibold text-ink-900">{{ __('Enable Instagram automation platform-wide') }}</span><br>
                        {{ __('When on, workspaces with the Instagram plan feature see the Instagram channel and can connect accounts.') }}
                    </span>
                </label>
                <div class="mt-3 text-[11.5px] text-ink-500 font-mono">{{ $settings['instagram_connected_count'] }} {{ __('account(s) connected platform-wide') }}</div>
            </div>

            {{-- App credentials --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 space-y-4">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Meta App credentials') }}</div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="text-[11.5px] text-ink-700">{{ __('App ID') }}</span>
                        <input name="instagram_app_id" value="{{ $settings['instagram_app_id'] }}" placeholder="123456789012345"
                            class="mt-1 w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                    <label class="block">
                        <span class="text-[11.5px] text-ink-700">{{ __('App Secret') }}</span>
                        <span class="relative block mt-1">
                            <input name="instagram_app_secret" type="password" autocomplete="off"
                                placeholder="{{ $settings['instagram_app_secret_set'] ? '•••••••• (saved — leave blank to keep)' : 'paste secret' }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 pr-10 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            <button type="button" data-reveal class="absolute inset-y-0 right-0 px-3 text-ink-500 hover:text-ink-900" aria-label="{{ __('Show / hide') }}">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M1 8s2.5-4.5 7-4.5S15 8 15 8s-2.5 4.5-7 4.5S1 8 1 8Z"/><circle cx="8" cy="8" r="1.8"/></svg>
                            </button>
                        </span>
                    </label>
                    <label class="block">
                        <span class="text-[11.5px] text-ink-700">{{ __('Login configuration ID') }}</span>
                        <input name="instagram_config_id" value="{{ $settings['instagram_config_id'] }}" placeholder="(embedded signup / login config)"
                            class="mt-1 w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                    <label class="block">
                        <span class="text-[11.5px] text-ink-700">{{ __('OAuth path') }}</span>
                        <select name="instagram_login_type" class="mt-1 w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            <option value="facebook"  @selected($settings['instagram_login_type']==='facebook')>{{ __('Facebook Login for Business (multi-tenant)') }}</option>
                            <option value="instagram" @selected($settings['instagram_login_type']==='instagram')>{{ __('Instagram Login (no FB Page)') }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-[11.5px] text-ink-700">{{ __('Webhook verify token') }}</span>
                        <input name="instagram_webhook_verify_token" value="{{ $settings['instagram_webhook_verify_token'] }}" placeholder="any-secret-string"
                            class="mt-1 w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                    <label class="block">
                        <span class="text-[11.5px] text-ink-700">{{ __('Graph API version') }}</span>
                        <input name="instagram_graph_version" value="{{ $settings['instagram_graph_version'] }}" placeholder="v21.0"
                            class="mt-1 w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                </div>
            </div>

            {{-- Webhook — the exact URL + verify token to paste into Meta. --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 space-y-4">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Webhook — Meta App → Instagram → Webhooks') }}</div>
                <div>
                    <span class="text-[11.5px] text-ink-700 block mb-1">{{ __('Callback URL') }}</span>
                    <div class="flex items-stretch gap-2">
                        <input type="text" readonly value="{{ url('/webhooks/instagram') }}" id="ig-webhook-url"
                            class="flex-1 rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[13px] font-mono text-ink-900 focus:outline-none">
                        <button type="button" data-copy="#ig-webhook-url"
                            class="shrink-0 px-3.5 rounded-xl bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="5" width="9" height="9" rx="1.5"/><path d="M11 5V3.5A1.5 1.5 0 0 0 9.5 2h-6A1.5 1.5 0 0 0 2 3.5v6A1.5 1.5 0 0 0 3.5 11H5"/></svg>
                            <span data-copy-label>{{ __('Copy') }}</span>
                        </button>
                    </div>
                </div>
                <div>
                    <span class="text-[11.5px] text-ink-700 block mb-1">{{ __('OAuth Redirect URI') }} <span class="text-ink-400">— {{ __('add under Instagram → OAuth redirect URIs') }}</span></span>
                    <div class="flex items-stretch gap-2">
                        <input type="text" readonly value="{{ url('/instagram/callback') }}" id="ig-redirect-uri"
                            class="flex-1 rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[13px] font-mono text-ink-900 focus:outline-none">
                        <button type="button" data-copy="#ig-redirect-uri"
                            class="shrink-0 px-3.5 rounded-xl border border-paper-200 bg-paper-0 text-[12px] font-semibold hover:bg-paper-50 inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="5" width="9" height="9" rx="1.5"/><path d="M11 5V3.5A1.5 1.5 0 0 0 9.5 2h-6A1.5 1.5 0 0 0 2 3.5v6A1.5 1.5 0 0 0 3.5 11H5"/></svg>
                            <span data-copy-label>{{ __('Copy') }}</span>
                        </button>
                    </div>
                </div>
                <div>
                    <span class="text-[11.5px] text-ink-700 block mb-1">{{ __('Verify token') }}</span>
                    <div class="flex items-stretch gap-2">
                        <input type="text" readonly id="ig-verify-token" value="{{ $settings['instagram_webhook_verify_token'] }}"
                            placeholder="{{ __('Set a verify token in the field above, then Save changes') }}"
                            class="flex-1 rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[13px] font-mono text-ink-900 focus:outline-none">
                        <button type="button" data-copy="#ig-verify-token"
                            class="shrink-0 px-3.5 rounded-xl border border-paper-200 bg-paper-0 text-[12px] font-semibold hover:bg-paper-50 inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="5" width="9" height="9" rx="1.5"/><path d="M11 5V3.5A1.5 1.5 0 0 0 9.5 2h-6A1.5 1.5 0 0 0 2 3.5v6A1.5 1.5 0 0 0 3.5 11H5"/></svg>
                            <span data-copy-label>{{ __('Copy') }}</span>
                        </button>
                    </div>
                </div>
                <p class="text-[11.5px] text-ink-500 leading-relaxed">
                    {{ __('Paste the Callback URL + Verify token into Meta, then subscribe to these fields:') }}
                    <span class="font-mono text-ink-700">messages, messaging_postbacks, message_reactions, messaging_seen, messaging_referral, comments, live_comments, mentions</span>.
                </p>
            </div>

            {{-- Node engine & plans — the Node bridge that connects Instagram accounts. --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 space-y-4">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Node engine & plans') }}</div>
                <p class="text-[12px] text-ink-600 -mt-1">{{ __('Connects Instagram accounts. Without it, no account can link.') }}</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="text-[11.5px] text-ink-700">{{ __('Node URL') }}</span>
                        <input name="node_url" type="url" value="{{ $settings['node_url'] }}" placeholder="http://127.0.0.1:3100"
                            class="mt-1 w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                    <label class="block">
                        <span class="text-[11.5px] text-ink-700">{{ __('Node token') }}</span>
                        <input name="node_token" type="password" autocomplete="new-password"
                            placeholder="{{ $settings['node_token_set'] ? '•••••••• (saved — leave blank to keep)' : 'shared secret' }}"
                            class="mt-1 w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                </div>
                <label class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3 cursor-pointer">
                    <span>
                        <span class="block text-[12.5px] font-semibold">{{ __('Enforce package limits') }}</span>
                        <span class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Off by default — each customer limited to their plan when on.') }}</span>
                    </span>
                    <span class="relative inline-block w-9 h-5 shrink-0">
                        <input type="hidden" name="enforce_plans" value="0">
                        <input type="checkbox" name="enforce_plans" value="1" class="peer opacity-0 w-0 h-0" @checked($settings['enforce_plans'])>
                        <span class="absolute inset-0 bg-paper-200 rounded-full transition peer-checked:bg-wa-deep before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:before:translate-x-[16px]"></span>
                    </span>
                </label>
            </div>

            {{-- GIF picker (GIPHY) — powers the Instagram inbox composer's GIF button. --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('GIF picker (GIPHY)') }}</div>
                    @if ($settings['instagram_giphy_key_set'])
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-wa-deep">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5l3 3 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ __('Key configured') }}
                        </span>
                    @else
                        <span class="text-[11px] font-semibold text-accent-coral">{{ __('Using GIPHY demo key') }}</span>
                    @endif
                </div>
                <label class="block">
                    <span class="text-[11.5px] text-ink-700">{{ __('GIPHY API key') }}</span>
                    <span class="relative block mt-1">
                        <input name="instagram_giphy_key" type="password" autocomplete="off"
                            placeholder="{{ $settings['instagram_giphy_key_set'] ? '•••••••• (saved — leave blank to keep)' : 'paste your free GIPHY API key' }}"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 pr-10 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                        <button type="button" data-reveal class="absolute inset-y-0 right-0 px-3 text-ink-500 hover:text-ink-900" aria-label="{{ __('Show / hide') }}">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M1 8s2.5-4.5 7-4.5S15 8 15 8s-2.5 4.5-7 4.5S1 8 1 8Z"/><circle cx="8" cy="8" r="1.8"/></svg>
                        </button>
                    </span>
                    <span class="mt-1.5 block text-[11.5px] text-ink-500 leading-relaxed">
                        {{ __('Free — get one at') }} <a href="https://developers.giphy.com/dashboard/" target="_blank" rel="noopener" class="text-wa-deep underline">developers.giphy.com</a>
                        {{ __('→ Create App → choose “API” → copy the key. Stored encrypted; the key stays server-side and overrides any GIPHY_API_KEY in .env. Leave blank to keep GIPHY’s rate-limited public demo key (fine for testing only).') }}
                    </span>
                </label>
            </div>

            {{-- Setup guide --}}
            <div class="bg-paper-50 border border-paper-200 rounded-2xl p-5">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('One-time Meta App setup') }}</div>
                <ol class="text-[12px] text-ink-700 leading-relaxed list-decimal pl-5 space-y-1.5">
                    <li>{{ __('Create a Meta Business app → add the Instagram product. Copy App ID + Secret above.') }}</li>
                    <li>{{ __('Webhook URL (paste in Meta App → Instagram → Webhooks):') }}
                        <span class="font-mono text-wa-deep">{{ url('/webhooks/instagram') }}</span> — {{ __('subscribe to ALL of these fields') }}:
                        <span class="font-mono">messages</span>, <span class="font-mono">messaging_postbacks</span>,
                        <span class="font-mono">message_reactions</span>, <span class="font-mono">messaging_seen</span>,
                        <span class="font-mono">messaging_referral</span>, <span class="font-mono">comments</span>,
                        <span class="font-mono">live_comments</span>, <span class="font-mono">mentions</span>.
                        <span class="text-accent-coral">{{ __('messaging_postbacks is required for button-tap replies.') }}</span></li>
                    <li>{{ __('Request these permissions in App Review (Advanced Access, ~6–8 weeks):') }}
                        <span class="font-mono text-wa-deep">instagram_business_basic</span>,
                        <span class="font-mono text-wa-deep">instagram_business_manage_messages</span>,
                        <span class="font-mono text-wa-deep">instagram_business_manage_comments</span>,
                        <span class="font-mono text-wa-deep">instagram_business_content_publish</span>.</li>
                    <li>{{ __('Connecting accounts requires an Instagram Professional (Business or Creator) account.') }}</li>
                </ol>
            </div>

        </form>

        <script>
            (function () {
                // Eye toggle on secret inputs.
                document.querySelectorAll('[data-reveal]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var inp = btn.parentElement.querySelector('input');
                        if (inp) inp.type = inp.type === 'password' ? 'text' : 'password';
                    });
                });
                // Copy-to-clipboard buttons (webhook URL + verify token).
                document.querySelectorAll('[data-copy]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var el = document.querySelector(btn.getAttribute('data-copy'));
                        if (!el) return;
                        var done = function () {
                            var lbl = btn.querySelector('[data-copy-label]');
                            if (!lbl) return;
                            var t = lbl.textContent; lbl.textContent = 'Copied';
                            setTimeout(function () { lbl.textContent = t; }, 1400);
                        };
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(el.value).then(done, function () { el.select(); document.execCommand('copy'); done(); });
                        } else { el.select(); document.execCommand('copy'); done(); }
                    });
                });
            })();
        </script>
    </main>
</x-layouts.admin>

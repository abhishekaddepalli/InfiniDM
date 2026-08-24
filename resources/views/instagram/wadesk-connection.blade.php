<x-layouts.admin :title="__('Connect WaDesk')" admin-key="wadesk-connection" page="admin-wadesk-connection">
    <header class="h-16 bg-paper-0 border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <button type="button" aria-label="{{ __('Menu') }}" onclick="window.__adminToggleSidebar(true)"
            class="md:hidden shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-lg text-ink-500 hover:text-ink-900 hover:bg-paper-50">
            <svg viewBox="0 0 16 16" class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4h12M2 8h12M2 12h12"/></svg>
        </button>
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.insights') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Connect WaDesk') }}</span>
        </div>
    </header>

    <div class="px-4 sm:px-7 py-7 space-y-5">

        {{-- ===== HERO ===== --}}
        <section class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __(':brand - Connection', ['brand' => brand_name()]) }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">
                    {{ __('Connect') }} <span class="ig-text italic">WaDesk</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Link this :brand to a WaDesk install so every Instagram DM lands in WaDesk’s unified team inbox, and replies sent from WaDesk come back out through Instagram. They authenticate each other with one shared secret.', ['brand' => brand_name()]) }}
                </p>
            </div>
        </section>

        {{-- Flash (inlined — x-admin.flash is host-owned and absent in standalone). --}}
        @php($flashOk = session('status') ?: session('success'))
        @if ($flashOk)
            <div class="rounded-xl px-4 py-3 text-[12.5px] font-mono flex items-center gap-2.5 bg-wa-mint border border-wa-deep/25 text-wa-deep">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="8" r="6"/><path d="M5.5 8.5l2 2 3-4"/></svg>
                <span>{{ $flashOk }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-xl px-4 py-3 text-[12.5px] font-mono flex items-center gap-2.5 bg-accent-coral/10 border border-accent-coral/30 text-accent-coral">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5M8 11v.5"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif
        @if (session('warning'))
            <div class="rounded-xl px-4 py-3 text-[12.5px] font-mono flex items-center gap-2.5 bg-accent-amber/15 border border-accent-amber/40 text-[#7B5A14]">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 2.5L14 13H2L8 2.5z"/><path d="M8 7v3M8 11.5v.5"/></svg>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        {{-- ===== STATUS STRIP ===== --}}
        <div class="rounded-2xl border border-paper-200 bg-paper-0 px-5 py-4 flex items-center gap-3">
            @if ($secret)
                <span class="w-2.5 h-2.5 rounded-full bg-wa-green shrink-0"></span>
                <div class="text-[13px]"><span class="font-semibold text-wa-deep">{{ __('Ready') }}</span>
                    <span class="text-ink-500">— {{ __('a secret is generated. Paste this URL + secret into WaDesk to finish connecting.') }}</span></div>
            @else
                <span class="w-2.5 h-2.5 rounded-full bg-ink-300 shrink-0"></span>
                <div class="text-[13px]"><span class="font-semibold text-ink-800">{{ __('Not connected yet') }}</span>
                    <span class="text-ink-500">— {{ __('generate a secret below, then paste it into WaDesk.') }}</span></div>
            @endif
        </div>

        {{-- ===== CARD A — paste into WaDesk ===== --}}
        <section class="rounded-2xl border border-paper-200 bg-paper-0 p-5 sm:p-6 space-y-4">
            <div>
                <h2 class="font-serif text-[19px] leading-tight">{{ __('Paste these into WaDesk') }}</h2>
                <p class="text-[12.5px] text-ink-500 mt-1">{{ __('In WaDesk, open Admin → Extensions → Connect Instagram and paste the URL and secret below. WaDesk pulls your Instagram conversations from here.') }}</p>
            </div>

            <div>
                <label class="block text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-1.5">{{ __('This :brand URL', ['brand' => brand_name()]) }}</label>
                <input type="text" readonly value="{{ $instaflowUrl }}"
                    class="w-full rounded-xl border border-paper-200 bg-paper-50 px-3.5 py-2.5 text-[13px] font-mono text-ink-800 focus:outline-none focus:ring-2 focus:ring-wa-deep/20" />
            </div>

            <div>
                <label class="block text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-1.5">{{ __('Shared secret') }}</label>
                <div class="flex flex-col sm:flex-row gap-2">
                    <input type="text" readonly value="{{ $secret }}"
                        placeholder="{{ __('— not generated yet —') }}"
                        class="flex-1 rounded-xl border border-paper-200 bg-paper-50 px-3.5 py-2.5 text-[13px] font-mono text-ink-800 focus:outline-none focus:ring-2 focus:ring-wa-deep/20" />
                    <form method="POST" action="{{ route('admin.wadesk-connection.generate') }}" class="shrink-0"
                          @if ($secret) onsubmit="return confirm('{{ __('Regenerate the secret? The current one stops working until you re-paste the new one into WaDesk.') }}')" @endif>
                        @csrf
                        <button type="submit" class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12.5px] font-semibold text-ink-800">
                            {{ $secret ? __('Regenerate') : __('Generate secret') }}
                        </button>
                    </form>
                </div>
                <p class="text-[11.5px] text-ink-400 mt-1.5">{{ __('Keep this private. Anyone with the URL and secret can read and send Instagram messages.') }}</p>
            </div>

        </section>

        {{-- ===== CARD C — endpoints reference ===== --}}
        <section class="rounded-2xl border border-paper-200 bg-paper-50/60 p-5 sm:p-6">
            <h2 class="font-serif text-[17px] leading-tight mb-1">{{ __('Endpoints WaDesk uses') }}</h2>
            <p class="text-[12px] text-ink-500 mb-3">{{ __('For reference — WaDesk calls these automatically once connected. Every call carries the shared secret in the X-Instaflow-Secret header.') }}</p>
            <div class="font-mono text-[11.5px] text-ink-600 space-y-1 overflow-x-auto">
                <div><span class="text-wa-deep">GET&nbsp;</span> {{ $instaflowUrl }}/api/wadesk/handshake</div>
                <div><span class="text-wa-deep">GET&nbsp;</span> {{ $instaflowUrl }}/api/wadesk/accounts</div>
                <div><span class="text-wa-deep">GET&nbsp;</span> {{ $instaflowUrl }}/api/wadesk/flows</div>
                <div><span class="text-wa-deep">GET&nbsp;</span> {{ $instaflowUrl }}/api/wadesk/conversations</div>
                <div><span class="text-wa-deep">GET&nbsp;</span> {{ $instaflowUrl }}/api/wadesk/conversations/&#123;id&#125;/messages</div>
                <div><span class="text-accent-coral">POST</span> {{ $instaflowUrl }}/api/wadesk/conversations/&#123;id&#125;/reply <span class="text-ink-400">— text · image · video · audio · file · quick_replies · buttons</span></div>
                <div><span class="text-accent-coral">POST</span> {{ $instaflowUrl }}/api/wadesk/conversations/&#123;id&#125;/react <span class="text-ink-400">— emoji reaction</span></div>
                <div><span class="text-accent-coral">POST</span> {{ $instaflowUrl }}/api/wadesk/conversations/&#123;id&#125;/action <span class="text-ink-400">— typing_on · typing_off · mark_seen</span></div>
                <div><span class="text-accent-coral">POST</span> {{ $instaflowUrl }}/api/wadesk/conversations/&#123;id&#125;/run-flow <span class="text-ink-400">— trigger a flow</span></div>
            </div>
        </section>

    </div>
</x-layouts.admin>

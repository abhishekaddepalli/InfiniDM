{{-- Shared right-hand showcase panel for the auth pages (login / forgot / reset).
     Purely decorative; kept in one place so every auth screen matches. --}}
<div class="hidden lg:flex flex-1 relative ig-grad overflow-hidden">
    <div class="absolute inset-0 dot-pattern opacity-60"></div>
    <div class="absolute -top-24 -right-24 w-[380px] h-[380px] rounded-full bg-white/10 blur-2xl"></div>
    <div class="absolute bottom-0 -left-20 w-[320px] h-[320px] rounded-full bg-ig-purple/40 blur-3xl"></div>

    <div class="relative z-10 flex flex-col justify-between p-12 xl:p-16 text-white w-full">
        <div class="max-w-[440px] rise d3">
            <div class="text-[11px] mono font-mono uppercase tracking-[0.2em] text-white/70 mb-4">{{ __('The all-in-one Instagram desk') }}</div>
            <h2 class="serif font-serif text-[38px] xl:text-[46px] leading-[1.05]">{{ __('Reply in seconds.') }}<br>{{ __('Grow while you sleep.') }}</h2>
        </div>

        <div class="relative h-[300px] my-4">
            <div class="f1 absolute left-0 top-4 w-[290px] glass-solid rounded-2xl p-4 shadow-2xl text-ink-900">
                <div class="flex items-center gap-2.5">
                    <span class="ig-ring shrink-0"><span class="block w-9 h-9 rounded-full bg-gradient-to-br from-ig-pink to-ig-orange grid place-items-center text-white text-[12px] font-semibold">JM</span></span>
                    <div class="flex-1 min-w-0"><div class="text-[13px] font-semibold flex items-center gap-1">jordan.makes <svg viewBox="0 0 12 12" class="w-3 h-3 text-ig-blue" fill="currentColor"><path d="M6 0l1.3 1.1 1.7-.2.6 1.6 1.5.8-.5 1.6.5 1.6-1.5.8-.6 1.6-1.7-.2L6 12l-1.3-1.1-1.7.2-.6-1.6-1.5-.8.5-1.6L.4 5.3l1.5-.8.6-1.6 1.7.2z"/></svg></div><div class="mono font-mono text-[10px] text-ink-500">{{ __('Direct message') }}</div></div>
                    <span class="w-2 h-2 rounded-full bg-green-500 pulse-dot"></span>
                </div>
                <div class="mt-3 space-y-1.5">
                    <div class="bg-paper-100 rounded-2xl rounded-tl-md px-3 py-2 text-[12px] max-w-[80%]">{{ __('Do you ship to Canada?') }}</div>
                    <div class="ig-grad-soft text-white rounded-2xl rounded-br-md px-3 py-2 text-[12px] max-w-[85%] ml-auto">{{ __('Yes! Free over $50. Want the link?') }}</div>
                </div>
                <div class="mt-2.5 flex items-center gap-1.5 text-[10px] mono font-mono text-ig-pink"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('Auto-replied in 0.8s') }}</div>
            </div>

            <div class="f1 absolute right-0 top-0 w-[190px] glass rounded-2xl p-4 shadow-xl">
                <div class="text-[11px] mono font-mono uppercase tracking-wider text-white/70">{{ __('Replies today') }}</div>
                <div class="serif font-serif text-[40px] leading-none mt-1">1,284</div>
                <div class="flex items-center gap-1 text-[11px] text-white/85 mt-1"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l4-4 3 3 4-5"/></svg>{{ __('86% automated') }}</div>
            </div>

            <div class="f1 absolute right-6 bottom-0 w-[240px] glass-solid rounded-2xl p-3.5 shadow-2xl text-ink-900">
                <div class="flex items-center gap-2 mb-2"><span class="w-7 h-7 rounded-lg ig-grad-soft grid place-items-center text-white"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 4h12v7H6l-3 2.5V11H2z"/></svg></span><div class="text-[12px] font-semibold">{{ __('New comment') }}</div></div>
                <div class="text-[12px] text-ink-700">{{ __('"Where can I buy this??"') }}</div>
                <div class="mt-2 flex items-center gap-1.5"><span class="rounded-full ig-grad-soft text-white text-[10px] px-2 py-0.5">{{ __('Auto-DM sent') }}</span><span class="text-[10px] mono font-mono text-ink-500">{{ __('link shared') }}</span></div>
            </div>
        </div>

        <div class="rise d5">
            <div class="flex items-center gap-3">
                <div class="flex -space-x-2.5">
                    <span class="w-8 h-8 rounded-full ring-2 ring-white bg-gradient-to-br from-ig-pink to-ig-orange grid place-items-center text-[10px] font-semibold">BF</span>
                    <span class="w-8 h-8 rounded-full ring-2 ring-white bg-gradient-to-br from-ig-blue to-ig-purple grid place-items-center text-[10px] font-semibold">UR</span>
                    <span class="w-8 h-8 rounded-full ring-2 ring-white bg-gradient-to-br from-ig-amber to-ig-pink grid place-items-center text-[10px] font-semibold">KL</span>
                    <span class="w-8 h-8 rounded-full ring-2 ring-white bg-white/25 grid place-items-center text-[10px] font-semibold">+9k</span>
                </div>
                <p class="text-[12.5px] text-white/85 max-w-[300px]">{!! __('Trusted by <b>9,400+</b> creators & shops to never miss a message.') !!}</p>
            </div>
        </div>
    </div>
</div>

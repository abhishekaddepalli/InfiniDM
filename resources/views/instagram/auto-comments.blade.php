<x-layouts.instagram :title="__('Comments')" ig-active="auto-comments" page="instagram-auto-comments">
    <div class="max-w-[1440px] mx-auto px-5 sm:px-6 py-6 space-y-4">
        <section class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-1.5">{{ __('Comment automation') }}</div>
                <h1 class="font-serif text-[36px] sm:text-[44px] leading-none">{{ __('Comment') }} <span class="ig-text">→ DM</span></h1>
                <p class="text-[13px] text-ink-600 mt-2">{{ __('When someone comments a keyword on your post or reel, auto-send them a DM — links, codes, catalogs.') }}</p>
                <div class="mt-3 inline-flex items-start gap-2 rounded-xl border border-accent-amber/40 bg-accent-amber/10 px-3 py-2 text-[11.5px] text-ink-700 leading-snug max-w-xl">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-accent-amber" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 6v3M8 11h.01M8 1.5 14.5 13h-13z"/></svg>
                    <span><span class="font-semibold">{{ __('Requires Meta Advanced Access.') }}</span> {{ __('This uses instagram_manage_comments (read the comment + post the public reply) and instagram_manage_messages (send the DM). It fires for everyone only after Meta approves these in App Review — until then it works only for accounts that have a role on your Meta app.') }}</span>
                </div>
            </div>
            <a href="{{ url('/instagram/automations') }}" class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold inline-flex items-center gap-2 hover:opacity-90">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('New comment rule') }}
            </a>
        </section>

        <x-admin.flash />

        <section class="space-y-2">
            @forelse ($rules as $r)
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 flex items-center gap-3 ig-card-hover {{ $r->is_active ? '' : 'opacity-70' }}">
                    <span class="w-10 h-10 rounded-xl ig-grad-soft text-white grid place-items-center shrink-0"><svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 5h12v7H9l-3 2v-2H2z"/></svg></span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-[13px] font-semibold truncate">{{ $r->name ?: ($r->trigger_keyword ?: __('Comment rule')) }}</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full {{ $r->is_active ? 'bg-wa-bubble text-wa-deep' : 'bg-paper-100 text-ink-500' }}">{{ $r->is_active ? __('on') : __('paused') }}</span>
                        </div>
                        <div class="font-mono text-[10.5px] text-ink-500 truncate">{{ __('keyword') }}: {{ $r->trigger_keyword ?: '—' }} · {{ number_format($r->fired_count) }} {{ __('DMs sent') }}</div>
                    </div>
                    <form method="POST" action="{{ url('/instagram/automations/'.$r->id.'/toggle') }}">@csrf
                        <button class="text-[11px] px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 font-medium">{{ $r->is_active ? __('Pause') : __('Resume') }}</button>
                    </form>
                    <form method="POST" action="{{ url('/instagram/automations/'.$r->id) }}" onsubmit="return confirm('{{ __('Delete this rule?') }}')">@csrf @method('DELETE')
                        <button class="text-[11px] text-accent-coral hover:underline">{{ __('Delete') }}</button>
                    </form>
                </div>
            @empty
                <div class="bg-paper-0 border border-dashed border-paper-300 rounded-2xl p-12 text-center">
                    <div class="w-12 h-12 rounded-2xl ig-grad-soft text-white grid place-items-center mx-auto mb-3"><svg viewBox="0 0 20 20" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2.5 5.5h15v8H10l-4 3v-3H2.5z"/></svg></div>
                    <div class="font-serif text-[22px]">{{ __('No comment rules yet') }}</div>
                    <p class="text-[12.5px] text-ink-500 mt-1">{{ __('Set a keyword like “LINK” and :brand DMs everyone who comments it.', ['brand' => brand_name()]) }}</p>
                    <a href="{{ url('/instagram/automations') }}" class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('Create your first rule') }}</a>
                </div>
            @endforelse
        </section>
    </div>
</x-layouts.instagram>

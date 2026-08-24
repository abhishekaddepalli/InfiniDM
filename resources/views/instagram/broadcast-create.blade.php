<x-layouts.instagram :title="__('New bulk DM')" ig-active="broadcast" page="instagram-broadcast-create">
    @php $first = $accounts->first(); $totalReach = array_sum($reach ?? []); @endphp

    {{-- ===== STICKY HEADER ===== --}}
    <div class="hairline-b bg-paper-0 sticky top-0 z-20">
        <div class="max-w-[1500px] mx-auto px-6 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('instagram.broadcast') }}" class="w-8 h-8 rounded-full hairline bg-paper-0 hover:bg-paper-50 grid place-items-center" title="{{ __('Back') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 4l-4 4 4 4"/></svg>
                </a>
                <div class="min-w-0">
                    <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Broadcasts / New') }}</div>
                    <div class="serif text-[20px] leading-tight truncate">{{ __('Create new') }} <span class="ig-text italic">{{ __('bulk DM') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="pill bg-paper-100 text-ink-600 mono">{{ __('200 / hr cap') }}</span>
                <button type="submit" form="igBcForm" class="px-4 py-1.5 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l12-5-4.5 12-2.5-5z"/></svg>{{ __('Queue bulk DM') }}
                </button>
            </div>
        </div>
    </div>

    <main class="max-w-[1500px] mx-auto px-6 py-6">
        <x-admin.flash />
        @error('instagram')<div class="mb-4 hairline rounded-xl bg-ig-pink/5 px-4 py-2.5 text-[12.5px] text-ig-pink">{{ $message }}</div>@enderror

        <form id="igBcForm" method="POST" action="{{ url('/instagram/broadcast') }}" class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5 items-start">@csrf

            {{-- ===== LEFT: setup card ===== --}}
            <section class="bg-paper-0 hairline rounded-2xl overflow-hidden">
                <div class="px-5 py-4 hairline-b bg-paper-50/40 flex items-center gap-2.5">
                    <span class="w-[23px] h-[23px] rounded-[7px] bg-paper-0 text-ig-pink grid place-items-center text-[10px] font-semibold mono shrink-0 hairline">01</span>
                    <span class="serif text-[18px] leading-none flex-1">{{ __('Broadcast setup') }}</span>
                    <span class="mono text-[10px] text-ink-500">{{ __('DM message') }}</span>
                </div>

                <div class="p-5 space-y-5">
                    {{-- account picker --}}
                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Send from account') }} <span class="text-accent-coral">*</span></label>
                        <div class="space-y-2">
                            @foreach ($accounts as $acc)
                                @php $ini = strtoupper(substr($acc->username ?: 'IG', 0, 2)); @endphp
                                <label class="flex items-center gap-2.5 hairline rounded-xl px-3 py-2.5 cursor-pointer hover:bg-paper-50 has-[:checked]:border-ig-pink has-[:checked]:bg-ig-pink/5">
                                    {{-- $preAccountId wins when the inbox compose picker sent us here
                                         with ?account=<id>, so the ticked people below belong to the
                                         account that is actually selected. --}}
                                    <input type="radio" name="instagram_account_id" value="{{ $acc->id }}" data-username="{{ '@'.($acc->username ?: $acc->ig_user_id) }}" data-initials="{{ $ini }}" data-reach="{{ (int) ($reach[$acc->id] ?? 0) }}" class="accent-ig-pink" required @checked(old('instagram_account_id', ($preAccountId ?? 0) ?: optional($first)->id) == $acc->id)>
                                    <span class="w-7 h-7 rounded-full ig-grad-soft text-white text-[10px] font-semibold grid place-items-center shrink-0">{{ $ini }}</span>
                                    <span class="text-[12.5px] font-medium flex-1 truncate">{{ '@'.($acc->username ?: $acc->ig_user_id) }}</span>
                                    <span class="pill bg-paper-100 text-ink-600 mono">{{ number_format($reach[$acc->id] ?? 0) }} {{ __('in window') }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- message --}}
                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 flex items-center justify-between" for="igBcBody">
                            <span>{{ __('Message') }} <span class="text-accent-coral">*</span></span>
                            <span id="igBcCount" class="mono text-[10px] text-ink-400">0 / 1,000</span>
                        </label>
                        <textarea id="igBcBody" name="body" rows="5" required maxlength="1000" placeholder="{{ __('Hey! Quick update for you…') }}" class="field resize-y">{{ old('body') }}</textarea>
                    </div>

                    {{-- audience --}}
                    <div class="border-t border-paper-200 pt-5">
                        <div class="flex items-center justify-between gap-4 mb-3">
                            <div>
                                <h2 class="serif text-[20px] leading-tight">{{ __('Who receives it?') }}</h2>
                                <p class="text-[12px] text-ink-500 mt-1">{{ __('Everyone in the 24h window, or pick specific people.') }}</p>
                            </div>
                            <span id="igBcSelSummary" class="mono text-[10.5px] text-ink-500">{{ __('Everyone') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                            <label class="hairline rounded-2xl p-4 cursor-pointer has-[:checked]:border-ig-pink has-[:checked]:bg-ig-pink/5" data-seg="all">
                                <input class="sr-only" type="radio" name="segment" value="all" checked>
                                <div class="serif text-[18px] leading-tight">{{ __('Everyone in window') }}</div>
                                <p class="text-[12px] text-ink-500 mt-1.5">{{ __('All contacts who DMed you in the last 24h.') }}</p>
                            </label>
                            <label class="hairline rounded-2xl p-4 cursor-pointer has-[:checked]:border-ig-pink has-[:checked]:bg-ig-pink/5" data-seg="pick">
                                <input class="sr-only" type="radio" name="segment" value="pick">
                                <div class="serif text-[18px] leading-tight">{{ __('Pick specific people') }}</div>
                                <p class="text-[12px] text-ink-500 mt-1.5">{{ __('Tick individual contacts below.') }}</p>
                            </label>
                        </div>

                        {{-- contact list (fixed: shows @username, not the last message) --}}
                        <div id="igBcContacts" class="hidden">
                            <div class="relative mb-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="7" cy="7" r="5"/><path d="m11 11 3 3"/></svg>
                                <input id="igBcSearch" type="text" placeholder="{{ __('Search contacts') }}" class="field pl-9">
                            </div>
                            <div class="hairline rounded-xl overflow-hidden bg-white">
                                <div class="flex items-center gap-2.5 px-3 py-2 hairline-b bg-paper-50">
                                    <input id="igBcSelectAll" type="checkbox" class="accent-ig-pink w-4 h-4">
                                    <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Select all') }} · {{ $contacts->count() }}</span>
                                </div>
                                <div class="max-h-64 overflow-y-auto ig-scroll">
                                    @forelse ($contacts as $c)
                                        <label class="igbc-row flex items-center gap-2.5 px-3 py-2 text-[12.5px] cursor-pointer hover:bg-paper-50 hairline-b last:border-b-0 has-[:checked]:bg-ig-pink/5" data-search="{{ mb_strtolower($c->label.' '.$c->last) }}">
                                            <input type="checkbox" name="igsids[]" value="{{ $c->igsid }}" class="igbc-cb accent-ig-pink w-4 h-4 shrink-0" @checked(in_array((string) $c->igsid, $preselect ?? [], true))>
                                            <span class="w-7 h-7 rounded-full ig-grad-soft text-white text-[9px] font-semibold grid place-items-center shrink-0">{{ strtoupper(substr(ltrim($c->label, '@'), 0, 2)) }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-medium truncate">{{ $c->label }}</span>
                                                <span class="block text-[10.5px] text-ink-400 truncate">{{ $c->last ?: __('(no recent text)') }}</span>
                                            </span>
                                        </label>
                                    @empty
                                        <div class="px-3 py-6 text-center text-[12px] text-ink-500">{{ __('No in-window contacts on this account yet.') }}</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ban-safe note --}}
                    <div class="hairline rounded-xl bg-paper-50 p-3 flex items-start gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-ig-pink/10 text-ig-pink grid place-items-center shrink-0"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 1.5l5.5 2.2v4c0 3.4-2.3 5.6-5.5 6.8C4.8 13.3 2.5 11.1 2.5 7.7v-4z"/><path d="M5.6 8l1.7 1.7L11 6"/></svg></span>
                        <p class="text-[11.5px] text-ink-600 leading-snug">{{ __('Only contacts who messaged you in the last 24h receive it. The send drains in the background at a safe rate — no spam, no ban risk.') }}</p>
                    </div>
                </div>
            </section>

            {{-- ===== RIGHT: live IG DM preview ===== --}}
            <aside class="space-y-4">
                <div class="bg-paper-0 hairline rounded-2xl p-4 sticky top-[80px]">
                    <div class="flex items-center justify-between mb-3">
                        <span class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('DM preview') }}</span>
                        <span class="text-[10px] mono px-2 py-0.5 rounded-full bg-ig-pink/10 text-ig-pink">{{ __('Instagram') }}</span>
                    </div>
                    <div class="rounded-[24px] bg-ink-900 p-2 shadow-xl">
                        <div class="rounded-[19px] overflow-hidden bg-white">
                            <div class="h-11 ig-grad-soft text-white flex items-center gap-2 px-3">
                                <span id="igPvAvatar" class="w-7 h-7 rounded-full bg-white/25 grid place-items-center text-[11px] font-semibold">{{ strtoupper(substr(optional($first)->username ?: 'IG', 0, 2)) }}</span>
                                <div class="min-w-0">
                                    <div id="igPvName" class="text-[12px] font-semibold truncate">{{ '@'.(optional($first)->username ?: 'your.account') }}</div>
                                    <div class="text-[9.5px] text-white/80 truncate">{{ __('Bulk DM preview') }}</div>
                                </div>
                            </div>
                            <div class="p-3 min-h-[240px] ig-chat-bg">
                                <div class="max-w-[240px] ig-grad-soft text-white rounded-2xl rounded-tr-md px-3 py-2 ml-auto">
                                    <p id="igPvBody" class="text-[12px] leading-relaxed whitespace-pre-wrap break-words">{{ __('Your message preview…') }}</p>
                                    <div class="text-[9.5px] text-white/70 text-right mt-1">{{ now()->format('g:i A') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div class="hairline rounded-lg bg-paper-50/60 p-3">
                            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Recipients') }}</div>
                            <div id="igPvCount" class="serif text-[22px] leading-tight mt-1 tabular">{{ number_format($totalReach) }}</div>
                        </div>
                        <div class="hairline rounded-lg bg-paper-50/60 p-3">
                            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Send') }}</div>
                            <div class="serif text-[22px] leading-tight mt-1">{{ __('Now') }}</div>
                        </div>
                    </div>
                </div>
            </aside>
        </form>
    </main>
</x-layouts.instagram>

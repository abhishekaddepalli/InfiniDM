<x-layouts.instagram :title="__('Auto-reply')" ig-active="automations" page="instagram-automations">

    @php
        // Real KPI roll-up from the live automation set.
        $igActiveRules = $automations->where('is_active', true)->count();
        $igTotalRules  = $automations->count();
        $igFired       = (int) $automations->sum('fired_count');
        // Per-type counts for the filter chips.
        $igCountAll    = $igTotalRules;
        $igCountDm     = $automations->where('type', 'dm_keyword')->count();
        $igCountStory  = $automations->where('type', 'story_reply')->count();
        // Visual styling per trigger type (icon tile colour + readable label + pill class).
        $typeMeta = [
            'dm_keyword'   => ['label' => __('DM keyword'),   'pill' => 'bg-ig-pink/10 text-ig-pink',     'tile' => 'bg-ig-pink/10 text-ig-pink'],
            'comment_to_dm'=> ['label' => __('Comment → DM'), 'pill' => 'bg-ig-magenta/10 text-ig-magenta','tile' => 'bg-ig-magenta/10 text-ig-magenta'],
            'story_reply'  => ['label' => __('Story reply'),  'pill' => 'bg-ig-purple/10 text-ig-purple', 'tile' => 'bg-ig-purple/10 text-ig-purple'],
            'story_mention'=> ['label' => __('Story mention'),'pill' => 'bg-ig-purple/10 text-ig-purple', 'tile' => 'bg-ig-purple/10 text-ig-purple'],
            'mention'      => ['label' => __('Mention'),      'pill' => 'bg-ig-orange/10 text-ig-orange', 'tile' => 'bg-ig-orange/10 text-ig-orange'],
            'ai_agent'     => ['label' => __('AI agent'),     'pill' => 'bg-ig-blue/10 text-ig-blue',     'tile' => 'bg-ig-blue/10 text-ig-blue'],
            'flow'         => ['label' => __('Visual flow'),  'pill' => 'bg-ig-blue/10 text-ig-blue',     'tile' => 'bg-ig-blue/10 text-ig-blue'],
        ];
    @endphp

    {{-- ===== HEADER ===== --}}
    <section class="w-full px-7 pt-7 pb-4 flex items-end justify-between">
        <div>
            <div class="flex items-center gap-3 mb-1.5 text-[11px] mono uppercase tracking-[0.18em] text-ink-500">
                <span>{{ __('Automation') }}</span>
                <span class="w-1 h-1 rounded-full bg-ink-400"></span>
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>{{ $igActiveRules }} {{ __('rules live') }}
                </span>
            </div>
            <h1 class="serif text-[40px] leading-none">{{ __('Auto-reply') }} <span class="ig-text italic">{{ __('rules') }}</span></h1>
        </div>
        @if ($accounts->isNotEmpty())
            <div class="flex items-center gap-2">
                <a href="#ig-greeters" data-igtab-go="greeters" class="px-5 py-2.5 rounded-full hairline text-[12.5px] font-medium hover:bg-paper-50 inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4h12v9H8l-4 3z"/></svg>{{ __('Menu & greeters') }}
                </a>
                <a href="{{ route('instagram.automations.analytics') }}" class="px-5 py-2.5 rounded-full hairline text-[12.5px] font-medium hover:bg-paper-50 inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 13V8M6 13V3M10 13V6M14 13V9"/></svg>{{ __('Analytics') }}
                </a>
                <a href="{{ route('instagram.automations.create') }}" class="px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold flex items-center gap-2 hover:opacity-90">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('Create rule') }}
                </a>
            </div>
        @endif
    </section>

    <div class="w-full px-7"><x-admin.flash />
        @error('instagram')<div class="mb-3 hairline rounded-xl bg-ig-pink/5 border-ig-pink/30 px-4 py-2.5 text-[12.5px] text-ig-pink">{{ $message }}</div>@enderror
    </div>

    @if ($accounts->isEmpty())
        {{-- ===== EMPTY: no connected account ===== --}}
        <section class="w-full px-7 pb-8">
            <div class="bg-white hairline rounded-2xl p-12 text-center">
                <span class="ig-grad w-14 h-14 rounded-2xl grid place-items-center text-white mx-auto mb-3"><svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/></svg></span>
                <div class="serif text-[24px]">{{ __('Connect an account to build rules') }}</div>
                <p class="text-[12.5px] text-ink-500 mt-1">{{ __('Link a Professional / Creator account, then auto-reply to DMs, comments, story replies and more.') }}</p>
                <a href="{{ url('/instagram/connect') }}" class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('Connect Instagram') }}</a>
            </div>
        </section>
    @else

    {{-- ===== STATS ===== --}}
    <section class="w-full px-7 pb-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white hairline rounded-2xl p-4">
            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Active rules') }}</div>
            <div class="serif text-[32px] leading-none mt-1.5 tabular">{{ $igActiveRules }}</div>
        </div>
        <div class="bg-white hairline rounded-2xl p-4">
            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('DMs auto-sent') }}</div>
            <div class="serif text-[32px] leading-none mt-1.5 tabular ig-text">{{ number_format($igFired) }}</div>
        </div>
        <div class="bg-white hairline rounded-2xl p-4">
            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Total rules') }}</div>
            <div class="serif text-[32px] leading-none mt-1.5 tabular">{{ $igTotalRules }}</div>
        </div>
        <div class="bg-white hairline rounded-2xl p-4">
            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Accounts') }}</div>
            <div class="serif text-[32px] leading-none mt-1.5 tabular">{{ $accounts->count() }}</div>
        </div>
    </section>

    {{-- ===== TABS BAR ===== --}}
    <section class="w-full px-7 pb-4">
        <div class="inline-flex items-center gap-1 bg-paper-100 rounded-full p-1 overflow-x-auto max-w-full" data-igtab-group>
            <button type="button" data-igtab="rules" class="igtab whitespace-nowrap px-4 py-2 rounded-full text-[12.5px] font-semibold text-ink-600 transition">{{ __('Auto-reply rules') }} <span class="ml-1 text-[11px] opacity-70">{{ $igTotalRules }}</span></button>
            <button type="button" data-igtab="greeters" class="igtab whitespace-nowrap px-4 py-2 rounded-full text-[12.5px] font-medium text-ink-600 transition">{{ __('Ice breakers') }}</button>
            <button type="button" data-igtab="menu" class="igtab whitespace-nowrap px-4 py-2 rounded-full text-[12.5px] font-medium text-ink-600 transition">{{ __('Persistent menu') }}</button>
            <button type="button" data-igtab="faq" class="igtab whitespace-nowrap px-4 py-2 rounded-full text-[12.5px] font-medium text-ink-600 transition">{{ __('FAQ') }}</button>
        </div>
    </section>

    {{-- ===== ICE BREAKERS ===== --}}
    <section id="ig-greeters" data-igtab-panel="greeters" class="w-full px-7 pb-4 scroll-mt-24 hidden">
        <div class="bg-white hairline rounded-2xl overflow-hidden">
            <div class="px-5 py-3 hairline-b flex items-center gap-2.5">
                <span class="ig-grad-soft w-8 h-8 rounded-xl grid place-items-center text-white shrink-0">
                    <svg viewBox="0 0 20 20" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4h12v9H8l-4 3z"/></svg>
                </span>
                <div>
                    <div class="text-[14px] font-semibold">{{ __('Ice breakers') }}</div>
                    <div class="text-[11.5px] text-ink-500">{{ __('Starter questions shown when someone opens a new chat. Up to 4 — a tap routes to your keyword or flow rules by its payload.') }}</div>
                </div>
            </div>
            <div class="p-5 space-y-6">
                @foreach ($accounts as $account)
                    @php $ib = array_values((array) data_get($account->meta_json, 'ice_breakers', [])); @endphp
                    <form method="POST" action="{{ route('instagram.ice-breakers.save') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="instagram_account_id" value="{{ $account->id }}">
                        @if ($accounts->count() > 1)
                            <div class="text-[12.5px] font-medium">{{ '@' . ($account->username ?? $account->ig_username ?? $account->name ?? ('#' . $account->id)) }}</div>
                        @endif
                        <div class="space-y-2">
                            @for ($i = 0; $i < 4; $i++)
                                @php $q = $ib[$i] ?? []; @endphp
                                <div class="grid grid-cols-1 md:grid-cols-[1fr_220px] gap-2">
                                    <input type="text" name="questions[{{ $i }}][question]" maxlength="80" value="{{ $q['question'] ?? '' }}"
                                        placeholder="{{ __('Question') }} {{ $i + 1 }} — {{ __('e.g. What are your prices?') }}"
                                        class="field w-full rounded-xl px-3.5 py-2.5 text-[13px]">
                                    <input type="text" name="questions[{{ $i }}][payload]" maxlength="200" value="{{ $q['payload'] ?? '' }}"
                                        placeholder="{{ __('Payload (optional)') }}"
                                        class="field w-full rounded-xl px-3.5 py-2.5 text-[13px]">
                                </div>
                            @endfor
                        </div>
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <span class="text-[11px] text-ink-500">{{ __('Leave all blank and save to remove ice breakers.') }}</span>
                            <button type="submit" class="px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('Save ice breakers') }}</button>
                        </div>
                    </form>
                    @if (!$loop->last)<div class="hairline-b pt-1"></div>@endif
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== PERSISTENT MENU ===== --}}
    <section data-igtab-panel="menu" class="w-full px-7 pb-4 hidden">
        <div class="bg-white hairline rounded-2xl overflow-hidden">
            <div class="px-5 py-3 hairline-b flex items-center gap-2.5">
                <span class="ig-grad-soft w-8 h-8 rounded-xl grid place-items-center text-white shrink-0">
                    <svg viewBox="0 0 20 20" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 5h14M3 10h14M3 15h14"/></svg>
                </span>
                <div>
                    <div class="text-[14px] font-semibold">{{ __('Persistent menu') }}</div>
                    <div class="text-[11.5px] text-ink-500">{{ __('The always-open menu in the DM composer. Up to 20 items — an “Action” tap routes to your keyword or flow rules by its payload (e.g. SHOP launches your product flow).') }}</div>
                </div>
            </div>
            <div class="p-5 space-y-6">
                @foreach ($accounts as $account)
                    @php $pm = array_values((array) data_get($account->meta_json, 'persistent_menu', [])); @endphp
                    <form method="POST" action="{{ route('instagram.persistent-menu.save') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="instagram_account_id" value="{{ $account->id }}">
                        @if ($accounts->count() > 1)
                            <div class="text-[12.5px] font-medium">{{ '@' . ($account->username ?? $account->ig_username ?? $account->name ?? ('#' . $account->id)) }}</div>
                        @endif
                        <div class="space-y-2 max-h-[440px] overflow-y-auto scrollbar pr-1">
                            @for ($i = 0; $i < 20; $i++)
                                @php $it = $pm[$i] ?? []; $ty = $it['type'] ?? 'postback'; @endphp
                                <div class="grid grid-cols-1 md:grid-cols-[130px_1fr_1fr] gap-2">
                                    <select name="items[{{ $i }}][type]" class="field w-full rounded-xl px-3 py-2.5 text-[13px]">
                                        <option value="postback" @selected($ty === 'postback')>{{ __('Action') }}</option>
                                        <option value="web_url" @selected($ty === 'web_url')>{{ __('Website') }}</option>
                                    </select>
                                    <input type="text" name="items[{{ $i }}][title]" maxlength="30" value="{{ $it['title'] ?? '' }}"
                                        placeholder="{{ __('Label') }} — {{ __('e.g. Shop') }}" class="field w-full rounded-xl px-3.5 py-2.5 text-[13px]">
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="text" name="items[{{ $i }}][payload]" maxlength="200" value="{{ $it['payload'] ?? '' }}"
                                            placeholder="{{ __('Payload (Action)') }}" class="field w-full rounded-xl px-3 py-2.5 text-[13px]">
                                        <input type="url" name="items[{{ $i }}][url]" maxlength="2000" value="{{ $it['url'] ?? '' }}"
                                            placeholder="https:// ({{ __('Website') }})" class="field w-full rounded-xl px-3 py-2.5 text-[13px]">
                                    </div>
                                </div>
                            @endfor
                        </div>
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <span class="text-[11px] text-ink-500">{{ __('For an Action, fill Payload and add a matching keyword rule. For a Website, fill the https URL. Leave all blank and save to remove.') }}</span>
                            <button type="submit" class="px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('Save menu') }}</button>
                        </div>
                    </form>
                    @if (!$loop->last)<div class="hairline-b pt-1"></div>@endif
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== FAQ ===== --}}
    <section data-igtab-panel="faq" class="w-full px-7 pb-4 hidden">
        <div class="bg-white hairline rounded-2xl overflow-hidden">
            <div class="px-5 py-3 hairline-b flex items-center gap-2.5">
                <span class="ig-grad-soft w-8 h-8 rounded-xl grid place-items-center text-white shrink-0">
                    <svg viewBox="0 0 20 20" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="10" cy="10" r="7.5"/><path d="M8 8a2 2 0 0 1 4 0c0 1.5-2 1.5-2 3M10 14h.01"/></svg>
                </span>
                <div>
                    <div class="text-[14px] font-semibold">{{ __('FAQ') }}</div>
                    <div class="text-[11.5px] text-ink-500">{{ __('Up to 4 question + answer pairs. Each question appears as a tappable ice-breaker and answers automatically when tapped or typed.') }}</div>
                </div>
            </div>
            <div class="p-5 space-y-6">
                @foreach ($accounts as $account)
                    @php
                        $faqRules = $automations->where('instagram_account_id', $account->id)
                            ->filter(fn ($r) => data_get($r->meta_json, 'faq') === true)->values();
                    @endphp
                    <form method="POST" action="{{ route('instagram.faq.save') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="instagram_account_id" value="{{ $account->id }}">
                        @if ($accounts->count() > 1)
                            <div class="text-[12.5px] font-medium">{{ '@' . ($account->username ?? $account->ig_username ?? ('#' . $account->id)) }}</div>
                        @endif
                        <div class="space-y-3">
                            @for ($i = 0; $i < 4; $i++)
                                @php $fq = $faqRules[$i] ?? null; @endphp
                                <div class="hairline rounded-xl p-3 bg-paper-50/50 space-y-2">
                                    <input type="text" name="faq[{{ $i }}][q]" maxlength="80" value="{{ $fq->trigger_keyword ?? '' }}"
                                        placeholder="{{ __('Question') }} {{ $i + 1 }} — {{ __('e.g. What are your prices?') }}" class="field w-full rounded-lg px-3 py-2 text-[13px]">
                                    <textarea name="faq[{{ $i }}][a]" rows="2" maxlength="1000"
                                        placeholder="{{ __('Answer — e.g. Our prices start at $9/mo. See :url', ['url' => 'yoursite.com/pricing']) }}" class="field w-full rounded-lg px-3 py-2 text-[13px]">{{ $fq->dm_message ?? '' }}</textarea>
                                </div>
                            @endfor
                        </div>
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <span class="text-[11px] text-ink-500">{{ __('Leave all blank and save to remove the FAQ.') }}</span>
                            <button type="submit" class="px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('Save FAQ') }}</button>
                        </div>
                    </form>
                    @if (!$loop->last)<div class="hairline-b pt-1"></div>@endif
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== RULES TABLE ===== --}}
    <section data-igtab-panel="rules" class="w-full px-7 pb-8">
        <div class="bg-white hairline rounded-2xl overflow-hidden">
            <div class="px-5 py-3 hairline-b flex flex-wrap items-center gap-3">
                <span class="text-[14px] font-semibold">{{ __('Your rules') }}</span>
                <div class="flex items-center gap-1 text-[11px] ml-2">
                    <span class="px-2.5 py-1 rounded-full bg-ink-900 text-white" data-rule-filter="all">{{ __('All') }} {{ $igCountAll }}</span>
                    <span class="px-2.5 py-1 rounded-full text-ink-600 hover:bg-paper-100 cursor-pointer" data-rule-filter="dm_keyword">{{ __('DM') }} {{ $igCountDm }}</span>
                    <span class="px-2.5 py-1 rounded-full text-ink-600 hover:bg-paper-100 cursor-pointer" data-rule-filter="story_reply">{{ __('Story') }} {{ $igCountStory }}</span>
                    <span class="px-2.5 py-1 rounded-full text-ink-600 hover:bg-paper-100 cursor-pointer" data-rule-filter="comment_to_dm">{{ __('Comment') }} {{ $automations->where('type', 'comment_to_dm')->count() }}</span>
                </div>
                <div class="flex-1"></div>
                <div class="flex items-center gap-2 hairline rounded-full px-3 py-1.5 bg-paper-50 w-56">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5"/><path d="m11 11 3 3"/></svg>
                    <input type="search" data-rule-search class="bg-transparent outline-none text-xs flex-1 placeholder:text-ink-500" placeholder="{{ __('Search rules…') }}">
                </div>
            </div>

            @if ($automations->isEmpty())
                {{-- ===== EMPTY STATE ===== --}}
                <div class="px-5 py-16 text-center">
                    <span class="ig-grad-soft w-12 h-12 rounded-2xl grid place-items-center text-white mx-auto mb-3"><svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3.5 6.5a5 5 0 0110 0v3l1.5 3h-13L3.5 9.5z"/><path d="M7.5 14a2.5 2.5 0 005 0"/></svg></span>
                    <div class="serif text-[22px] leading-none">{{ __('No rules yet') }}</div>
                    <p class="text-[12.5px] text-ink-500 mt-1.5">{{ __('Create your first auto-reply — when a DM, comment or story reply matches, :brand answers instantly.', ['brand' => brand_name()]) }}</p>
                    <a href="{{ route('instagram.automations.create') }}" class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('Create rule') }}
                    </a>
                </div>
            @else
                <table class="w-full text-[12.5px]">
                    <thead>
                        <tr class="text-left mono text-[9.5px] uppercase tracking-widest text-ink-500 hairline-b">
                            <th class="pl-5 py-2.5 font-normal">{{ __('Rule') }}</th>
                            <th class="py-2.5 font-normal">{{ __('Trigger') }}</th>
                            <th class="py-2.5 font-normal">{{ __('Keywords / condition') }}</th>
                            <th class="py-2.5 font-normal">{{ __('Account') }}</th>
                            <th class="py-2.5 font-normal text-right">{{ __('Fired') }}</th>
                            <th class="py-2.5 font-normal text-center">{{ __('Status') }}</th>
                            <th class="py-2.5 font-normal text-right pr-5">{{ __('Manage') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @foreach ($automations as $a)
                            @php
                                $m   = $typeMeta[$a->type] ?? $typeMeta['dm_keyword'];
                                $acc = $a->account;
                                $accName = $acc ? '@'.($acc->username ?: $acc->ig_user_id) : __('account removed');
                                $ruleName = $a->name ?: ($a->trigger_keyword ?: ($m['label']));
                                $kws = array_filter(array_map('trim', explode(',', (string) $a->trigger_keyword)));
                            @endphp
                            <tr class="row {{ $a->is_active ? '' : 'opacity-65' }}" data-rule-type="{{ $a->type }}" data-rule-name="{{ \Illuminate\Support\Str::lower($ruleName.' '.$a->trigger_keyword) }}">
                                <td class="pl-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <span class="w-9 h-9 rounded-lg {{ $m['tile'] }} grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 5.5a4 4 0 018 0v2.5l1.2 2.4H1.8L3 8z"/><path d="M6 11a2 2 0 004 0"/></svg>
                                        </span>
                                        <span class="font-semibold truncate max-w-[200px]">{{ $ruleName }}</span>
                                    </div>
                                </td>
                                <td><span class="pill {{ $m['pill'] }}">{{ $m['label'] }}</span></td>
                                <td>
                                    @if ($a->match_mode === 'any' || empty($kws))
                                        <span class="text-ink-500">{{ $a->match_mode === 'any' ? __('any message') : '—' }}</span>
                                    @else
                                        <div class="flex gap-1">
                                            @foreach (array_slice($kws, 0, 2) as $kw)
                                                <span class="kw">{{ $kw }}</span>
                                            @endforeach
                                            @if (count($kws) > 2)<span class="kw">+{{ count($kws) - 2 }}</span>@endif
                                        </div>
                                    @endif
                                </td>
                                <td class="text-ink-600 truncate max-w-[150px]">{{ $accName }}</td>
                                <td class="text-right tabular font-medium {{ (int) $a->fired_count ? '' : 'text-ink-400' }}">{{ (int) $a->fired_count ? number_format($a->fired_count) : '—' }}</td>
                                <td class="text-center">
                                    <form method="POST" action="{{ url('/instagram/automations/'.$a->id.'/toggle') }}" class="inline-block align-middle">@csrf
                                        <button type="submit" class="toggle {{ $a->is_active ? 'on' : 'off' }}" title="{{ $a->is_active ? __('Pause') : __('Resume') }}"><span></span></button>
                                    </form>
                                </td>
                                <td class="text-right pr-5">
                                    <div class="inline-flex items-center gap-1 align-middle">
                                        <a href="{{ route('instagram.automations.edit', $a->id) }}" class="w-7 h-7 rounded-lg grid place-items-center text-ink-400 hover:text-ig-pink hover:bg-ig-pink/5" title="{{ __('Edit') }}">
                                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M11.5 2.5l2 2L5.5 12.5 3 13l.5-2.5z"/></svg>
                                        </a>
                                        <form method="POST" action="{{ url('/instagram/automations/'.$a->id) }}" data-confirm-form data-confirm-title="{{ __('Delete rule?') }}" data-confirm-message="{{ __('This auto-reply rule will be removed.') }}">@csrf @method('DELETE')
                                            <button type="submit" class="w-7 h-7 rounded-lg grid place-items-center text-ink-400 hover:text-ig-pink hover:bg-ig-pink/5" title="{{ __('Delete') }}">
                                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4.5h10M6.5 4.5V3h3v1.5M5 4.5l.6 8h4.8l.6-8"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div data-rule-noresults class="hidden px-5 py-10 text-center text-[12.5px] text-ink-500">{{ __('No rules match your search.') }}</div>
            @endif
        </div>
    </section>

    @endif

    @push('scripts')
        <script src="{{ asset('assets/ig-automations-tabs.js') }}?v={{ @filemtime(public_path('assets/ig-automations-tabs.js')) }}"></script>
    @endpush
</x-layouts.instagram>

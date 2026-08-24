{{--
    Shared multi-step automation form — used by BOTH create.blade.php and
    edit.blade.php. A near-verbatim visual clone of the WaDesk auto-reply
    create stepper (sticky wrapper header lives in create/edit; here: the
    step-indicator bar, numbered 01/02/03/04 section headers, step-pane wizard,
    reply-type card grid, compose editor with formatting toolbar, Back / "Step
    X of 4" / Next / Save footer, and a right-hand live preview) — re-skinned in
    the IgDesk theme (serif/mono, hairline, ig-grad/ig-grad-soft/ig-text,
    field/seg/trig helper classes, paper-*/ink-*/ig-* tokens only — no hard-coded
    light hex, so dark mode flips cleanly).

    Backend contract (must not break): the form fields are
      instagram_account_id, type, name, trigger_keyword, match_mode, post_id,
      public_reply, dm_message, ai_assistant_id, ai_model, flow_id
    posted to the store/update route declared by the wrapping <form> in the
    create/edit page. Values pre-fill from old() first, then $automation
    (and $automation->meta_json['assistant_id'] / ['model'] for AI agents).

    JS hooks driven by resources/js/charts/instagram-automations-form.js — every
    one below MUST keep its attribute name: [data-ig-autoform], [data-store-action],
    #stepper, .step-node[data-n] (> .dot, .lab), .step-pane[data-step],
    [data-step-prev|next|submit|cur], [data-trig-group], [data-trig], [data-trig-hint],
    [data-seg-group], [data-seg], [data-rule-section="match|comment|nokeyword|message|ai|flow"],
    [data-msg-label], [data-msg-hint], [data-nokeyword-text], #ruleType, #ruleMatch,
    #igReplyText, #igPpBody, [data-rv="…"].

    Expects: $automation (nullable), $accounts, $assistants, $igFlows.
--}}
@php
    $automation = $automation ?? null;
    $isEdit     = (bool) $automation;

    // Resolved current values — old() (failed re-render) wins, then a template
    // recipe prefill (?recipe= on create), then the row being edited.
    $prefill   = $prefill ?? [];
    $vAccount  = old('instagram_account_id', $automation->instagram_account_id ?? '');
    $vType     = old('type', $prefill['type'] ?? ($automation->type ?? 'dm_keyword'));
    $vName     = old('name', $prefill['name'] ?? ($automation->name ?? ''));
    $vKeyword  = old('trigger_keyword', $prefill['trigger_keyword'] ?? ($automation->trigger_keyword ?? ''));
    $vMatch    = old('match_mode', $prefill['match_mode'] ?? ($automation->match_mode ?? 'contains'));
    $vPostId   = old('post_id', $automation->post_id ?? '');
    $vPublic   = old('public_reply', $prefill['public_reply'] ?? ($automation->public_reply ?? ''));
    $vDm       = old('dm_message', $prefill['dm_message'] ?? ($automation->dm_message ?? ''));
    $vAssistant = old('ai_assistant_id', $automation ? data_get($automation->meta_json, 'assistant_id') : '');
    $vModel    = old('ai_model', $automation ? data_get($automation->meta_json, 'model') : '');
    $vFlow     = old('flow_id', $automation->flow_id ?? '');
    // Rotating reply variations (ManyChat parity) — stored one per line in meta_json.
    $vPublicVars = old('public_reply_variants', $automation ? implode("\n", (array) data_get($automation->meta_json, 'public_reply_variants', [])) : '');
    $vDmVars     = old('dm_variants', $automation ? implode("\n", (array) data_get($automation->meta_json, 'dm_variants', [])) : '');

    // The 6 trigger tiles (icon + label) — sets the hidden `type` field.
    $triggerTiles = [
        ['key' => 'dm_keyword',    'label' => __('DM keyword'),   'color' => 'ig-pink',    'svg' => 'M2.5 4.5h13v8H7l-3.5 3z'],
        ['key' => 'comment_to_dm', 'label' => __('Comment → DM'), 'color' => 'ig-magenta', 'svg' => 'M2.5 4.5h13v6.5H7l-2.5 2.5z'],
        ['key' => 'story_reply',   'label' => __('Story reply'),  'color' => 'ig-purple',  'svg' => null], // story icon below
        ['key' => 'story_mention', 'label' => __('Story mention'),'color' => 'ig-purple',  'svg' => null], // @-story icon below
        ['key' => 'mention',       'label' => __('Mention'),      'color' => 'ig-orange',  'svg' => null], // @ icon below
        ['key' => 'ai_agent',      'label' => __('AI agent'),     'color' => 'ig-blue',    'svg' => null], // bot icon below
        ['key' => 'flow',          'label' => __('Visual flow'),  'color' => 'ig-blue',    'svg' => null], // flow icon below
    ];

    $accountUsername = optional($accounts->first())->username ?? 'yourbrand';
@endphp

<div class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5 items-start"
     data-ig-autoform
     data-store-action="{{ route('instagram.automations.store') }}"
     data-is-edit="{{ $isEdit ? '1' : '0' }}">

    {{-- Hidden contract fields driven by the tiles / segmented control --}}
    <input type="hidden" name="type" id="ruleType" value="{{ $vType }}">
    <input type="hidden" name="match_mode" id="ruleMatch" value="{{ $vMatch ?: 'contains' }}">

    {{-- ===== Stepper card ===== --}}
    <div class="bg-white hairline rounded-2xl shadow-card overflow-hidden">
        <div class="ig-grad h-1.5"></div>

        {{-- ── Step-indicator bar ── --}}
        <div class="px-5 py-4 hairline-b bg-paper-50/50 overflow-x-auto">
            <div class="flex items-center min-w-[460px]" id="stepper">
                @foreach (['1' => __('Trigger'), '2' => __('Match'), '3' => __('Reply'), '4' => __('Review')] as $n => $lab)
                    <div class="step-node flex items-center gap-2.5 {{ $n < 4 ? 'flex-1' : '' }} cursor-pointer" data-n="{{ $n }}">
                        <span class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold mono shrink-0 transition border-[1.5px] {{ $n === '1' ? 'bg-white border-ig-pink text-ig-pink ring-4 ring-ig-pink/10' : 'bg-white border-paper-200 text-ink-500' }}">{{ $n }}</span>
                        <span class="lab text-[11.5px] {{ $n === '1' ? 'font-semibold ig-text' : 'font-medium text-ink-500' }} whitespace-nowrap">{{ $lab }}</span>
                        @if ($n < 4)
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ── Step body ── --}}
        <div class="p-5">

            {{-- ===== Step 1 · Account + Trigger type ===== --}}
            <div class="step-pane" data-step="1">
                <div class="flex items-center gap-2.5 mb-4">
                    <span class="w-[23px] h-[23px] rounded-[7px] bg-ig-pink/10 text-ig-pink inline-flex items-center justify-center text-[10px] font-semibold mono shrink-0">01</span>
                    <span class="serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Account & trigger') }}</span>
                    <span class="mono text-[10px] text-ink-500">{{ __('required') }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Rule name') }}</label>
                        <input name="name" value="{{ $vName }}" class="field" placeholder="{{ __('e.g. Pricing keyword reply') }}">
                        <div class="text-[10.5px] text-ink-500 mt-1">{{ __("Internal label only — your followers don't see this.") }}</div>
                    </div>
                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Account') }} <span class="text-accent-coral">*</span></label>
                        <select name="instagram_account_id" required class="field">
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected((string) $vAccount === (string) $acc->id)>{{ '@'.($acc->username ?: $acc->ig_user_id) }}</option>
                            @endforeach
                        </select>
                        <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Which connected Instagram account this rule runs on.') }}</div>
                    </div>
                </div>

                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Trigger on') }} <span class="text-accent-coral">*</span></label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2" data-trig-group>
                    @foreach ($triggerTiles as $t)
                        <div class="trig {{ $vType === $t['key'] ? 'active' : '' }} hairline rounded-xl p-3 text-center" data-trig="{{ $t['key'] }}">
                            <span class="block mb-1.5">
                                @switch($t['key'])
                                    @case('story_reply')
                                        <svg viewBox="0 0 18 18" class="w-5 h-5 text-{{ $t['color'] }} mx-auto" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="2.5" width="10" height="13" rx="4"/><circle cx="9" cy="9" r="2.5"/></svg>
                                        @break
                                    @case('story_mention')
                                        <svg viewBox="0 0 18 18" class="w-5 h-5 text-{{ $t['color'] }} mx-auto" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="2.5" width="10" height="13" rx="4"/><circle cx="9" cy="9" r="2"/><path d="M11 9v.6a1.6 1.6 0 0 0 3 0A5 5 0 1 0 11.5 13"/></svg>
                                        @break
                                    @case('mention')
                                        <svg viewBox="0 0 18 18" class="w-5 h-5 text-{{ $t['color'] }} mx-auto" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="9" r="3"/><path d="M12 9v1a2.5 2.5 0 005 0 7 7 0 10-3 5.7"/></svg>
                                        @break
                                    @case('ai_agent')
                                        <svg viewBox="0 0 18 18" class="w-5 h-5 text-{{ $t['color'] }} mx-auto" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="5" width="12" height="9" rx="2.5"/><path d="M9 2v3M6 9h.01M12 9h.01"/></svg>
                                        @break
                                    @case('flow')
                                        <svg viewBox="0 0 18 18" class="w-5 h-5 text-{{ $t['color'] }} mx-auto" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="2.5" width="5" height="5" rx="1.2"/><rect x="10.5" y="10.5" width="5" height="5" rx="1.2"/><path d="M7.5 5h3a2 2 0 012 2v3.5"/></svg>
                                        @break
                                    @default
                                        <svg viewBox="0 0 18 18" class="w-5 h-5 text-{{ $t['color'] }} mx-auto" fill="none" stroke="currentColor" stroke-width="1.6"><path d="{{ $t['svg'] }}"/></svg>
                                @endswitch
                            </span>
                            <div class="text-[11.5px] font-semibold">{{ $t['label'] }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="text-[10.5px] text-ink-500 mt-3" data-trig-hint>{{ __('Pick what kicks off this automation. Each type asks for slightly different details next.') }}</div>
            </div>

            {{-- ===== Step 2 · Match ===== --}}
            <div class="step-pane hidden" data-step="2">
                <div class="flex items-center gap-2.5 mb-4">
                    <span class="w-[23px] h-[23px] rounded-[7px] bg-ig-purple/10 text-ig-purple inline-flex items-center justify-center text-[10px] font-semibold mono shrink-0">02</span>
                    <span class="serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Match') }}</span>
                    <span class="mono text-[10px] text-ink-500">{{ __('keywords') }}</span>
                </div>

                {{-- keyword-driven match config --}}
                <div data-rule-section="match">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Match mode') }}</label>
                            <div class="bg-paper-100 rounded-full p-1 inline-flex gap-1" data-seg-group>
                                <span class="seg {{ $vMatch === 'contains' ? 'active' : '' }}" data-seg="contains">{{ __('Contains') }}</span>
                                <span class="seg {{ $vMatch === 'exact' ? 'active' : '' }}" data-seg="exact">{{ __('Exact') }}</span>
                                <span class="seg {{ $vMatch === 'any' ? 'active' : '' }}" data-seg="any">{{ __('Any message') }}</span>
                            </div>
                            <div class="text-[10.5px] text-ink-500 mt-2">{{ __('“Any message” ignores keywords entirely — replies to every incoming DM.') }}</div>
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Keywords') }}</label>
                            <input name="trigger_keyword" value="{{ $vKeyword }}" class="field" placeholder="{{ __('price, cost, 🔥, 💜') }}">
                            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Comma-separate multiple keywords. You can also use an emoji reaction like 🔥 or 💜 — it fires when the comment or DM contains it.') }}</div>
                        </div>
                    </div>
                </div>

                {{-- comment-to-DM only: post scope + public reply --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-xl bg-paper-50/60 hairline p-4 mt-4 hidden" data-rule-section="comment">
                    <div class="sm:col-span-2 mono text-[10px] uppercase tracking-[0.16em] text-ig-magenta">{{ __('Comment-to-DM extras') }}</div>
                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Limit to a post') }}</label>
                        <input type="hidden" name="post_id" id="ig-am-post-id" value="{{ $vPostId }}">
                        <div class="flex items-center gap-2">
                            <button type="button" id="ig-am-post-pick" class="field flex items-center gap-2 text-left flex-1 hover:border-ig-pink transition">
                                <span id="ig-am-post-thumb-wrap" class="hidden shrink-0"><img id="ig-am-post-thumb" class="w-7 h-7 rounded object-cover bg-paper-100" alt=""></span>
                                <span id="ig-am-post-label" class="truncate {{ $vPostId ? 'text-ink-800' : 'text-ink-400' }}">{{ $vPostId ? __('Post') . ' · ' . $vPostId : __('All posts — tap to pick one') }}</span>
                            </button>
                            <button type="button" id="ig-am-post-clear" class="{{ $vPostId ? '' : 'hidden' }} shrink-0 w-9 h-9 grid place-items-center rounded-lg text-ink-400 hover:bg-paper-100 hover:text-ink-700" title="{{ __('Clear — apply to all posts') }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                            </button>
                        </div>
                        {{-- thumbnail picker — populated from the connected account's recent media --}}
                        <div id="ig-am-post-grid" class="hidden mt-2 grid grid-cols-4 gap-1.5 max-h-56 overflow-y-auto scrollbar p-1.5 rounded-lg bg-paper-0 hairline"></div>
                    </div>
                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Public reply') }}</label>
                        <input name="public_reply" value="{{ $vPublic }}" class="field" placeholder="{{ __('Sent you a DM!') }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('More public replies — one per line, rotated at random') }}</label>
                        <textarea name="public_reply_variants" rows="3" class="field w-full text-[12.5px]" placeholder="Sent you a message!&#10;Check your DMs!&#10;Just replied — take a look!">{{ $vPublicVars }}</textarea>
                        <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Instagram flags identical repeated comments as spam — add up to 3 variations and one is chosen at random each time.') }}</div>
                    </div>
                    <p class="sm:col-span-2 text-[10.5px] text-ink-500">{{ __('We post the public reply under the comment, then slide into their DMs with the message on the next step.') }}</p>
                </div>

                {{-- story-reply only: preview the account's active stories in an
                     Instagram-style player (gradient ring → tap → modal plays). --}}
                <div class="rounded-xl bg-paper-50/60 hairline p-4 mt-4 hidden" data-rule-section="story">
                    <div class="mono text-[10px] uppercase tracking-[0.16em] text-ig-purple mb-3">{{ __('Your active stories') }}</div>
                    <div class="flex items-center gap-4">
                        <button type="button" id="ig-am-story-ring" class="relative shrink-0 grid place-items-center rounded-full p-[3px]" title="{{ __('Watch your active stories') }}">
                            <span id="ig-am-story-grad" class="absolute inset-0 rounded-full bg-paper-200"></span>
                            <img id="ig-am-story-avatar" class="relative w-[62px] h-[62px] rounded-full object-cover bg-paper-100 ring-[2.5px] ring-white" alt="" src="">
                        </button>
                        <div class="min-w-0">
                            <div class="serif text-[15px] leading-tight text-ink-900" id="ig-am-story-title">{{ __('Loading your stories…') }}</div>
                            <p class="text-[11.5px] text-ink-500 mt-0.5" id="ig-am-story-hint">{{ __('Only stories live in the last 24h show here.') }}</p>
                        </div>
                    </div>
                </div>

                {{-- mention / flow have no keyword config --}}
                <div class="rounded-xl bg-paper-50/60 hairline p-4 mt-1 hidden" data-rule-section="nokeyword">
                    <p class="text-[12px] text-ink-700 leading-relaxed" data-nokeyword-text>{{ __('This trigger type fires without keyword matching — nothing to set here. Continue to the reply step.') }}</p>
                </div>
            </div>

            {{-- ===== Step 3 · Reply ===== --}}
            <div class="step-pane hidden" data-step="3">
                <div class="flex items-center gap-2.5 mb-4">
                    <span class="w-[23px] h-[23px] rounded-[7px] bg-ig-orange/10 text-ig-orange inline-flex items-center justify-center text-[10px] font-semibold mono shrink-0">03</span>
                    <span class="serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Reply') }}</span>
                    <span class="mono text-[10px] text-ink-500">{{ __('what we send back') }}</span>
                </div>

                {{-- Reply-type cards. Only the block(s) matching the Step-1 trigger show —
                     the shared JS toggles [data-rule-section] via .hidden, so exactly the
                     relevant card(s) appear (message for DM/comment/story/mention, message +
                     ai for AI agent, flow for visual flow). --}}

                {{-- Send a DM — compose editor card (everyone except flow) --}}
                <div class="rt-tile hairline rounded-2xl p-4 border-ig-pink/40 bg-ig-pink/[0.03]" data-rule-section="message">
                    <div class="flex items-start gap-3 mb-3">
                        <span class="w-10 h-10 rounded-xl bg-ig-pink/10 text-ig-pink grid place-items-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H8l-3 2v-2a2 2 0 0 1-2-2z"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="serif text-[17px] leading-tight" data-msg-label>{{ __('Auto-reply message') }}</div>
                            <p class="text-[12px] text-ink-500 leading-snug mt-0.5" data-msg-hint>{{ __('Use {first_name} to personalise. This is the DM :brand sends back.', ['brand' => brand_name()]) }}</p>
                        </div>
                    </div>

                    {{-- Compose editor with formatting toolbar (WaDesk chrome, IgDesk skin) --}}
                    <div class="hairline rounded-xl overflow-hidden bg-white">
                        <div class="flex items-center gap-1 px-2 py-1.5 hairline-b bg-paper-50">
                            <button type="button" class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center text-[11px] font-bold">B</button>
                            <button type="button" class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center text-[11px] italic">I</button>
                            <button type="button" class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center text-[11px] line-through">S</button>
                            <span class="w-px h-4 bg-paper-200 mx-1"></span>
                            <button type="button" class="px-2 h-7 rounded hover:bg-white text-ink-700 text-[10.5px] mono inline-flex items-center gap-1">{first_name}</button>
                            <button type="button" class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center" title="{{ __('Emoji') }}">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6"/><path d="M5.5 6.5h.01M10.5 6.5h.01M5.5 9.5a3 3 0 0 0 5 0"/></svg>
                            </button>
                            <span class="ml-auto mono text-[10px] text-ink-500"><span id="igCharCount">{{ mb_strlen((string) $vDm) }}</span> / 2000</span>
                        </div>
                        <textarea name="dm_message" id="igReplyText" rows="6" maxlength="2000"
                            class="w-full px-3 py-2.5 text-[12.5px] text-ink-900 bg-transparent focus:outline-none resize-none"
                            placeholder="{{ __('Here is your link: …') }}">{{ $vDm }}</textarea>
                    </div>
                    <div class="text-[10.5px] text-ink-500 mt-1.5">{{ __('Use') }} <span class="mono">{first_name}</span> {{ __('to personalise the greeting.') }}</div>

                    <div class="mt-3 pt-3 hairline-t">
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('More reply variations — one per line, sent at random') }}</label>
                        <textarea name="dm_variants" rows="3" class="field w-full text-[12.5px]" placeholder="Thanks for reaching out! 💜&#10;Hey! Got your message — here you go.&#10;So glad you messaged!">{{ $vDmVars }}</textarea>
                        <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Optional — perfect for story mentions. Add up to 6 and a random one is sent so every reply feels fresh.') }}</div>
                    </div>
                </div>

                {{-- AI Agent — knowledge base + model (AI agent only; stacks under the message card) --}}
                <div class="rt-tile hairline rounded-2xl p-4 mt-4 border-ig-blue/40 bg-ig-blue/[0.03] hidden" data-rule-section="ai">
                    <div class="flex items-start gap-3 mb-3">
                        <span class="w-10 h-10 rounded-xl bg-ig-blue/10 text-ig-blue grid place-items-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="5" width="11" height="8" rx="2"/><path d="M8 2v3M5.5 8.5h.01M10.5 8.5h.01"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="serif text-[17px] leading-tight">{{ __('AI Agent') }}</div>
                            <p class="text-[12px] text-ink-500 leading-snug mt-0.5">{{ __('Pulls your AI-Training content into every reply. The message box above becomes the system prompt.') }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Knowledge base') }}</label>
                            <select name="ai_assistant_id" class="field">
                                <option value="">{{ __('— none —') }}</option>
                                @foreach ($assistants as $as)
                                    <option value="{{ $as->id }}" @selected((string) $vAssistant === (string) $as->id)>{{ $as->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Model') }}</label>
                            <input name="ai_model" value="{{ $vModel }}" class="field mono" placeholder="gpt-4o-mini">
                        </div>
                    </div>
                </div>

                {{-- Run a flow — pick a built Instagram flow (flow only) --}}
                <div class="rt-tile hairline rounded-2xl p-4 border-ig-magenta/40 bg-ig-magenta/[0.03] hidden" data-rule-section="flow">
                    <div class="flex items-start gap-3 mb-3">
                        <span class="w-10 h-10 rounded-xl bg-ig-magenta/10 text-ig-magenta grid place-items-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="2.5" width="4.5" height="4.5" rx="1.2"/><rect x="9.5" y="9" width="4.5" height="4.5" rx="1.2"/><path d="M6.5 4.75h3a2 2 0 0 1 2 2V9"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="serif text-[17px] leading-tight">{{ __('Run a flow') }}</div>
                            <p class="text-[12px] text-ink-500 leading-snug mt-0.5">{{ __('Hand off to a visual Instagram flow. The matched event launches it.') }}</p>
                        </div>
                    </div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Visual flow to run') }} <span class="text-accent-coral">*</span></label>
                    <select name="flow_id" class="field">
                        <option value="">{{ __('— pick a flow —') }}</option>
                        @foreach ($igFlows as $f)
                            <option value="{{ $f->id }}" @selected((string) $vFlow === (string) $f->id)>{{ $f->flow_name ?: ('Flow #'.$f->id) }}</option>
                        @endforeach
                    </select>
                    <p class="text-[10.5px] text-ink-500 mt-1.5">{{ __('Build flows in the canvas: Flows → New Instagram Flow.') }}</p>
                    @if ($igFlows->isEmpty())
                        <p class="text-[10.5px] text-ig-pink mt-1.5">{{ __('No Instagram flows yet — create one first, then come back to wire it here.') }}</p>
                    @endif
                </div>
            </div>

            {{-- ===== Step 4 · Review ===== --}}
            <div class="step-pane hidden" data-step="4">
                <div class="flex items-center gap-2.5 mb-4">
                    <span class="w-[23px] h-[23px] rounded-[7px] bg-ig-blue/10 text-ig-blue inline-flex items-center justify-center text-[10px] font-semibold mono shrink-0">04</span>
                    <span class="serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Review & activate') }}</span>
                    <span class="mono text-[10px] text-ink-500">{{ __('final') }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <div class="hairline rounded-xl p-4 bg-paper-50/60">
                        <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2.5">{{ __('Trigger') }}</div>
                        <dl class="space-y-2 text-[12px]">
                            <div class="flex items-center justify-between gap-3"><dt class="text-ink-500">{{ __('Rule name') }}</dt><dd class="mono text-ink-900 truncate max-w-[60%] text-right" data-rv="name">—</dd></div>
                            <div class="flex items-center justify-between gap-3"><dt class="text-ink-500">{{ __('Account') }}</dt><dd class="mono text-ink-900 truncate max-w-[60%] text-right" data-rv="account">—</dd></div>
                            <div class="flex items-center justify-between gap-3"><dt class="text-ink-500">{{ __('Trigger type') }}</dt><dd class="mono text-ink-900" data-rv="type">—</dd></div>
                        </dl>
                    </div>
                    <div class="hairline rounded-xl p-4 bg-paper-50/60">
                        <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2.5">{{ __('Match & reply') }}</div>
                        <dl class="space-y-2 text-[12px]">
                            <div class="flex items-center justify-between gap-3"><dt class="text-ink-500">{{ __('Match mode') }}</dt><dd class="mono text-ink-900" data-rv="match">—</dd></div>
                            <div class="flex items-center justify-between gap-3"><dt class="text-ink-500">{{ __('Keywords') }}</dt><dd class="mono text-ink-900 truncate max-w-[60%] text-right" data-rv="keywords">—</dd></div>
                            <div class="flex items-center justify-between gap-3"><dt class="text-ink-500">{{ __('Reply') }}</dt><dd class="mono text-ink-900 truncate max-w-[60%] text-right" data-rv="reply">—</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 bg-paper-50/60 hairline rounded-xl p-4">
                    <div>
                        <div class="text-[13px] font-semibold">{{ __('Activate immediately') }}</div>
                        <div class="text-[11px] text-ink-500">{{ __('The rule starts matching incoming activity the moment you save.') }}</div>
                    </div>
                    <span class="pill bg-wa-green/10 text-wa-deep mono">{{ __('LIVE') }}</span>
                </div>

                @error('instagram')
                    <div class="mt-3 hairline rounded-xl bg-ig-pink/5 border-ig-pink/30 px-4 py-2.5 text-[12.5px] text-ig-pink">{{ $message }}</div>
                @enderror
            </div>

        </div>{{-- /step body --}}

        {{-- ── Step nav footer ── --}}
        <div class="px-5 py-4 hairline-t bg-paper-50/50 flex items-center justify-between gap-2">
            <button type="button" data-step-prev class="px-4 py-2 hairline rounded-full bg-white hover:bg-paper-50 text-[12px] font-medium text-ink-700 inline-flex items-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 4l-4 4 4 4"/></svg>{{ __('Back') }}
            </button>
            <div class="mono text-[11px] text-ink-500">{{ __('Step') }} <span data-step-cur>1</span> {{ __('of') }} 4</div>
            <button type="button" data-step-next class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90 inline-flex items-center gap-2">
                {{ __('Next') }}<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4l4 4-4 4"/></svg>
            </button>
            <button type="submit" data-step-submit class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90 hidden items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l5 5 7-9"/></svg>{{ $isEdit ? __('Update rule') : __('Activate rule') }}
            </button>
        </div>
    </div>

    {{-- ===== Live preview rail ===== --}}
    <aside class="space-y-4 xl:sticky xl:top-[80px] xl:self-start">
        <div class="bg-white hairline rounded-2xl shadow-card overflow-hidden">
            <div class="px-4 py-3 hairline-b flex items-center justify-between">
                <span class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Live preview') }}</span>
                <span class="mono text-[10px] ig-text">{{ __('Instagram DM') }}</span>
            </div>
            <div class="p-4 bg-paper-50/60">
                <div class="mx-auto w-[240px] rounded-[26px] bg-ink-900 p-1.5 shadow-soft">
                    <div class="rounded-[20px] overflow-hidden bg-white">
                        <div class="ig-grad-soft px-3 py-2.5 flex items-center gap-2">
                            <span class="w-7 h-7 rounded-full bg-white/25 text-white grid place-items-center text-[11px] font-semibold">{{ strtoupper(mb_substr($accountUsername, 0, 2)) }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] text-white font-semibold leading-tight truncate">{{ '@'.$accountUsername }}</div>
                                <div class="text-[9.5px] text-white/75 leading-tight">{{ __('active now · auto reply') }}</div>
                            </div>
                        </div>
                        <div class="p-3 min-h-[240px] ig-chat-bg space-y-1.5">
                            <div class="bg-paper-100 rounded-[16px] rounded-tl-[4px] px-3 py-1.5 max-w-[80%] text-[12px] leading-snug text-ink-900">{{ __('price?') }}</div>
                            <div class="ig-grad-soft text-white rounded-[16px] rounded-tr-[4px] px-3 py-1.5 max-w-[88%] ml-auto text-[12px] leading-snug">
                                <div id="igPpBody" class="whitespace-pre-wrap break-words">{{ $vDm ?: __('your DM appears here…') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-white hairline rounded-2xl shadow-card p-4">
            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Tip') }}</div>
            <p class="text-[12px] text-ink-700 leading-relaxed mt-1.5">{{ __('Comment → DM converts best: reply publicly once, then move the buyer into a private DM where you can drop a link.') }}</p>
        </div>
    </aside>
</div>

<x-layouts.instagram :title="__('Watermark')" ig-active="watermark" page="instagram-watermark">
    {{-- Content protection. Choose an account (or all), an image or text
         watermark, where it sits, how big and how strong — and every image you
         publish gets a stamped COPY. Server-rendered and usable without JS; the
         script only adds the live preview + inline save. --}}
    @php
        $mkUrl = fn ($p) => $p ? (function_exists('media_url') ? media_url($p) : asset('storage/' . $p)) : null;

        // Configs, keyed by scope ('all' or an account id), for the client to
        // load into the form when the account picker changes. Built in PHP so no
        // Blade echo ever contains a literal double brace.
        $jsConfigs = [];
        foreach ($configs as $key => $c) {
            $jsConfigs[(string) $key] = [
                'id'         => (int) $c->id,
                'type'       => (string) $c->type,
                'image_url'  => $mkUrl($c->image_path),
                'text'       => (string) ($c->text ?? ''),
                'text_color' => (string) ($c->text_color ?: '#ffffff'),
                'position'   => (string) $c->position,
                'size'       => (int) $c->size,
                'opacity'    => (int) $c->opacity,
                'is_active'  => (bool) $c->is_active,
            ];
        }

        // Default form state = the "All accounts" config if one exists, else sane
        // starting values.
        $cur = $jsConfigs['all'] ?? [
            'type' => 'image', 'image_url' => null, 'text' => '', 'text_color' => '#ffffff',
            'position' => 'br', 'size' => 22, 'opacity' => 70, 'is_active' => true,
        ];

        // The 3x3 grid, in row order, with a human label per anchor.
        $grid = [
            'tl' => __('Top left'),    'tc' => __('Top'),     'tr' => __('Top right'),
            'ml' => __('Left'),        'mc' => __('Center'),  'mr' => __('Right'),
            'bl' => __('Bottom left'), 'bc' => __('Bottom'),  'br' => __('Bottom right'),
        ];

        $jsData = [
            'saveUrl'  => url('/instagram/watermark'),
            'configs'  => $jsConfigs,
            'current'  => $cur,
        ];
    @endphp

    <div class="px-4 sm:px-6 lg:px-7 py-6" style="width:100%;max-width:100%;">

        {{-- Header --}}
        <div class="mb-6">
            <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-400">{{ __('Instagram') }} / {{ __('Content protection') }}</div>
            <h1 class="serif text-[26px] leading-tight mt-1">{{ __('Protect your') }} <span class="ig-text italic">{{ __('content') }}</span></h1>
            <p class="text-[12.5px] text-ink-500 mt-1.5 max-w-[620px] leading-relaxed">
                {{ __('Stamp a logo or a line of text onto every image you publish. We watermark a copy at publish time — your original file is never changed. Videos and reels are left untouched.') }}
            </p>
        </div>

        {{-- Flash --}}
        @if (session('status'))
            <div class="hairline rounded-xl bg-wa-mint/10 text-wa-deep px-4 py-2.5 text-[12.5px] mb-4">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="hairline rounded-xl bg-red-50 text-red-700 px-4 py-2.5 text-[12.5px] mb-4">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="hairline rounded-xl bg-red-50 text-red-700 px-4 py-2.5 text-[12.5px] mb-4">{{ $errors->first() }}</div>
        @endif

        {{-- Data island for the page script (no inline JS in Blade). --}}
        <script type="application/json" id="ig-wm-data">@json($jsData)</script>

        <form method="POST" action="{{ url('/instagram/watermark') }}" enctype="multipart/form-data"
              id="ig-wm-form" style="display:flex;flex-wrap:wrap;gap:1.25rem;align-items:flex-start;">
            @csrf

            {{-- ===== Settings column ===== --}}
            <div class="bg-paper-0 border border-paper-200 rounded-[16px] shadow-card p-5 sm:p-6" style="flex:1 1 420px;min-width:0;">

                {{-- Account scope --}}
                <div class="mb-5">
                    <label class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Apply to') }}</label>
                    <select name="scope" id="ig-wm-scope"
                            class="mt-1.5 w-full hairline rounded-xl bg-paper-0 px-3 py-2.5 text-[13px] text-ink-900">
                        <option value="all">{{ __('All accounts') }}</option>
                        @foreach ($accounts as $acc)
                            <option value="{{ $acc->id }}">{{ '@' . ($acc->username ?: ('#' . $acc->id)) }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-ink-500 mt-1.5">{{ __('A per-account watermark overrides the "All accounts" one for that account.') }}</p>
                </div>

                {{-- Type toggle --}}
                <div class="mb-5">
                    <label class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Watermark type') }}</label>
                    <div class="mt-1.5 inline-flex hairline rounded-full p-0.5" role="tablist">
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="image" class="hidden ig-wm-type" @checked($cur['type'] === 'image')>
                            <span data-type="image" class="ig-wm-type-btn inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-[12.5px]">{{ __('Image') }}</span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="text" class="hidden ig-wm-type" @checked($cur['type'] === 'text')>
                            <span data-type="text" class="ig-wm-type-btn inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-[12.5px]">{{ __('Text') }}</span>
                        </label>
                    </div>
                </div>

                {{-- Image panel --}}
                <div id="ig-wm-panel-image" class="mb-5 @if($cur['type'] !== 'image') hidden @endif">
                    <label class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Watermark image') }}</label>
                    <div class="mt-1.5 flex items-center gap-3">
                        <div id="ig-wm-img-thumb"
                             style="width:64px;height:64px;background:#f4f2ee;background-size:contain;background-position:center;background-repeat:no-repeat;@if($cur['image_url'])background-image:url('{{ $cur['image_url'] }}');@endif"
                             class="hairline rounded-xl shrink-0"></div>
                        <div>
                            <input type="file" name="image_file" id="ig-wm-img-input" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden">
                            <label for="ig-wm-img-input"
                                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full ig-grad text-white text-[12.5px] font-medium cursor-pointer hover:opacity-95 transition">
                                <svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 10.5V2.8M5 5.6 8 2.6l3 3M3 10.5v1.7A1.3 1.3 0 0 0 4.3 13.5h7.4A1.3 1.3 0 0 0 13 12.2v-1.7"/></svg>
                                {{ __('Upload image') }}
                            </label>
                            <p class="text-[11px] text-ink-500 mt-1.5">{{ __('PNG with transparency works best. Max 10 MB.') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Text panel --}}
                <div id="ig-wm-panel-text" class="mb-5 @if($cur['type'] !== 'text') hidden @endif">
                    <label class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Watermark text') }}</label>
                    <div class="mt-1.5 flex items-center gap-3">
                        <input type="text" name="text" id="ig-wm-text" maxlength="60" value="{{ $cur['text'] }}"
                               placeholder="{{ '@yourbrand' }}"
                               class="flex-1 hairline rounded-xl bg-paper-0 px-3 py-2.5 text-[13px] text-ink-900">
                        <label class="flex items-center gap-2 text-[11.5px] text-ink-600 shrink-0">
                            {{ __('Colour') }}
                            <input type="color" name="text_color" id="ig-wm-color" value="{{ $cur['text_color'] }}"
                                   style="width:34px;height:34px;padding:0;border:none;background:none;cursor:pointer">
                        </label>
                    </div>
                </div>

                {{-- Position grid --}}
                <div class="mb-5">
                    <label class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Position') }}</label>
                    <input type="hidden" name="position" id="ig-wm-position" value="{{ $cur['position'] }}">
                    <div id="ig-wm-grid" class="mt-1.5"
                         style="display:grid;grid-template-columns:repeat(3,44px);grid-template-rows:repeat(3,44px);gap:6px">
                        @foreach ($grid as $code => $label)
                            <button type="button" class="ig-wm-cell hairline rounded-lg" data-pos="{{ $code }}"
                                    title="{{ $label }}" aria-label="{{ $label }}"
                                    style="display:grid;place-items:center;background:#faf9f7;cursor:pointer">
                                <span style="width:12px;height:12px;border-radius:3px;background:#cfc9c0;display:block"></span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Size --}}
                <div class="mb-5">
                    <div class="flex items-center justify-between">
                        <label class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Size') }}</label>
                        <span id="ig-wm-size-val" class="text-[12px] text-ink-700 font-medium">{{ $cur['size'] }}%</span>
                    </div>
                    <input type="range" name="size" id="ig-wm-size" min="5" max="90" step="1" value="{{ $cur['size'] }}"
                           style="width:100%;margin-top:8px;accent-color:#c13584">
                    <p class="text-[11px] text-ink-500 mt-1">{{ __('Width of the watermark, as a share of the image width.') }}</p>
                </div>

                {{-- Opacity --}}
                <div class="mb-5">
                    <div class="flex items-center justify-between">
                        <label class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Opacity') }}</label>
                        <span id="ig-wm-opacity-val" class="text-[12px] text-ink-700 font-medium">{{ $cur['opacity'] }}%</span>
                    </div>
                    <input type="range" name="opacity" id="ig-wm-opacity" min="5" max="100" step="1" value="{{ $cur['opacity'] }}"
                           style="width:100%;margin-top:8px;accent-color:#c13584">
                </div>

                {{-- Active + Save --}}
                <div class="flex items-center justify-between gap-4 pt-3 border-t border-paper-200">
                    <label class="flex items-center gap-2 text-[12.5px] text-ink-700 cursor-pointer">
                        <input type="checkbox" name="is_active" id="ig-wm-active" value="1" @checked($cur['is_active'])
                               style="width:16px;height:16px;accent-color:#c13584">
                        {{ __('Enabled') }}
                    </label>
                    <button type="submit" id="ig-wm-save"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full ig-grad text-white text-[13px] font-semibold hover:opacity-95 transition">
                        <svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 3.5h7l2.5 2.5v6.5a.9.9 0 0 1-.9.9H3.5a.9.9 0 0 1-.9-.9V4.4a.9.9 0 0 1 .9-.9Z"/><path d="M5.5 3.5v3h4v-3M5.5 13v-3.2h5V13"/></svg>
                        {{ __('Save watermark') }}
                    </button>
                </div>
            </div>

            {{-- ===== Preview column ===== --}}
            <div class="bg-paper-0 border border-paper-200 rounded-[16px] shadow-card p-5" style="flex:0 0 340px;max-width:100%;">
                <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Preview') }}</div>
                <div id="ig-wm-preview"
                     style="position:relative;width:100%;aspect-ratio:4/5;border-radius:12px;overflow:hidden;background:linear-gradient(135deg,#e9e4dc 0%,#d8cfc4 55%,#c9bdae 100%)">
                    {{-- A faint placeholder subject so the watermark has context. --}}
                    <div style="position:absolute;inset:0;display:grid;place-items:center;color:#ffffff;opacity:.55">
                        <svg viewBox="0 0 24 24" width="52" height="52" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="9" cy="10" r="2"/><path d="m4 18 5-5 4 4 3-3 4 4"/></svg>
                    </div>
                    {{-- The moving watermark chip. Position/size/opacity set by JS. --}}
                    <div id="ig-wm-overlay"
                         style="position:absolute;max-width:90%;pointer-events:none;font-weight:700;color:#ffffff;text-shadow:0 1px 3px rgba(0,0,0,.4);line-height:1;white-space:nowrap"></div>
                </div>
                <p class="text-[11px] text-ink-500 mt-2 leading-relaxed">{{ __('Approximate placement before publishing. The real overlay is rendered onto a copy of each image at publish time.') }}</p>

                <noscript>
                    <p class="text-[11px] text-ink-500 mt-3">{{ __('Live preview needs JavaScript, but Save works without it.') }}</p>
                </noscript>
            </div>
        </form>

        {{-- ===== Saved watermarks ===== --}}
        @if (count($configs))
            <div class="mt-7">
                <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">{{ __('Saved watermarks') }}</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($configs as $key => $c)
                        @php
                            $scopeLabel = $key === 'all'
                                ? __('All accounts')
                                : '@' . optional($accounts->firstWhere('id', (int) $key))->username;
                            if ($key !== 'all' && !optional($accounts->firstWhere('id', (int) $key))->username) {
                                $scopeLabel = __('Account') . ' #' . $key;
                            }
                        @endphp
                        <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-4 flex items-start gap-3">
                            <div style="width:44px;height:44px;background:#f4f2ee;background-size:contain;background-position:center;background-repeat:no-repeat;@if($c->type === 'image' && $mkUrl($c->image_path))background-image:url('{{ $mkUrl($c->image_path) }}');@endif"
                                 class="hairline rounded-lg shrink-0 grid place-items-center">
                                @if ($c->type === 'text')
                                    <span class="text-[10px] text-ink-500 font-semibold">{{ __('Aa') }}</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-[13px] font-semibold text-ink-900 truncate">{{ $scopeLabel }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5">
                                    {{ $c->type === 'text' ? __('Text') : __('Image') }} · {{ $c->size }}% · {{ $c->opacity }}%
                                    @if (!$c->is_active) <span class="text-red-600">· {{ __('off') }}</span> @endif
                                </div>
                                <div class="flex items-center gap-2 mt-2">
                                    <form method="POST" action="{{ url('/instagram/watermark/' . $c->id . '/toggle') }}">
                                        @csrf
                                        <button type="submit" class="text-[11.5px] px-2.5 py-1 rounded-full hairline hover:bg-paper-50">
                                            {{ $c->is_active ? __('Disable') : __('Enable') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ url('/instagram/watermark/' . $c->id) }}"
                                          onsubmit="return confirm('{{ __('Remove this watermark?') }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-[11.5px] px-2.5 py-1 rounded-full hairline text-red-600 hover:bg-red-50">{{ __('Remove') }}</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-layouts.instagram>

<x-layouts.instagram :title="__('Create')" ig-active="composer" page="instagram-composer">
    @php $first = $accounts->first(); @endphp

    <form method="POST" action="{{ url('/instagram/composer') }}" id="composer-form" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="media_type" id="cmp-media-type" value="{{ old('media_type', 'image') }}">
        {{-- Set by the AI Image tool so a generated image is published as the post
             media (mirrors an uploaded file; a real upload overrides it server-side). --}}
        <input type="hidden" name="image_url" id="cmp-image-url" value="{{ old('image_url') }}">

        {{-- ===== HEADER ===== --}}
        <section class="max-w-[1500px] mx-auto px-6 pt-6 pb-4 flex items-end justify-between">
            <div>
                <div class="flex items-center gap-3 mb-1.5 text-[11px] mono uppercase tracking-[0.18em] text-ink-500"><span>{{ __('Studio') }}</span><span class="w-1 h-1 rounded-full bg-ink-400"></span><span>{{ __('Create & schedule') }}</span></div>
                <h1 class="serif text-[40px] leading-none">{{ __('Compose') }} <span class="ig-text italic">{{ __('content') }}</span></h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ url('/instagram/calendar') }}" class="px-4 py-2 hairline rounded-full bg-white text-[12px] font-medium hover:bg-paper-50 flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M2 6h12"/></svg>{{ __('View calendar') }}</a>
            </div>
        </section>

        <div class="max-w-[1500px] mx-auto px-6"><x-admin.flash />
            @error('instagram')<div class="mb-3 hairline rounded-xl bg-ig-pink/5 border-ig-pink/30 px-4 py-2.5 text-[12.5px] text-ig-pink">{{ $message }}</div>@enderror
            @unless (\Illuminate\Support\Str::startsWith(config('app.url'), 'https://'))
                <div class="mb-3 hairline rounded-xl bg-accent-amber/10 border-accent-amber/40 px-4 py-2.5 text-[12px] text-ink-700 flex items-start gap-2">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 text-accent-amber shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 5.5v3.5M8 11.5v.01"/><circle cx="8" cy="8" r="6.5"/></svg>
                    <span>{{ __('Heads up: Instagram publishes media from a public HTTPS link. On this non-HTTPS host the post is saved but the final publish will error. It works once the app runs on an HTTPS domain or cloud media storage is enabled.') }}</span>
                </div>
            @endunless
            @if (!empty($publishLimit) && ($publishLimit['total'] ?? 0) > 0)
                @php $left = max(0, ($publishLimit['total'] ?? 0) - ($publishLimit['used'] ?? 0)); @endphp
                <div class="mb-3 inline-flex items-center gap-2 hairline rounded-full bg-white px-3 py-1.5 text-[12px]">
                    <span class="w-2 h-2 rounded-full {{ $left > 0 ? 'bg-ig-blue' : 'bg-accent-coral' }}"></span>
                    <span class="text-ink-700">{{ __('Posts left today') }}: <span class="font-semibold tabular">{{ $left }}</span> / {{ $publishLimit['total'] }}</span>
                </div>
            @endif
        </div>

        @if ($accounts->isEmpty())
            <section class="max-w-[1500px] mx-auto px-6 pb-8">
                <div class="bg-white hairline rounded-2xl p-12 text-center">
                    <span class="ig-grad w-14 h-14 rounded-2xl grid place-items-center text-white mx-auto mb-3"><svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/></svg></span>
                    <div class="serif text-[24px]">{{ __('Connect an account to start posting') }}</div>
                    <p class="text-[12.5px] text-ink-500 mt-1">{{ __('Link a Professional / Creator account, then compose & schedule right here.') }}</p>
                    <a href="{{ url('/instagram/connect') }}" class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('Connect Instagram') }}</a>
                </div>
            </section>
        @else
        <section class="max-w-[1500px] mx-auto px-6 pb-8 grid grid-cols-12 gap-3">

            {{-- ===== LEFT: composer form ===== --}}
            <div class="col-span-12 lg:col-span-8 space-y-3">

                {{-- mode tabs --}}
                <div class="bg-white hairline rounded-2xl p-2 flex gap-2">
                    <button type="button" data-mode="image" class="mode-tab flex items-center justify-center gap-2"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="2" width="12" height="12" rx="2"/><circle cx="6" cy="6" r="1.3"/><path d="M2 11l3-3 3 2 3-3 3 3"/></svg>{{ __('Post') }}</button>
                    <button type="button" data-mode="story" class="mode-tab flex items-center justify-center gap-2"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="2" width="10" height="12" rx="3"/><circle cx="8" cy="8" r="2.5"/></svg>{{ __('Story') }}</button>
                    <button type="button" data-mode="reels" class="mode-tab flex items-center justify-center gap-2"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="2" width="12" height="12" rx="2"/><path d="M6 5l5 3-5 3z"/></svg>{{ __('Reel') }}</button>
                    <button type="button" data-mode="carousel" class="mode-tab flex items-center justify-center gap-2"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="1.5" y="3" width="6" height="10" rx="1"/><rect x="8.5" y="3" width="6" height="10" rx="1"/></svg>{{ __('Carousel') }}</button>
                </div>

                {{-- media --}}
                <div class="bg-white hairline rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="serif text-[20px]">{{ __('Media') }}</h2>
                        <span class="pill bg-paper-100 text-ink-600 mono">{{ __('public HTTPS url') }}</span>
                    </div>
                    {{-- Upload (single) for post / story / reel --}}
                    <div data-media-for="image,story,reels">
                        <label for="cmp-file" id="cmp-drop" class="upload-zone rounded-2xl p-8 flex flex-col items-center justify-center text-center cursor-pointer block">
                            <span class="ig-grad w-14 h-14 rounded-full grid place-items-center mb-3"><svg viewBox="0 0 20 20" class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 4v9M6 8l4-4 4 4M4 16h12"/></svg></span>
                            <div class="text-[14px] font-semibold">{{ __('Drag a photo or video here') }}</div>
                            <div class="text-[12px] text-ink-500 mt-1">{{ __('or click to browse · JPG, PNG, MP4 up to 100MB') }}</div>
                        </label>
                        <input type="file" id="cmp-file" name="media_file" accept="image/*,video/*" class="hidden">
                        {{-- selected-file thumb --}}
                        <div id="cmp-file-thumb" class="hidden mt-3 flex items-center gap-3">
                            <div id="cmp-file-img" class="w-16 h-16 rounded-xl bg-paper-100 bg-center bg-cover ring-2 ring-ig-pink ring-offset-1"></div>
                            <div class="min-w-0">
                                <div id="cmp-file-name" class="text-[12px] font-medium truncate"></div>
                                <button type="button" id="cmp-file-clear" class="text-[11px] text-accent-coral hover:underline">{{ __('Remove') }}</button>
                            </div>
                        </div>
                    </div>
                    {{-- Upload (multiple) for carousel --}}
                    <div data-media-for="carousel">
                        <label for="cmp-files" id="cmp-drop-multi" class="upload-zone rounded-2xl p-8 flex flex-col items-center justify-center text-center cursor-pointer block">
                            <span class="ig-grad w-14 h-14 rounded-full grid place-items-center mb-3"><svg viewBox="0 0 20 20" class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 4v9M6 8l4-4 4 4M4 16h12"/></svg></span>
                            <div class="text-[14px] font-semibold">{{ __('Drag 2–10 photos here') }}</div>
                            <div class="text-[12px] text-ink-500 mt-1">{{ __('or click to browse · select multiple for the carousel') }}</div>
                        </label>
                        <input type="file" id="cmp-files" name="media_files[]" accept="image/*,video/*" multiple class="hidden">
                        <div id="cmp-files-thumbs" class="hidden flex-wrap gap-2 mt-3"></div>
                    </div>
                </div>

                {{-- caption --}}
                <div class="bg-white hairline rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="serif text-[20px]">{{ __('Caption') }}</h2>
                    </div>
                    <textarea name="caption" id="cmp-caption" class="field h-28 resize-none" placeholder="{{ __('Write a caption… add hashtags and a call-to-action.') }}">{{ old('caption') }}</textarea>
                    <div class="flex items-center justify-end mt-2">
                        <span id="cmp-count" class="mono text-[10px] text-ink-400">0 / 2,200</span>
                    </div>
                </div>

                {{-- ===== AI composer tools ===== --}}
                <div class="bg-white border border-paper-200 rounded-2xl p-5 shadow-card relative overflow-hidden" id="cmp-ai">
                    {{-- soft brand glow, purely decorative --}}
                    <div class="pointer-events-none absolute -top-12 -right-12 w-36 h-36 rounded-full ig-grad opacity-[0.08] blur-2xl"></div>
                    <div class="relative flex items-center justify-between mb-1">
                        <div class="flex items-center gap-2">
                            <span class="ig-grad w-7 h-7 rounded-lg grid place-items-center text-white shrink-0"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 2l1.3 3.4L12.7 6 9.3 7.3 8 10.7 6.7 7.3 3.3 6l3.4-.6L8 2z"/><path d="M12.5 10.5l.6 1.6 1.6.6-1.6.6-.6 1.6-.6-1.6-1.6-.6 1.6-.6.6-1.6z"/></svg></span>
                            <h2 class="serif text-[20px]">{{ __('AI composer tools') }}</h2>
                        </div>
                        @if ($aiAllowed ?? true)
                            <span class="pill bg-paper-100 text-ink-600 mono">{{ __('beta') }}</span>
                        @else
                            <span class="pill ig-grad-soft text-white mono flex items-center gap-1">
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3.5" y="7" width="9" height="6" rx="1.4"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2"/></svg>{{ __('Plan feature') }}
                            </span>
                        @endif
                    </div>
                    <p class="text-[11.5px] text-ink-500 mb-3">{{ __('Draft, rewrite, and review your caption — or generate an image — with AI.') }}</p>

                    @unless ($aiAllowed ?? true)
                        <div class="mb-3 rounded-xl bg-ig-pink/5 border border-ig-pink/20 p-3 flex items-center justify-between gap-3 flex-wrap">
                            <div class="text-[12px] text-ink-700">{{ __('AI tools aren\'t included in your current plan.') }}</div>
                            <a href="{{ url('/instagram/plans') }}" class="pill ig-grad-soft text-white text-[11px] px-3 py-1 shrink-0">{{ __('Upgrade to unlock') }}</a>
                        </div>
                    @endunless

                    {{-- tool buttons — each with a distinct colored icon tile so the
                         row reads as a designed toolkit, not five identical pills. --}}
                    <div class="relative grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 {{ ($aiAllowed ?? true) ? '' : 'opacity-40 pointer-events-none select-none' }}">
                        <button type="button" data-ai="caption" class="ai-btn flex items-center justify-center gap-2">
                            <span class="w-6 h-6 rounded-md grid place-items-center shrink-0" style="background:#E9F7EF;color:#1E7D53"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4h12M2 8h9M2 12h12"/></svg></span>{{ __('AI Caption') }}
                        </button>
                        <button type="button" data-ai="image" class="ai-btn flex items-center justify-center gap-2">
                            <span class="w-6 h-6 rounded-md grid place-items-center shrink-0" style="background:#FCE7F0;color:#E1306C"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="2" width="12" height="12" rx="2"/><circle cx="6" cy="6" r="1.3"/><path d="M2 11l3-3 3 2 3-3 3 3"/></svg></span>{{ __('AI Image') }}
                        </button>
                        <button type="button" data-ai="repurpose" class="ai-btn flex items-center justify-center gap-2">
                            <span class="w-6 h-6 rounded-md grid place-items-center shrink-0" style="background:#FDECEA;color:#E5572B"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8a5 5 0 0 1 8.5-3.5L14 7M13 8a5 5 0 0 1-8.5 3.5L2 9"/><path d="M14 4v3h-3M2 12V9h3"/></svg></span>{{ __('Repurpose') }}
                        </button>
                        <button type="button" data-ai="review" class="ai-btn flex items-center justify-center gap-2">
                            <span class="w-6 h-6 rounded-md grid place-items-center shrink-0" style="background:#ECEBFB;color:#5B4BD6"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 3h10v8H8l-3 2v-2H3z"/><path d="M6 6h4M6 8h2.5"/></svg></span>{{ __('Review') }}
                        </button>
                        <button type="button" data-ai="best-time" class="ai-btn flex items-center justify-center gap-2">
                            <span class="w-6 h-6 rounded-md grid place-items-center shrink-0" style="background:#FDF3E0;color:#C77A0A"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 1"/></svg></span>{{ __('Best time') }}
                        </button>
                    </div>

                    {{-- caption affordance: notes + get / save --}}
                    <div class="mt-3 pt-3 border-t border-paper-200/70 {{ ($aiAllowed ?? true) ? '' : 'opacity-40 pointer-events-none select-none' }}">
                        <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('What is this post about?') }} <span class="text-ink-400 normal-case tracking-normal">{{ __('· optional, guides AI Caption / Image') }}</span></label>
                        <textarea id="cmp-ai-notes" rows="2" class="field mt-1.5 resize-none" placeholder="{{ __('e.g. launch of our summer skincare line, playful tone, target Gen-Z') }}"></textarea>
                        <div class="flex items-center gap-2 mt-2">
                            <button type="button" data-ai="caption" class="ai-btn-primary flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 2l1.3 3.4L12.7 6 9.3 7.3 8 10.7 6.7 7.3 3.3 6l3.4-.6L8 2z"/></svg>{{ __('Get caption') }}
                            </button>
                            <button type="button" id="cmp-ai-save" class="ai-btn flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 3h8l2 2v8H3z"/><path d="M5 3v3h5M5 13v-4h6v4"/></svg>{{ __('Save caption') }}
                            </button>
                        </div>
                    </div>

                    {{-- review output panel (Review does NOT overwrite the caption) --}}
                    <div id="cmp-ai-review" class="hidden mt-3 hairline rounded-xl bg-paper-50 px-4 py-3">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Caption review') }}</span>
                            <button type="button" data-ai-dismiss="cmp-ai-review" class="text-ink-400 hover:text-ink-700"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4l8 8M12 4l-8 8"/></svg></button>
                        </div>
                        <div id="cmp-ai-review-body" class="text-[12.5px] text-ink-700 leading-relaxed whitespace-pre-line"></div>
                    </div>

                    {{-- best-time output panel --}}
                    <div id="cmp-ai-times" class="hidden mt-3 hairline rounded-xl bg-paper-50 px-4 py-3">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Suggested posting windows') }}</span>
                            <button type="button" data-ai-dismiss="cmp-ai-times" class="text-ink-400 hover:text-ink-700"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4l8 8M12 4l-8 8"/></svg></button>
                        </div>
                        <div id="cmp-ai-times-body" class="space-y-1.5"></div>
                        <p id="cmp-ai-times-note" class="text-[11px] text-ink-400 mt-2"></p>
                    </div>

                    {{-- AI image preview + attach --}}
                    <div id="cmp-ai-image" class="hidden mt-3 hairline rounded-xl bg-paper-50 px-4 py-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Generated image') }}</span>
                            <button type="button" data-ai-dismiss="cmp-ai-image" class="text-ink-400 hover:text-ink-700"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4l8 8M12 4l-8 8"/></svg></button>
                        </div>
                        <div class="flex items-start gap-3">
                            <img id="cmp-ai-image-img" src="" alt="" class="w-24 h-24 rounded-xl object-cover ring-1 ring-paper-200 shrink-0">
                            <div class="min-w-0">
                                <p class="text-[12px] text-ink-600 mb-2">{{ __('Attach this as your post image, or generate another.') }}</p>
                                <button type="button" id="cmp-ai-image-attach" class="ai-btn-primary flex items-center gap-2">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 8l4 4 6-8"/></svg>{{ __('Use this image') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Shoppable post — tag catalog products (photo posts only). Only
                     shown when a catalog is synced. Requires the account to be
                     approved for Instagram Shopping; if not, Meta rejects the tag
                     and the composer surfaces the error. --}}
                @if (($products ?? collect())->isNotEmpty())
                    <details class="bg-white hairline rounded-2xl p-5 group">
                        <summary class="flex items-center justify-between cursor-pointer list-none">
                            <div>
                                <h2 class="serif text-[18px]">{{ __('Tag products') }}</h2>
                                <p class="text-[11.5px] text-ink-500 mt-0.5">{{ __('Make this photo shoppable. Up to 5 — needs an Instagram Shopping–approved account.') }}</p>
                            </div>
                            <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-400 group-open:rotate-180 transition" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 6l4 4 4-4"/></svg>
                        </summary>
                        <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-[240px] overflow-y-auto">
                            @foreach ($products as $p)
                                <label class="flex items-center gap-2 rounded-lg border border-paper-200 p-1.5 cursor-pointer hover:bg-paper-50 has-[:checked]:border-ig-purple has-[:checked]:bg-ig-purple/5">
                                    <input type="checkbox" name="product_ids[]" value="{{ $p->id }}" class="shrink-0">
                                    @if ($p->image_url)<img src="{{ $p->image_url }}" alt="" class="w-8 h-8 rounded object-cover border border-paper-200 shrink-0" loading="lazy">@endif
                                    <span class="min-w-0">
                                        <span class="block text-[11.5px] font-medium truncate">{{ $p->name }}</span>
                                        <span class="block text-[10px] text-ink-500 font-mono truncate">{{ $p->currency }} {{ $p->price !== null ? number_format((float) $p->price, 2) : '—' }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                @endif

                {{-- publish to + automate --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="bg-white hairline rounded-2xl p-5">
                        <h2 class="serif text-[18px] mb-3">{{ __('Publish to') }}</h2>
                        <div class="space-y-2">
                            @foreach ($accounts as $acc)
                                @php $ini = strtoupper(substr($acc->username ?: 'IG', 0, 2)); @endphp
                                <label class="acct-check flex">
                                    <input type="radio" name="instagram_account_id" value="{{ $acc->id }}" class="hidden" data-username="{{ $acc->username ?: $acc->ig_user_id }}" data-initials="{{ $ini }}" data-avatar="{{ $acc->profile_pic_url }}" @checked(old('instagram_account_id', optional($first)->id) == $acc->id)>
                                    <div class="hairline rounded-xl p-2.5 flex items-center gap-2.5 w-full transition">
                                        <span class="ig-ring"><span class="relative block w-7 h-7 rounded-full ig-grad-soft text-white text-[10px] font-semibold grid place-items-center overflow-hidden">{{ $ini }}@if ($acc->profile_pic_url)<img src="{{ $acc->profile_pic_url }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">@endif</span></span>
                                        <span class="text-[12.5px] font-medium flex-1 truncate">{{ '@'.($acc->username ?: $acc->ig_user_id) }}</span>
                                        <span class="acct-tick w-4 h-4 rounded-full border border-paper-200 grid place-items-center"><svg viewBox="0 0 10 10" class="w-2.5 h-2.5 text-white opacity-0 transition" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 5l2 2 4-5"/></svg></span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-white hairline rounded-2xl p-5">
                        <h2 class="serif text-[18px] mb-3">{{ __('Comment → DM') }} <span class="text-ink-400 text-[12px] font-normal">{{ __('· optional') }}</span></h2>
                        <div class="rounded-xl border border-accent-amber/40 bg-accent-amber/10 px-3 py-2 mb-3 text-[11.5px] text-ink-700 leading-snug">
                            <span class="font-semibold">{{ __('Needs Meta Advanced Access.') }}</span>
                            {{ __('Public replies + auto-DM on comments use the instagram_manage_comments and instagram_manage_messages permissions. They fire for everyone only after Meta approves Advanced Access (App Review); before that they work only for accounts with a role on your Meta app.') }}
                        </div>
                        <div class="space-y-2.5">
                            <div>
                                <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Trigger keyword') }}</label>
                                <input type="text" name="auto_keyword" value="{{ old('auto_keyword') }}" placeholder="LINK" class="field mt-1.5">
                            </div>
                            <div>
                                <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Public reply') }}</label>
                                <input type="text" name="auto_public_reply" value="{{ old('auto_public_reply') }}" placeholder="{{ __('Check your DMs! 💌') }}" class="field mt-1.5">
                            </div>
                            <div>
                                <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('DM to send') }}</label>
                                <textarea name="auto_dm" rows="2" placeholder="{{ __('Here is the link you asked for: …') }}" class="field mt-1.5 resize-none">{{ old('auto_dm') }}</textarea>
                            </div>
                            @if ($igFlows->count())
                                <div>
                                    <label class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('…or run a flow') }}</label>
                                    <select name="auto_flow_id" class="field mt-1.5">
                                        <option value="">{{ __('— none —') }}</option>
                                        @foreach ($igFlows as $f)
                                            <option value="{{ $f->id }}" @selected(old('auto_flow_id') == $f->id)>{{ $f->flow_name ?: ('Flow #'.$f->id) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- when to publish --}}
                <div class="bg-white hairline rounded-2xl p-5">
                    <h2 class="serif text-[20px] mb-3">{{ __('When to publish') }}</h2>
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <button type="button" data-when="now" class="hairline rounded-xl px-4 py-4 flex items-center justify-center gap-2 hover:bg-paper-50"><svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-500 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8l4 4 6-8"/></svg><span class="text-[13px] font-semibold">{{ __('Publish now') }}</span></button>
                        <button type="button" data-when="schedule" class="hairline rounded-xl px-4 py-4 flex items-center justify-center gap-2 hover:bg-paper-50"><svg viewBox="0 0 16 16" class="w-4 h-4 text-ig-pink shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 1"/></svg><span class="text-[13px] font-semibold">{{ __('Schedule') }}</span></button>
                    </div>
                    <div id="cmp-schedule-fields" class="hidden">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label for="cmp-sched-date" class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Send date') }}</label>
                                <input type="date" name="send_date" id="cmp-sched-date" value="{{ old('send_date') }}" class="field mt-1.5">
                            </div>
                            <div>
                                <label for="cmp-sched-time" class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Send time') }}</label>
                                <input type="time" name="send_time" id="cmp-sched-time" value="{{ old('send_time') }}" class="field mt-1.5">
                            </div>
                            <div>
                                <label for="cmp-sched-tz" class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Timezone') }}</label>
                                <select name="timezone" id="cmp-sched-tz" class="field mt-1.5">
                                    @php
                                        $userTz = old('timezone', optional(auth()->user()?->currentWorkspace)->timezone ?: (setting('default_timezone') ?: (config('app.timezone') ?: 'Asia/Kolkata')));
                                        try { $tzList = \DateTimeZone::listIdentifiers(); } catch (\Throwable $e) { $tzList = ['UTC', 'Asia/Kolkata', 'Asia/Dubai', 'Europe/London', 'America/New_York']; }
                                    @endphp
                                    @foreach ($tzList as $tz)
                                        <option value="{{ $tz }}" {{ $tz === $userTz ? 'selected' : '' }}>{{ $tz }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="text-[11px] text-ink-400 mt-2">{{ __('Your post publishes automatically at the selected date & time.') }}</p>
                    </div>
                </div>

                {{-- footer --}}
                <div class="flex items-center justify-between">
                    <span class="text-[12px] text-ink-500 mono">{{ $accounts->count() }} {{ __('account(s) connected') }}</span>
                    <button type="submit" class="px-6 py-2.5 rounded-full ig-grad-soft text-white text-[13px] font-semibold hover:opacity-90 flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 1"/></svg><span id="cmp-submit-label">{{ __('Publish now') }}</span>
                    </button>
                </div>
            </div>

            {{-- ===== RIGHT: live preview ===== --}}
            <div class="col-span-12 lg:col-span-4">
                <div class="sticky top-4">
                    <div class="bg-white hairline rounded-2xl p-5">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-[12px] font-semibold">{{ __('Live preview') }}</span>
                            <span class="text-[10px] mono uppercase tracking-widest text-ink-400">{{ __('Instagram') }}</span>
                        </div>
                        {{-- Instagram feed-post mockup. Fixed light colours (#262626/#8e8e8e/#efefef)
                             so it always looks like a real IG post — never inverts in dark mode. --}}
                        <div class="mx-auto rounded-2xl overflow-hidden bg-[#ffffff] border border-[#efefef] shadow-soft" style="max-width:300px;">
                            {{-- header --}}
                            <div class="px-3 py-2.5 flex items-center gap-2.5">
                                <span class="ig-ring shrink-0"><span id="pv-avatar" class="relative block w-8 h-8 rounded-full ig-grad-soft text-white text-[10px] font-semibold grid place-items-center overflow-hidden"><span id="pv-initials">{{ $first ? strtoupper(substr($first->username ?: 'IG', 0, 2)) : 'IG' }}</span><img id="pv-avatar-img" src="{{ $first->profile_pic_url ?? '' }}" alt="" referrerpolicy="no-referrer" class="absolute inset-0 w-full h-full object-cover {{ $first && $first->profile_pic_url ? '' : 'hidden' }}" onerror="this.classList.add('hidden')"></span></span>
                                <div class="flex-1 min-w-0 leading-tight">
                                    <div class="flex items-center gap-1">
                                        <span id="pv-name" class="text-[12.5px] font-semibold text-[#262626] truncate">{{ $first->username ?? 'your.account' }}</span>
                                        <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 shrink-0" fill="#3897f0"><path d="M12 2.2 14 3.9l2.6-.3 1 2.4 2.4 1.1-.4 2.6L23 12l-1.4 2.3.4 2.6-2.4 1.1-1 2.4-2.6-.3L12 21.8 9.4 20l-2.6.3-1-2.4L3.4 16.8l.4-2.6L2.4 12l1.4-2.3-.4-2.6 2.4-1.1 1-2.4 2.6.3z"/><path d="M8 12l2.6 2.6L16 9.4" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </div>
                                    <div class="text-[9px] text-[#8e8e8e]">{{ __('Preview') }}</div>
                                </div>
                                <svg viewBox="0 0 16 16" class="w-4 h-4 text-[#262626] shrink-0" fill="currentColor"><circle cx="3" cy="8" r="1.2"/><circle cx="8" cy="8" r="1.2"/><circle cx="13" cy="8" r="1.2"/></svg>
                            </div>
                            {{-- media --}}
                            <div id="pv-image" class="relative bg-[#efefef] bg-center bg-cover" style="aspect-ratio: 4 / 5;">
                                <div id="pv-placeholder" class="absolute inset-0 grid place-items-center text-[#c7c7c7]">
                                    <svg viewBox="0 0 48 48" class="w-14 h-14" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="6" y="9" width="36" height="30" rx="4"/><circle cx="16.5" cy="19.5" r="3.5"/><path d="M6 33l10.5-9 8 7 6-5L42 34"/></svg>
                                </div>
                            </div>
                            {{-- actions --}}
                            <div class="px-3 pt-2.5 pb-1 flex items-center gap-4 text-[#262626]">
                                <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 21s-7-4.6-9.5-9C1 9 2.5 5.5 6 5.5c2 0 3.2 1.2 4 2.3.8-1.1 2-2.3 4-2.3 3.5 0 5 3.5 3.5 6.5C19 16.4 12 21 12 21z"/></svg>
                                <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 11.5a8.4 8.4 0 0 1-12.9 7.4L3 20.5l1.6-4.3A8.4 8.4 0 1 1 21 11.5z"/></svg>
                                <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M22 3 11 14M22 3l-7 19-4-8-8-4 19-7z"/></svg>
                                <span class="flex-1"></span>
                                <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 3h12v18l-6-4.5L6 21z"/></svg>
                            </div>
                            {{-- likes + caption --}}
                            <div class="px-3 pb-3.5">
                                <div class="text-[12px] font-semibold text-[#262626]">{{ __('Liked by your followers') }}</div>
                                <div class="text-[11.5px] mt-1 leading-snug text-[#262626]"><span class="font-semibold">{{ $first->username ?? 'your.account' }}</span> <span id="pv-caption" class="text-[#404040]">{{ __('Your caption preview…') }}</span></div>
                                <div class="text-[10.5px] text-[#8e8e8e] mt-1.5">{{ __('View all comments') }}</div>
                                <div class="text-[9px] text-[#8e8e8e] mt-1.5 uppercase tracking-wide">{{ __('Just now') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        @endif
    </form>
@push('scripts')
    <script src="{{ url('assets/ig-schedule-tz.js') }}?v=1" defer></script>
@endpush
</x-layouts.instagram>

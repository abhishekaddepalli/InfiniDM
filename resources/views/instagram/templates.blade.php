<x-layouts.instagram :title="__('Template Library')" ig-active="templates" page="instagram-templates">

    {{-- ===== HEADER ===== --}}
    <section class="w-full px-7 pt-7 pb-4 flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1.5 text-[11px] mono uppercase tracking-[0.18em] text-ink-500">
                <span>{{ __('Instagram') }}</span>
                <span>/</span>
                <span>{{ __('Templates') }}</span>
            </div>
            <h1 class="serif text-[40px] leading-none">{{ __('Template') }} <span class="ig-text italic">{{ __('library') }}</span></h1>
            <p class="text-[12.5px] text-ink-500 max-w-xl mt-2">
                {{ __('Save reusable DM replies — plain text, quick replies, or button templates — and drop them into any conversation from the inbox composer.') }}
                <span class="text-ink-400">{{ __('No Meta approval needed — ready to use instantly.') }}</span>
            </p>
        </div>
        <a href="{{ route('instagram.templates.create') }}" class="px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold flex items-center gap-2 hover:opacity-90 shrink-0">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('New template') }}
        </a>
    </section>

    <div class="w-full px-7"><x-admin.flash /></div>

    {{-- ===== TABS + SEARCH ===== --}}
    <section class="w-full px-7" data-igt-state>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-paper-200 pb-0">
            <div class="flex items-center gap-1" id="igt-tabs">
                @php
                    $tabs = [
                        'all'           => __('All'),
                        'text'          => __('Text'),
                        'quick_replies' => __('Quick replies'),
                        'buttons'       => __('Buttons'),
                    ];
                @endphp
                @foreach ($tabs as $key => $label)
                    <button type="button" data-igt-tab="{{ $key }}"
                        class="igt-tab relative px-3 py-2.5 text-[13px] font-medium flex items-center gap-2 {{ $loop->first ? 'text-ig-pink' : 'text-ink-500 hover:text-ink-800' }}">
                        {{ $label }}
                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded-full {{ $loop->first ? 'bg-ig-pink/10 text-ig-pink' : 'bg-paper-100 text-ink-500' }}">{{ $typeCounts[$key] ?? 0 }}</span>
                        <span class="igt-underline absolute left-0 -bottom-px h-0.5 w-full ig-grad-soft rounded-full {{ $loop->first ? '' : 'hidden' }}"></span>
                    </button>
                @endforeach
            </div>
            <div class="relative pb-2.5">
                <svg viewBox="0 0 16 16" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="7" cy="7" r="4.5"/><path d="M11 11l3 3"/></svg>
                <input type="text" id="igt-search" placeholder="{{ __('Search…') }}"
                    class="w-56 max-w-full pl-9 pr-3 py-2 hairline rounded-full text-[13px] bg-white focus:outline-none focus:border-ig-pink">
            </div>
        </div>
    </section>

    {{-- ===== CARD GRID ===== --}}
    <section class="w-full px-7 py-6">
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4" id="igt-grid">
            @forelse ($templates as $t)
                @php
                    $typeLabel = ['text' => __('Text'), 'quick_replies' => __('Quick replies'), 'buttons' => __('Buttons')][$t->type] ?? ucfirst($t->type);
                    // Highlight {{variables}} in the preview bubble.
                    $fmt = e((string) $t->body);
                    $fmt = preg_replace('/\{\{\s*([^{}]+?)\s*\}\}/', '<span class="inline-block px-1 rounded bg-ig-pink/10 text-ig-pink font-medium">{{$1}}</span>', $fmt);
                    $fmt = nl2br($fmt);
                    $items = is_array($t->items) ? $t->items : [];
                @endphp
                <div class="igt-card bg-white border border-paper-200 rounded-[16px] p-4 flex flex-col transition hover:border-ig-pink hover:shadow-soft hover:-translate-y-px"
                     data-igt-type="{{ $t->type }}" data-igt-name="{{ strtolower($t->name) }}">
                    <div class="flex items-start justify-between gap-2 mb-2.5">
                        <span class="text-[13.5px] font-semibold text-ink-900 break-words">{{ $t->name }}</span>
                        <span class="shrink-0 text-[10px] font-medium px-2 py-0.5 rounded-full bg-ig-pink/8 text-ig-pink">{{ $typeLabel }}</span>
                    </div>

                    {{-- Instagram DM bubble preview --}}
                    <div class="flex-1 rounded-xl border border-paper-200 ig-chat-bg p-3 flex flex-col gap-1.5">
                        <div class="self-start w-full max-w-[92%] bg-white rounded-2xl rounded-tl-md shadow-sm px-3 py-2">
                            <div class="text-[12px] leading-[1.5] text-ink-800 break-words">{!! $fmt ?: '<span class="text-ink-400 italic">'.__('(empty)').'</span>' !!}</div>
                            <div class="text-[9px] text-ink-400 text-right mt-1">10:30</div>
                        </div>
                        @if (in_array($t->type, ['quick_replies', 'buttons'], true) && count($items))
                            {{-- Quick replies + buttons both render as full-width action rows below the bubble (WhatsApp/WaDesk style). --}}
                            <div class="self-start w-full max-w-[92%] space-y-1 mt-1">
                                @foreach (array_slice($items, 0, 3) as $it)
                                    @php $isUrl = ($it['type'] ?? '') === 'web_url' || (!empty($it['value']) && \Illuminate\Support\Str::startsWith((string) $it['value'], ['http://', 'https://'])); @endphp
                                    <div class="bg-white rounded-xl shadow-sm text-center text-[12.5px] text-ig-pink font-medium py-2 flex items-center justify-center gap-1.5">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5">
                                            @if ($isUrl)
                                                <path d="M6.5 9.5 9.5 6.5M7 4.5 8.5 3a2.5 2.5 0 0 1 3.5 3.5L10.5 8M9 11.5 7.5 13A2.5 2.5 0 0 1 4 9.5L5.5 8"/>
                                            @else
                                                <path d="M13 8a5 5 0 1 1-1.5-3.5M13 3v2h-2"/>
                                            @endif
                                        </svg>
                                        <span class="truncate">{{ $it['title'] ?? __('Button') }}</span>
                                    </div>
                                @endforeach
                                @if (count($items) > 3)
                                    <div class="text-[10px] font-mono text-ink-400 text-center">+{{ count($items) - 3 }} {{ __('more') }}</div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <a href="{{ route('instagram.templates.edit', $t->id) }}"
                            class="flex-1 border border-dashed border-ig-pink text-ig-pink bg-transparent text-[11.5px] font-medium py-[7px] rounded-full transition hover:bg-ig-pink hover:text-white hover:border-solid text-center">{{ __('Edit template') }}</a>
                        <form method="POST" action="{{ route('instagram.templates.destroy', $t->id) }}"
                              onsubmit="return confirm('{{ __('Delete this template?') }}')" class="shrink-0">@csrf @method('DELETE')
                            <button class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-accent-coral/10 hover:border-accent-coral hover:text-accent-coral grid place-items-center" title="{{ __('Delete') }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10M5 4V2.5h6V4M4 4l1 10h6l1-10"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white hairline rounded-2xl px-5 py-16 text-center">
                    <span class="ig-grad-soft w-12 h-12 rounded-2xl grid place-items-center text-white mx-auto mb-3"><svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="14" height="14" rx="2.5"/><path d="M3 7.5h14M7 7.5V17"/></svg></span>
                    <div class="serif text-[22px] leading-none">{{ __('No templates yet') }}</div>
                    <p class="text-[12.5px] text-ink-500 mt-1.5">{{ __('Create your first reusable reply — it will appear in the inbox composer’s Templates picker.') }}</p>
                    <a href="{{ route('instagram.templates.create') }}" class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('New template') }}
                    </a>
                </div>
            @endforelse
        </div>
        <div id="igt-empty-filter" class="hidden text-center text-[12.5px] text-ink-500 py-16">{{ __('No templates match your filters.') }}</div>
    </section>
</x-layouts.instagram>

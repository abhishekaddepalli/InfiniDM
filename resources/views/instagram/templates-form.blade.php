<x-layouts.instagram :title="$template ? __('Edit template') : __('New template')" ig-active="templates" page="instagram-templates">

    {{-- ===== HEADER ===== --}}
    <section class="w-full px-7 pt-7 pb-4">
        <div class="flex items-center gap-2 mb-1.5 text-[11px] mono uppercase tracking-[0.18em] text-ink-500">
            <a href="{{ route('instagram.templates') }}" class="hover:text-ig-pink">{{ __('Templates') }}</a>
            <span>/</span>
            <span>{{ $template ? __('Edit') : __('New') }}</span>
        </div>
        <h1 class="serif text-[36px] leading-none">{{ $template ? __('Edit') : __('New') }} <span class="ig-text italic">{{ __('template') }}</span></h1>
    </section>

    <div class="w-full px-7"><x-admin.flash /></div>

    <section class="w-full px-7 pb-10">
        <div class="grid lg:grid-cols-5 gap-5">
            {{-- ===== FORM ===== --}}
            <div class="lg:col-span-3">
                <form method="POST" id="igt-form"
                      action="{{ $template ? route('instagram.templates.update', $template->id) : route('instagram.templates.store') }}"
                      data-items='@json($template->items ?? [], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)' data-type="{{ old('type', $template->type ?? 'text') }}"
                      class="bg-white hairline rounded-2xl p-6 space-y-5">
                    @csrf
                    @if ($template) @method('PUT') @endif

                    <div>
                        <label class="block mono text-[10px] uppercase tracking-widest text-ink-500 mb-1.5">{{ __('Template name') }}</label>
                        <input name="name" id="igt-name" required maxlength="120" value="{{ old('name', $template->name ?? '') }}"
                               placeholder="{{ __('e.g. Welcome + menu') }}"
                               class="w-full px-3.5 py-2.5 hairline rounded-xl text-[13.5px] bg-white focus:outline-none focus:border-ig-pink">
                    </div>

                    <div>
                        <label class="block mono text-[10px] uppercase tracking-widest text-ink-500 mb-1.5">{{ __('Type') }}</label>
                        <div class="grid grid-cols-3 gap-2" id="igt-type">
                            @foreach (['text' => __('Text'), 'quick_replies' => __('Quick replies'), 'buttons' => __('Buttons')] as $val => $lbl)
                                <label class="cursor-pointer text-center px-2 py-2.5 hairline rounded-xl text-[12.5px] has-[:checked]:border-ig-pink has-[:checked]:bg-ig-pink/5 has-[:checked]:text-ig-pink font-medium">
                                    <input type="radio" name="type" value="{{ $val }}" class="hidden" @checked(old('type', $template->type ?? 'text') === $val)>{{ $lbl }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block mono text-[10px] uppercase tracking-widest text-ink-500 mb-1.5">{{ __('Message') }}</label>
                        <textarea name="body" id="igt-body" required rows="4" maxlength="1000" placeholder="{{ __('The message text. Use') }} @{{name}} {{ __('for variables.') }}"
                                  class="w-full px-3.5 py-2.5 hairline rounded-xl text-[13.5px] bg-white resize-y focus:outline-none focus:border-ig-pink">{{ old('body', $template->body ?? '') }}</textarea>
                    </div>

                    {{-- Quick replies rows --}}
                    <div id="igt-qr" class="hidden space-y-2">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Quick replies') }} <span class="text-ink-400">{{ __('(max 13)') }}</span></div>
                        <div id="igt-qr-rows" class="space-y-2"></div>
                        <button type="button" data-add="qr" class="text-[11.5px] text-ig-pink font-medium hover:underline">+ {{ __('Add quick reply') }}</button>
                    </div>

                    {{-- Buttons rows --}}
                    <div id="igt-btn" class="hidden space-y-2">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Buttons') }} <span class="text-ink-400">{{ __('(max 3)') }}</span></div>
                        <div id="igt-btn-rows" class="space-y-2"></div>
                        <button type="button" data-add="btn" class="text-[11.5px] text-ig-pink font-medium hover:underline">+ {{ __('Add button') }}</button>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="px-6 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ $template ? __('Save changes') : __('Save template') }}</button>
                        <a href="{{ route('instagram.templates') }}" class="text-[12.5px] text-ink-500 hover:text-ink-900">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>

            {{-- ===== LIVE PREVIEW ===== --}}
            <div class="lg:col-span-2">
                <div class="sticky top-4">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-2">{{ __('Live preview') }}</div>
                    <div class="rounded-2xl border border-paper-200 ig-chat-bg p-4 min-h-[220px] flex flex-col gap-2">
                        <div class="self-start w-full max-w-[92%] bg-white rounded-2xl rounded-tl-md shadow-sm px-3 py-2">
                            <div id="igt-preview-body" class="text-[12.5px] leading-[1.5] text-ink-800 break-words"><span class="text-ink-400 italic">{{ __('Your message will appear here…') }}</span></div>
                            <div class="text-[9px] text-ink-400 text-right mt-1">10:30</div>
                        </div>
                        <div id="igt-preview-qr" class="self-start flex flex-wrap gap-1.5 mt-0.5"></div>
                        <div id="igt-preview-btn" class="self-start w-full max-w-[92%] space-y-1 mt-0.5"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.instagram>

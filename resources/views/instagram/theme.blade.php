{{--
    Appearance.

    Four choices — mode, accent, font, size — saved together as one JSON cookie
    and replayed by the layout as CSS custom-property overrides. Because the
    whole suite already reads those tokens (the flow builder alone spells them
    out 208 times), overriding a handful of variables recolours and re-types
    every screen without touching a single call site.

    Saved through a POST, never document.cookie: Laravel encrypts cookies, so a
    value written by JS fails decryption on the very next request and the choice
    silently reverts.

    No JavaScript: every control is a <label> wrapping a radio, so selection is
    the browser's own and this works with the bundle blocked.
--}}
<x-layouts.instagram :title="__('Appearance')" ig-active="theme" page="instagram-theme">

    @php
        $a = \App\Support\InstaflowAppearance::current();

        $modes = [
            ['value' => 'paper', 'label' => __('Light'), 'desc' => __('White surfaces, dark text.')],
            ['value' => 'dark',  'label' => __('Dark'),  'desc' => __("Instagram's own dark palette.")],
        ];
        $accents = \App\Support\InstaflowAppearance::ACCENTS;
        $fonts   = \App\Support\InstaflowAppearance::FONTS;
        $sizes   = \App\Support\InstaflowAppearance::SIZES;
    @endphp

    <div class="px-4 sm:px-7 py-7">

        <div class="mb-7">
            <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Appearance') }}
            </div>
            <h1 class="serif font-serif font-normal text-[32px] sm:text-[38px] lg:text-[44px] leading-[1.05] tracking-tight">
                {{ __('Make it') }} <span class="ig-text italic">{{ __('yours') }}</span>.
            </h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                {{ __('Applies to every :brand screen on this device and stays put until you change it here.', ['brand' => brand_name()]) }}
            </p>
        </div>

        @if (session('success'))
            <div class="mb-6 rounded-xl px-4 py-3 text-[12.5px] bg-wa-mint border border-wa-deep/25 text-wa-deep">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ url('/instagram/theme') }}" class="space-y-11">
            @csrf

            {{-- ===== MODE ===== --}}
            <section>
                <h2 class="font-serif text-[20px] leading-tight mb-1">{{ __('Mode') }}</h2>
                <p class="text-[12px] text-ink-500 mb-3">{{ __('The base surface everything else sits on.') }}</p>

                <div class="grid grid-cols-2 gap-3 max-w-lg">
                    @foreach ($modes as $opt)
                        <label class="ig-theme-card cursor-pointer">
                            <input type="radio" name="theme" value="{{ $opt['value'] }}" class="sr-only"
                                @checked($a['theme'] === $opt['value'])>
                            <span class="ig-theme-card-inner block bg-paper-0 border border-paper-200 rounded-[14px] p-3 shadow-card transition">
                                {{-- Miniature of the shell in the colours that option
                                     actually produces — hardcoded, because the dark
                                     preview must render dark while this page is light. --}}
                                <span class="block rounded-[8px] overflow-hidden border border-paper-200 mb-2.5">
                                    <span class="flex h-[72px]" style="background:{{ $opt['value'] === 'dark' ? '#000' : '#FBF8FC' }}">
                                        <span class="w-[15%] min-w-[26px] shrink-0 flex flex-col items-center gap-1.5 pt-2"
                                            style="background:{{ $opt['value'] === 'dark' ? '#121212' : '#FFF' }};border-right:1px solid {{ $opt['value'] === 'dark' ? '#2A2A2A' : '#EBE3EE' }}">
                                            <span class="w-5 h-5 rounded-lg block" style="background:{{ $a['accent_grad'] }}"></span>
                                            @for ($i = 0; $i < 3; $i++)
                                                <span class="w-4 h-1.5 rounded-full block" style="background:{{ $opt['value'] === 'dark' ? '#2A2A2A' : '#EBE3EE' }}"></span>
                                            @endfor
                                        </span>
                                        <span class="flex-1 p-2.5">
                                            <span class="block w-2/3 h-2.5 rounded mb-2" style="background:{{ $opt['value'] === 'dark' ? '#2A2A2A' : '#EBE3EE' }}"></span>
                                            <span class="block rounded-md h-[52px]" style="background:{{ $opt['value'] === 'dark' ? '#121212' : '#FFF' }};border:1px solid {{ $opt['value'] === 'dark' ? '#2A2A2A' : '#EBE3EE' }}"></span>
                                        </span>
                                    </span>
                                </span>
                                <span class="flex items-start gap-2.5">
                                    <span class="ig-theme-dot mt-0.5 shrink-0"></span>
                                    <span class="block">
                                        <span class="block font-semibold text-[13.5px] text-ink-900">{{ $opt['label'] }}</span>
                                        <span class="block text-[11.5px] text-ink-500 mt-0.5">{{ $opt['desc'] }}</span>
                                    </span>
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            {{-- ===== ACCENT ===== --}}
            <section>
                <h2 class="font-serif text-[20px] leading-tight mb-1">{{ __('Accent') }}</h2>
                <p class="text-[12px] text-ink-500 mb-3">
                    {{ __('Buttons, links and highlights. Each carries its own darker shade for light mode and lighter shade for dark, so text stays readable either way.') }}
                </p>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-7 gap-3">
                    @foreach ($accents as $key => $acc)
                        <label class="ig-theme-card cursor-pointer">
                            <input type="radio" name="accent" value="{{ $key }}" class="sr-only"
                                @checked($a['accent'] === $key)>
                            <span class="ig-theme-card-inner block bg-paper-0 border border-paper-200 rounded-[14px] p-3 shadow-card transition text-center">
                                <span class="block h-16 rounded-xl mb-3" style="background:{{ $acc['grad'] }}"></span>
                                <span class="block text-[12px] font-semibold text-ink-900">{{ $acc['label'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            {{-- ===== FONT ===== --}}
            <section>
                <h2 class="font-serif text-[20px] leading-tight mb-1">{{ __('Typeface') }}</h2>
                <p class="text-[12px] text-ink-500 mb-3">{{ __('Used for all interface text. Headings keep their serif.') }}</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    @foreach ($fonts as $key => $font)
                        <label class="ig-theme-card cursor-pointer">
                            <input type="radio" name="font" value="{{ $key }}" class="sr-only"
                                @checked($a['font'] === $key)>
                            <span class="ig-theme-card-inner block bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card transition">
                                {{-- Rendered in the face itself, so the choice is
                                     visible rather than described. --}}
                                <span class="block text-[19px] text-ink-900 leading-tight mb-1" style="font-family:{{ $font['stack'] }}">
                                    {{ __('Reply sent') }}
                                </span>
                                <span class="block text-[11.5px] text-ink-500" style="font-family:{{ $font['stack'] }}">
                                    {{ $font['label'] }} · {{ __('The quick brown fox') }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            {{-- ===== SIZE ===== --}}
            <section>
                <h2 class="font-serif text-[20px] leading-tight mb-1">{{ __('Text size') }}</h2>
                <p class="text-[12px] text-ink-500 mb-3">{{ __('Scales the whole interface, not just body copy.') }}</p>

                <div class="grid grid-cols-3 gap-3">
                    @foreach ($sizes as $key => $size)
                        <label class="ig-theme-card cursor-pointer">
                            <input type="radio" name="size" value="{{ $key }}" class="sr-only"
                                @checked($a['size'] === $key)>
                            <span class="ig-theme-card-inner block bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card transition text-center">
                                <span class="block text-ink-900 leading-none mb-2" style="font-size:{{ $size['sample'] }}">Aa</span>
                                <span class="block text-[12px] font-semibold text-ink-900">{{ $size['label'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit" class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">
                    {{ __('Save appearance') }}
                </button>
                <button type="submit" name="reset" value="1"
                    class="px-4 py-2 rounded-full border border-paper-200 bg-paper-0 text-[12px] font-semibold text-ink-700 hover:bg-paper-50">
                    {{ __('Reset to defaults') }}
                </button>
            </div>
        </form>

    </div>

    <script src="{{ asset('assets/theme-autoapply.js') }}" defer></script>
</x-layouts.instagram>

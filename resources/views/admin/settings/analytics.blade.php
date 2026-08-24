<x-layouts.admin :title="__('Analytics settings')" admin-key="tracking">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Analytics') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.settings.analytics.save') }}">
        @csrf
        <main class="px-4 sm:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Tracking') }}
                        <span class="italic ig-text">{{ __('scripts') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Provider IDs and custom snippets injected into public pages. Paste each ID once, or drop raw script into the head or body slots.') }}</p>
                </div>
                <div class="shrink-0 pb-1">
                    <button type="submit" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

            @if (session('success'))
                <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">{{ $errors->first() }}</div>
            @endif

            @php
                $fld = 'w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep';
                $fldMono = $fld . ' font-mono';
            @endphp

            {{-- Analytics IDs --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('provider-ids') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Analytics IDs') }}</h2>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3 cursor-pointer sm:col-span-2">
                        <span>
                            <span class="block text-[12.5px] font-semibold">{{ __('Enable analytics') }}</span>
                            <span class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Master switch — render tracking snippets on public pages.') }}</span>
                        </span>
                        <span class="relative inline-block w-9 h-5 shrink-0">
                            <input type="hidden" name="analytics_enabled" value="0">
                            <input type="checkbox" name="analytics_enabled" value="1" class="peer opacity-0 w-0 h-0" @checked(old('analytics_enabled', $values['analytics_enabled']))>
                            <span class="absolute inset-0 bg-paper-200 rounded-full transition peer-checked:bg-wa-deep before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:before:translate-x-[16px]"></span>
                        </span>
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Google Analytics 4 (Measurement ID)') }}</span>
                        <input name="analytics_ga4_id" value="{{ old('analytics_ga4_id', $values['analytics_ga4_id']) }}" maxlength="60" placeholder="G-XXXXXXXXXX" class="{{ $fldMono }}">
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Google Tag Manager (Container ID)') }}</span>
                        <input name="analytics_gtm_id" value="{{ old('analytics_gtm_id', $values['analytics_gtm_id']) }}" maxlength="60" placeholder="GTM-XXXXXXX" class="{{ $fldMono }}">
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Meta (Facebook) Pixel ID') }}</span>
                        <input name="analytics_meta_pixel_id" value="{{ old('analytics_meta_pixel_id', $values['analytics_meta_pixel_id']) }}" maxlength="60" placeholder="123456789012345" class="{{ $fldMono }}">
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Microsoft Clarity (Project ID)') }}</span>
                        <input name="analytics_clarity_id" value="{{ old('analytics_clarity_id', $values['analytics_clarity_id']) }}" maxlength="60" placeholder="abcd1234ef" class="{{ $fldMono }}">
                    </label>
                </div>
            </section>

            {{-- Custom scripts --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('raw-injection') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Custom scripts') }}</h2>
                    <p class="text-[12px] text-ink-600 mt-1">{{ __('Raw HTML injected verbatim. Only paste code you trust.') }}</p>
                </div>
                <div class="p-5 grid grid-cols-1 gap-4">
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Head scripts') }}</span>
                        <textarea name="analytics_head_scripts" rows="6" maxlength="8000" placeholder="&lt;!-- injected before &lt;/head&gt; --&gt;" class="{{ $fldMono }} resize-none">{{ old('analytics_head_scripts', $values['analytics_head_scripts']) }}</textarea>
                        <span class="text-[11px] text-ink-500">{{ __('Injected just before the closing') }} <code class="font-mono text-[11px]">&lt;/head&gt;</code> {{ __('tag.') }}</span>
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Body scripts') }}</span>
                        <textarea name="analytics_body_scripts" rows="6" maxlength="8000" placeholder="&lt;!-- injected before &lt;/body&gt; --&gt;" class="{{ $fldMono }} resize-none">{{ old('analytics_body_scripts', $values['analytics_body_scripts']) }}</textarea>
                        <span class="text-[11px] text-ink-500">{{ __('Injected just before the closing') }} <code class="font-mono text-[11px]">&lt;/body&gt;</code> {{ __('tag.') }}</span>
                    </label>
                </div>
            </section>

            {{-- Sticky save bar --}}
            <div class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Changes apply only after you save.') }}</span>
                <button type="submit" class="px-5 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
            </div>

        </main>
    </form>

</x-layouts.admin>

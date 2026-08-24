<x-layouts.admin :title="__('SEO settings')" admin-key="seo">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('SEO') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.settings.seo.save') }}">
        @csrf
        <main class="px-4 sm:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('SEO') }}
                        <span class="italic ig-text">{{ __('meta') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Search-engine titles, descriptions, robots directives, canonical URL, and the link-preview card shown when someone shares your site.') }}</p>
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

            {{-- Meta tags --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('search-engines') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Meta tags') }}</h2>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="space-y-1.5 sm:col-span-2">
                        <span class="text-[11.5px] font-semibold">{{ __('Meta title') }}</span>
                        <input name="seo_title" value="{{ old('seo_title', $values['seo_title']) }}" maxlength="180" placeholder="{{ __(':brand — Instagram automation, on autopilot', ['brand' => brand_name()]) }}" class="{{ $fld }}">
                        <span class="text-[11px] text-ink-500">{{ __('Shown in search results and browser tabs. Aim for 50–60 characters.') }}</span>
                    </label>
                    <label class="space-y-1.5 sm:col-span-2">
                        <span class="text-[11.5px] font-semibold">{{ __('Meta description') }}</span>
                        <textarea name="seo_description" rows="3" maxlength="400" placeholder="{{ __('A 1–2 sentence pitch shown under the title in Google.') }}" class="{{ $fld }} resize-none">{{ old('seo_description', $values['seo_description']) }}</textarea>
                        <span class="text-[11px] text-ink-500">{{ __('Aim for 140–160 characters.') }}</span>
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Meta keywords') }}</span>
                        <input name="seo_keywords" value="{{ old('seo_keywords', $values['seo_keywords']) }}" maxlength="400" placeholder="{{ __('instagram automation, dm, comment reply') }}" class="{{ $fld }}">
                        <span class="text-[11px] text-ink-500">{{ __('Comma-separated. Most engines ignore this.') }}</span>
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Robots directive') }}</span>
                        @php $rb = old('seo_robots', $values['seo_robots'] ?: 'index,follow'); @endphp
                        <select name="seo_robots" class="{{ $fld }}">
                            <option value="index,follow" @selected($rb === 'index,follow')>{{ __('Index, follow — public site') }}</option>
                            <option value="index,nofollow" @selected($rb === 'index,nofollow')>{{ __('Index, nofollow') }}</option>
                            <option value="noindex,follow" @selected($rb === 'noindex,follow')>{{ __('Noindex, follow') }}</option>
                            <option value="noindex,nofollow" @selected($rb === 'noindex,nofollow')>{{ __('Noindex, nofollow — hide from search') }}</option>
                        </select>
                        <span class="text-[11px] text-ink-500">{{ __('Use noindex for staging or private deployments.') }}</span>
                    </label>
                    <label class="space-y-1.5 sm:col-span-2">
                        <span class="text-[11.5px] font-semibold">{{ __('Canonical URL') }}</span>
                        <input name="seo_canonical_url" value="{{ old('seo_canonical_url', $values['seo_canonical_url']) }}" maxlength="300" placeholder="https://app.instaflow.test" class="{{ $fldMono }}">
                        <span class="text-[11px] text-ink-500">{{ __('Default canonical URL. Leave blank to use each page\'s own URL.') }}</span>
                    </label>
                </div>
            </section>

            {{-- Open Graph & social --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('social-previews') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Open Graph & social') }}</h2>
                    <p class="text-[12px] text-ink-600 mt-1">{{ __('The card shown by Facebook, LinkedIn, WhatsApp, Slack and X when your link is shared.') }}</p>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="space-y-1.5 sm:col-span-2">
                        <span class="text-[11.5px] font-semibold">{{ __('OG image URL') }}</span>
                        <input name="seo_og_image" value="{{ old('seo_og_image', $values['seo_og_image']) }}" maxlength="300" placeholder="https://app.instaflow.test/og.png" class="{{ $fldMono }}">
                        <span class="text-[11px] text-ink-500">{{ __('Recommended') }} <span class="font-mono">1200×630 px</span>. {{ __('PNG or JPG.') }}</span>
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Twitter / X handle') }}</span>
                        <input name="seo_twitter_handle" value="{{ old('seo_twitter_handle', $values['seo_twitter_handle']) }}" maxlength="60" placeholder="@instaflow" class="{{ $fldMono }}">
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

<x-layouts.admin :title="__('Site settings')" admin-key="site">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Site settings') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.site.save') }}">
        @csrf
        <main class="px-4 sm:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Public site') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Site') }}
                        <span class="italic ig-text">{{ __('settings') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Brand name, contact details and social links shared across the footer, contact and about pages — edit once, they update everywhere.') }}</p>
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

            <section class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5 items-start">
                <div class="space-y-5 min-w-0">

                    {{-- Brand --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('brand') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Brand') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Site name') }}</span>
                                <input name="site_name" value="{{ old('site_name', $values['site_name']) }}" placeholder="IgDesk" class="{{ $fld }}">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Tagline') }}</span>
                                <input name="site_tagline" value="{{ old('site_tagline', $values['site_tagline']) }}" placeholder="{{ __('Instagram automation, on autopilot') }}" class="{{ $fld }}">
                            </label>
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Footer text') }}</span>
                                <textarea name="footer_text" rows="3" class="{{ $fld }} resize-none" placeholder="{{ __('© :brand. All rights reserved.', ['brand' => brand_name()]) }}">{{ old('footer_text', $values['footer_text']) }}</textarea>
                                <span class="text-[10.5px] text-ink-500">{{ __('Shown at the bottom of every public page. 1000 char max.') }}</span>
                            </label>
                        </div>
                    </section>

                    {{-- Contact --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('contact') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Contact') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Email') }}</span>
                                <input type="email" name="contact_email" value="{{ old('contact_email', $values['contact_email']) }}" placeholder="support@instaflow.test" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Phone') }}</span>
                                <input name="contact_phone" value="{{ old('contact_phone', $values['contact_phone']) }}" placeholder="+1 …" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Address') }}</span>
                                <textarea name="contact_address" rows="3" class="{{ $fld }} resize-none">{{ old('contact_address', $values['contact_address']) }}</textarea>
                            </label>
                        </div>
                    </section>

                    {{-- Social profiles --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('social') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Social profiles') }}</h2>
                            <p class="text-[12px] text-ink-600 mt-1">{{ __('Full profile URLs. Blank links are hidden from the footer.') }}</p>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Facebook') }}</span>
                                <input type="url" name="social_facebook" value="{{ old('social_facebook', $values['social_facebook']) }}" placeholder="https://facebook.com/…" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Instagram') }}</span>
                                <input type="url" name="social_instagram" value="{{ old('social_instagram', $values['social_instagram']) }}" placeholder="https://instagram.com/…" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Twitter / X') }}</span>
                                <input type="url" name="social_twitter" value="{{ old('social_twitter', $values['social_twitter']) }}" placeholder="https://x.com/…" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('YouTube') }}</span>
                                <input type="url" name="social_youtube" value="{{ old('social_youtube', $values['social_youtube']) }}" placeholder="https://youtube.com/@…" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('LinkedIn') }}</span>
                                <input type="url" name="social_linkedin" value="{{ old('social_linkedin', $values['social_linkedin']) }}" placeholder="https://linkedin.com/company/…" class="{{ $fldMono }}">
                            </label>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-50 border border-paper-200 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Edit once, live everywhere') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('These details feed the public footer, contact and about pages. Page copy and colours are edited in the Frontend editor.') }}</p>
                    </div>
                </aside>
            </section>

            <div class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Changes apply only after you save.') }}</span>
                <button type="submit" class="px-5 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
            </div>
        </main>
    </form>

</x-layouts.admin>

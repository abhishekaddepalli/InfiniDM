<x-layouts.admin :title="__('Social login')" admin-key="social">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Social login') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.settings.social.save') }}">
        @csrf
        <main class="px-4 sm:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Authentication') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Social') }}
                        <span class="italic ig-text">{{ __('login') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Let users sign in with Google or Facebook and shield the auth pages with reCAPTCHA. Each provider only appears once you enable it and paste its keys.') }}</p>
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

                    {{-- Google --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('oauth-google') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Google') }}</h2>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer shrink-0">
                                <span class="text-[12px] text-ink-700">{{ __('Enable') }}</span>
                                <span class="relative inline-block w-9 h-5 shrink-0">
                                    <input type="hidden" name="google_login_enabled" value="0">
                                    <input type="checkbox" name="google_login_enabled" value="1" class="peer opacity-0 w-0 h-0" @checked(old('google_login_enabled', $values['google_login_enabled']))>
                                    <span class="absolute inset-0 bg-paper-200 rounded-full transition peer-checked:bg-wa-deep before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:before:translate-x-[16px]"></span>
                                </span>
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Client ID') }}</span>
                                <input name="google_client_id" value="{{ old('google_client_id', $values['google_client_id']) }}" placeholder="000000000000-xxxx.apps.googleusercontent.com" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Client secret') }}</span>
                                <input type="password" name="google_client_secret" autocomplete="new-password" value="" placeholder="{{ $values['google_client_secret_set'] ? __('•••• stored (leave blank to keep)') : 'GOCSPX-xxxxxxxxxxxxxxxx' }}" class="{{ $fldMono }}">
                                <span class="text-[10.5px] text-ink-500">{{ __('Never rendered back. Leave blank to keep the saved one.') }}</span>
                            </label>
                        </div>
                    </section>

                    {{-- Facebook --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('oauth-facebook') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Facebook') }}</h2>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer shrink-0">
                                <span class="text-[12px] text-ink-700">{{ __('Enable') }}</span>
                                <span class="relative inline-block w-9 h-5 shrink-0">
                                    <input type="hidden" name="facebook_login_enabled" value="0">
                                    <input type="checkbox" name="facebook_login_enabled" value="1" class="peer opacity-0 w-0 h-0" @checked(old('facebook_login_enabled', $values['facebook_login_enabled']))>
                                    <span class="absolute inset-0 bg-paper-200 rounded-full transition peer-checked:bg-wa-deep before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:before:translate-x-[16px]"></span>
                                </span>
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('App ID') }}</span>
                                <input name="facebook_client_id" value="{{ old('facebook_client_id', $values['facebook_client_id']) }}" placeholder="0000000000000000" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('App secret') }}</span>
                                <input type="password" name="facebook_client_secret" autocomplete="new-password" value="" placeholder="{{ $values['facebook_client_secret_set'] ? __('•••• stored (leave blank to keep)') : __('32-character app secret') }}" class="{{ $fldMono }}">
                                <span class="text-[10.5px] text-ink-500">{{ __('Never rendered back. Leave blank to keep the saved one.') }}</span>
                            </label>
                        </div>
                    </section>

                    {{-- reCAPTCHA --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('bot-protection') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('reCAPTCHA') }}</h2>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer shrink-0">
                                <span class="text-[12px] text-ink-700">{{ __('Enable') }}</span>
                                <span class="relative inline-block w-9 h-5 shrink-0">
                                    <input type="hidden" name="recaptcha_enabled" value="0">
                                    <input type="checkbox" name="recaptcha_enabled" value="1" class="peer opacity-0 w-0 h-0" @checked(old('recaptcha_enabled', $values['recaptcha_enabled']))>
                                    <span class="absolute inset-0 bg-paper-200 rounded-full transition peer-checked:bg-wa-deep before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:before:translate-x-[16px]"></span>
                                </span>
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Version') }}</span>
                                <select name="recaptcha_version" class="{{ $fld }}">
                                    <option value="v2" @selected(old('recaptcha_version', $values['recaptcha_version']) === 'v2')>{{ __('v2 — checkbox') }}</option>
                                    <option value="v3" @selected(old('recaptcha_version', $values['recaptcha_version']) === 'v3')>{{ __('v3 — invisible score') }}</option>
                                </select>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Site key') }}</span>
                                <input name="recaptcha_site_key" value="{{ old('recaptcha_site_key', $values['recaptcha_site_key']) }}" placeholder="6Lxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Secret key') }}</span>
                                <input type="password" name="recaptcha_secret_key" autocomplete="new-password" value="" placeholder="{{ $values['recaptcha_secret_key_set'] ? __('•••• stored (leave blank to keep)') : '6Lxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' }}" class="{{ $fldMono }}">
                                <span class="text-[10.5px] text-ink-500">{{ __('Server key used to verify tokens. Never rendered back. Leave blank to keep the saved one.') }}</span>
                            </label>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-50 border border-paper-200 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('How it appears') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('A provider only shows on the login & register pages once it is enabled and both keys are saved. Secrets are stored server-side and never sent back to this form.') }}</p>
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

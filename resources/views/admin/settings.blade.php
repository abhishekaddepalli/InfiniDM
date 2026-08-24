<x-layouts.admin :title="__('General settings')" admin-key="settings">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('General') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.settings.save') }}" enctype="multipart/form-data">
        @csrf

        <main class="px-4 sm:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('General') }}
                        <span class="italic ig-text">{{ __('settings') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Core application identity, contact information, default locale and currency, and platform-level service switches.') }}</p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <button type="submit" class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ __('Save changes') }}</button>
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

                    {{-- Application identity --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('general-setting') }}</div>
                                <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('Application identity') }}</h2>
                            </div>
                            <span class="rounded-full bg-wa-mint text-wa-deep border border-wa-green/40 px-2.5 py-1 text-[11px] font-mono">{{ __('healthy') }}</span>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('App name') }} <span class="text-accent-coral">*</span></span>
                                <input name="site_name" value="{{ old('site_name', $settings['site_name']) }}" required class="{{ $fld }}" placeholder="IgDesk">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Tagline') }}</span>
                                <input name="site_tagline" value="{{ old('site_tagline', $settings['site_tagline']) }}" class="{{ $fld }}" placeholder="{{ __('Instagram automation, on autopilot') }}">
                            </label>
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Platform message footer') }}</span>
                                <input name="platform_footer" maxlength="80" value="{{ old('platform_footer', $settings['platform_footer']) }}" placeholder="{{ __('Sent via') }} {{ $settings['site_name'] ?: 'IgDesk' }}" class="{{ $fld }}">
                                <span class="text-[10.5px] text-ink-500">{{ __('Appended to outbound automated messages. 80 char max.') }}</span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('App URL') }}</span>
                                <input name="app_url" value="{{ old('app_url', $settings['app_url']) }}" placeholder="https://app.instaflow.test" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Support email') }}</span>
                                <input type="email" name="support_email" value="{{ old('support_email', $settings['support_email']) }}" placeholder="support@…" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Contact number') }}</span>
                                <input name="contact_number" value="{{ old('contact_number', $settings['contact_number']) }}" placeholder="+1 …" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('From email (outbound)') }}</span>
                                <input type="email" name="from_email" value="{{ old('from_email', $settings['from_email']) }}" placeholder="hello@…" class="{{ $fldMono }}">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Default timezone') }}</span>
                                <select name="default_timezone" class="{{ $fldMono }}">
                                    @foreach ($timezones as $tz)
                                        <option value="{{ $tz }}" @selected(old('default_timezone', $settings['default_timezone']) === $tz)>{{ $tz }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Default language') }}</span>
                                <select name="default_language" class="{{ $fldMono }}">
                                    @foreach ($languages as $l)
                                        <option value="{{ $l->code }}" @selected(old('default_language', $settings['default_language']) === $l->code)>{{ $l->name }} ({{ $l->code }})</option>
                                    @endforeach
                                </select>
                                <a href="{{ route('admin.languages.index') }}" class="text-[10.5px] ig-text font-semibold">{{ __('Manage languages →') }}</a>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Default currency') }}</span>
                                <select name="default_currency" class="{{ $fldMono }}">
                                    @foreach ($currencies as $c)
                                        <option value="{{ $c->code }}" @selected(old('default_currency', $settings['default_currency']) === $c->code)>{{ $c->code }} — {{ $c->name }}</option>
                                    @endforeach
                                </select>
                                <a href="{{ route('admin.currencies.index') }}" class="text-[10.5px] ig-text font-semibold">{{ __('Manage currencies →') }}</a>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Default country (phone pickers)') }}</span>
                                @php
                                    $countryOpts = [
                                        ['code' => '+1', 'iso' => 'us', 'label' => 'United States (+1)'],
                                        ['code' => '+44', 'iso' => 'gb', 'label' => 'United Kingdom (+44)'],
                                        ['code' => '+91', 'iso' => 'in', 'label' => 'India (+91)'],
                                        ['code' => '+62', 'iso' => 'id', 'label' => 'Indonesia (+62)'],
                                        ['code' => '+61', 'iso' => 'au', 'label' => 'Australia (+61)'],
                                        ['code' => '+55', 'iso' => 'br', 'label' => 'Brazil (+55)'],
                                        ['code' => '+971', 'iso' => 'ae', 'label' => 'UAE (+971)'],
                                        ['code' => '+234', 'iso' => 'ng', 'label' => 'Nigeria (+234)'],
                                        ['code' => '+63', 'iso' => 'ph', 'label' => 'Philippines (+63)'],
                                        ['code' => '+92', 'iso' => 'pk', 'label' => 'Pakistan (+92)'],
                                    ];
                                    $currentKey = ($settings['default_country_code'] ?: '+1') . '|' . ($settings['default_country_iso'] ?: 'us');
                                @endphp
                                <select onchange="(function(s){var p=s.value.split('|');document.getElementById('def-cc').value=p[0]||'+1';document.getElementById('def-iso').value=p[1]||'us';})(this)" class="{{ $fld }}">
                                    @foreach ($countryOpts as $opt)
                                        @php $key = $opt['code'] . '|' . $opt['iso']; @endphp
                                        <option value="{{ $key }}" @selected($currentKey === $key)>{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                <input id="def-cc" type="hidden" name="default_country_code" value="{{ $settings['default_country_code'] ?: '+1' }}">
                                <input id="def-iso" type="hidden" name="default_country_iso" value="{{ $settings['default_country_iso'] ?: 'us' }}">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Font family') }}</span>
                                <select name="font_family" class="{{ $fld }}">
                                    <option value="" @selected(($settings['font_family'] ?? '') === '')>{{ __('Theme default') }}</option>
                                    @foreach ($fonts as $fkey => $f)
                                        <option value="{{ $fkey }}" @selected(old('font_family', $settings['font_family']) === $fkey) style="font-family: {{ $f['stack'] }}">{{ $f['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Address') }}</span>
                                <textarea name="address" rows="3" class="{{ $fld }} resize-none">{{ old('address', $settings['address']) }}</textarea>
                            </label>
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Map iframe URL') }}</span>
                                <input name="map_iframe_url" value="{{ old('map_iframe_url', $settings['map_iframe_url']) }}" placeholder="https://www.google.com/maps/embed?pb=..." class="{{ $fldMono }}">
                            </label>
                        </div>
                    </section>

                    {{-- Brand assets --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('brand-assets') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Brand assets') }}</h2>
                            <p class="text-[12px] text-ink-600 mt-1">{{ __('Favicon is shared. Logo is per-theme — upload a light and a dark variant.') }}</p>
                        </div>
                        <div class="p-5 space-y-5">
                            {{-- Favicon --}}
                            <div class="grid grid-cols-[120px_1fr] gap-4 items-center">
                                <div class="rounded-2xl border border-paper-200 bg-paper-50 h-[120px] grid place-items-center overflow-hidden">
                                    <img src="{{ brand_asset($settings['brand_favicon']) }}" alt="" id="prev-favicon"
                                         class="max-h-16 max-w-16 object-contain {{ $settings['brand_favicon'] ? '' : 'hidden' }}">
                                    <span class="text-[10px] font-mono text-ink-500 uppercase tracking-[0.14em] {{ $settings['brand_favicon'] ? 'hidden' : '' }}" data-empty-for="prev-favicon">{{ __('ico / png') }}</span>
                                </div>
                                <div>
                                    <div class="font-semibold text-[13px]">{{ __('Favicon') }}</div>
                                    <p class="text-[11.5px] text-ink-600 mt-0.5">{{ __('Shown in browser tabs + bookmarks. Recommended') }} <span class="font-mono">35×35 px</span>.</p>
                                    <input type="file" name="favicon" accept=".png,.ico,.jpg,.jpeg,.svg,.webp" data-logo-preview="prev-favicon"
                                        class="mt-2 block w-full text-[12px] file:mr-3 file:px-3 file:py-1.5 file:rounded-full file:border-0 file:bg-wa-mint file:text-wa-deep file:text-[11.5px] file:font-medium file:cursor-pointer">
                                </div>
                            </div>

                            {{-- Per-theme logos --}}
                            <div>
                                <div class="font-semibold text-[13px] mb-1">{{ __('Logo per theme') }}</div>
                                <div class="grid grid-cols-2 gap-3">
                                    @php $logoMap = ['paper' => $settings['site_logo'], 'dark' => $settings['logo_dark']]; @endphp
                                    @foreach ($brandThemes as $t)
                                        @php $cur = $logoMap[$t['id']] ?? ''; $fieldName = $t['id'] === 'dark' ? 'logo_dark' : 'logo'; @endphp
                                        <div class="rounded-2xl border border-paper-200 p-3 bg-paper-0">
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="font-semibold text-[12.5px]">{{ $t['label'] }}</div>
                                                <span class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">{{ $t['id'] }}</span>
                                            </div>
                                            <div class="rounded-xl border border-paper-200 {{ $t['id'] === 'dark' ? 'bg-[#121212]' : 'bg-paper-50' }} h-20 grid place-items-center overflow-hidden mb-2">
                                                <img src="{{ brand_asset($cur) }}" alt="" id="prev-logo-{{ $t['id'] }}"
                                                     class="max-h-14 max-w-[140px] object-contain {{ $cur ? '' : 'hidden' }}">
                                                <span class="text-[10px] font-mono {{ $t['id'] === 'dark' ? 'text-paper-300' : 'text-ink-500' }} uppercase tracking-[0.14em] {{ $cur ? 'hidden' : '' }}" data-empty-for="prev-logo-{{ $t['id'] }}">{{ __('no logo') }}</span>
                                            </div>
                                            <input type="file" name="{{ $fieldName }}" accept=".png,.jpg,.jpeg,.svg,.webp" data-logo-preview="prev-logo-{{ $t['id'] }}"
                                                class="block w-full text-[11px] file:mr-2 file:px-2.5 file:py-1 file:rounded-full file:border-0 file:bg-paper-100 file:text-ink-700 file:text-[10.5px] file:font-medium hover:file:bg-paper-200 file:cursor-pointer">
                                            <p class="text-[10.5px] text-ink-500 mt-1.5">{{ $t['note'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- Platform toggles --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('platform-toggles') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Platform toggles') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @php $switches = [
                                ['preloader', __('Preloader'), __('Show the loading splash on every page')],
                                ['maintenance_mode', __('Maintenance mode'), __('All user pages show "be right back"')],
                                ['allow_registration', __('Public registration'), __('Anyone can sign up at /register')],
                                ['auth_show_logo', __('Logo on login pages'), __('Show your logo, centered, on sign-in / register / reset')],
                            ]; @endphp
                            @foreach ($switches as [$name, $label, $hint])
                                <label class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3 cursor-pointer">
                                    <span>
                                        <span class="block text-[12.5px] font-semibold">{{ $label }}</span>
                                        <span class="block text-[10.5px] text-ink-500 mt-0.5">{{ $hint }}</span>
                                    </span>
                                    <span class="relative inline-block w-9 h-5 shrink-0">
                                        <input type="hidden" name="{{ $name }}" value="0">
                                        <input type="checkbox" name="{{ $name }}" value="1" class="peer opacity-0 w-0 h-0" @checked(old($name, $settings[$name]))>
                                        <span class="absolute inset-0 bg-paper-200 rounded-full transition peer-checked:bg-wa-deep before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:before:translate-x-[16px]"></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    {{-- Signups & free trial --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('signups-and-trial') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Signups & free trial') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Default plan for new signups') }}</span>
                                <select name="registration_default_plan_id" class="{{ $fld }}">
                                    <option value="">{{ __('Auto — first active free plan') }}</option>
                                    @foreach ($signupPlans as $p)
                                        <option value="{{ $p->id }}" @selected((string) old('registration_default_plan_id', $settings['registration_default_plan_id']) === (string) $p->id)>
                                            {{ $p->name }} — {{ (float) $p->price <= 0 ? __('Free') : __('Paid plan') }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="block text-[10.5px] text-ink-500">{{ __('Assigned automatically when a new account finishes registration.') }} <a href="{{ route('admin.packages.index') }}" class="ig-text font-semibold">{{ __('Manage plans →') }}</a></span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Free trial length (days)') }}</span>
                                <input name="registration_trial_days" type="number" min="0" max="365" value="{{ old('registration_trial_days', $settings['registration_trial_days']) }}" class="{{ $fldMono }}">
                                <span class="block text-[10.5px] text-ink-500">{{ __('Only applies when the default plan is free.') }} <code class="font-mono text-[11px]">0</code> = {{ __('no expiry.') }}</span>
                            </label>
                        </div>
                    </section>

                    {{-- Meta / Instagram app + Node engine moved to Admin → Settings → Instagram. --}}

                </div>

                {{-- Aside --}}
                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Quick guide') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Where things go') }}</h3>
                        </div>
                        <div class="p-4 space-y-3 text-[12px] text-ink-700">
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('App name') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Used in page titles, emails, and the in-app brand. Keep short.') }}</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('App URL') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('The canonical address. All callbacks, emails and OAuth redirects derive from this.') }}</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Meta app') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Connects Instagram accounts. Without it, no account can link.') }}</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Maintenance mode') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('When ON every non-admin route shows a placeholder. Admin can still reach') }} <code class="font-mono text-[11px]">/admin</code>.</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-paper-50 border border-paper-200 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Production rule') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('Save only after testing the affected user flow. Identity changes propagate to invoices and outbound emails.') }}</p>
                    </div>
                </aside>

            </section>

            {{-- Sticky save bar --}}
            <div class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Changes apply only after you save.') }}</span>
                <div class="flex items-center gap-2">
                    <button type="submit" class="px-5 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ __('Save changes') }}</button>
                </div>
            </div>

        </main>

    </form>

    @push('scripts')
        <script src="{{ asset('assets/admin-logo-preview.js') }}" defer></script>
    @endpush

</x-layouts.admin>

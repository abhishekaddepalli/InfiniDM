@php
    $isEdit = $package->exists;
    $title  = $isEdit ? 'Edit package' : 'Create package';
    $action = $isEdit ? route('admin.packages.update', $package->id) : route('admin.packages.store');

    $val = function ($field, $default = null) use ($package) {
        return old($field, $package->{$field} ?? $default);
    };
    $activeFeatures = (array) old('features', $package->features ?? []);

    $limitGroups = [
        'Usage caps' => [
            'max_accounts'    => 'Instagram accounts',
            'max_flows'       => 'Flows',
            'max_automations' => 'Automations',
        ],
        'Volume & team' => [
            'monthly_dms' => 'DMs per month',
            'team_seats'  => 'Team seats',
        ],
    ];
    $currencyOpts = ['USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound', 'INR' => 'Indian Rupee', 'AUD' => 'Australian Dollar', 'CAD' => 'Canadian Dollar', 'BRL' => 'Brazilian Real', 'AED' => 'UAE Dirham'];
    $curNow = strtoupper((string) $val('currency', 'USD'));
    if (! array_key_exists($curNow, $currencyOpts)) { $currencyOpts = [$curNow => $curNow] + $currencyOpts; }
@endphp

<x-layouts.admin :title="$title" admin-key="packages" page="admin-packages-create">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <button type="button" onclick="window.__adminToggleSidebar(true)"
            class="md:hidden -ml-1 w-9 h-9 rounded-lg grid place-items-center text-ink-600 hover:bg-paper-50">
            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4h12M2 8h12M2 12h12"/></svg>
        </button>
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ route('admin.packages.index') }}" class="hover:text-ink-900">{{ __('Packages') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $isEdit ? 'Edit' : 'New' }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2 flex-wrap justify-end">
            <span class="font-mono text-[11px] text-ink-500 mr-2">{{ __('Step') }} <span id="cur-step">1</span> / 4</span>
            <a href="{{ route('admin.packages.index') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="button" id="prevBtn" disabled
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium disabled:opacity-40 disabled:cursor-not-allowed">{{ __('Back') }}</button>
            <button type="button" id="nextBtn"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                {{ __('Next') }}
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4l4 4-4 4" /></svg>
            </button>
            <button type="submit" form="pkgForm" id="submitBtn"
                class="hidden px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l5 5 7-9" /></svg>
                {{ $isEdit ? __('Save changes') : __('Create package') }}
            </button>
        </div>
    </header>

    <div class="px-4 sm:px-7 pt-7 pb-2">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Packages') }} · {{ $isEdit ? __('Edit') : __('New') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[36px] leading-[1.0]">
                {{ $isEdit ? __('Edit') : __('Create a') }} <span class="italic ig-text">{{ __('package') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                {{ __("Set pricing, numeric limits, feature toggles, and display options. Empty limit field = unlimited. Unchecked toggle = customers on this plan can't use that feature.") }}
            </p>
        </div>
    </div>

    <main class="px-4 sm:px-7 pb-7">
        @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                <div class="font-semibold mb-1">{{ __('Please fix the following:') }}</div>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="pkgForm" method="POST" action="{{ $action }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">

                {{-- Stepper bar --}}
                <div class="px-5 py-4 border-b border-paper-200 bg-paper-50/40 overflow-x-auto">
                    <div class="flex items-center min-w-[520px]" id="stepper">
                        @php $steps = ['Basics', 'Limits', 'Features', 'Display & review']; @endphp
                        @foreach ($steps as $i => $label)
                            <div class="step-node flex items-center gap-2.5 {{ $loop->last ? '' : 'flex-1' }} cursor-pointer" data-n="{{ $loop->iteration }}">
                                <span class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 {{ $loop->first ? 'border-wa-deep text-wa-deep ring-4 ring-wa-deep/10' : 'border-paper-200 text-ink-500' }}">{{ $loop->iteration }}</span>
                                <span class="lab text-[11.5px] {{ $loop->first ? 'font-semibold text-wa-deep' : 'font-medium text-ink-500' }} whitespace-nowrap">{{ $label }}</span>
                                @if (!$loop->last)
                                    <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="p-5">

                    {{-- STEP 1 — Basics --}}
                    <div class="step-pane" data-step="1">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                            <span class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Plan basics') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Package name') }} <span class="text-accent-coral">*</span></label>
                                <input type="text" name="name" value="{{ $val('name') }}" required maxlength="120"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('Starter / Growth / Pro') }}">
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Description (shown on pricing page)') }}</label>
                                <input type="text" name="description" value="{{ $val('description') }}" maxlength="191"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('For growing creators') }}">
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Price') }} <span class="text-accent-coral">*</span></label>
                                <input type="number" name="price" value="{{ $val('price', 0) }}" required step="0.01" min="0"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('0 = free plan.') }}</div>
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Currency') }} <span class="text-accent-coral">*</span></label>
                                <select name="currency" required
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    @foreach ($currencyOpts as $code => $cname)
                                        <option value="{{ $code }}" @selected($curNow === $code)>{{ $code }} — {{ $cname }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Billing interval') }} <span class="text-accent-coral">*</span></label>
                                <select name="interval"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    @foreach (['month' => __('Monthly'), 'year' => __('Yearly'), 'lifetime' => __('Lifetime')] as $k => $lbl)
                                        <option value="{{ $k }}" @selected($val('interval', 'month') === $k)>{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Free trial (days)') }}</label>
                                <input type="number" name="trial_days" value="{{ $val('trial_days', 0) }}" min="0" max="365"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="0">
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('0 = no trial.') }}</div>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @php $basicsFlags = [
                                'is_active'   => ['Active', 'Visible on the public pricing page'],
                                'is_default'  => ['Default for signups', 'New accounts get this plan automatically'],
                                'is_featured' => ['Highlight as popular', 'Adds a "Featured" badge'],
                            ]; @endphp
                            @foreach ($basicsFlags as $name => $meta)
                                <label class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                                    <span>
                                        <span class="block text-[12.5px] font-semibold">{{ $meta[0] }}</span>
                                        <span class="block text-[10.5px] text-ink-500">{{ $meta[1] }}</span>
                                    </span>
                                    <input type="hidden" name="{{ $name }}" value="0">
                                    <span class="relative inline-block w-[34px] h-5 shrink-0">
                                        <input class="peer opacity-0 w-0 h-0" type="checkbox" name="{{ $name }}" value="1" @checked($val($name, $name === 'is_active'))>
                                        <span class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- STEP 2 — Limits --}}
                    <div class="step-pane hidden" data-step="2">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                            <span class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Numeric limits') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('leave blank = unlimited') }}</span>
                        </div>
                        @foreach ($limitGroups as $groupName => $cols)
                            <div class="mb-5">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ $groupName }}</div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    @foreach ($cols as $col => $label)
                                        <label class="block">
                                            <span class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ $label }}</span>
                                            <input type="number" name="{{ $col }}" value="{{ $val($col) ?: '' }}" min="0" placeholder="∞ unlimited"
                                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                            <span class="block text-[9.5px] text-ink-500 mt-0.5 font-mono">{{ $col }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- STEP 3 — Features --}}
                    <div class="step-pane hidden" data-step="3">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Feature toggles') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('unchecked = blocked') }}</span>
                        </div>
                        <div class="mb-5">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Instagram features') }}</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach (\App\Models\Package::FEATURES as $key => $label)
                                    <label class="flex items-center gap-3 px-3 py-2 border border-paper-200 rounded-lg cursor-pointer hover:bg-paper-50">
                                        <span class="relative inline-block w-[30px] h-[18px] shrink-0">
                                            <input class="peer opacity-0 w-0 h-0" type="checkbox" name="features[]" value="{{ $key }}" @checked(in_array($key, $activeFeatures, true))>
                                            <span class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-3.5 before:w-3.5 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[12px]"></span>
                                        </span>
                                        <span class="flex-1 min-w-0">
                                            <span class="block text-[12.5px] font-semibold truncate">{{ __($label) }}</span>
                                            <span class="block text-[9.5px] text-ink-500 font-mono truncate">{{ $key }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- STEP 4 — Display + Review --}}
                    <div class="step-pane hidden" data-step="4">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                            <span class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Display & review') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('final step') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Sort order') }}</label>
                                <input type="number" name="sort" value="{{ $val('sort', 0) }}" min="0"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Lower number = shown first on pricing page.') }}</div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-paper-200 bg-paper-50/50 p-5">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">{{ __('Review') }}</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[12.5px]" id="pkg-review"></div>
                        </div>
                    </div>

                </div>
            </div>
        </form>
    </main>

</x-layouts.admin>

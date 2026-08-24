{{--
    /account — the signed-in customer's tabbed account page.

    Ported from WaDesk's user/account/index.blade.php and trimmed to what
    IgDesk has: Profile, Plan & billing, Invoices, and Change password.
    There is no Workspace here — the plan + orders hang off the user. Billing
    is one-time only, so no subscription-cancel / auto-renew UI.

    Tabs switch client-side via a tiny inline script (deep-linked with ?tab=),
    so the page works with the pre-built extension assets and no npm build.
--}}
<x-layouts.instagram :title="__('Account')" ig-active="account">

    @php
        $u = $authUser;
        $initials = \Illuminate\Support\Str::of($u->name ?? 'U')->trim()->limit(2, '')->upper();
        $avatarUrl = $u->avatar
            ? (\Illuminate\Support\Str::startsWith($u->avatar, ['http://', 'https://'])
                ? $u->avatar
                : asset('storage/' . ltrim($u->avatar, '/')))
            : null;

        $planName = $package?->name ?: __('Free');

        // Which tab opens first: an explicit ?tab= wins; otherwise land on the
        // pane whose form failed validation so the errors are visible.
        $initialTab = request('tab');
        if (! in_array($initialTab, ['profile', 'plan', 'invoices', 'ai-keys', 'security'], true)) {
            $initialTab = ($errors->has('current_password') || $errors->has('password')) ? 'security' : 'profile';
        }

        $genders = ['m' => __('Male'), 'f' => __('Female'), 'o' => __('Other')];
    @endphp

    <div class="px-4 sm:px-6 lg:px-7 py-7" style="width:100%;max-width:100%;">
        <div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-start;">

            {{-- ───────────── LEFT: identity card + tab nav ───────────── --}}
            {{-- Inline flex sizing: IgDesk ships PRE-BUILT CSS (no live Tailwind
                 compile), so arbitrary width utilities like lg:w-64 aren't in the
                 bundle and collapse the layout. Inline styles are build-proof. --}}
            <aside class="space-y-3 self-start" style="flex:0 0 260px;max-width:100%;">
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card text-center">
                    <span class="relative overflow-hidden w-20 h-20 rounded-full ig-grad-soft text-white text-[28px] font-semibold grid place-items-center mx-auto">
                        <span>{{ $initials }}</span>
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="" class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">
                        @endif
                    </span>
                    <div class="font-serif text-[18px] mt-2">{{ $u->name }}</div>
                    <div class="font-mono text-[10.5px] text-ink-500 break-all">{{ $u->email }}</div>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono bg-wa-mint text-wa-deep mt-2">
                        <span class="w-1.5 h-1.5 rounded-full ig-grad"></span>{{ $planName }} {{ __('plan') }}
                    </span>
                </div>

                <nav class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card space-y-0.5">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">{{ __('Account') }}</div>

                    <button type="button" data-acc-tab="profile" class="acc-tab w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px] text-left transition text-ink-700 hover:bg-paper-50">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="6" r="3"/><path d="M2 14c0-3 2.5-5 6-5s6 2 6 5"/></svg>{{ __('Profile') }}
                    </button>
                    <button type="button" data-acc-tab="plan" class="acc-tab w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px] text-left transition text-ink-700 hover:bg-paper-50">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 8l6-5 6 5M3.5 7v6h9V7"/><path d="M6.5 13V9.5h3V13"/></svg>{{ __('Plan & billing') }}
                    </button>
                    <button type="button" data-acc-tab="invoices" class="acc-tab w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px] text-left transition text-ink-700 hover:bg-paper-50">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 2h6l2 2v10H4z"/><path d="M6 6h4M6 8.5h4M6 11h2.5"/></svg>{{ __('Invoices') }}
                    </button>
                    <button type="button" data-acc-tab="ai-keys" class="acc-tab w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px] text-left transition text-ink-700 hover:bg-paper-50">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="6" cy="10" r="3"/><path d="M8.2 8.2 14 2.4M11 2h3v3"/></svg>{{ __('AI keys') }}
                    </button>

                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-3 pb-1.5">{{ __('Security') }}</div>
                    <button type="button" data-acc-tab="security" class="acc-tab w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px] text-left transition text-ink-700 hover:bg-paper-50">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="7" width="10" height="7" rx="1.5"/><path d="M5 7V5a3 3 0 0 1 6 0v2"/></svg>{{ __('Change password') }}
                    </button>
                </nav>

                <a href="{{ route('instagram.plans') }}" class="block border border-paper-200 bg-wa-mint/40 rounded-2xl p-4 hover:border-wa-deep transition">
                    <div class="font-serif text-[14px] leading-tight text-wa-deep">{{ __('Need more room?') }}</div>
                    <p class="text-[11px] text-ink-700 mt-1 leading-snug">{{ __('Upgrade any time as your automations and audience grow.') }}</p>
                    <span class="inline-flex items-center gap-1 mt-2 text-[11px] font-semibold text-wa-deep">{{ __('See plans') }} &rarr;</span>
                </a>
            </aside>

            {{-- ───────────── RIGHT: hero + panes ───────────── --}}
            <section class="space-y-5" style="flex:1 1 380px;min-width:0;">

                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Account') }} <span class="mx-1 text-ink-400">/</span> <span id="acc-bc">{{ __('Profile') }}</span>
                    </div>
                    <h1 class="font-serif font-normal tracking-tight text-[30px] sm:text-[38px] leading-none">
                        {{ __('Your') }} <span class="ig-text italic">{{ __('account') }}</span>
                    </h1>
                </div>

                {{-- ========= PROFILE ========= --}}
                <div data-acc-pane="profile" class="space-y-5 hidden">
                    <form method="POST" action="{{ route('account.profile.update') }}" enctype="multipart/form-data"
                        class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        @csrf
                        @method('PATCH')
                        <h3 class="font-serif text-[20px] mb-4">{{ __('Personal info') }}</h3>

                        @if (session('status'))
                            <div class="mb-4 rounded-lg border border-wa-deep/30 bg-wa-mint px-3 py-2 text-[12px] text-wa-deep">{{ session('status') }}</div>
                        @endif
                        @if ($errors->any())
                            <div class="mb-4 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-accent-coral">
                                @foreach ($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                            </div>
                        @endif

                        {{-- Avatar --}}
                        <div class="flex items-center gap-4 mb-6">
                            <span id="acc-avatar" class="relative overflow-hidden w-16 h-16 rounded-full ig-grad-soft text-white text-[22px] font-semibold grid place-items-center shrink-0">
                                <span>{{ $initials }}</span>
                                @if ($avatarUrl)
                                    <img id="acc-avatar-img" src="{{ $avatarUrl }}" alt="" class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">
                                @endif
                            </span>
                            <div>
                                <label for="acc-avatar-input" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium cursor-pointer">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M11 2l3 3-8 8H3v-3l8-8z"/></svg>{{ __('Change photo') }}
                                </label>
                                <input id="acc-avatar-input" name="avatar" type="file" accept="image/*" class="hidden" />
                                <div class="text-[10.5px] text-ink-500 mt-1.5">{{ __('JPG, PNG or WEBP. Max 2 MB.') }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="acc-name">{{ __('Full name') }}</label>
                                <input id="acc-name" name="name" type="text" required maxlength="120" value="{{ old('name', $u->name) }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="acc-email">{{ __('Email') }}</label>
                                <input id="acc-email" name="email" type="email" required maxlength="160" value="{{ old('email', $u->email) }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="acc-mobile">{{ __('Phone') }}</label>
                                <input id="acc-mobile" name="mobile" type="tel" maxlength="40" value="{{ old('mobile', $u->mobile) }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="acc-gender">{{ __('Gender') }}</label>
                                <select id="acc-gender" name="gender"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                    <option value="">{{ __('Prefer not to say') }}</option>
                                    @foreach ($genders as $val => $label)
                                        <option value="{{ $val }}" @selected(old('gender', $u->gender) === $val)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="acc-address">{{ __('Address') }}</label>
                                <textarea id="acc-address" name="address" rows="2" maxlength="1000"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] resize-none focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">{{ old('address', $u->address) }}</textarea>
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="acc-country">{{ __('Country') }}</label>
                                <input id="acc-country" name="country" type="text" maxlength="80" value="{{ old('country', $u->country) }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="acc-state">{{ __('State / Region') }}</label>
                                <input id="acc-state" name="state" type="text" maxlength="80" value="{{ old('state', $u->state) }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="acc-city">{{ __('City') }}</label>
                                <input id="acc-city" name="city" type="text" maxlength="80" value="{{ old('city', $u->city) }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="acc-zip">{{ __('ZIP / Postal code') }}</label>
                                <input id="acc-zip" name="zip" type="text" maxlength="20" value="{{ old('zip', $u->zip) }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <div class="lg:col-span-2">
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="acc-notes">{{ __('Notes') }}</label>
                                <textarea id="acc-notes" name="notes" rows="2" maxlength="2000"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] resize-none focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">{{ old('notes', $u->notes) }}</textarea>
                            </div>
                        </div>

                        <div class="mt-5 pt-4 border-t border-paper-200 flex items-center justify-end gap-2">
                            <button type="reset" class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Reset') }}</button>
                            <button type="submit" class="px-4 py-2 rounded-full ig-grad text-white text-[12px] font-semibold hover:opacity-95 transition">{{ __('Save changes') }}</button>
                        </div>
                    </form>
                </div>

                {{-- ========= PLAN & BILLING ========= --}}
                <div data-acc-pane="plan" class="space-y-5 hidden">
                    @php
                        $isFree = ! $package || $package->free || $package->chargeableAmount() <= 0;
                        $endsAt = $u->plan_ends_at;
                        $expired = $endsAt && $endsAt->isPast();
                        $onTrial = $u->trial_ends_at && $u->trial_ends_at->isFuture();
                        $priceStr = $isFree ? __('Free') : \App\Support\FormatSettings::display($package->chargeableAmount(), $package->currency);
                        $unlocked = []; $locked = [];
                        foreach (\App\Models\Package::FEATURES as $fk => $flabel) {
                            if ($package && $package->hasFeature($fk)) { $unlocked[] = $flabel; } else { $locked[] = $flabel; }
                        }
                        $ftotal = count(\App\Models\Package::FEATURES);
                        $orderCol = collect($orders ?? []);
                    @endphp

                    {{-- Gradient hero — WaDesk plan-usage design --}}
                    <div class="rounded-2xl ig-grad text-white p-6 shadow-card">
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-white/70">{{ __('Current plan') }}</div>
                                <h2 class="font-serif text-[34px] leading-none mt-1">{{ $planName }}</h2>
                                <div class="mt-2 text-[12.5px] text-white/80">
                                    @if ($onTrial){{ __('Free trial until :date', ['date' => $u->trial_ends_at->format('M j, Y')]) }}
                                    @elseif ($expired){{ __('Expired on :date', ['date' => $endsAt->format('M j, Y')]) }}
                                    @elseif ($endsAt){{ __('Active until :date', ['date' => $endsAt->format('M j, Y')]) }}
                                    @else{{ __('No expiry') }}@endif
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-serif text-[30px] leading-none">{!! $priceStr !!}</div>
                                @unless ($isFree)<div class="text-[11px] font-mono text-white/70 mt-1">{{ $package->periodLabel() }}</div>@endunless
                                <a href="{{ route('instagram.plans') }}" class="inline-block mt-3 px-3.5 py-2 rounded-full bg-paper-0 text-[12px] font-semibold hover:opacity-90 transition" style="color:#c026a3;">{{ __('Upgrade plan') }}</a>
                            </div>
                        </div>
                        <div class="mt-5 pt-4" style="border-top:1px solid rgba(255,255,255,0.22);">
                            <div class="flex items-center justify-between text-[12px] mb-1.5">
                                <span class="text-white/80">{{ __('Features unlocked') }}</span>
                                <span class="font-mono">{{ count($unlocked) }} / {{ $ftotal }}</span>
                            </div>
                            <div class="rounded-full overflow-hidden" style="height:8px;background:rgba(255,255,255,0.25);">
                                <div class="h-full rounded-full" style="width:{{ $ftotal ? max(4, (int) round(count($unlocked) / $ftotal * 100)) : 4 }}%;background:#ffffff;"></div>
                            </div>
                        </div>
                    </div>

                    {{-- What {plan} includes --}}
                    <div class="rounded-2xl bg-paper-0 border border-paper-200 shadow-card overflow-hidden">
                        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-paper-200">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Features') }}</div>
                                <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('What :plan includes', ['plan' => $planName]) }}</h3>
                            </div>
                            <span class="shrink-0 text-[11.5px] font-mono px-2.5 py-1 rounded-full bg-wa-mint text-wa-deep">{{ count($unlocked) }}/{{ $ftotal }}</span>
                        </div>
                        <div class="p-5" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:0.6rem;">
                            @foreach ($unlocked as $label)
                                <div class="flex items-center gap-2 text-[12.5px] text-ink-800">
                                    <span class="w-5 h-5 rounded-full grid place-items-center shrink-0 bg-wa-mint text-wa-deep"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M3.5 8.5l3 3 6-7"/></svg></span>{{ $label }}
                                </div>
                            @endforeach
                            @foreach ($locked as $label)
                                <div class="flex items-center gap-2 text-[12.5px] text-ink-400">
                                    <span class="w-5 h-5 rounded-full grid place-items-center shrink-0 bg-paper-100 text-ink-400"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3.5" y="7" width="9" height="6" rx="1.3"/><path d="M5.5 7V5.3a2.5 2.5 0 0 1 5 0V7"/></svg></span>{{ $label }}
                                </div>
                            @endforeach
                        </div>
                        @if (count($locked))
                            <div class="px-5 py-3 border-t border-paper-200 flex items-center justify-between gap-3 flex-wrap bg-paper-50">
                                <span class="text-[12px] text-ink-600">{{ __(':n more features unlock on a higher plan.', ['n' => count($locked)]) }}</span>
                                <a href="{{ route('instagram.plans') }}" class="text-[11.5px] font-semibold ig-text hover:underline">{{ __('See plans') }} &rarr;</a>
                            </div>
                        @endif
                    </div>

                    {{-- Recent purchases --}}
                    @if ($orderCol->isNotEmpty())
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                            <div class="flex items-center justify-between px-5 py-3 border-b border-paper-200">
                                <div class="font-serif text-[15px]">{{ __('Recent purchases') }}</div>
                                <button type="button" data-acc-tab="invoices" class="text-[11.5px] font-semibold ig-text hover:underline">{{ __('All invoices') }} &rarr;</button>
                            </div>
                            <div class="divide-y divide-paper-100">
                                @foreach ($orderCol->sortByDesc('created_at')->take(3) as $o)
                                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                                        <div class="min-w-0">
                                            <div class="text-[12.5px] font-semibold truncate">{{ optional($o->package)->name ?: __('Plan') }}</div>
                                            <div class="font-mono text-[11px] text-ink-400">{{ optional($o->created_at)->format('M j, Y') }}</div>
                                        </div>
                                        <div class="text-[12.5px] font-semibold shrink-0">{!! \App\Support\FormatSettings::display((float) ($o->amount ?? 0), $o->currency ?? ($package->currency ?? null)) !!}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- ========= INVOICES / ORDERS ========= --}}
                <div data-acc-pane="invoices" class="space-y-4 hidden">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
                        <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-3">
                            <h3 class="font-serif text-[20px]">{{ __('Invoices') }}</h3>
                            <span class="font-mono text-[10.5px] text-ink-500">
                                {{ $orders->count() }} {{ trans_choice('order|orders', $orders->count()) }} ·
                                {!! \App\Support\FormatSettings::currency($ordersLifetimeAmount) !!} {{ __('lifetime') }}
                            </span>
                        </div>

                        @if ($orders->isEmpty())
                            <div class="px-5 py-10 text-center text-[12.5px] text-ink-500 italic">
                                {{ __('No orders yet.') }}
                                <a href="{{ route('instagram.plans') }}" class="text-wa-deep font-semibold hover:underline not-italic">{{ __('Browse plans') }} &rarr;</a>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-[12.5px] min-w-[640px]">
                                    <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                        <tr>
                                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">{{ __('Invoice') }}</th>
                                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">{{ __('Plan') }}</th>
                                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">{{ __('Date') }}</th>
                                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">{{ __('Amount') }}</th>
                                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">{{ __('Status') }}</th>
                                            <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">{{ __('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-paper-200">
                                        @foreach ($orders as $o)
                                            @php
                                                [$badgeCls, $dotCls] = match ($o->status) {
                                                    'paid'     => ['bg-wa-mint text-wa-deep', 'bg-wa-deep'],
                                                    'refunded' => ['bg-accent-coral/15 text-accent-coral', 'bg-accent-coral'],
                                                    'failed'   => ['bg-accent-coral/15 text-accent-coral', 'bg-accent-coral'],
                                                    default    => ['bg-accent-amber/15 text-accent-amber', 'bg-accent-amber'],
                                                };
                                            @endphp
                                            <tr>
                                                <td class="px-4 py-3 font-mono text-wa-deep">{{ $o->order_number }}</td>
                                                <td class="px-2 py-3">
                                                    <div>{{ optional($o->package)->name ?: 'Plan #' . $o->package_id }}</div>
                                                    @if ($o->coupon_code)
                                                        <div class="text-[10px] font-mono text-ink-500 mt-0.5">{{ __('coupon') }} <span class="text-wa-deep">{{ $o->coupon_code }}</span></div>
                                                    @endif
                                                </td>
                                                <td class="px-2 py-3 font-mono text-[11.5px]">{{ optional($o->created_at)->format('M j, Y') }}</td>
                                                <td class="px-2 py-3 font-semibold">
                                                    {!! \App\Support\FormatSettings::display($o->total_amount ?: $o->amount, $o->currency) !!}
                                                </td>
                                                <td class="px-2 py-3">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $badgeCls }} text-[10.5px] font-mono">
                                                        <span class="w-1.5 h-1.5 rounded-full {{ $dotCls }}"></span>{{ $o->status }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    @if ($o->status === 'paid')
                                                        <a href="{{ route('invoices.show', $o) }}" target="_blank"
                                                            class="text-wa-deep font-semibold hover:underline text-[11.5px]">{{ __('View invoice') }}</a>
                                                    @else
                                                        <span class="text-ink-400 text-[11.5px]">&mdash;</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ========= AI KEYS (bring your own) ========= --}}
                <div data-acc-pane="ai-keys" class="space-y-5 hidden">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        <h3 class="font-serif text-[20px] mb-1">{{ __('AI keys') }} <span class="text-ink-400 text-[13px] font-sans">— {{ __('bring your own') }}</span></h3>
                        <p class="text-[12px] text-ink-500 mb-5 max-w-2xl">
                            {{ __('Add your own provider API key to power the AI steps in your flows. When set, your key is used instead of the platform default — usage is billed to your provider account. Keys are encrypted at rest and never shown again once saved.') }}
                        </p>

                        @if (session('ai_keys_status'))
                            <div class="mb-4 rounded-lg border border-wa-deep/30 bg-wa-mint px-3 py-2 text-[12px] text-wa-deep">{{ session('ai_keys_status') }}</div>
                        @endif

                        <form method="POST" action="{{ route('account.ai-keys.update') }}" class="space-y-5">
                            @csrf
                            @method('PATCH')

                            @foreach ($aiProviders as $prov)
                                <div class="border border-paper-200 rounded-xl p-4">
                                    <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
                                        <div class="font-serif text-[16px]">{{ $prov['label'] }}</div>
                                        @if ($prov['has_key'])
                                            <span class="inline-flex items-center gap-1.5 text-[10px] font-mono uppercase px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep">
                                                <span class="w-1.5 h-1.5 rounded-full ig-grad"></span>{{ __('Key saved') }}
                                            </span>
                                        @else
                                            <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full bg-paper-100 text-ink-500">{{ __('No key') }}</span>
                                        @endif
                                    </div>
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('API key') }}</label>
                                            <input type="password" name="keys[{{ $prov['provider'] }}]" value="" autocomplete="new-password"
                                                placeholder="{{ $prov['has_key'] ? '•••••••••• ' . __('(saved — blank keeps it, “-” clears it)') : __('paste key here') }}"
                                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                        </div>
                                        <div>
                                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Default model') }} <span class="text-ink-400 font-normal">({{ __('optional') }})</span></label>
                                            <select name="models[{{ $prov['provider'] }}]"
                                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                                <option value="">{{ __('— Provider default —') }}</option>
                                                @foreach ($prov['models'] as $model)
                                                    <option value="{{ $model }}" @selected($prov['default_model'] === $model)>{{ $model }}</option>
                                                @endforeach
                                                @if ($prov['default_model'] && ! in_array($prov['default_model'], $prov['models'], true))
                                                    <option value="{{ $prov['default_model'] }}" selected>{{ $prov['default_model'] }} (custom)</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <div class="flex items-center justify-end gap-2 pt-1">
                                <button type="submit" class="px-4 py-2 rounded-full ig-grad text-white text-[12px] font-semibold hover:opacity-95 transition">{{ __('Save AI keys') }}</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ========= CHANGE PASSWORD ========= --}}
                <div data-acc-pane="security" class="hidden">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card max-w-2xl">
                        <h3 class="font-serif text-[20px] mb-1">{{ __('Change password') }}</h3>
                        <p class="text-[12px] text-ink-500 mb-5">{{ __('Use a strong password — at least 8 characters with a mix of letters, numbers, and symbols.') }}</p>

                        @if (session('password_status'))
                            <div class="mb-4 rounded-lg border border-wa-deep/30 bg-wa-mint px-3 py-2 text-[12px] text-wa-deep">{{ session('password_status') }}</div>
                        @endif
                        @if ($errors->has('current_password') || $errors->has('password'))
                            <div class="mb-4 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-accent-coral">
                                @foreach ($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="pw-current">{{ __('Current password') }}</label>
                                <input id="pw-current" name="current_password" type="password" required autocomplete="current-password"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="pw-new">{{ __('New password') }}</label>
                                <input id="pw-new" name="password" type="password" required minlength="8" autocomplete="new-password"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="pw-confirm">{{ __('Confirm new password') }}</label>
                                <input id="pw-confirm" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <div class="flex items-center justify-end gap-2 pt-2">
                                <button type="reset" class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Reset') }}</button>
                                <button type="submit" class="px-4 py-2 rounded-full ig-grad text-white text-[12px] font-semibold hover:opacity-95 transition">{{ __('Update password') }}</button>
                            </div>
                        </form>
                    </div>
                </div>

            </section>
        </div>
    </div>

    {{-- Tiny inline tab switcher — no build step. Toggles pane visibility, the
         active-nav highlight and the breadcrumb, and keeps ?tab= in the URL so
         a refresh / deep link lands on the same tab. --}}
    <script>
        (function () {
            var initial = @json($initialTab);
            var labels = {
                profile:  @json(__('Profile')),
                plan:     @json(__('Plan & billing')),
                invoices: @json(__('Invoices')),
                'ai-keys': @json(__('AI keys')),
                security: @json(__('Change password')),
            };
            var tabs  = Array.prototype.slice.call(document.querySelectorAll('[data-acc-tab]'));
            var panes = Array.prototype.slice.call(document.querySelectorAll('[data-acc-pane]'));
            var bc = document.getElementById('acc-bc');

            function show(key) {
                if (!labels[key]) key = 'profile';
                panes.forEach(function (p) { p.classList.toggle('hidden', p.getAttribute('data-acc-pane') !== key); });
                tabs.forEach(function (t) {
                    var on = t.getAttribute('data-acc-tab') === key;
                    t.classList.toggle('bg-wa-mint', on);
                    t.classList.toggle('text-wa-deep', on);
                    t.classList.toggle('font-semibold', on);
                    t.classList.toggle('text-ink-700', !on);
                });
                if (bc) bc.textContent = labels[key];
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', key);
                    window.history.replaceState(null, '', url);
                } catch (e) {}
            }

            tabs.forEach(function (t) {
                t.addEventListener('click', function () { show(t.getAttribute('data-acc-tab')); });
            });
            show(initial);

            // Live avatar preview when a new photo is picked.
            var input = document.getElementById('acc-avatar-input');
            if (input) {
                input.addEventListener('change', function (e) {
                    var f = e.target.files && e.target.files[0];
                    if (!f) return;
                    var url = URL.createObjectURL(f);
                    var box = document.getElementById('acc-avatar');
                    if (!box) return;
                    var img = document.getElementById('acc-avatar-img');
                    if (!img) {
                        img = document.createElement('img');
                        img.id = 'acc-avatar-img';
                        img.className = 'absolute inset-0 w-full h-full object-cover';
                        box.appendChild(img);
                    }
                    img.src = url;
                });
            }
        })();
    </script>

</x-layouts.instagram>

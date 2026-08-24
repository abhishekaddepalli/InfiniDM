@php
    $isEdit = $user->exists;
    $action = $isEdit ? route('admin.users.update', $user->id) : route('admin.users.store');
    $fld = 'w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10';
    $gender = old('gender', $user->gender ?? 'm');
@endphp
<form id="userForm" action="{{ $action }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-3 gap-5">
    @csrf
    @if ($isEdit) @method('PATCH') @endif

    {{-- ── 01 · Personal details ── --}}
    <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <span class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
            <span class="font-serif text-[18px] leading-none">{{ __('Personal details') }}</span>
        </div>
        <div class="space-y-3">
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]" for="name">{{ __('Full name') }} <span class="text-accent-coral">*</span></label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" class="{{ $fld }}" placeholder="{{ __('Enter full name') }}" required>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]" for="email">{{ __('Email') }} <span class="text-accent-coral">*</span></label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" class="{{ $fld }} font-mono" placeholder="user@example.com" required>
                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Used for login and notifications.') }}</div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]" for="mobile">{{ __('Mobile') }}</label>
                <input id="mobile" name="mobile" type="tel" value="{{ old('mobile', $user->mobile) }}" class="{{ $fld }} font-mono" placeholder="{{ __('Number with country code') }}">
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Avatar') }}</label>
                <div class="flex items-center gap-3 px-3 py-2.5 border border-dashed border-paper-300 rounded-lg bg-paper-0 hover:border-wa-deep transition">
                    <span id="avatar-preview" class="w-12 h-12 rounded-full bg-paper-100 grid place-items-center text-ink-500 overflow-hidden shrink-0">
                        @if ($user->avatar)
                            <img src="{{ asset('storage/' . $user->avatar) }}" alt="" class="w-full h-full object-cover">
                        @else
                            <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="6" r="3" /><path d="M2 14c0-3 3-5 6-5s6 2 6 5" /></svg>
                        @endif
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="text-[12px] font-semibold">{{ __('Profile photo') }}</div>
                        <div class="text-[10.5px] text-ink-500 font-mono">{{ __('PNG / JPG · up to 2 MB') }}</div>
                    </div>
                    <label class="text-[10.5px] font-semibold text-wa-deep px-[10px] py-1.5 rounded-full bg-paper-0 border border-wa-deep cursor-pointer hover:bg-wa-bubble transition shrink-0">
                        {{ __('Select image') }}
                        <input id="avatar-input" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="hidden">
                    </label>
                </div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Gender') }}</label>
                <div class="flex items-center gap-2" data-gender-group>
                    @foreach (['m' => __('Male'), 'f' => __('Female'), 'o' => __('Other')] as $g => $label)
                        <label class="gender-opt flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border cursor-pointer transition {{ $gender === $g ? 'border-wa-deep bg-wa-bubble' : 'border-paper-200 hover:border-wa-deep' }}">
                            <input type="radio" name="gender" value="{{ $g }}" class="sr-only" @checked($gender === $g)>
                            <span class="text-[12px] font-medium">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ── 02 · Access & plan ── --}}
    <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <span class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
            <span class="font-serif text-[18px] leading-none">{{ __('Access & plan') }}</span>
        </div>
        <div class="space-y-3">
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]" for="package_id">{{ __('Assign plan') }}</label>
                <select id="package_id" name="package_id" class="{{ $fld }}">
                    <option value="">{{ __('No plan') }}</option>
                    @foreach ($packages as $p)
                        <option value="{{ $p->id }}" @selected((string) old('package_id', $user->package_id) === (string) $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('The subscription this account is on.') }}</div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]" for="role_id">
                    {{ __('Role') }}
                    <a href="{{ route('admin.roles.index') }}" class="text-[10px] font-normal text-wa-deep hover:underline">{{ __('Manage roles') }}</a>
                </label>
                <select id="role_id" name="role_id" class="{{ $fld }}">
                    <option value="">{{ __('No role') }}</option>
                    @foreach (($roles ?? collect()) as $r)
                        <option value="{{ $r->id }}" @selected((string) old('role_id', $user->role_id) === (string) $r->id)>{{ $r->name }}</option>
                    @endforeach
                </select>
                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('What this user can access. Platform admins bypass roles.') }}</div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]" for="password">{{ __('Password') }} @unless($isEdit)<span class="text-accent-coral">*</span>@endunless</label>
                <input id="password" name="password" type="password" autocomplete="new-password" class="{{ $fld }} font-mono" placeholder="{{ $isEdit ? __('leave blank to keep') : __('Min 6 characters') }}" {{ $isEdit ? '' : 'required' }}>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]" for="password_confirm">{{ __('Confirm password') }} @unless($isEdit)<span class="text-accent-coral">*</span>@endunless</label>
                <input id="password_confirm" name="password_confirmation" type="password" autocomplete="new-password" class="{{ $fld }} font-mono" placeholder="{{ __('Repeat password') }}" {{ $isEdit ? '' : 'required' }}>
            </div>
            <div class="space-y-2 pt-1">
                <label class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                    <span>
                        <span class="block text-[12.5px] font-semibold">{{ __('Administrator') }}</span>
                        <span class="block text-[10.5px] text-ink-500">{{ __('Full access to this admin console') }}</span>
                    </span>
                    <input type="hidden" name="is_admin" value="0">
                    <span class="relative inline-block w-[34px] h-5 shrink-0">
                        <input class="peer opacity-0 w-0 h-0" type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $user->is_admin))>
                        <span class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                    </span>
                </label>
                @unless ($isEdit)
                    <label class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                        <span>
                            <span class="block text-[12.5px] font-semibold">{{ __('Active immediately') }}</span>
                            <span class="block text-[10.5px] text-ink-500">{{ __('Skip email verification') }}</span>
                        </span>
                        <input type="hidden" name="active" value="0">
                        <span class="relative inline-block w-[34px] h-5 shrink-0">
                            <input class="peer opacity-0 w-0 h-0" type="checkbox" name="active" value="1" @checked(old('active', '1') === '1')>
                            <span class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                        </span>
                    </label>
                @endunless
            </div>
        </div>
    </div>

    {{-- ── 03 · Address & notes ── --}}
    <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <span class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
            <span class="font-serif text-[18px] leading-none">{{ __('Address & notes') }}</span>
        </div>
        <div class="space-y-3">
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]" for="address">{{ __('Address') }}</label>
                <textarea id="address" name="address" rows="2" class="{{ $fld }}" placeholder="{{ __('Street, area') }}">{{ old('address', $user->address) }}</textarea>
            </div>
            {{-- Country → State → City cascade, populated by admin-users-form.js
                 (country-state-city npm). data-value preserves the saved choice. --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]" for="country">{{ __('Country') }}</label>
                    <select id="country" name="country" data-value="{{ old('country', $user->country) }}" class="{{ $fld }}">
                        <option value="">— {{ __('select country') }} —</option>
                    </select>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]" for="state">{{ __('State') }}</label>
                    <select id="state" name="state" data-value="{{ old('state', $user->state) }}" class="{{ $fld }}">
                        <option value="">— {{ __('select state') }} —</option>
                    </select>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]" for="city">{{ __('City') }}</label>
                    <select id="city" name="city" data-value="{{ old('city', $user->city) }}" class="{{ $fld }}">
                        <option value="">— {{ __('select city') }} —</option>
                    </select>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]" for="zip">{{ __('ZIP / PIN') }}</label>
                    <input id="zip" name="zip" type="text" value="{{ old('zip', $user->zip) }}" class="{{ $fld }}">
                </div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]" for="notes">{{ __('Internal notes') }}</label>
                <textarea id="notes" name="notes" rows="3" class="{{ $fld }}" placeholder="{{ __('Visible to admins only — context, vetting notes, etc.') }}">{{ old('notes', $user->notes) }}</textarea>
            </div>
            <div class="rounded-xl border border-wa-green/30 bg-wa-bubble/40 p-3 text-[11px] text-ink-700 leading-snug">
                <b>{{ __('Admin reminder:') }}</b> {{ __('Administrators can view billing, users, and every workspace. Customers only see their own account.') }}
            </div>
        </div>
    </div>
</form>

{{-- Gender picker — explicit click handling so selection works without :has(). --}}
<script>
    (function () {
        var group = document.querySelector('[data-gender-group]');
        if (!group) return;
        group.querySelectorAll('.gender-opt').forEach(function (opt) {
            opt.addEventListener('click', function () {
                group.querySelectorAll('.gender-opt').forEach(function (o) {
                    o.classList.remove('border-wa-deep', 'bg-wa-bubble');
                    o.classList.add('border-paper-200');
                });
                opt.classList.add('border-wa-deep', 'bg-wa-bubble');
                opt.classList.remove('border-paper-200');
                var r = opt.querySelector('input[type=radio]');
                if (r) r.checked = true;
            });
        });

        // Avatar — live preview of the chosen file.
        var input = document.getElementById('avatar-input');
        var preview = document.getElementById('avatar-preview');
        if (input && preview) {
            input.addEventListener('change', function () {
                var f = input.files && input.files[0];
                if (!f) return;
                var img = document.createElement('img');
                img.className = 'w-full h-full object-cover';
                img.src = URL.createObjectURL(f);
                preview.innerHTML = '';
                preview.appendChild(img);
            });
        }
    })();
</script>

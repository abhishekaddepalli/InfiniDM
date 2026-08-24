<x-layouts.admin :title="$coupon->exists ? __('Edit coupon') : __('New coupon')" admin-key="coupons">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
            <a href="{{ route('admin.coupons.index') }}" class="hover:text-ink-900">{{ __('Coupons') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $coupon->exists ? __('Edit') : __('New') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon->id) : route('admin.coupons.store') }}">
        @csrf
        @if ($coupon->exists) @method('PUT') @endif

        <main class="px-4 sm:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Billing & plans') }} · {{ $coupon->exists ? __('Edit') : __('New') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ $coupon->exists ? __('Edit') : __('New') }}
                        <span class="italic ig-text">{{ __('coupon') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('A percentage or fixed-amount discount code. Limit its lifetime and how many times it can be redeemed.') }}</p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <a href="{{ route('admin.coupons.index') }}"
                        class="px-3.5 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
                    <button type="submit" class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ $coupon->exists ? __('Save changes') : __('Create coupon') }}</button>
                </div>
            </div>

            @if ($errors->any())
                <div class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                    <div class="font-semibold mb-1">{{ __('Please fix the following:') }}</div>
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('coupon-details') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Code & discount') }}</h2>
                    <p class="text-[12px] text-ink-600 mt-1">{{ __('The code is stored uppercase. Percentage values are 0–100; fixed values are a flat amount off.') }}</p>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Code') }} <span class="text-accent-coral">*</span></span>
                        <input name="code" value="{{ old('code', $coupon->code) }}" required maxlength="60" placeholder="WELCOME20"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Description') }}</span>
                        <input name="description" value="{{ old('description', $coupon->description) }}" maxlength="255" placeholder="{{ __('Launch offer for new customers') }}"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Type') }}</span>
                        <select name="type"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                            <option value="percent" @selected(old('type', $coupon->type) === 'percent')>{{ __('Percentage (%)') }}</option>
                            <option value="fixed" @selected(old('type', $coupon->type) === 'fixed')>{{ __('Fixed amount') }}</option>
                        </select>
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Value') }} <span class="text-accent-coral">*</span></span>
                        <input name="value" type="number" step="0.01" min="0" value="{{ old('value', $coupon->value) }}"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Max redemptions') }}</span>
                        <input name="max_redemptions" type="number" min="0" value="{{ old('max_redemptions', $coupon->max_redemptions) }}" placeholder="{{ __('Blank = unlimited') }}"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                        <span class="block text-[10.5px] text-ink-500">{{ __('Leave blank for unlimited redemptions.') }}</span>
                    </label>
                    <div class="hidden sm:block"></div>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Starts at') }}</span>
                        <input name="starts_at" type="date" value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d')) }}"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Expires at') }}</span>
                        <input name="expires_at" type="date" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d')) }}"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                    <div class="sm:col-span-2 flex items-end">
                        <label class="rounded-2xl border border-paper-200 p-3.5 w-full flex items-center justify-between gap-3 cursor-pointer">
                            <span>
                                <span class="block text-[12.5px] font-semibold">{{ __('Active') }}</span>
                                <span class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Inactive coupons cannot be redeemed at checkout.') }}</span>
                            </span>
                            <span class="relative inline-block w-9 h-5 shrink-0">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="peer opacity-0 w-0 h-0" @checked(old('is_active', $coupon->is_active))>
                                <span class="absolute inset-0 bg-paper-200 rounded-full transition peer-checked:bg-wa-deep before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:before:translate-x-[16px]"></span>
                            </span>
                        </label>
                    </div>
                </div>
            </section>

            <div class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Changes apply only after you save.') }}</span>
                <button type="submit" class="px-5 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ $coupon->exists ? __('Save changes') : __('Create coupon') }}</button>
            </div>

        </main>
    </form>

</x-layouts.admin>

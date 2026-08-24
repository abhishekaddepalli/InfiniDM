<x-layouts.admin :title="__('Security')" admin-key="security">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Security') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.security.save') }}">
        @csrf
        <main class="px-4 sm:px-7 py-7 space-y-5">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · System') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Platform') }}
                        <span class="italic ig-text">{{ __('security') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Login throttling, sessions, password policy and admin access.') }}</p>
                </div>
                <div class="shrink-0 pb-1">
                    <button type="submit" class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ __('Save changes') }}</button>
                </div>
            </div>

            @if (session('success'))
                <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">{{ $errors->first() }}</div>
            @endif

            <section class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5 items-start">
                <div class="space-y-5 min-w-0">
                    {{-- Toggles --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('access-policy') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Access policy') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @php $toggles = [
                                ['force_https', __('Force HTTPS'), __('Redirect all traffic to https://')],
                                ['require_email_verification', __('Require email verification'), __('New accounts must confirm their email')],
                            ]; @endphp
                            @foreach ($toggles as [$name, $label, $hint])
                                <label class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3 cursor-pointer">
                                    <span>
                                        <span class="block text-[12.5px] font-semibold">{{ $label }}</span>
                                        <span class="block text-[10.5px] text-ink-500 mt-0.5">{{ $hint }}</span>
                                    </span>
                                    <span class="relative inline-block w-9 h-5 shrink-0">
                                        <input type="hidden" name="{{ $name }}" value="0">
                                        <input type="checkbox" name="{{ $name }}" value="1" class="peer opacity-0 w-0 h-0" @checked(old($name, $values[$name]))>
                                        <span class="absolute inset-0 bg-paper-200 rounded-full transition peer-checked:bg-wa-deep before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:before:translate-x-[16px]"></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    {{-- Login throttle + sessions --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('login-throttle') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Login & sessions') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @php $nums = [
                                ['login_max_attempts', __('Max login attempts')],
                                ['login_decay_minutes', __('Lockout window (minutes)')],
                                ['session_lifetime_minutes', __('Session lifetime (minutes)')],
                                ['password_min_length', __('Minimum password length')],
                            ]; @endphp
                            @foreach ($nums as [$name, $label])
                                <label class="space-y-1.5">
                                    <span class="text-[11.5px] font-semibold">{{ $label }}</span>
                                    <input name="{{ $name }}" type="number" min="1" value="{{ old($name, $values[$name]) }}"
                                        class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                </label>
                            @endforeach
                        </div>
                    </section>

                    {{-- Admin IP allowlist --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('ip-allowlist') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Admin IP allowlist') }}</h2>
                            <p class="text-[12px] text-ink-600 mt-1">{{ __('One IP or CIDR per line. Leave blank to allow all.') }}</p>
                        </div>
                        <div class="p-5">
                            <textarea name="allowed_admin_ips" rows="4" placeholder="203.0.113.4&#10;198.51.100.0/24"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono resize-none focus:outline-none focus:border-wa-deep">{{ old('allowed_admin_ips', $values['allowed_admin_ips']) }}</textarea>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-50 border border-paper-200 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px] text-wa-deep">{{ __('Heads up') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('These are platform defaults. Session lifetime applies on next login; the IP allowlist locks the admin area — test from your own IP before enabling.') }}</p>
                    </div>
                </aside>
            </section>

            <div class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Changes apply only after you save.') }}</span>
                <button type="submit" class="px-5 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ __('Save changes') }}</button>
            </div>
        </main>
    </form>

</x-layouts.admin>

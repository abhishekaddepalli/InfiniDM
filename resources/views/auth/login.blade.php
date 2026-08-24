{{--
    Sign in — faithful port of login.html. Markup, classes and copy are the
    mockup's; only the form is real (route('login'), CSRF, old(), error bag,
    $allowRegistration). Google/Facebook are disabled (this build has no OAuth);
    the mockup's "Forgot?" link is omitted (no password-reset route exists).
--}}
@extends('auth.layout')

@section('title', __('Sign in'))

@section('body')
    {{-- ===== FORM SIDE ===== --}}
    <div class="w-full lg:w-[46%] xl:w-[42%] shrink-0 flex flex-col min-h-screen px-6 sm:px-12 lg:px-14 py-5 overflow-y-auto ig-scroll">
        @if (setting('auth_show_logo', false))
            <a href="{{ url('/') }}" class="flex items-center justify-center gap-2.5 rise d1">
                @if ($authLogo = site_logo())
                    <img src="{{ $authLogo }}" alt="{{ brand_name() }}" class="h-10 max-w-[190px] object-contain">
                @else
                    <span class="ig-grad w-9 h-9 rounded-xl grid place-items-center shadow-sm"><svg viewBox="0 0 24 24" class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg></span>
                    <span class="text-[17px] font-bold tracking-tight">{{ brand_name() }}</span>
                @endif
            </a>
        @endif

        <div class="flex-1 flex flex-col justify-center max-w-[380px] w-full mx-auto">
            <div class="rise d2">
                <span class="inline-flex items-center gap-1.5 text-[11px] mono font-mono uppercase tracking-[0.16em] text-ink-500 mb-3"><span class="w-1.5 h-1.5 rounded-full bg-green-500 pulse-dot"></span>{{ __('Welcome back') }}</span>
                <h1 class="serif font-serif text-[34px] leading-[1.03]">{{ __('Sign in to your') }}<br>{{ __('studio') }}<span class="ig-text">.</span></h1>
                <p class="text-[13px] text-ink-600 mt-2">{{ __('Manage every Instagram DM, comment and campaign from one calm workspace.') }}</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="space-y-3.5 rise d4">
                @csrf
                @if ($errors->any())
                    <div class="rounded-xl px-4 py-3 text-[12.5px] bg-accent-coral/10 border border-accent-coral/25 text-accent-coral">{{ $errors->first() }}</div>
                @endif
                <div>
                    <label for="email" class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" class="field" value="{{ old('email') }}" placeholder="you@studio.com" required autofocus autocomplete="username">
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="pw" class="block text-[12px] font-semibold text-ink-700">{{ __('Password') }}</label>
                        <a href="{{ route('password.request') }}" class="text-[12px] text-ig-pink hover:underline">{{ __('Forgot password?') }}</a>
                    </div>
                    <div class="relative">
                        <input id="pw" name="password" type="password" class="field pw" placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" data-pw-toggle="pw" class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-400 hover:text-ink-700" aria-label="{{ __('Show password') }}"><svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 10s3-6 8-6 8 6 8 6-3 6-8 6-8-6-8-6z"/><circle cx="10" cy="10" r="2.4"/></svg></button>
                    </div>
                </div>
                <label class="flex items-center gap-2.5 pt-1 cursor-pointer select-none">
                    <input type="checkbox" name="remember" value="1" class="peer sr-only" @checked(old('remember', true))>
                    <span class="w-[38px] h-[22px] rounded-full bg-paper-300 peer-checked:bg-wa-deep relative transition-colors after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:w-4 after:h-4 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-4"></span>
                    <span class="text-[13px] text-ink-700">{{ __('Keep me signed in') }}</span>
                </label>
                <button type="submit" class="btn-primary ig-grad-soft w-full mt-2 flex items-center justify-center gap-2">{{ __('Sign in') }}<svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h11M11 6l4 4-4 4"/></svg></button>
            </form>

            @if ($allowRegistration)
                <p class="text-center text-[13px] text-ink-600 mt-5 rise d5">{{ __('New to :brand?', ['brand' => brand_name()]) }} <a href="{{ route('register') }}" class="font-semibold ig-text">{{ __('Create an account') }}</a></p>
            @endif
        </div>

        <p class="text-center text-[11px] text-ink-400 rise d6">{{ __('Protected by 2FA') }} · <a href="#" class="hover:text-ink-600">{{ __('Privacy') }}</a> · <a href="#" class="hover:text-ink-600">{{ __('Terms') }}</a></p>
    </div>

    {{-- ===== SHOWCASE SIDE (shared partial) ===== --}}
    @include('auth._showcase')
@endsection

{{-- Step 1: request a reset link. Two-panel shell matching login.blade. --}}
@extends('auth.layout')

@section('title', __('Reset password'))

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
                <span class="inline-flex items-center gap-1.5 text-[11px] mono font-mono uppercase tracking-[0.16em] text-ink-500 mb-3"><span class="w-1.5 h-1.5 rounded-full bg-green-500 pulse-dot"></span>{{ __('Password help') }}</span>
                <h1 class="serif font-serif text-[34px] leading-[1.03]">{{ __('Forgot your') }}<br>{{ __('password') }}<span class="ig-text">?</span></h1>
                <p class="text-[13px] text-ink-600 mt-2">{{ __('Enter the email on your account and we\'ll send you a link to set a new password.') }}</p>
            </div>

            @if (session('status'))
                <div class="mt-5 rounded-xl px-4 py-3 text-[12.5px] bg-wa-bubble/60 border border-wa-deep/20 text-wa-deep rise d3">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mt-5 rounded-xl px-4 py-3 text-[12.5px] bg-accent-coral/10 border border-accent-coral/25 text-accent-coral rise d3">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-3.5 mt-5 rise d4">
                @csrf
                <div>
                    <label for="email" class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" class="field" value="{{ old('email') }}" placeholder="you@studio.com" required autofocus autocomplete="username">
                </div>
                <button type="submit" class="btn-primary ig-grad-soft w-full mt-2 flex items-center justify-center gap-2">{{ __('Send reset link') }}<svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h11M11 6l4 4-4 4"/></svg></button>
            </form>

            <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 mt-5 text-[13px] text-ink-600 hover:text-ink-900 rise d5">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3 5 8l5 5"/></svg>
                {{ __('Back to sign in') }}
            </a>
        </div>

        <p class="text-center text-[11px] text-ink-400 rise d6">{{ __('Protected by 2FA') }} · <a href="#" class="hover:text-ink-600">{{ __('Privacy') }}</a> · <a href="#" class="hover:text-ink-600">{{ __('Terms') }}</a></p>
    </div>

    {{-- ===== SHOWCASE SIDE (shared partial) ===== --}}
    @include('auth._showcase')
@endsection

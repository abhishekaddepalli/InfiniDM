{{-- Step 3: the reset form the emailed link opens. --}}
@extends('auth.layout')

@section('title', __('Set a new password'))

@section('body')
    <div class="w-full flex flex-col min-h-screen px-6 sm:px-12 py-8 items-center justify-center">
        <div class="w-full max-w-[400px]">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 mb-8">
                <span class="ig-grad w-9 h-9 rounded-xl grid place-items-center">
                    <svg viewBox="0 0 24 24" class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
                </span>
                <span class="serif text-[19px]">{{ brand_name() }}</span>
            </a>

            <h1 class="serif font-serif text-[28px] leading-tight mb-1.5">{{ __('Set a new password') }}<span class="ig-text">.</span></h1>
            <p class="text-[13px] text-ink-600 mb-6">{{ __('Choose a new password for your account.') }}</p>

            @if ($errors->any())
                <div class="mb-4 rounded-xl px-4 py-3 text-[12.5px] bg-accent-coral/10 border border-accent-coral/25 text-accent-coral">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div>
                    <label for="email" class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" class="field" value="{{ old('email', $email) }}" placeholder="you@studio.com" required autocomplete="username">
                </div>
                <div>
                    <label for="pw" class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('New password') }}</label>
                    <div class="relative">
                        <input id="pw" name="password" type="password" class="field pw" placeholder="••••••••" required autocomplete="new-password">
                        <button type="button" data-pw-toggle="pw" class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-400 hover:text-ink-700" aria-label="{{ __('Show password') }}"><svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 10s3-6 8-6 8 6 8 6-3 6-8 6-8-6-8-6z"/><circle cx="10" cy="10" r="2.4"/></svg></button>
                    </div>
                </div>
                <div>
                    <label for="pw2" class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Confirm new password') }}</label>
                    <input id="pw2" name="password_confirmation" type="password" class="field" placeholder="••••••••" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn-primary ig-grad-soft w-full flex items-center justify-center gap-2">
                    {{ __('Reset password') }}
                    <svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h11M11 6l4 4-4 4"/></svg>
                </button>
            </form>

            <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 mt-6 text-[13px] text-ink-600 hover:text-ink-900">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3 5 8l5 5"/></svg>
                {{ __('Back to sign in') }}
            </a>
        </div>
    </div>
@endsection

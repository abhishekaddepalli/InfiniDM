{{--
    Create account — faithful port of register.html. Markup, classes and copy are
    the mockup's; only the form is real (route('register'), CSRF, old(), error
    bag). The mockup has NO confirm field — it relies on the reveal toggle + the
    strength meter — so RegisterController drops the `confirmed` rule to match.
    Google/Facebook are disabled (this build has no OAuth).
--}}
@extends('auth.layout')

@section('title', __('Create account'))

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
                <span class="inline-flex items-center gap-1.5 text-[11px] mono font-mono uppercase tracking-[0.16em] text-ink-500 mb-3"><span class="w-1.5 h-1.5 rounded-full ig-grad-soft"></span>{{ __('14-day free trial') }}</span>
                <h1 class="serif font-serif text-[34px] leading-[1.03]">{{ __('Create your') }}<br>{{ brand_name() }}<span class="ig-text"> {{ __('studio.') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2">{{ __('No card needed. Connect your first account in under two minutes.') }}</p>
            </div>

            <form method="POST" action="{{ route('register') }}" class="space-y-3.5 rise d4">
                @csrf
                @if ($errors->any())
                    <div class="rounded-xl px-4 py-3 text-[12.5px] bg-accent-coral/10 border border-accent-coral/25 text-accent-coral">{{ $errors->first() }}</div>
                @endif
                <div>
                    <label for="name" class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Full name') }}</label>
                    <input id="name" name="name" type="text" class="field" value="{{ old('name') }}" placeholder="Vetrick Rao" required autofocus autocomplete="name">
                </div>
                <div>
                    <label for="email" class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Work email') }}</label>
                    <input id="email" name="email" type="email" class="field" value="{{ old('email') }}" placeholder="you@studio.com" required autocomplete="username">
                </div>
                <div>
                    <label for="pw" class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Password') }}</label>
                    <div class="relative">
                        <input id="pw" name="password" type="password" class="field pw" placeholder="{{ __('At least 8 characters') }}" required autocomplete="new-password" data-pw-meter>
                        <button type="button" data-pw-toggle="pw" class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-400 hover:text-ink-700" aria-label="{{ __('Show password') }}"><svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 10s3-6 8-6 8 6 8 6-3 6-8 6-8-6-8-6z"/><circle cx="10" cy="10" r="2.4"/></svg></button>
                    </div>
                    <div class="flex items-center gap-2 mt-2" aria-hidden="true">
                        <span class="pw-bar"><i data-pw-b1></i></span><span class="pw-bar"><i data-pw-b2></i></span><span class="pw-bar"><i data-pw-b3></i></span>
                        <span data-pw-label class="text-[10.5px] mono font-mono text-ink-400 w-14 text-right">—</span>
                    </div>
                </div>
                <label class="flex items-start gap-2.5 pt-1 cursor-pointer select-none">
                    <input type="checkbox" name="terms" value="1" class="peer sr-only" required @checked(old('terms'))>
                    <span class="mt-0.5 w-[18px] h-[18px] rounded-[6px] border border-paper-200 peer-checked:ig-grad-soft peer-checked:border-transparent grid place-items-center transition-colors shrink-0"><svg viewBox="0 0 16 16" class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M3 8.5l3 3 7-7"/></svg></span>
                    <span class="text-[12.5px] text-ink-600 leading-snug">{!! __('I agree to the <a href="#" class="font-semibold ig-text">Terms</a> and <a href="#" class="font-semibold ig-text">Privacy Policy</a>.') !!}</span>
                </label>
                <button type="submit" class="btn-primary ig-grad-soft w-full mt-1 flex items-center justify-center gap-2">{{ __('Create account') }}<svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h11M11 6l4 4-4 4"/></svg></button>
            </form>

            <p class="text-center text-[13px] text-ink-600 mt-5 rise d5">{{ __('Already have an account?') }} <a href="{{ route('login') }}" class="font-semibold ig-text">{{ __('Sign in') }}</a></p>
        </div>

    </div>

    {{-- ===== SHOWCASE SIDE ===== --}}
    <div class="hidden lg:flex flex-1 relative ig-grad overflow-hidden">
        <div class="absolute inset-0 dot-pattern opacity-60"></div>
        <div class="absolute -bottom-24 -right-24 w-[380px] h-[380px] rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute top-0 -left-20 w-[320px] h-[320px] rounded-full bg-ig-purple/40 blur-3xl"></div>

        {{-- Two columns on xl (content left, floating cards right) so they SHARE
             the width via flex and can never overlap. Below xl only the content
             column shows and the cards are hidden — no room for both. --}}
        <div class="relative z-10 w-full flex items-stretch p-12 xl:p-14 text-white">

            {{-- Left: headline · steps · metrics --}}
            <div class="flex-1 min-w-0 flex flex-col justify-between xl:pr-10">
                <div class="max-w-[440px] rise d3">
                    <div class="text-[11px] mono font-mono uppercase tracking-[0.2em] text-white/70 mb-4">{{ __('Set up in minutes') }}</div>
                    <h2 class="serif font-serif text-[38px] xl:text-[46px] leading-[1.05]">{{ __('Your whole') }}<br>{{ __('Instagram, on') }}<br>{{ __('autopilot.') }}</h2>
                </div>

                <div class="space-y-4 my-6 max-w-[440px]">
                    <div class="step d3 glass rounded-2xl p-4 flex items-center gap-4">
                        <span class="w-11 h-11 rounded-xl bg-white/20 grid place-items-center shrink-0 serif font-serif text-[22px]">1</span>
                        <div><div class="text-[14px] font-semibold">{{ __('Connect your accounts') }}</div><div class="text-[12px] text-white/75 mt-0.5">{{ __('Link Creator & Business profiles via the official API.') }}</div></div>
                    </div>
                    <div class="step d4 glass rounded-2xl p-4 flex items-center gap-4">
                        <span class="w-11 h-11 rounded-xl bg-white/20 grid place-items-center shrink-0 serif font-serif text-[22px]">2</span>
                        <div><div class="text-[14px] font-semibold">{{ __('Turn on automations') }}</div><div class="text-[12px] text-white/75 mt-0.5">{{ __('Auto-reply to DMs & comments with ready-made flows.') }}</div></div>
                    </div>
                    <div class="step d5 glass-solid rounded-2xl p-4 flex items-center gap-4 text-ink-900">
                        <span class="w-11 h-11 rounded-xl ig-grad-soft grid place-items-center shrink-0 serif font-serif text-[22px] text-white">3</span>
                        <div class="flex-1"><div class="text-[14px] font-semibold">{{ __('Watch it grow') }}</div><div class="text-[12px] text-ink-600 mt-0.5">{{ __('Track replies, sales & sentiment in one dashboard.') }}</div></div>
                        <span class="w-2.5 h-2.5 rounded-full bg-green-500 pulse-dot"></span>
                    </div>
                </div>

                <div class="rise d5 flex items-center gap-6">
                    <div><div class="serif font-serif text-[32px] leading-none">0.8s</div><div class="text-[11px] mono font-mono uppercase tracking-wider text-white/70 mt-1">{{ __('Avg reply') }}</div></div>
                    <span class="w-px h-9 bg-white/25"></span>
                    <div><div class="serif font-serif text-[32px] leading-none">86%</div><div class="text-[11px] mono font-mono uppercase tracking-wider text-white/70 mt-1">{{ __('Automated') }}</div></div>
                    <span class="w-px h-9 bg-white/25"></span>
                    <div><div class="serif font-serif text-[32px] leading-none">9.4k</div><div class="text-[11px] mono font-mono uppercase tracking-wider text-white/70 mt-1">{{ __('Creators') }}</div></div>
                </div>
            </div>

            {{-- Right: floating growth cluster. xl+ only (needs the width), and
                 decorative so aria-hidden. --}}
            <div class="hidden xl:flex flex-col justify-center gap-4 w-[250px] shrink-0" aria-hidden="true">
                {{-- Follower growth card with a 7-bar mini chart --}}
                <div class="f1 glass-solid rounded-2xl p-4 text-ink-900 shadow-2xl">
                    <div class="flex items-center justify-between">
                        <div class="mono font-mono text-[10px] uppercase tracking-wider text-ink-500">{{ __('Followers · 7d') }}</div>
                        <span class="text-[11px] font-semibold text-green-600 flex items-center gap-0.5">
                            <svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l3-3 2 2 3-4"/></svg>+18.2%
                        </span>
                    </div>
                    <div class="serif font-serif text-[30px] leading-none mt-1.5">12,480</div>
                    <svg viewBox="0 0 200 48" class="w-full h-11 mt-2" preserveAspectRatio="none">
                        @foreach ([16,22,19,28,26,35,44] as $i => $h)
                            <rect x="{{ $i * 28 + 4 }}" y="{{ 48 - $h }}" width="18" height="{{ $h }}" rx="3" fill="url(#igbar)"></rect>
                        @endforeach
                        <defs><linearGradient id="igbar" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#833AB4"/><stop offset="1" stop-color="#F77737"/></linearGradient></defs>
                    </svg>
                </div>

                {{-- Connected accounts card with story-ring avatars --}}
                <div class="f1 glass rounded-2xl p-4 text-white shadow-xl">
                    <div class="flex items-center justify-between mb-2.5">
                        <div class="mono font-mono text-[10px] uppercase tracking-wider text-white/75">{{ __('Connected') }}</div>
                        <span class="text-[10px] font-semibold flex items-center gap-1 text-white/90"><span class="w-1.5 h-1.5 rounded-full bg-green-400 pulse-dot"></span>3 {{ __('live') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="ig-ring shrink-0"><span class="block w-8 h-8 rounded-full bg-gradient-to-br from-ig-pink to-ig-orange grid place-items-center text-[10px] font-semibold">SN</span></span>
                        <span class="ig-ring shrink-0"><span class="block w-8 h-8 rounded-full bg-gradient-to-br from-ig-blue to-ig-purple grid place-items-center text-[10px] font-semibold">GG</span></span>
                        <span class="ig-ring shrink-0"><span class="block w-8 h-8 rounded-full bg-gradient-to-br from-ig-amber to-ig-pink grid place-items-center text-[10px] font-semibold">LX</span></span>
                        <div class="ml-1 text-[11px] text-white/80 leading-tight">&#64;sona.studio<br><span class="text-white/55">+2 {{ __('more') }}</span></div>
                    </div>
                </div>

                {{-- New-DM toast --}}
                <div class="f1 glass-solid rounded-2xl px-4 py-3 text-ink-900 shadow-xl flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl ig-grad-soft grid place-items-center text-white shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-[12px] font-semibold truncate">{{ __('New DM · auto-replied') }}</div>
                        <div class="text-[11px] text-ink-500 truncate">{{ __('"Is this in stock?" &rarr; link sent') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

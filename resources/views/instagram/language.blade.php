{{--
    Language picker — a full page (like Theme), not a rail flyout. Each card is
    a form that POSTs to locale.update; SetLocale applies it and flips <html dir>
    for RTL languages. No JS, no dropdown, no off-screen popup.
--}}
<x-layouts.instagram :title="__('Language')" ig-active="language" page="instagram-language">

    <section class="px-4 sm:px-7 lg:px-9 pt-7 pb-16">

        <div class="mb-6">
            <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Appearance') }}</div>
            <h1 class="serif font-serif font-normal text-[30px] sm:text-[36px] leading-[1.05] tracking-tight">
                {{ __('Language') }}
            </h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-xl">
                {{ __('Pick the language for your account. It applies everywhere immediately and is saved to your profile.') }}
            </p>
        </div>

        @if (session('status'))
            <div class="mb-5 rounded-2xl bg-wa-bubble text-wa-deep px-4 py-3 text-[13px]">{{ session('status') }}</div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
            @foreach ($langs as $l)
                @php $isCurrent = $l->code === $current; @endphp
                <form method="POST" action="{{ route('locale.update') }}">
                    @csrf
                    <input type="hidden" name="code" value="{{ $l->code }}">
                    <button type="submit"
                        class="w-full text-left bg-paper-0 border rounded-2xl p-4 shadow-card transition flex items-center gap-3
                               {{ $isCurrent ? 'border-wa-deep ring-2 ring-wa-deep/15' : 'border-paper-200 hover:border-wa-deep hover:shadow-soft' }}">
                        <span class="w-11 h-11 rounded-xl {{ $isCurrent ? 'ig-grad text-white' : 'bg-wa-mint text-wa-deep' }} grid place-items-center shrink-0">
                            <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="7.4"/><path d="M2.6 10h14.8M10 2.6c2 2 3 4.6 3 7.4s-1 5.4-3 7.4c-2-2-3-4.6-3-7.4s1-5.4 3-7.4z"/></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="font-semibold text-[14px] text-ink-900 truncate">{{ $l->native_name ?: $l->name }}</span>
                                @if ($l->is_rtl)
                                    <span class="mono text-[8px] uppercase px-1 py-0.5 rounded bg-ig-pink/10 text-ig-pink">RTL</span>
                                @endif
                            </span>
                            <span class="block text-[11.5px] text-ink-500 mt-0.5">{{ $l->name }} · <span class="uppercase mono">{{ $l->code }}</span></span>
                        </span>
                        @if ($isCurrent)
                            <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-green shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 8.5 3 3L13 4"/></svg>
                        @endif
                    </button>
                </form>
            @endforeach
        </div>

        @if ($langs->isEmpty())
            <p class="text-[13px] text-ink-500 py-10 text-center">{{ __('No languages are enabled yet. An admin can enable them under Admin → Languages.') }}</p>
        @endif
    </section>
</x-layouts.instagram>

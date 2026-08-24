{{-- The list of languages inside a switcher menu. Shared by the header
     (<details> pill) and the rail (JS-toggled button) so the markup lives once.
     Expects: $langs (collection), $current (locale code). --}}
<div class="mono text-[9px] uppercase tracking-[0.15em] text-ink-400 px-2.5 pt-1 pb-1.5 sticky top-0 bg-paper-0">{{ __('Language') }}</div>
@foreach ($langs as $l)
    <form method="POST" action="{{ route('locale.update') }}">
        @csrf
        <input type="hidden" name="code" value="{{ $l->code }}">
        <button type="submit"
            class="w-full flex items-center justify-between gap-3 px-2.5 py-1.5 rounded-xl text-[12.5px] text-left hover:bg-paper-50 {{ $l->code === $current ? 'bg-paper-50 font-semibold' : '' }}">
            <span class="flex items-center gap-2 min-w-0">
                <span class="truncate">{{ $l->native_name ?: $l->name }}</span>
                @if ($l->is_rtl)
                    <span class="mono text-[8px] uppercase px-1 py-0.5 rounded bg-ig-pink/10 text-ig-pink">RTL</span>
                @endif
            </span>
            <span class="flex items-center gap-2 shrink-0">
                <span class="mono text-[10px] text-ink-400 uppercase">{{ $l->code }}</span>
                @if ($l->code === $current)
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-green" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 8.5 3 3L13 4"/></svg>
                @endif
            </span>
        </button>
    </form>
@endforeach

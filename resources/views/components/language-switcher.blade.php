{{--
    Language switcher. Two shapes from one component:
      • header (compact=false) — a native <details> pill (globe + code).
      • rail   (compact=true)  — a real rail row (button.ig-rail-ic, exactly like
        the Sign-out button) + a JS-toggled menu positioned FIXED so the rail's
        clipped overflow never cuts it off.

    The rail deliberately does NOT use <details>: a <details> with
    display:contents rendered as a row but swallowed the open click, so nothing
    happened on tap. An explicit button + JS toggle is reliable.

    Props: align 'left'|'right'|'up' (header menu side), compact bool.
--}}
@props(['align' => 'right', 'compact' => false])

@php
    $langs   = \App\Support\LocaleSettings::active();
    $current = app()->getLocale();
    $show    = $langs->count() > 1;

    $menuPos = match ($align) {
        'left'  => 'left-0 top-full mt-2',
        'up'    => 'left-full bottom-0 ml-2',
        default => 'right-0 top-full mt-2',
    };
    $menuStyle = 'min-width:186px;max-height:min(320px,60vh);overflow-y:auto;overscroll-behavior:contain;';
@endphp

@if ($show)
    @if ($compact)
        {{-- RAIL --}}
        <div class="ig-lang" data-lang-switcher data-lang-compact>
            <button type="button" class="ig-rail-ic" data-lang-toggle aria-haspopup="true" aria-expanded="false" aria-label="{{ __('Language') }}">
                <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="7.4"/><path d="M2.6 10h14.8M10 2.6c2 2 3 4.6 3 7.4s-1 5.4-3 7.4c-2-2-3-4.6-3-7.4s1-5.4 3-7.4z"/></svg>
                <span class="ig-tip">{{ __('Language') }}</span>
            </button>
            <div class="ig-lang-menu rounded-2xl border border-paper-200 bg-paper-0 shadow-soft p-1.5" data-lang-menu hidden
                 style="position:fixed;z-index:9999;{{ $menuStyle }}">
                @include('partials.language-options', ['langs' => $langs, 'current' => $current])
            </div>
        </div>
    @else
        {{-- HEADER --}}
        <details class="ig-lang relative" data-lang-switcher>
            <summary class="list-none cursor-pointer select-none flex items-center gap-1.5 px-3 h-9 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-medium"
                aria-label="{{ __('Language') }}" title="{{ __('Language') }}">
                <svg viewBox="0 0 20 20" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="7.4"/><path d="M2.6 10h14.8M10 2.6c2 2 3 4.6 3 7.4s-1 5.4-3 7.4c-2-2-3-4.6-3-7.4s1-5.4 3-7.4z"/></svg>
                <span class="uppercase">{{ strtoupper($current) }}</span>
                <svg class="w-3 h-3 text-ink-400" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 5l3 3 3-3"/></svg>
            </summary>
            <div class="ig-lang-menu absolute {{ $menuPos }} z-50 rounded-2xl border border-paper-200 bg-paper-0 shadow-soft p-1.5"
                 data-lang-menu style="{{ $menuStyle }}">
                @include('partials.language-options', ['langs' => $langs, 'current' => $current])
            </div>
        </details>
    @endif

    @once
        <style>
            details.ig-lang > summary { list-style: none; }
            details.ig-lang > summary::-webkit-details-marker { display: none; }
        </style>
        @push('scripts')
            <script src="{{ asset('assets/lang-switcher.js') }}" defer></script>
        @endpush
    @endonce
@endif

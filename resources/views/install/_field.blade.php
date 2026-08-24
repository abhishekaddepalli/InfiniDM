@php($fieldType = $type ?? 'text')
@php($isPw = $fieldType === 'password')
<div class="mb-3" data-iw-fieldwrap>
    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ $label }}</label>
    <div class="relative mt-1">
        <input name="{{ $name }}" type="{{ $fieldType }}" value="{{ $value ?? '' }}" autocomplete="off" data-iw-input
            placeholder="{{ $placeholder ?? '' }}"
            class="w-full h-10 pl-3 {{ $isPw ? 'pr-10' : 'pr-3' }} rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono text-ink-900 focus:outline-none focus:border-ig-magenta focus:bg-paper-0 transition-colors">
        @if ($isPw)
            <button type="button" data-iw-eye tabindex="-1" aria-label="Show password"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-ink-500 hover:text-ig-magenta transition-colors">
                <svg data-iw-eye-open viewBox="0 0 20 20" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M1.5 10S4.5 4 10 4s8.5 6 8.5 6-3 6-8.5 6S1.5 10 1.5 10Z"/><circle cx="10" cy="10" r="2.4"/></svg>
                <svg data-iw-eye-off viewBox="0 0 20 20" class="w-4 h-4 hidden" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l14 14M8.2 8.3A2.5 2.5 0 0011.6 11.7M6.1 6.3C3.5 7.7 1.5 10 1.5 10s3 6 8.5 6c1.4 0 2.6-.3 3.7-.8M11.8 4.3C11.2 4.1 10.6 4 10 4"/></svg>
            </button>
        @endif
    </div>
    <p data-iw-fieldmsg class="hidden mt-1.5 text-[11px] font-medium text-accent-coral"></p>
</div>

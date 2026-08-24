<x-layouts.instagram :title="__('Edit automation')" ig-active="automations" page="instagram-automations-form">

    {{-- ===== STICKY HEADER (WaDesk auto-reply clone, IgDesk skin) ===== --}}
    <div class="hairline-b bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('instagram.automations') }}" class="w-8 h-8 rounded-full hairline bg-paper-0 hover:bg-paper-50 grid place-items-center" title="{{ __('Back to automations') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 4l-4 4 4 4"/></svg>
                </a>
                <div class="min-w-0">
                    <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Automations / Edit') }} · #{{ $automation->id }}</div>
                    <div class="serif text-[20px] leading-tight truncate">{{ __('Edit auto') }} <span class="ig-text italic">{{ __('reply') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="pill bg-paper-100 text-ink-600 mono">{{ __('Editing') }}</span>
                <button type="submit" form="igAutoForm" class="px-4 py-1.5 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l5 5 7-9"/></svg>{{ __('Update rule') }}
                </button>
            </div>
        </div>
    </div>

    <div class="w-full px-7 pt-4"><x-admin.flash /></div>

    <section class="w-full px-7 py-6">
        <form id="igAutoForm" method="POST" action="{{ route('instagram.automations.update', $automation->id) }}">
            @csrf
            @method('PUT')
            @include('instagram.automations._form', ['automation' => $automation])
        </form>
    </section>

</x-layouts.instagram>

<x-layouts.instagram :title="__('New automation')" ig-active="automations" page="instagram-automations-form">

    {{-- ===== STICKY HEADER (WaDesk auto-reply clone, IgDesk skin) ===== --}}
    <div class="hairline-b bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('instagram.automations') }}" class="w-8 h-8 rounded-full hairline bg-paper-0 hover:bg-paper-50 grid place-items-center" title="{{ __('Back to automations') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 4l-4 4 4 4"/></svg>
                </a>
                <div class="min-w-0">
                    <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Automations / New') }}</div>
                    <div class="serif text-[20px] leading-tight truncate">{{ __('Create an auto') }} <span class="ig-text italic">{{ __('reply') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="pill bg-paper-100 text-ink-600 mono">{{ __('Draft / unsaved') }}</span>
                <button type="submit" form="igAutoForm" class="px-4 py-1.5 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l5 5 7-9"/></svg>{{ __('Activate rule') }}
                </button>
            </div>
        </div>
    </div>

    <div class="w-full px-7 pt-4"><x-admin.flash /></div>

    {{-- ===== TEMPLATE GALLERY (ManyChat "pick a recipe" parity) ===== --}}
    @if (!empty($recipes))
        <section class="w-full px-7 pt-4">
            <div class="bg-white hairline rounded-2xl p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <span class="mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Start from a template') }}</span>
                    @if (request('recipe'))
                        <a href="{{ route('instagram.automations.create') }}" class="text-[11px] text-ink-500 hover:text-ig-pink">{{ __('Clear') }}</a>
                    @endif
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2.5">
                    @foreach ($recipes as $key => $r)
                        @php $active = request('recipe') === $key; @endphp
                        <a href="{{ route('instagram.automations.create', ['recipe' => $key]) }}"
                           class="hairline rounded-xl p-3 transition {{ $active ? 'border-ig-pink ring-2 ring-ig-pink/15 bg-ig-pink/[0.03]' : 'bg-paper-0 hover:border-ig-pink' }}">
                            <div class="text-[12.5px] font-semibold text-ink-900">{{ __($r['label']) }}</div>
                            <div class="text-[11px] text-ink-500 mt-0.5 leading-snug">{{ __($r['desc']) }}</div>
                        </a>
                    @endforeach
                    <a href="{{ route('instagram.automations.create') }}"
                       class="hairline rounded-xl p-3 bg-paper-50/60 hover:border-ink-300 transition grid place-items-center text-center text-[12px] text-ink-500 font-medium {{ request('recipe') ? '' : 'border-ink-300 ring-2 ring-ink-200' }}">
                        {{ __('Start from scratch') }}
                    </a>
                </div>
            </div>
        </section>
    @endif

    <section class="w-full px-7 py-6">
        <form id="igAutoForm" method="POST" action="{{ route('instagram.automations.store') }}">
            @csrf
            @include('instagram.automations._form', ['automation' => $automation, 'prefill' => $prefill ?? []])
        </form>
    </section>

</x-layouts.instagram>

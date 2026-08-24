<x-layouts.admin :title="__('Admin · Pricing FAQ')" admin-key="pricing-faqs">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Pricing FAQ') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5 max-w-[1000px]">

        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Billing & plans') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Pricing') }} <span class="italic ig-text">{{ __('FAQ') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('The questions shown on the plans page. Add, edit, reorder or hide any of them — the plans page updates instantly.') }}</p>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">{{ session('success') }}</div>
        @endif
        @foreach ($errors->all() as $e)
            <div class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-2 text-[12.5px]">{{ $e }}</div>
        @endforeach

        {{-- Add new --}}
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">{{ __('Add a question') }}</div>
            <form method="post" action="{{ route('admin.pricing-faqs.store') }}" class="space-y-3">
                @csrf
                <input name="question" required maxlength="255" placeholder="{{ __('Question') }}"
                       class="w-full px-3.5 py-2.5 hairline border border-paper-200 rounded-xl bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                <textarea name="answer" required rows="2" maxlength="5000" placeholder="{{ __('Answer') }}"
                          class="w-full px-3.5 py-2.5 hairline border border-paper-200 rounded-xl bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep resize-none"></textarea>
                <div class="flex items-center gap-3">
                    <input name="sort_order" type="number" min="0" max="9999" placeholder="{{ __('Order') }}"
                           class="w-24 px-3 py-2 hairline border border-paper-200 rounded-xl bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                    <label class="flex items-center gap-2 text-[12.5px] text-ink-700"><input type="checkbox" name="is_active" value="1" checked class="rounded"> {{ __('Active') }}</label>
                    <button class="ml-auto px-4 py-2 rounded-lg bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Add FAQ') }}</button>
                </div>
            </form>
        </section>

        {{-- Existing --}}
        <section class="space-y-3">
            @forelse ($faqs as $faq)
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
                    <form method="post" action="{{ route('admin.pricing-faqs.update', $faq->id) }}" class="space-y-3">
                        @csrf @method('PUT')
                        <div class="flex items-start gap-3">
                            <input name="question" required maxlength="255" value="{{ $faq->question }}"
                                   class="flex-1 px-3.5 py-2.5 hairline border border-paper-200 rounded-xl bg-paper-0 text-[13px] font-medium focus:outline-none focus:border-wa-deep" />
                            <span class="inline-flex shrink-0 px-2 py-1 rounded-full text-[10.5px] font-semibold {{ $faq->is_active ? 'bg-wa-bubble text-wa-deep' : 'bg-paper-100 text-ink-500' }}">
                                {{ $faq->is_active ? __('active') : __('hidden') }}
                            </span>
                        </div>
                        <textarea name="answer" required rows="2" maxlength="5000"
                                  class="w-full px-3.5 py-2.5 hairline border border-paper-200 rounded-xl bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep resize-none">{{ $faq->answer }}</textarea>
                        <div class="flex items-center gap-3 flex-wrap">
                            <label class="text-[11.5px] text-ink-500">{{ __('Order') }}
                                <input name="sort_order" type="number" min="0" max="9999" value="{{ $faq->sort_order }}"
                                       class="w-20 ml-1 px-2 py-1.5 hairline border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                            </label>
                            <label class="flex items-center gap-2 text-[12.5px] text-ink-700"><input type="checkbox" name="is_active" value="1" @checked($faq->is_active) class="rounded"> {{ __('Active') }}</label>
                            <button class="ml-auto px-3.5 py-1.5 rounded-lg bg-wa-deep text-paper-0 text-[11.5px] font-semibold hover:bg-wa-teal">{{ __('Save') }}</button>
                        </div>
                    </form>
                    <form method="post" action="{{ route('admin.pricing-faqs.destroy', $faq->id) }}" class="mt-2 text-right"
                          onsubmit="return confirm('{{ __('Delete this FAQ?') }}')">
                        @csrf @method('DELETE')
                        <button class="text-[11px] text-accent-coral hover:underline">{{ __('Delete') }}</button>
                    </form>
                </div>
            @empty
                <div class="text-center py-10 text-ink-500 text-[13px] bg-paper-0 border border-paper-200 rounded-2xl">{{ __('No FAQs yet — add one above.') }}</div>
            @endforelse
        </section>

    </main>

</x-layouts.admin>

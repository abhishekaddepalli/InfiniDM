<x-layouts.instagram :title="__('Attributes')" ig-active="attributes" page="instagram-attributes">
    {{-- WaDesk-style contact attributes. name / email / phone are built-ins that
         always exist; custom ones are the operator's own fields. A flow's Ask
         node can save an answer into any of these, and every flow pre-loads what
         we already know as {{key}}. --}}
    <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">

        <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
            <div>
                <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-400">{{ __('Instagram') }} / {{ __('Attributes') }}</div>
                <h1 class="serif text-[26px] leading-tight mt-1">{{ __('Contact') }} <span class="ig-text italic">{{ __('attributes') }}</span></h1>
                <p class="text-[12.5px] text-ink-500 mt-1.5 max-w-[600px] leading-relaxed">{{ __('Fields you save on a contact. Fill them with an Ask question node in a flow, or by hand on a contact, then reuse anywhere as a merge tag.') }}</p>
            </div>
            <a href="{{ route('instagram.contacts.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full hairline text-[12.5px] font-medium hover:bg-paper-50 transition shrink-0">{{ __('View contacts') }}</a>
        </div>

        @if (session('status'))
            <div class="hairline rounded-xl bg-wa-mint/10 text-wa-deep px-4 py-2.5 text-[12.5px] mb-4">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="hairline rounded-xl bg-accent-coral/10 text-accent-coral px-4 py-2.5 text-[12.5px] mb-4">{{ session('error') }}</div>
        @endif

        {{-- add --}}
        <form method="POST" action="{{ route('instagram.attributes.store') }}" class="flex items-center gap-2 mb-4">
            @csrf
            <input type="text" name="label" required maxlength="120" placeholder="{{ __('New attribute name — e.g. City, Order ID, Plan') }}"
                   class="flex-1 max-w-[420px] px-3.5 py-2.5 hairline rounded-xl bg-paper-0 text-[13px] focus:outline-none focus:border-ig-pink" />
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl ig-grad-soft text-white text-[12.5px] font-semibold shadow-soft hover:opacity-90 transition shrink-0">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M8 3.5v9M3.5 8h9"/></svg>{{ __('Add attribute') }}
            </button>
        </form>

        {{-- table --}}
        <div class="hairline rounded-2xl bg-paper-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead>
                        <tr class="text-left text-ink-500 mono font-mono text-[10px] uppercase tracking-[0.12em] border-b border-paper-200">
                            <th class="px-4 py-2.5 font-medium">{{ __('Attribute') }}</th>
                            <th class="px-4 py-2.5 font-medium">{{ __('Merge tag') }}</th>
                            <th class="px-4 py-2.5 font-medium">{{ __('Type') }}</th>
                            <th class="px-4 py-2.5 font-medium text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-100">
                        @foreach ($builtIns as $key => $label)
                            @php $tag = '{'.'{'.$key.'}'.'}'; @endphp
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-4 py-3 font-semibold">{{ __($label) }}</td>
                                <td class="px-4 py-3">
                                    <button type="button" class="ig-copy-tag mono text-[11px] px-2 py-1 rounded-lg bg-paper-100 hover:bg-paper-200 transition" data-tag="{{ $tag }}" title="{{ __('Copy') }}">{{ $tag }}</button>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="mono text-[9px] uppercase tracking-[0.12em] px-1.5 py-0.5 rounded bg-paper-100 text-ink-500">{{ __('built-in') }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-ink-300 text-[11px]">—</td>
                            </tr>
                        @endforeach

                        @foreach ($attributes as $a)
                            @php $tag = '{'.'{'.$a->key.'}'.'}'; @endphp
                            <tr class="hover:bg-paper-50/60" data-attr-row>
                                <td class="px-4 py-3">
                                    <span class="font-semibold" data-attr-label>{{ $a->label }}</span>
                                    <form method="POST" action="{{ route('instagram.attributes.update', $a->id) }}" class="ig-attr-rename-form hidden items-center gap-2 mt-2">
                                        @csrf @method('PUT')
                                        <input type="text" name="label" value="{{ $a->label }}" maxlength="120" class="px-3 py-1.5 hairline rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-ig-pink" />
                                        <button type="submit" class="px-3 py-1.5 rounded-lg ig-grad-soft text-white text-[11.5px] font-semibold">{{ __('Save') }}</button>
                                    </form>
                                </td>
                                <td class="px-4 py-3">
                                    <button type="button" class="ig-copy-tag mono text-[11px] px-2 py-1 rounded-lg bg-paper-100 hover:bg-paper-200 transition" data-tag="{{ $tag }}" title="{{ __('Copy') }}">{{ $tag }}</button>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="mono text-[9px] uppercase tracking-[0.12em] px-1.5 py-0.5 rounded bg-ig-pink/10 text-ig-pink">{{ __('custom') }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" class="ig-attr-rename w-8 h-8 rounded-lg hover:bg-paper-100 text-ink-500 grid place-items-center" title="{{ __('Rename') }}">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 2.5l2.5 2.5L6 12.5l-3 .5.5-3z"/></svg>
                                        </button>
                                        <form method="POST" action="{{ route('instagram.attributes.destroy', $a->id) }}" onsubmit="return confirm('{{ __('Remove this attribute? Values already saved on contacts stay.') }}');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="w-8 h-8 rounded-lg hover:bg-accent-coral/15 text-accent-coral grid place-items-center" title="{{ __('Delete') }}">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($attributes->isEmpty())
            <p class="text-[12px] text-ink-400 mt-3 text-center">{{ __('No custom attributes yet — add one above. Built-ins are always available.') }}</p>
        @endif
    </div>

    <script>
        document.addEventListener('click', function (e) {
            const b = e.target.closest('.ig-copy-tag');
            if (b) {
                const tag = b.getAttribute('data-tag') || '';
                navigator.clipboard?.writeText(tag).then(() => {
                    const prev = b.textContent;
                    b.textContent = @json(__('Copied!'));
                    setTimeout(() => { b.textContent = prev; }, 1100);
                });
                return;
            }
            const r = e.target.closest('.ig-attr-rename');
            if (r) {
                const row = r.closest('[data-attr-row]');
                const form = row?.querySelector('.ig-attr-rename-form');
                if (form) {
                    form.classList.toggle('hidden');
                    form.classList.toggle('flex');
                    form.querySelector('input')?.focus();
                }
            }
        });
    </script>
</x-layouts.instagram>

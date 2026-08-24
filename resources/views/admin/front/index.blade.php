<x-layouts.admin :title="__('Front pages')" admin-key="front">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Front pages') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.front.save') }}">
        @csrf
        <main class="px-4 sm:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Website content') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Front') }} <span class="italic ig-text">{{ __('pages') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Edit every line of the public marketing site — home, about, contact, legal, header and footer. Changes go live immediately.') }}</p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <a href="{{ url('/') }}" target="_blank" class="px-4 py-2 rounded-full border border-paper-200 text-[12px] font-semibold hover:border-wa-deep">{{ __('View site') }}</a>
                    <button type="submit" class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ __('Save changes') }}</button>
                </div>
            </div>

            @if (session('success'))
                <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">{{ $errors->first() }}</div>
            @endif

            @php
                $fld = 'w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep';
            @endphp

            {{-- Tab strip --}}
            <div class="flex flex-wrap gap-2" data-front-tabs>
                @foreach ($groups as $gkey => $glabel)
                    <button type="button" data-front-tab="{{ $gkey }}"
                        class="px-3.5 py-2 rounded-full text-[12px] font-semibold border transition
                        {{ $loop->first ? 'ig-grad-soft text-white border-transparent' : 'border-paper-200 text-ink-600 hover:border-wa-deep' }}">
                        {{ $glabel }}@if($gkey === 'contact' && $unread) <span class="ml-1 inline-flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full bg-white/25 text-[10px]">{{ $unread }}</span>@endif
                    </button>
                @endforeach
            </div>

            {{-- Panels --}}
            @foreach ($groups as $gkey => $glabel)
                <section data-front-panel="{{ $gkey }}" class="{{ $loop->first ? '' : 'hidden' }} bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Section') }}</div>
                        <h2 class="font-serif text-[24px] leading-tight mt-1">{{ $glabel }}</h2>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach (($fields[$gkey] ?? []) as $key => $meta)
                            @php
                                $label = $meta[0];
                                $type = $meta[1];
                                $val = $values[$key] ?? '';
                            @endphp
                            @if ($type === 'bool')
                                <label class="flex items-center justify-between gap-3 md:col-span-2 rounded-xl border border-paper-200 px-4 py-3">
                                    <span class="text-[12.5px] font-semibold">{{ $label }}</span>
                                    <input type="checkbox" name="fc[{{ $key }}]" value="1" @checked($val === '1' || $val === 1 || $val === true) class="w-4 h-4 accent-[#C13584]">
                                </label>
                            @elseif ($type === 'textarea' || $type === 'html')
                                <label class="space-y-1.5 md:col-span-2">
                                    <span class="text-[11.5px] font-semibold">{{ $label }}@if($type === 'html') <span class="font-mono text-[10px] text-ink-400">{{ __('HTML allowed') }}</span>@endif</span>
                                    <textarea name="fc[{{ $key }}]" rows="{{ $type === 'html' ? 6 : 3 }}" class="{{ $fld }} resize-y">{{ $val }}</textarea>
                                </label>
                            @else
                                <label class="space-y-1.5">
                                    <span class="text-[11.5px] font-semibold">{{ $label }}</span>
                                    <input name="fc[{{ $key }}]" value="{{ $val }}" class="{{ $fld }}" @if($type === 'url') type="url" placeholder="https://…" @endif>
                                </label>
                            @endif
                        @endforeach
                    </div>

                    @if ($gkey === 'contact')
                        {{-- Recent contact submissions --}}
                        <div class="px-5 pb-5">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2 mt-2">{{ __('Recent messages') }}</div>
                            @if (empty($messages) || count($messages) === 0)
                                <div class="rounded-xl border border-paper-200 px-4 py-6 text-center text-[12.5px] text-ink-500">{{ __('No messages yet.') }}</div>
                            @else
                                <div class="overflow-x-auto rounded-xl border border-paper-200">
                                    <table class="w-full text-[12.5px]">
                                        <thead class="bg-paper-50 text-ink-500 font-mono text-[10px] uppercase">
                                            <tr><th class="text-left px-3 py-2">{{ __('From') }}</th><th class="text-left px-3 py-2">{{ __('Message') }}</th><th class="text-left px-3 py-2">{{ __('When') }}</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($messages as $m)
                                                <tr class="border-t border-paper-200 {{ $m->is_read ? '' : 'bg-wa-bubble/40' }}">
                                                    <td class="px-3 py-2 align-top"><div class="font-semibold">{{ $m->name }}</div><a href="mailto:{{ $m->email }}" class="text-ink-500 hover:text-wa-deep">{{ $m->email }}</a></td>
                                                    <td class="px-3 py-2 align-top">@if($m->subject)<div class="font-semibold">{{ $m->subject }}</div>@endif<div class="text-ink-600 max-w-[420px]">{{ \Illuminate\Support\Str::limit($m->message, 160) }}</div></td>
                                                    <td class="px-3 py-2 align-top text-ink-500 whitespace-nowrap">{{ $m->created_at?->diffForHumans() }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endif
                </section>
            @endforeach

            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('Save changes') }}</button>
            </div>
        </main>
    </form>

    <script src="{{ asset('admin/front-pages.js') }}?v=1"></script>
</x-layouts.admin>

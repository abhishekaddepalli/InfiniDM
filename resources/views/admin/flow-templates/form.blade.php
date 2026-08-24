<x-layouts.admin :title="$template->exists ? __('Edit template') : __('New template')" admin-key="flow-templates">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ route('admin.flow-templates.index') }}" class="hover:text-ink-900">{{ __('Flow templates') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $template->exists ? __('Edit') : __('New') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ $template->exists ? route('admin.flow-templates.update', $template->id) : route('admin.flow-templates.store') }}">
        @csrf
        @if ($template->exists) @method('PUT') @endif

        <main class="px-4 sm:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Automation') }} · {{ $template->exists ? __('Edit') : __('New') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ $template->exists ? __('Edit') : __('New') }}
                        <span class="italic ig-text">{{ __('template') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('A reusable starting point customers can clone into their own flows.') }}</p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <a href="{{ route('admin.flow-templates.index') }}" class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
                    <button type="submit" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ $template->exists ? __('Save changes') : __('Create template') }}</button>
                </div>
            </div>

            @if ($errors->any())
                <div class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                    <div class="font-semibold mb-1">{{ __('Please fix the following:') }}</div>
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php
                $fld = 'w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep';
                $fldMono = $fld . ' font-mono';
            @endphp

            {{-- Template details --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('template-details') }}</div>
                    <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('Template details') }}</h2>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="space-y-1.5 sm:col-span-2">
                        <span class="text-[11.5px] font-semibold">{{ __('Name') }} <span class="text-accent-coral">*</span></span>
                        <input name="name" value="{{ old('name', $template->name) }}" required maxlength="120" class="{{ $fld }}" placeholder="{{ __('Welcome DM funnel') }}">
                    </label>
                    <label class="space-y-1.5 sm:col-span-2">
                        <span class="text-[11.5px] font-semibold">{{ __('Description') }}</span>
                        <textarea name="description" rows="3" maxlength="400" class="{{ $fld }} resize-none" placeholder="{{ __('What this template does and when to use it.') }}">{{ old('description', $template->description) }}</textarea>
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Category') }} <span class="text-accent-coral">*</span></span>
                        <select name="category" class="{{ $fld }}">
                            @foreach (\App\Models\FlowTemplate::CATEGORIES as $key => $label)
                                <option value="{{ $key }}" @selected(old('category', $template->category ?: 'general') === $key)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Channel') }} <span class="text-accent-coral">*</span></span>
                        <select name="channel" class="{{ $fld }}">
                            @foreach (['instagram' => __('Instagram'), 'whatsapp' => __('WhatsApp')] as $key => $label)
                                <option value="{{ $key }}" @selected(old('channel', $template->channel ?: 'instagram') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Sort order') }}</span>
                        <input name="sort" type="number" value="{{ old('sort', $template->sort ?? 0) }}" class="{{ $fldMono }}">
                        <span class="block text-[10.5px] text-ink-500">{{ __('Lower number = shown first in the catalog.') }}</span>
                    </label>
                    <div class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold block">{{ __('Status') }}</span>
                        <label class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3 cursor-pointer">
                            <span>
                                <span class="block text-[12.5px] font-semibold">{{ __('Active') }}</span>
                                <span class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Visible to customers in the template catalog.') }}</span>
                            </span>
                            <span class="relative inline-block w-9 h-5 shrink-0">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="peer opacity-0 w-0 h-0" @checked(old('is_active', $template->is_active))>
                                <span class="absolute inset-0 bg-paper-200 rounded-full transition peer-checked:bg-wa-deep before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:before:translate-x-[16px]"></span>
                            </span>
                        </label>
                    </div>
                </div>
            </section>

            {{-- Sticky save bar --}}
            <div class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Changes apply only after you save.') }}</span>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.flow-templates.index') }}" class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
                    <button type="submit" class="px-5 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ $template->exists ? __('Save changes') : __('Create template') }}</button>
                </div>
            </div>

        </main>

    </form>

</x-layouts.admin>

<x-layouts.admin :title="$announcement->exists ? __('Edit announcement') : __('New announcement')" admin-key="announcements">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
            <a href="{{ route('admin.announcements.index') }}" class="hover:text-ink-900">{{ __('Announcements') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $announcement->exists ? __('Edit') : __('New') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ $announcement->exists ? route('admin.announcements.update', $announcement->id) : route('admin.announcements.store') }}">
        @csrf
        @if ($announcement->exists) @method('PUT') @endif

        <main class="px-4 sm:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Marketing') }} · {{ $announcement->exists ? __('Edit') : __('New') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ $announcement->exists ? __('Edit') : __('New') }}
                        <span class="italic ig-text">{{ __('announcement') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Write the notice, choose its tone, and set when it should appear for customers.') }}</p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <button type="submit" class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ $announcement->exists ? __('Save changes') : __('Create announcement') }}</button>
                </div>
            </div>

            @if ($errors->any())
                <div class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">{{ $errors->first() }}</div>
            @endif

            {{-- Notice content --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('announcement') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Notice content') }}</h2>
                    <p class="text-[12px] text-ink-600 mt-1">{{ __('The headline and message every targeted customer will see.') }}</p>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="space-y-1.5 sm:col-span-2">
                        <span class="text-[11.5px] font-semibold">{{ __('Title') }}</span>
                        <input name="title" value="{{ old('title', $announcement->title) }}" required maxlength="160" placeholder="{{ __('Scheduled maintenance this weekend') }}"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                    </label>
                    <label class="space-y-1.5 sm:col-span-2">
                        <span class="text-[11.5px] font-semibold">{{ __('Body') }}</span>
                        <textarea name="body" rows="4" placeholder="{{ __('Optional detail shown beneath the title.') }}"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">{{ old('body', $announcement->body) }}</textarea>
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Type') }}</span>
                        <select name="type"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                            @foreach (['info' => __('Info'), 'success' => __('Success'), 'warning' => __('Warning'), 'critical' => __('Critical')] as $k => $lbl)
                                <option value="{{ $k }}" @selected(old('type', $announcement->type) === $k)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Audience') }}</span>
                        <input name="audience" value="{{ old('audience', $announcement->audience ?: 'all') }}" maxlength="40" placeholder="all"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                </div>
            </section>

            {{-- Schedule & visibility --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('schedule') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Schedule & visibility') }}</h2>
                    <p class="text-[12px] text-ink-600 mt-1">{{ __('Leave both dates blank to show the notice for as long as it is active.') }}</p>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Starts at') }}</span>
                        <input name="starts_at" type="date" value="{{ old('starts_at', $announcement->starts_at?->format('Y-m-d')) }}"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-[11.5px] font-semibold">{{ __('Ends at') }}</span>
                        <input name="ends_at" type="date" value="{{ old('ends_at', $announcement->ends_at?->format('Y-m-d')) }}"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                    <div class="sm:col-span-2">
                        <label class="rounded-2xl border border-paper-200 p-3.5 w-full flex items-center justify-between gap-3 cursor-pointer">
                            <span>
                                <span class="block text-[12.5px] font-semibold">{{ __('Active') }}</span>
                                <span class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('When off, the notice is hidden regardless of its dates.') }}</span>
                            </span>
                            <span class="relative inline-block w-9 h-5 shrink-0">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="peer opacity-0 w-0 h-0" @checked(old('is_active', $announcement->is_active))>
                                <span class="absolute inset-0 bg-paper-200 rounded-full transition peer-checked:bg-wa-deep before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:before:translate-x-[16px]"></span>
                            </span>
                        </label>
                    </div>
                </div>
            </section>

            <div class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Changes apply only after you save.') }}</span>
                <button type="submit" class="px-5 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ $announcement->exists ? __('Save changes') : __('Create announcement') }}</button>
            </div>

        </main>
    </form>

</x-layouts.admin>

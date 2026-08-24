<x-layouts.admin :title="$post->exists ? __('Edit post') : __('New post')" admin-key="blog">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
            <a href="{{ route('admin.blog.index') }}" class="hover:text-ink-900">{{ __('Blog') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $post->exists ? __('Edit') : __('New') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ $post->exists ? route('admin.blog.update', $post->id) : route('admin.blog.store') }}">
        @csrf
        @if ($post->exists) @method('PUT') @endif

        <main class="px-4 sm:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Content') }} · {{ $post->exists ? __('Edit') : __('New') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ $post->exists ? __('Edit') : __('New') }}
                        <span class="italic ig-text">{{ __('post') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Give the post a title and body, choose a status, and save. Drafts stay hidden until published.') }}</p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <button type="submit" class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ __('Save post') }}</button>
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

            <section class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5 items-start">

                <div class="space-y-5 min-w-0">

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('post-content') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Content') }}</h2>
                        </div>
                        <div class="p-5 space-y-4">
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Title') }}</span>
                                <input name="title" required value="{{ old('title', $post->title) }}" placeholder="{{ __('Announcing our new feature') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Slug') }}</span>
                                <input name="slug" value="{{ old('slug', $post->slug) }}" placeholder="{{ __('auto from title') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Excerpt') }}</span>
                                <textarea name="excerpt" rows="2" placeholder="{{ __('A short summary shown in post listings.') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">{{ old('excerpt', $post->excerpt) }}</textarea>
                            </label>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Body') }}</span>
                                <textarea name="body" rows="10" placeholder="{{ __('Write the full post here.') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">{{ old('body', $post->body) }}</textarea>
                            </label>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('publishing') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Settings') }}</h3>
                        </div>
                        <div class="p-4 space-y-4">
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Status') }}</span>
                                <select name="status"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    @foreach (['draft' => __('Draft'), 'published' => __('Published')] as $k => $lbl)
                                        <option value="{{ $k }}" @selected(old('status', $post->status) === $k)>{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Author') }}</span>
                                <input name="author" value="{{ old('author', $post->author) }}" placeholder="{{ __('Jane Doe') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                            </label>
                        </div>
                    </div>
                </aside>

            </section>

            <div class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Drafts stay hidden until published.') }}</span>
                <button type="submit" class="px-5 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90">{{ __('Save post') }}</button>
            </div>

        </main>
    </form>

</x-layouts.admin>

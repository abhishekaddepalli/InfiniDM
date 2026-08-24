<x-layouts.admin :title="__('Admin · Blog')" admin-key="blog">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Blog') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Content') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('The') }}
                    <span class="italic ig-text">{{ __('blog') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Write, publish, and manage the marketing posts shown on your public site.') }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ route('admin.blog.create') }}"
                    class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>
                    {{ __('New post') }}
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-2 text-[12.5px]">{{ session('error') }}</div>
        @endif

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Published') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['published']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('live on the site') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Drafts') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['draft']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('not yet published') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('in the archive') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Views') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['views']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('across all posts') }}</div>
            </div>
        </section>

        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="px-5 py-4 border-b border-paper-200">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('All posts') }}</div>
                <h2 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Detailed list') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[760px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-3 py-2.5">{{ __('Post') }}</th>
                            <th class="text-left px-2 py-2.5 w-[150px]">{{ __('Author') }}</th>
                            <th class="text-center px-2 py-2.5 w-[100px]">{{ __('Status') }}</th>
                            <th class="text-right px-2 py-2.5 w-[90px]">{{ __('Views') }}</th>
                            <th class="text-right px-2 py-2.5 w-[130px]">{{ __('Date') }}</th>
                            <th class="text-center px-2 py-2.5 w-[70px]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($posts as $post)
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-3 py-2">
                                    <div class="font-semibold leading-none text-[12.5px]">{{ $post->title }}</div>
                                    <div class="text-[10px] text-ink-500 mt-1 font-mono truncate max-w-[320px]">{{ $post->slug }}</div>
                                </td>
                                <td class="px-2 py-2 text-[11.5px]">{{ $post->author ?: '—' }}</td>
                                <td class="px-2 py-2 text-center">
                                    @if ($post->status === 'published')
                                        <span class="px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10px] font-semibold">{{ __('Published') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-500 text-[10px] font-semibold">{{ __('Draft') }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right font-mono">{{ number_format($post->views) }}</td>
                                <td class="px-2 py-2 text-right font-mono text-[10.5px] text-ink-500">{{ ($post->published_at ?? $post->created_at)?->diffForHumans() }}</td>
                                <td class="px-2 py-2 text-center">
                                    <div class="inline-flex items-center gap-1">
                                        <a href="{{ route('admin.blog.edit', $post->id) }}" title="{{ __('Edit') }}"
                                            class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-600 hover:text-wa-deep">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9.5 3.5 12.5 6.5 6 13H3v-3z"/></svg>
                                        </a>
                                        <form action="{{ route('admin.blog.destroy', $post->id) }}" method="POST"
                                            onsubmit="return confirm('{{ __('Delete this post? This cannot be undone.') }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="{{ __('Delete') }}"
                                                class="w-8 h-8 rounded-full hover:bg-accent-coral/10 grid place-items-center text-ink-600 hover:text-accent-coral">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-ink-500">{{ __('No posts yet.') }} <a href="{{ route('admin.blog.create') }}" class="ig-text font-semibold">{{ __('Write the first one →') }}</a></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($posts->hasPages())
                <div class="px-5 py-4 border-t border-paper-200">
                    {{ $posts->onEachSide(1)->links() }}
                </div>
            @endif
        </div>
    </main>

</x-layouts.admin>

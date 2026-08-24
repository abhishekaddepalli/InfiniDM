<x-layouts.admin :title="__('Flow templates')" admin-key="flow-templates">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Flow templates') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Automation') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Flow') }}
                    <span class="italic ig-text">{{ __('templates') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Reusable starting points customers can clone into their own flows.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ route('admin.flow-templates.create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    {{ __('New template') }}
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div
                class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-2 text-[12.5px]">
                {{ session('error') }}</div>
        @endif

        @php $categoryCount = $templates->pluck('category')->filter()->unique()->count(); @endphp

        <section class="grid grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total templates') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('in catalog') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['active']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('visible to customers') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Categories') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($categoryCount) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('distinct groups') }}</div>
            </div>
        </section>

        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="px-5 py-4 border-b border-paper-200">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('All templates') }}</div>
                <h2 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Flow catalog') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[720px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-3 py-2.5">{{ __('Template') }}</th>
                            <th class="text-left px-2 py-2.5 w-[130px]">{{ __('Category') }}</th>
                            <th class="text-left px-2 py-2.5 w-[120px]">{{ __('Channel') }}</th>
                            <th class="text-center px-2 py-2.5 w-[100px]">{{ __('Status') }}</th>
                            <th class="text-center px-2 py-2.5 w-[44px]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($templates as $t)
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-3 py-2">
                                    <div class="font-semibold leading-none text-[12.5px]">{{ $t->name }}</div>
                                    <div class="text-[10.5px] text-ink-500 mt-1 truncate max-w-[320px]">
                                        {{ $t->description ?: __('no description') }}</div>
                                </td>
                                <td class="px-2 py-2">
                                    <span class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ \App\Models\FlowTemplate::CATEGORIES[$t->category] ?? $t->category }}</span>
                                </td>
                                <td class="px-2 py-2 font-mono text-[11.5px]">{{ $t->channel }}</td>
                                <td class="px-2 py-2 text-center">
                                    @if ($t->is_active)
                                        <span class="px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10px] font-semibold">{{ __('Active') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-500 text-[10px] font-semibold">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <div class="relative inline-block" data-row-menu>
                                        <button type="button"
                                            class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center mx-auto"
                                            title="{{ __('Actions') }}" data-row-menu-toggle>
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-600"
                                                fill="currentColor">
                                                <circle cx="3" cy="8" r="1.2" />
                                                <circle cx="8" cy="8" r="1.2" />
                                                <circle cx="13" cy="8" r="1.2" />
                                            </svg>
                                        </button>
                                        <div data-row-menu-panel
                                            class="hidden absolute right-0 top-full mt-1 z-50 w-[180px] bg-paper-0 border border-paper-200 rounded-xl shadow-soft py-1 text-left">
                                            <a href="{{ route('admin.flow-templates.edit', $t) }}"
                                                class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                                    stroke="currentColor" stroke-width="1.6">
                                                    <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                                                </svg>{{ __('Edit') }}
                                            </a>
                                            <div class="border-t border-paper-200 my-1"></div>
                                            <form action="{{ route('admin.flow-templates.destroy', $t) }}" method="POST"
                                                onsubmit="return confirm('{{ __('Delete') }} {{ addslashes($t->name) }}? {{ __('This cannot be undone.') }}')">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                    class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-coral hover:bg-accent-coral/10">
                                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                        stroke="currentColor" stroke-width="1.6">
                                                        <path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6" />
                                                    </svg>{{ __('Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-ink-500">
                                    {{ __('No templates yet.') }} <a href="{{ route('admin.flow-templates.create') }}"
                                        class="ig-text font-semibold">{{ __('Create the first one →') }}</a></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</x-layouts.admin>

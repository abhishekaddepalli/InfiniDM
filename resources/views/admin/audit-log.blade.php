<x-layouts.admin :title="__('Audit log')" admin-key="audit">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Audit log') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Security') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Audit') }}
                    <span class="italic ig-text">{{ __('log') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('A read-only trail of administrator and system actions across the platform.') }}</p>
            </div>
        </div>

        {{-- Stat cards --}}
        <section class="grid grid-cols-2 sm:grid-cols-2 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total events') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('all time') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Today') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['today']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('since midnight') }}</div>
            </div>
        </section>

        {{-- Search --}}
        <form method="get" action="{{ route('admin.audit') }}"
            class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-2 flex flex-wrap items-center gap-1 shadow-card">
            <div class="flex-1 min-w-0"></div>
            <div class="relative">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5" /><path d="m11 11 3 3" /></svg>
                <input name="q" value="{{ $q }}" placeholder="{{ __('Search action, actor, target...') }}"
                    class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full max-w-72 sm:w-72 focus:outline-none focus:border-wa-deep" />
            </div>
        </form>

        {{-- Table --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[720px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-3 py-2.5 w-[140px]">{{ __('Time') }}</th>
                            <th class="text-left px-2 py-2.5 w-[160px]">{{ __('Actor') }}</th>
                            <th class="text-left px-2 py-2.5 w-[180px]">{{ __('Action') }}</th>
                            <th class="text-left px-2 py-2.5">{{ __('Target') }}</th>
                            <th class="text-left px-2 py-2.5 w-[130px]">{{ __('IP') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($logs as $l)
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-3 py-2.5 font-mono text-[10.5px] text-ink-600 whitespace-nowrap align-top">{{ $l->created_at?->diffForHumans() }}</td>
                                <td class="px-2 py-2.5 align-top truncate">{{ $l->actor_name ?: '—' }}</td>
                                <td class="px-2 py-2.5 font-medium align-top truncate">{{ $l->action }}</td>
                                <td class="px-2 py-2.5 font-mono text-[11px] text-ink-600 align-top truncate">{{ $l->target ?: '—' }}</td>
                                <td class="px-2 py-2.5 font-mono text-[10.5px] text-ink-600 align-top truncate">{{ $l->ip ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-ink-500">{{ __('No audit events yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 flex flex-wrap gap-2 items-center justify-between rounded-b-2xl">
                <div class="text-[11px] font-mono text-ink-500">
                    {{ __('Showing') }} {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} {{ __('of') }} {{ number_format($logs->total()) }}
                </div>
                <div>{{ $logs->onEachSide(1)->links() }}</div>
            </div>
        </div>
    </main>

</x-layouts.admin>

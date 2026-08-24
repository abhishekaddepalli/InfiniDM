{{-- Generic admin list page — the WaDesk admin table idiom (breadcrumb header,
     serif hero, stat cards, table, pagination), driven entirely by the config
     the controller passes so every read-only admin list shares one layout.

     Expects: $title, $adminKey, $crumb, $heading, $accent, $desc,
     $stats (array of ['label','value']), $cols (array of ['label','th'?,'cell'=>fn($row)=>html]),
     $rows (paginator), $empty. --}}
<x-layouts.admin :title="$title" :admin-key="$adminKey">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $crumb }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin') }} · {{ $crumb }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ $heading }}
                    <span class="italic ig-text">{{ $accent }}</span></h1>
                @isset($desc)<p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ $desc }}</p>@endisset
            </div>
        </div>

        @if (!empty($stats))
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($stats as $s)
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ $s['label'] }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1">{{ $s['value'] }}</div>
                        @isset($s['sub'])<div class="text-[11px] text-ink-500 mt-2">{{ $s['sub'] }}</div>@endisset
                    </div>
                @endforeach
            </section>
        @endif

        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[720px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            @foreach ($cols as $c)
                                <th class="text-left px-3 py-2.5 {{ $c['th'] ?? '' }}">{{ $c['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-paper-50/60">
                                @foreach ($cols as $c)
                                    <td class="px-3 py-2.5 align-middle {{ $c['td'] ?? '' }}">{!! ($c['cell'])($row) !!}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($cols) }}" class="px-4 py-10 text-center text-ink-500">{{ $empty ?? __('Nothing here yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($rows->hasPages() || $rows->total() > 0)
                <div class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 flex flex-wrap gap-2 items-center justify-between rounded-b-2xl">
                    <div class="text-[11px] font-mono text-ink-500">{{ __('Showing') }} {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} {{ __('of') }} {{ number_format($rows->total()) }}</div>
                    <div>{{ $rows->onEachSide(1)->links() }}</div>
                </div>
            @endif
        </div>
    </main>

</x-layouts.admin>

@props([
    'paginator' => null,
    'label' => 'items',
    'pageAttr' => 'data-fl-page',
])

@php
    /**
     * Numbered pager, ported from WaDesk's user/partials/pagination so the two
     * products page identically. Laravel's own ->links() renders Bootstrap-ish
     * markup that has no relationship to this design system.
     *
     * The page-number attribute is emitted so a list can be re-fetched in place
     * later; as plain <a href> it already works with JavaScript switched off.
     */
    $last = $paginator ? $paginator->lastPage() : 1;
    $current = $paginator ? $paginator->currentPage() : 1;

    // Windowed: first, current-1..current+1, last. Beyond 7 pages a full run of
    // numbers wraps the bar onto a second line on a laptop.
    $pages = [];
    if ($last <= 7) {
        $pages = range(1, $last);
    } else {
        $start = max(2, $current - 1);
        $end = min($last - 1, $current + 1);
        $pages[] = 1;
        if ($start > 2) $pages[] = 'gap-a';
        foreach (range($start, $end) as $page) $pages[] = $page;
        if ($end < $last - 1) $pages[] = 'gap-b';
        $pages[] = $last;
    }
@endphp

@if ($paginator && $paginator->total() > 0)
    <nav class="mt-4 border border-paper-200 rounded-2xl bg-paper-0 shadow-card px-4 py-3 flex flex-wrap items-center justify-between gap-3 text-[12px]"
        aria-label="{{ ucfirst($label) }} pagination">
        <div class="font-mono text-[11px] text-ink-500">
            {{ __('Showing') }}
            <span class="text-ink-900">{{ number_format($paginator->firstItem()) }}-{{ number_format($paginator->lastItem()) }}</span>
            {{ __('of') }} <span class="text-ink-900">{{ number_format($paginator->total()) }}</span> {{ $label }}
        </div>

        @if ($last > 1)
            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="px-3 py-1.5 rounded-full border border-paper-200 text-ink-400 cursor-not-allowed">{{ __('Prev') }}</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" {{ $pageAttr }}="{{ $current - 1 }}"
                        class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700">{{ __('Prev') }}</a>
                @endif

                @foreach ($pages as $page)
                    @if (is_string($page))
                        <span class="px-2 py-1.5 text-ink-400">...</span>
                    @elseif ($page === $current)
                        <span aria-current="page"
                            class="min-w-8 px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-center font-semibold">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" {{ $pageAttr }}="{{ $page }}"
                            class="min-w-8 px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700 text-center">{{ $page }}</a>
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" {{ $pageAttr }}="{{ $current + 1 }}"
                        class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700">{{ __('Next') }}</a>
                @else
                    <span class="px-3 py-1.5 rounded-full border border-paper-200 text-ink-400 cursor-not-allowed">{{ __('Next') }}</span>
                @endif
            </div>
        @endif
    </nav>
@endif

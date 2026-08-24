@props(['flow'])

@php
    /**
     * One flow in the /flows grid — the WaDesk card, Instagram-side.
     *
     * Every tint is a token. A literal hex would survive the palette retarget
     * in instaflow.css and leave one WhatsApp-green chip on an otherwise
     * Instagram page, which is harder to spot than a uniformly green one.
     */
    $categoryStyles = [
        'welcome' => ['icon' => 'M3 11s2-1 5-1 5 1 5 1M5 6.5h.01M11 6.5h.01M8 9.5s1 .8 0 1.5', 'bg' => 'bg-wa-bubble', 'text' => 'text-wa-deep'],
        'cart' => ['icon' => 'M3 5h8l1 6H4z', 'bg' => 'bg-wa-mint', 'text' => 'text-wa-deep'],
        'cart-recovery' => ['icon' => 'M3 5h8l1 6H4z', 'bg' => 'bg-wa-mint', 'text' => 'text-wa-deep'],
        'post-purchase' => ['icon' => 'M8 1.5l1.6 4.2H14l-3.5 2.5 1.4 4.3L8 9.9l-3.9 2.6 1.4-4.3L2 5.7h4.4z', 'bg' => 'bg-accent-coral/12', 'text' => 'text-accent-coral'],
        're-engagement' => ['icon' => 'M3 8a5 5 0 0 1 8.5-3.5L13 6M13 3v3h-3M13 8a5 5 0 0 1-8.5 3.5L3 10M3 13v-3h3', 'bg' => 'bg-paper-100', 'text' => 'text-ink-700'],
        'lead' => ['icon' => 'M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 14c0-3 2.7-5 6-5s6 2 6 5', 'bg' => 'bg-accent-amber/20', 'text' => 'text-ink-800'],
        'lead-nurture' => ['icon' => 'M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 14c0-3 2.7-5 6-5s6 2 6 5', 'bg' => 'bg-accent-amber/20', 'text' => 'text-ink-800'],
        'event' => ['icon' => 'M3 4h10v9H3zM3 7h10M6 2v3M10 2v3', 'bg' => 'bg-wa-bubble', 'text' => 'text-wa-deep'],
        'special-events' => ['icon' => 'M3 4h10v9H3zM3 7h10M6 2v3M10 2v3', 'bg' => 'bg-wa-bubble', 'text' => 'text-wa-deep'],
    ];

    // Three states, two columns: is_published gates draft, is_active gates
    // paused. Standalone only ever writes is_published, so 'paused' shows up
    // solely on rows authored in WaDesk — kept because those rows do arrive
    // here through an imported database, and mislabelling them 'Live' would
    // claim a flow is answering DMs when it is not.
    $state = $flow->is_published ? ($flow->is_active ? 'live' : 'paused') : 'draft';
    $stateStyle = [
        'live' => ['bg' => 'bg-wa-green/10', 'text' => 'text-wa-deep', 'border' => 'border-wa-green/30', 'dot' => 'bg-wa-green', 'label' => __('Live')],
        'paused' => ['bg' => 'bg-accent-amber/15', 'text' => 'text-ink-800', 'border' => 'border-accent-amber/40', 'dot' => 'bg-accent-amber', 'label' => __('Paused')],
        'draft' => ['bg' => 'bg-paper-50', 'text' => 'text-ink-500', 'border' => 'border-paper-200', 'dot' => 'bg-paper-300', 'label' => __('Draft')],
    ];
    $sty = $stateStyle[$state];

    $cat = $flow->category ?: 'uncategorized';
    $catSty = $categoryStyles[$cat] ?? ['icon' => 'M2 4h12v8H2zM2 7h12', 'bg' => 'bg-paper-100', 'text' => 'text-ink-700'];

    $decoded = $flow->decoded_flow_data ?? [];
    $nodeCount = is_array($decoded['flowNodes'] ?? null) ? count($decoded['flowNodes']) : 0;
    $edgeCount = is_array($decoded['flowEdges'] ?? null) ? count($decoded['flowEdges']) : 0;

    // There is no description column, so the second line is derived from the
    // category rather than left blank.
    $purpose = match ($cat) {
        'welcome' => __('Greets a new DM and routes it to products, support, or a first-order incentive.'),
        'cart', 'cart-recovery' => __('Follows up after checkout drop-off with product context and a way to ask for help.'),
        'post-purchase' => __('Checks in after delivery to earn a review, a repeat order, or feedback.'),
        're-engagement' => __('Re-opens a quiet conversation with a personalised offer or check-in.'),
        'lead', 'lead-nurture' => __('Qualifies a new lead over a few DMs before handing it to a person.'),
        'event', 'special-events' => __('Time-boxed run around a launch, a sale, or a seasonal moment.'),
        default => __('Automated Instagram DM journey built in the flow editor.'),
    };

    // Catch-all list matches InstagramFlow::syncTriggerAutomation(), which is
    // what actually decides whether the automation row runs on every message.
    $kw = trim((string) ($flow->trigger_keywords ?? ''));
    $triggerLabel = $kw === ''
        ? __('No trigger set — will not run')
        : (in_array($kw, ['*', '.*', '.+', 'any'], true) ? __('Runs on any DM') : __('Keyword') . ': ' . $kw);
@endphp

{{-- px-5 rather than the tidier p-[18px]: the shipped delete handler finds the
     row to remove with closest('[class*="px-5"]'). Once it switches to the
     [data-flow-card] hook below, this can collapse back to p-[18px]. --}}
<div class="bg-paper-0 border border-paper-200 rounded-[14px] px-5 py-[18px] transition flex flex-col hover:border-wa-deep hover:shadow-soft hover:-translate-y-px"
    data-flow-card="{{ $flow->id }}">
    <div class="flex items-start justify-between gap-2 mb-3">
        <div class="w-9 h-9 rounded-full {{ $catSty['bg'] }} {{ $catSty['text'] }} flex items-center justify-center shrink-0">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="{{ $catSty['icon'] }}" />
            </svg>
        </div>
        <span class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium border {{ $sty['bg'] }} {{ $sty['text'] }} {{ $sty['border'] }}">
            <span class="w-1.5 h-1.5 rounded-full {{ $sty['dot'] }}"></span>{{ $sty['label'] }}
        </span>
    </div>

    <div class="text-[14px] font-semibold mb-1 break-words">{{ $flow->flow_name ?: __('Untitled flow') }}</div>
    <p class="text-[12.5px] text-ink-500 leading-relaxed">{{ $purpose }}</p>

    <ul class="mt-3 space-y-1 text-[12px] text-ink-600">
        <li class="flex items-center gap-2">
            <svg viewBox="0 0 12 12" class="w-3 h-3 text-wa-deep shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M2 6.5l3 2 5-6" />
            </svg>
            {{ number_format($nodeCount) }} {{ \Illuminate\Support\Str::plural('node', $nodeCount) }} ·
            {{ number_format($edgeCount) }} {{ \Illuminate\Support\Str::plural('edge', $edgeCount) }}
        </li>
        <li class="flex items-center gap-2">
            <svg viewBox="0 0 12 12" class="w-3 h-3 text-wa-deep shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M2 6.5l3 2 5-6" />
            </svg>
            <span class="truncate">{{ $triggerLabel }}</span>
        </li>
        <li class="flex items-center gap-2">
            <svg viewBox="0 0 12 12" class="w-3 h-3 text-wa-deep shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M2 6.5l3 2 5-6" />
            </svg>
            {{ __('Edited') }} {{ $flow->updated_at?->diffForHumans() ?? __('just now') }}
        </li>
    </ul>

    <div class="mt-4 flex items-center gap-2">
        <a href="{{ route('instagram.flows.edit', $flow->id) }}"
            class="flex-1 text-center border border-paper-200 rounded-full px-3 py-1.5 text-[11.5px] font-medium hover:bg-paper-50">{{ __('Open builder') }}</a>

        {{-- Publish, not pause: this build exposes /flows/api/publish and
             /flows/api/unpublish and nothing that moves is_active, so the
             button says what it actually does. --}}
        <button type="button" data-flow-publish="{{ $flow->id }}" data-flow-published="{{ $flow->is_published ? 1 : 0 }}"
            class="border border-paper-200 rounded-full w-7 h-7 hover:bg-paper-50 flex items-center justify-center shrink-0"
            title="{{ $flow->is_published ? __('Unpublish') : __('Publish') }}">
            @if ($flow->is_published)
                <svg viewBox="0 0 12 12" class="w-3 h-3" fill="currentColor">
                    <rect x="3" y="2" width="2" height="8" rx="0.5" />
                    <rect x="7" y="2" width="2" height="8" rx="0.5" />
                </svg>
            @else
                <svg viewBox="0 0 12 12" class="w-3 h-3" fill="currentColor">
                    <polygon points="3,2 10,6 3,10" />
                </svg>
            @endif
        </button>

        {{-- data-name too: WaDesk's handler reads dataset.name, this build's
             reads dataset.flowName. Both spellings keep either one working. --}}
        <button type="button" data-flow-delete="{{ $flow->id }}" data-flow-name="{{ $flow->flow_name }}" data-name="{{ $flow->flow_name }}"
            class="border border-paper-200 rounded-full w-7 h-7 hover:bg-accent-coral/10 hover:border-accent-coral hover:text-accent-coral flex items-center justify-center shrink-0"
            title="{{ __('Delete') }}">
            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" />
            </svg>
        </button>
    </div>
</div>

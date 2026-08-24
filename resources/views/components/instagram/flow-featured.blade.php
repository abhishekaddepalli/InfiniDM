@props(['flow'])

@php
    /**
     * The one flow pinned above the grid, with a four-step preview of its
     * graph. WaDesk calls this "most-used"; there is no per-flow run counter in
     * this build, so it is the most recently touched published flow instead —
     * the one being worked on, which is what the card is actually useful for.
     */
    $cat = $flow->category ?: 'flow';
    $catLabel = ucfirst(str_replace('-', ' ', $cat));

    $data = $flow->decoded_flow_data;
    $nodes = is_array($data['flowNodes'] ?? null) ? $data['flowNodes'] : [];
    $edges = is_array($data['flowEdges'] ?? null) ? $data['flowEdges'] : [];
    $stepCount = count($nodes);
    $messageCount = count(array_filter(
        $nodes,
        fn ($n) => in_array($n['type'] ?? '', ['message', 'template', 'media', 'buttons', 'list', 'cta', 'ig_products'], true),
    ));

    $waitTotal = 0;
    foreach ($nodes as $n) {
        if (($n['type'] ?? '') !== 'delay') continue;
        $amount = (int) ($n['data']['amount'] ?? 0);
        $waitTotal += match ($n['data']['unit'] ?? 'min') {
            'sec' => max(1, intdiv($amount, 60)),
            'hour' => $amount * 60,
            'day' => $amount * 1440,
            default => $amount,
        };
    }
    $waitLabel = $waitTotal > 0
        ? '~' . ($waitTotal >= 60 ? round($waitTotal / 60, 1) . ' h' : $waitTotal . ' min') . ' ' . __('total')
        : __('no waits');

    $previewable = array_slice(array_values(array_filter($nodes, fn ($n) => !empty($n['type']))), 0, 4);

    $nodeStyle = fn (string $type) => match ($type) {
        'trigger' => 'start',
        'delay' => 'wait',
        'condition' => 'cond',
        'message', 'template', 'media', 'buttons', 'list', 'ig_products' => 'send',
        'tag', 'assign' => 'tag',
        'end' => 'end',
        default => '',
    };

    $nodeLabel = function (array $n) {
        $t = $n['type'] ?? '';
        $d = is_array($n['data'] ?? null) ? $n['data'] : [];
        return match ($t) {
            'trigger' => __('Trigger') . ' / ' . ($d['kind'] ?? 'keyword'),
            'message' => \Illuminate\Support\Str::limit((string) ($d['text'] ?? __('Send DM')), 28),
            'media' => __('Send') . ' ' . ($d['kind'] ?? 'media'),
            'buttons' => __('Quick replies'),
            'list' => __('List message'),
            'ig_products' => __('Product carousel'),
            'ig_ask' => \Illuminate\Support\Str::limit((string) ($d['prompt'] ?? __('Ask')), 28),
            'ig_lead' => __('Capture lead'),
            'ask' => \Illuminate\Support\Str::limit((string) ($d['prompt'] ?? __('Ask')), 28),
            'condition' => __('If') . ' ' . ($d['var'] ?? 'value') . ' ' . ($d['op'] ?? '==') . ' ' . ($d['value'] ?? ''),
            'delay' => __('Wait') . ' ' . ($d['amount'] ?? '?') . ' ' . ($d['unit'] ?? 'min'),
            'webhook' => __('Webhook') . ' ' . ($d['method'] ?? 'POST'),
            'ai' => 'AI ' . ($d['model'] ?? ''),
            'tag' => __('Tag') . ': ' . ($d['action'] ?? 'add') . ' ' . ($d['tag'] ?? ''),
            'assign' => __('Assign to') . ' ' . ($d['team'] ?? 'team'),
            'end' => __('End flow'),
            default => ucfirst((string) $t),
        };
    };

    $stateBadge = $flow->is_published
        ? ['bg-wa-mint', 'text-wa-deep', 'bg-wa-green', __('Live')]
        : ['bg-paper-50', 'text-ink-500', 'bg-paper-300', __('Draft')];
@endphp

<div class="border border-paper-200 rounded-2xl bg-paper-0 shadow-card overflow-hidden mb-5 grid grid-cols-1 xl:grid-cols-[1.5fr_1fr]">
    <div class="p-6 md:p-7">
        <div class="flex items-center gap-2 flex-wrap mb-4">
            <span class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-bubble text-wa-deep">{{ $catLabel }}</span>
            <span class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-deep/25">
                <svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M2 6.5l3 2 5-6" />
                </svg>
                {{ __('Most recent') }}
            </span>
            <span class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $stateBadge[0] }} {{ $stateBadge[1] }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $stateBadge[2] }}"></span>{{ $stateBadge[3] }}
            </span>
        </div>

        <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Your latest flow') }}</div>
        <h2 class="serif font-serif font-normal text-[26px] sm:text-[32px] lg:text-[36px] leading-[1.05] tracking-tight break-words">
            {{ $flow->flow_name ?: __('Untitled flow') }}</h2>

        <div class="mt-3 flex items-center flex-wrap gap-x-3 gap-y-1.5 text-[12.5px] text-ink-500 mono font-mono">
            <span class="flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M4 4v8M12 4v8M4 8h8" />
                </svg>{{ $stepCount }} {{ \Illuminate\Support\Str::plural('step', $stepCount) }}</span>
            <span class="flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <rect x="2" y="3" width="12" height="9" rx="1.5" />
                    <path d="M5 15h6" />
                </svg>{{ $messageCount }} {{ \Illuminate\Support\Str::plural('message', $messageCount) }}</span>
            <span class="flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <circle cx="8" cy="8" r="6" />
                    <path d="M8 5v3l2 2" />
                </svg>{{ $waitLabel }}</span>
            <span class="flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M3 8l3 3 7-7" />
                </svg>{{ count($edges) }} {{ \Illuminate\Support\Str::plural('connection', count($edges)) }}</span>
        </div>

        <p class="mt-4 text-[14px] leading-[1.65] text-ink-700 max-w-xl">
            {{ __('Updated') }} {{ $flow->updated_at?->diffForHumans() ?? __('just now') }}.
            {{ __('Open it to keep iterating — only a published flow answers DMs.') }}
        </p>

        <div class="mt-6 flex items-center gap-2 flex-wrap">
            <a href="{{ route('instagram.flows.edit', $flow->id) }}"
                class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                {{ __('Open flow') }}
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M3 13l10-10M5 3h8v8" />
                </svg>
            </a>
            <a href="{{ route('instagram.flows.create') }}"
                class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Start a new one') }}</a>
        </div>
    </div>

    {{-- Stripe reads the token rather than a literal, so it follows the palette
         instead of staying the WhatsApp green it was cut from. --}}
    <div class="border-t border-paper-200 xl:border-t-0 xl:border-l p-5 flex items-center justify-center bg-[repeating-linear-gradient(135deg,var(--color-wa-bubble)_0_6px,transparent_6px_12px)]">
        <div class="w-full max-w-xs">
            @if (empty($previewable))
                <div class="border border-dashed border-paper-200 bg-paper-0 rounded-[10px] px-3 py-6 text-center text-[11px] text-ink-500">
                    {{ __('This flow has no steps yet. Open the builder to add some.') }}
                </div>
            @else
                @foreach ($previewable as $n)
                    @php
                        $variant = match ($nodeStyle($n['type'] ?? '')) {
                            'start' => 'bg-wa-bubble border-solid border-wa-green text-wa-deep font-medium',
                            'send' => 'bg-wa-bubble border-solid border-wa-deep/30 text-wa-deep',
                            'wait' => 'bg-accent-amber/15 border-solid border-accent-amber text-ink-800',
                            'cond' => 'bg-accent-amber/25 border-solid border-accent-amber text-ink-800',
                            'tag' => 'bg-paper-100 border-solid border-paper-300 text-ink-700',
                            'end' => 'bg-accent-coral/12 border-solid border-accent-coral text-accent-coral font-medium',
                            default => '',
                        };
                    @endphp
                    <div class="bg-paper-0 border border-dashed border-paper-300 rounded-[10px] px-2.5 py-2 text-[11px] text-ink-700 flex items-center gap-1.5 {{ $variant }}">
                        <svg viewBox="0 0 12 12" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="6" cy="6" r="3" />
                        </svg>
                        <span class="truncate">{{ $nodeLabel($n) }}</span>
                    </div>
                    @if (!$loop->last)
                        <div class="flex justify-center text-ink-500 my-1.5">
                            <svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                                <path d="M6 2v8M3.5 7.5 6 10l2.5-2.5" />
                            </svg>
                        </div>
                    @endif
                @endforeach
                @if ($stepCount > count($previewable))
                    <div class="text-center text-[10px] mono font-mono text-ink-500 mt-2">
                        + {{ $stepCount - count($previewable) }}
                        {{ \Illuminate\Support\Str::plural('more step', $stepCount - count($previewable)) }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>

@props([
    /** Eyebrow above the headline. */
    'kicker' => 'FAQ',
    /** Big serif headline (HTML allowed for italic spans). */
    'headline' => null,
    /** Subtitle paragraph below the headline. */
    'subtitle' => null,
    /** Items: array of ['q' => string, 'a' => string, 'open' => bool?]. */
    'items' => null,
    /** Page namespace (e.g. 'contact', 'features') for page-scoped editable keys. */
    'scope' => null,
])

@php
    $ns = $scope ? ($scope . '.faq') : 'faq';

    $itemsAreCustom = $items !== null;
    $itemsEditable = ! $itemsAreCustom || (bool) $scope;

    $kicker = fcp("{$ns}.kicker_text", $kicker);
    $headline = fcp("{$ns}.headline", $headline ?? 'Frequently <span class="italic text-wa-deep">asked.</span>');
    $subtitle = fcp(
        "{$ns}.subtitle",
        $subtitle ?? __('Still unsure? Email :email — a real human replies inside 4 hours.', ['email' => brand_email('support')]),
    );

    if ($itemsAreCustom && $scope) {
        $items = collect($items)->values()->map(function ($it, $i) use ($ns) {
            $n = $i + 1;
            return [
                'q'    => fcp("{$ns}.faq{$n}_q", $it['q'] ?? ''),
                'a'    => fcp("{$ns}.faq{$n}_a", $it['a'] ?? ''),
                'open' => $it['open'] ?? false,
            ];
        })->all();
    }

    $items = $items ?? [
        ['q' => fcp("{$ns}.faq1_q", __('Do I need a special Instagram account to start?')), 'a' => fcp("{$ns}.faq1_a", __('Yes — a free Instagram Professional (Business or Creator) account linked to a Facebook Page. :brand connects it in one click through Meta login; nothing to install.', ['brand' => brand_name()])), 'open' => true],
        ['q' => fcp("{$ns}.faq2_q", __('How fast does automation reply?')), 'a' => fcp("{$ns}.faq2_a", __('Instantly. Comment, story-reply and DM triggers fire within a couple of seconds of the event landing on Meta\'s webhook.'))],
        ['q' => fcp("{$ns}.faq3_q", __('Can I migrate from ManyChat or Chatfuel?')), 'a' => fcp("{$ns}.faq3_a", __('Yes — rebuild any flow in our visual builder in minutes, or ask us for free white-glove migration on Pro & Scale.'))],
        ['q' => fcp("{$ns}.faq4_q", __('What can I automate?')), 'a' => fcp("{$ns}.faq4_a", __('Comment replies, story-mention replies, keyword DMs, welcome messages, lead capture, product links, and full AI conversations.'))],
        ['q' => fcp("{$ns}.faq5_q", __('Where is my data stored?')), 'a' => fcp("{$ns}.faq5_a", __('Encrypted at rest (AES-256) and in transit (TLS 1.3). GDPR-native, with EU, US or India residency on Scale.'))],
    ];
@endphp

<section class="bg-paper-50 hairline-t hairline-b" data-fc-section="faq">
    <div class="max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-7 py-28">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
            <div class="col-span-12 lg:col-span-4">
                <div class="badge-num">— <span>{{ $kicker }}</span></div>
                <h2 class="serif text-[40px] sm:text-[52px] lg:text-[64px] leading-[0.95] mt-4">
                    {!! $headline !!}</h2>
                <p class="text-[13px] text-ink-600 mt-3">{{ $subtitle }}</p>
            </div>

            <div class="col-span-12 lg:col-span-8 reveal" style="--d:120ms">
                <div class="hairline rounded-2xl bg-white divide-y divide-paper-200">
                    @foreach ($items as $i => $item)
                        @php $isOpen = $item['open'] ?? false; @endphp
                        <details class="details group p-5" @if ($isOpen) open @endif>
                            <summary class="flex items-center justify-between">
                                <span class="text-[15px] font-medium">{{ $item['q'] }}</span>
                                <span
                                    class="w-7 h-7 rounded-full hairline flex items-center justify-center shrink-0 group-open:bg-wa-deep group-open:text-paper-0 transition">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M3 8h10" />
                                        <path d="M8 3v10" class="group-open:hidden" />
                                    </svg>
                                </span>
                            </summary>
                            <p class="text-[13px] text-ink-600 mt-3 leading-relaxed">{{ $item['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

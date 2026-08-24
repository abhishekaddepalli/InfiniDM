{{--
    Everything the left rail no longer carries.

    The rail had grown to eighteen icons — taller than most laptop viewports, so
    the last few were only reachable by scrolling a nav. It now keeps the seven
    an operator touches daily and hands the rest to this page as cards, which is
    the same split WaDesk uses for its own /more.

    Cards are grouped by what the tool is FOR, not by which controller owns it:
    someone looking for "Orders" is thinking about selling, not about routing.
--}}
<x-layouts.instagram :title="__('More')" ig-active="more" page="instagram-more">

    @php
        // Local to this view on purpose. The rail keeps its own list, so neither
        // file has to know about the other — the only shared contract is the URL.
        $groups = [
            [
                'title' => __('Content'),
                'blurb' => __('What you publish, and what people say about it.'),
                'items' => [
                    ['href' => url('/instagram/posts'),     'label' => __('My posts'),  'desc' => __('Everything published, with its comments'),
                     'svg' => '<rect x="2.6" y="2.6" width="4.6" height="4.6" rx="1"/><rect x="7.7" y="2.6" width="4.6" height="4.6" rx="1"/><rect x="12.8" y="2.6" width="4.6" height="4.6" rx="1"/><rect x="2.6" y="7.7" width="4.6" height="4.6" rx="1"/><rect x="7.7" y="7.7" width="4.6" height="4.6" rx="1"/><rect x="12.8" y="7.7" width="4.6" height="4.6" rx="1"/><rect x="2.6" y="12.8" width="4.6" height="4.6" rx="1"/><rect x="7.7" y="12.8" width="4.6" height="4.6" rx="1"/><rect x="12.8" y="12.8" width="4.6" height="4.6" rx="1"/>'],
                    ['href' => url('/instagram/comments'),  'label' => __('Moderate'),  'desc' => __('Review and hide comments across your posts'),
                     'svg' => '<path d="M10 2.6 16 4.8v4.2c0 3.6-2.5 6.3-6 7.4-3.5-1.1-6-3.8-6-7.4V4.8z"/><path d="m7.6 9.8 1.6 1.6 3.2-3.3"/>'],
                    ['href' => url('/instagram/calendar'),  'label' => __('Calendar'),  'desc' => __('Everything scheduled, on one timeline'),
                     'svg' => '<rect x="3" y="4.5" width="14" height="12.5" rx="2.6"/><path d="M3 8.4h14M6.8 3v3M13.2 3v3"/>'],
                    ['href' => url('/instagram/reposter'),  'label' => __('Reels Autopilot'), 'desc' => __('Auto-repost reels on a schedule you set once'),
                     'svg' => '<rect x="3" y="4.3" width="14" height="11.4" rx="2.6"/><path d="M8.6 7.8 12.6 10l-4 2.2z"/>'],
                    ['href' => url('/instagram/files'),     'label' => __('Files'),     'desc' => __('Your personal media library — uploads and AI images'),
                     'svg' => '<path d="M2.6 5.2A1.4 1.4 0 0 1 4 3.8h2.9l1.4 1.7h5.7A1.4 1.4 0 0 1 15.4 6.9v7A1.4 1.4 0 0 1 14 15.3H4a1.4 1.4 0 0 1-1.4-1.4z"/><path d="m5.6 12.4 2.2-2.4 1.5 1.5 1.9-2.1 1.6 1.9"/>'],
                    ['href' => url('/instagram/watermark'), 'label' => __('Watermark'), 'desc' => __('Stamp your logo or text onto images before they publish'),
                     'svg' => '<path d="M10 2.6 16 4.8v4.2c0 3.6-2.5 6.3-6 7.4-3.5-1.1-6-3.8-6-7.4V4.8z"/><path d="M7.4 9.4h5.2M7.4 11.8h5.2M8.4 7h3.2"/>'],
                ],
            ],
            [
                'title' => __('Messaging'),
                'blurb' => __('Reaching people, and the pieces your replies are built from.'),
                'items' => [
                    ['href' => url('/instagram/broadcast'), 'label' => __('Bulk DM'),   'desc' => __('Send one message to a segment of your audience'),
                     'svg' => '<path d="M17.4 2.6 2.7 8.9l5.7 2.2 2.2 5.7z"/><path d="M17.4 2.6 8.4 11.1"/>'],
                    ['href' => url('/instagram/templates'), 'label' => __('Templates'), 'desc' => __('Saved replies to reuse across flows and the inbox'),
                     'svg' => '<rect x="3.3" y="3.3" width="13.4" height="13.4" rx="2.6"/><path d="M3.3 7.6h13.4M7.7 7.6v9.1"/>'],
                    ['href' => url('/instagram/message-history'), 'label' => __('Message History'), 'desc' => __('A searchable log of every DM sent and received'),
                     'svg' => '<circle cx="10" cy="10" r="7.4"/><path d="M10 5.6V10l3 1.8"/>'],
                    ['href' => url('/instagram/attributes'),'label' => __('Attributes'),'desc' => __('Custom fields a flow can read and write'),
                     'svg' => '<path d="M10.6 2.6H4.2a1.6 1.6 0 0 0-1.6 1.6v6.4c0 .43.17.83.47 1.13l5.8 5.8a1.6 1.6 0 0 0 2.26 0l5.2-5.2a1.6 1.6 0 0 0 0-2.26l-5.8-5.8a1.6 1.6 0 0 0-1.13-.47Z"/><circle cx="6.4" cy="6.4" r=".9" fill="currentColor" stroke="none"/>'],
                    ['href' => url('/instagram/contacts'), 'label' => __('Contacts'), 'desc' => __('Everyone who DMed you, with their saved attribute values'),
                     'svg' => '<circle cx="7" cy="6" r="2.6"/><path d="M2.6 15a4.4 4.4 0 0 1 8.8 0"/><path d="M12.2 4.2a2.4 2.4 0 0 1 0 4.4M13 15a4 4 0 0 0-2.2-3.4"/>'],
                    ['href' => url('/instagram/discovery'), 'label' => __('Discovery'), 'desc' => __('Find accounts and posts worth engaging'),
                     'svg' => '<circle cx="8.6" cy="8.6" r="5.4"/><path d="m12.6 12.6 4 4"/>'],
                ],
            ],
            [
                'title' => __('Selling'),
                'blurb' => __('Turning a conversation into an order.'),
                'items' => [
                    ['href' => url('/instagram/commerce'),  'label' => __('Shop'),   'desc' => __('Products you can send straight into a DM'),
                     'svg' => '<path d="M4.6 6.6h10.8l-.9 9.4a1 1 0 0 1-1 .9H6.5a1 1 0 0 1-1-.9z"/><path d="M7.4 6.6V5.2a2.6 2.6 0 0 1 5.2 0v1.4"/>'],
                    ['href' => url('/instagram/orders'),    'label' => __('Orders'), 'desc' => __('What customers bought through a conversation'),
                     'svg' => '<path d="M3 5h2l1.5 8.5a1 1 0 0 0 1 .8h6.7a1 1 0 0 0 1-.8L17 7H6"/><circle cx="8" cy="16.5" r="1"/><circle cx="15" cy="16.5" r="1"/>'],
                    ['href' => url('/instagram/leads'),     'label' => __('Leads'),  'desc' => __('Contacts captured by a flow, with their answers'),
                     'svg' => '<circle cx="8" cy="6.8" r="2.9"/><path d="M2.9 16.3c0-2.7 2.3-4.3 5.1-4.3 1 0 1.9.2 2.7.6"/><path d="M13.6 12.3v4.1M11.6 14.3h4.1"/>'],
                    ['href' => url('/instagram/ads'),       'label' => __('Ads'),    'desc' => __('Click-to-DM campaigns and how they performed'),
                     'svg' => '<circle cx="10" cy="10" r="7"/><circle cx="10" cy="10" r="3.1"/><circle cx="10" cy="10" r=".55" fill="currentColor" stroke="none"/>'],
                ],
            ],
            [
                'title' => __('Account & settings'),
                'blurb' => __('Your profile, plan and how the app looks.'),
                'items' => [
                    ['href' => url('/account'), 'label' => __('Account'), 'desc' => __('Profile, plan & billing, invoices and password'),
                     'svg' => '<circle cx="10" cy="6.4" r="3.1"/><path d="M4 16.4a6 6 0 0 1 12 0"/>'],
                    ['href' => url('/instagram/plans'), 'label' => __('Plans'), 'desc' => __('Upgrade or change your plan'),
                     'svg' => '<path d="M10.6 2.6H4.2a1.6 1.6 0 0 0-1.6 1.6v6.4c0 .43.17.83.47 1.13l5.8 5.8a1.6 1.6 0 0 0 2.26 0l5.2-5.2a1.6 1.6 0 0 0 0-2.26l-5.8-5.8a1.6 1.6 0 0 0-1.13-.47Z"/><circle cx="6.4" cy="6.4" r=".9" fill="currentColor" stroke="none"/>'],
                    ['href' => url('/instagram/theme'), 'label' => __('Theme'), 'desc' => __('Colours, mode and text size for this device'),
                     'svg' => '<circle cx="10" cy="10" r="7.4"/><circle cx="7" cy="7.6" r="1" fill="currentColor" stroke="none"/><circle cx="12.6" cy="7.6" r="1" fill="currentColor" stroke="none"/><circle cx="13" cy="11.6" r="1" fill="currentColor" stroke="none"/>'],
                    ['href' => url('/instagram/notifications'), 'label' => __('Notifications'), 'desc' => __('What you get pinged about'),
                     'svg' => '<path d="M10 3a4 4 0 0 0-4 4c0 3.6-1.4 4.8-1.4 4.8h10.8S14 10.6 14 7a4 4 0 0 0-4-4z"/><path d="M8.4 14.4a1.6 1.6 0 0 0 3.2 0"/>'],
                ],
            ],
        ];

        // Plan gating for these tool cards — same model as the rail. Each tool
        // path maps to an InstagramGate feature; when the plan doesn't include it
        // the card gets a PREMIUM crown and routes to the pricing page instead.
        // Paths not listed (Files, Message History, Contacts, Account, Theme…) are
        // baseline and never locked. Admins bypass inside allows().
        $moreFeat = [
            '/instagram/posts'     => 'posts',
            '/instagram/comments'  => 'comments',
            '/instagram/calendar'  => 'scheduler',
            '/instagram/reposter'  => 'reposter',
            '/instagram/broadcast' => 'broadcast',
            '/instagram/templates' => 'templates',
            '/instagram/discovery' => 'discovery',
            '/instagram/commerce'  => 'commerce',
            '/instagram/orders'    => 'orders',
            '/instagram/leads'     => 'leads',
            '/instagram/ads'       => 'ads',
        ];
        $moreLocked = function ($href) use ($moreFeat) {
            $path = parse_url($href, PHP_URL_PATH) ?: '';
            foreach ($moreFeat as $suffix => $feat) {
                if (str_ends_with($path, $suffix)) {
                    return ! \App\Services\Instagram\InstagramGate::allows($feat);
                }
            }
            return false;
        };
        $morePlansHref = url('/instagram/plans');
        $moreCrown = '<svg viewBox="0 0 20 20" class="w-4 h-4" fill="currentColor" aria-hidden="true"><path d="M2.5 6.4l3 2.3 3.2-4.6a1.6 1.6 0 0 1 2.6 0l3.2 4.6 3-2.3c.8-.6 1.9.1 1.7 1.1l-1.5 7.2a1.3 1.3 0 0 1-1.3 1H3.6a1.3 1.3 0 0 1-1.3-1L.8 7.5C.6 6.5 1.7 5.8 2.5 6.4z"/></svg>';
    @endphp

    <div class="px-4 sm:px-7 py-7">

        <div class="mb-7">
            <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('All tools') }}
            </div>
            <h1 class="serif font-serif font-normal text-[32px] sm:text-[38px] lg:text-[44px] leading-[1.05] tracking-tight">
                {{ __('Everything') }} <span class="ig-text italic">{{ __('else') }}</span>.
            </h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                {{ __('The tools you set up once and leave running. The sidebar keeps what you reach for every day.') }}
            </p>
        </div>

        @foreach ($groups as $group)
            <section class="mb-8">
                <div class="mb-3">
                    <h2 class="font-serif text-[20px] leading-tight">{{ $group['title'] }}</h2>
                    <p class="text-[12px] text-ink-500 mt-0.5">{{ $group['blurb'] }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach ($group['items'] as $item)
                        @php $locked = $moreLocked($item['href']); @endphp
                        <a href="{{ $locked ? $morePlansHref : $item['href'] }}"
                            class="group relative bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card hover:border-wa-deep hover:shadow-soft transition flex flex-col">
                            @if ($locked)
                                {{-- Premium crown in the card corner (icon, not text). --}}
                                <span class="absolute top-3 right-3" style="color:#E8A62E" title="{{ __('Premium — upgrade to unlock') }}">{!! $moreCrown !!}</span>
                            @endif
                            <span class="w-11 h-11 rounded-xl bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                <svg viewBox="0 0 20 20" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $item['svg'] !!}</svg>
                            </span>
                            <div class="mt-3">
                                <div class="font-semibold text-[13.5px] text-ink-900 flex items-center gap-1.5">{{ $item['label'] }}@if ($locked)<span class="text-[9px] font-mono uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-accent-amber/15 text-accent-amber">{{ __('Premium') }}</span>@endif</div>
                                <div class="text-[11.5px] text-ink-500 mt-0.5 leading-snug">{{ $item['desc'] }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach

    </div>
</x-layouts.instagram>

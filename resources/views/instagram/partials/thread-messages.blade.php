{{-- Thread message bubbles — the closures + @foreach extracted from inbox.blade so the "load older" endpoint (InstagramController::inboxOlder) renders older messages with the EXACT same markup. Needs $messages. --}}
                    @php
                        $lastDay = null;
                        // Render an inbound/outbound media attachment (image / reel /
                        // video / audio / share) as its real element, not "[media]".
                        $renderIgMedia = function ($msg) {
                            $t = $msg->attachment_type; $u = $msg->attachment_url;
                            if (!$t) return '';
                            $url = e($u);
                            if ($u && in_array($t, ['image', 'story_mention'])) {
                                return '<a href="' . $url . '" target="_blank" rel="noopener"><img src="' . $url . '" alt="" loading="lazy" class="rounded-2xl max-w-full max-h-72 object-cover mb-1"></a>';
                            }
                            // Video / reel — Instagram's card, not a raw <video controls>.
                            // The browser's default chrome (grey bar, "0:00", kebab menu)
                            // is desktop-Chrome furniture bolted onto a chat bubble: it
                            // ignored our radius and looked broken. Poster frame + one
                            // play affordance; real controls appear only once playing.
                            if ($u && in_array($t, ['video', 'ig_reel', 'reel'])) {
                                $isReel = in_array($t, ['ig_reel', 'reel']);
                                // Meta ships the reel's caption as payload.title — print it
                                // over the video like the app does. The reel's AUTHOR is NOT
                                // in the webhook payload and there's no endpoint to resolve
                                // someone else's media, so no @handle chip: an invented one
                                // would be worse than none.
                                $cap = trim((string) (($msg->meta['title'] ?? '')));
                                $capHtml = $cap !== ''
                                    ? '<div class="absolute inset-x-0 bottom-0 pt-8 pb-2.5 px-2.5 bg-gradient-to-t from-black/75 to-transparent pointer-events-none">'
                                        . '<div class="text-[11.5px] text-white font-medium leading-snug line-clamp-3 drop-shadow">' . e($cap) . '</div></div>'
                                    : '';
                                // Tapping plays the reel in an in-app modal (JS below). The
                                // href stays as a no-JS fallback (new-tab). Meta's signed CDN
                                // url won't decode in an inline <video>, so the modal streams
                                // it in an <iframe> — same document-level fetch the browser
                                // uses for a direct open, which does play. `muted` on the
                                // thumbnail gives the best shot at a real poster frame.
                                return '<a href="' . $url . '" target="_blank" rel="noopener" data-reel="' . $url . '" data-reel-id="' . (int) $msg->id . '" data-cap="' . e($cap) . '" class="ig-reel-open ig-video relative block rounded-2xl overflow-hidden bg-black mb-1 w-[188px] max-w-full cursor-pointer">'
                                    . '<video src="' . $url . '" muted playsinline preload="metadata" class="block w-full ' . ($isReel ? 'aspect-[9/16]' : 'max-h-[280px]') . ' object-cover bg-black"></video>'
                                    . '<span class="absolute inset-0 grid place-items-center bg-black/15 hover:bg-black/25 transition">'
                                    . '<span class="w-10 h-10 rounded-full bg-black/55 grid place-items-center shadow-soft"><svg viewBox="0 0 24 24" class="w-4.5 h-4.5 text-white translate-x-[1px]" fill="currentColor"><path d="M8 5.5v13l11-6.5z"/></svg></span>'
                                    . '</span>'
                                    . $capHtml
                                    . ($isReel
                                        ? '<span class="absolute left-2 top-2 w-5 h-5 rounded-full bg-black/55 grid place-items-center pointer-events-none"><svg viewBox="0 0 24 24" class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><path d="M3 8.5h18M9 3l3 5.5M15 3l3 5.5"/><path d="m10.5 12.5 4 2.2-4 2.3z" fill="currentColor" stroke="none"/></svg></span>'
                                        : '')
                                    . '</a>';
                            }
                            if ($u && $t === 'audio') {
                                return '<audio src="' . $url . '" controls preload="none" class="w-56 max-w-full mb-1"></audio>';
                            }
                            // Story reply → a small story thumbnail + "Replied to your
                            // story" caption above their reply text (like the IG app).
                            if ($t === 'story_reply') {
                                $isVid = $u && preg_match('/\.(mp4|mov|webm|m4v)$/i', (string) $u);
                                $thumb = $u
                                    ? ($isVid
                                        ? '<video src="' . $url . '" muted preload="metadata" class="w-9 h-14 rounded-lg object-cover bg-black shrink-0"></video>'
                                        : '<img src="' . $url . '" alt="" loading="lazy" class="w-9 h-14 rounded-lg object-cover bg-paper-100 shrink-0">')
                                    : '<div class="w-9 h-14 rounded-lg bg-paper-100 grid place-items-center shrink-0"><svg viewBox="0 0 20 20" class="w-4 h-4 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="3" width="12" height="14" rx="3"/></svg></div>';
                                $inner = '<div class="flex items-center gap-2 mb-1.5">' . $thumb
                                    . '<div class="text-[10.5px] text-ink-400 flex items-center gap-1"><svg viewBox="0 0 16 16" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 4 4 9l5 5M4 9h7a4 4 0 0 1 4 4v1"/></svg>' . e(__('Replied to your story')) . '</div></div>';
                                return $u ? '<a href="' . $url . '" target="_blank" rel="noopener">' . $inner . '</a>' : $inner;
                            }
                            // Unsent by the customer → "This message was deleted" with a
                            // slashed-circle icon (mirrors Instagram), never a blank bubble.
                            if ($t === 'deleted') {
                                $delIcon = '<svg viewBox="0 0 24 24" class="w-3.5 h-3.5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M6.4 6.4l11.2 11.2"/></svg>';
                                return '<div class="inline-flex items-center gap-1.5 text-[12px] italic opacity-70">' . $delIcon . '<span>' . e(__('This message was deleted')) . '</span></div>';
                            }
                            // Shared reel/post (or a reel whose video Meta withheld on
                            // read-back) → tappable card, links out when we have the
                            // permalink. Prevents an empty bubble for DM shares.
                            if (in_array($t, ['share', 'ig_reel', 'reel'])) {
                                $icon = '<svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 text-ig-pink" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4.5" width="18" height="15" rx="3.5"/><path d="M3 9h18M8.5 4.5 11 9M14 4.5l2.5 4.5"/><path d="M10.5 12.2v4.1l3.6-2.05z" fill="currentColor" stroke="none"/></svg>';
                                // A DM-shared reel carries ONLY a permalink — no video src —
                                // so it can't use the <video> tile above. It rendered as a
                                // small grey row instead, which made OUR shares look nothing
                                // like the ones we receive. The tile above is just a black
                                // box + play badge + caption (the <video> rarely paints a
                                // poster anyway), so build the same tile without the source:
                                // identical bubble either way, and the tap still opens the
                                // embed modal via ig-reel-open.
                                if ($u) {
                                    $rcap = trim((string) ($cap ?? ''));
                                    return '<a href="' . $url . '" target="_blank" rel="noopener" data-reel="' . $url . '" data-reel-id="' . (int) $msg->id . '" data-cap="' . e($rcap) . '" class="ig-reel-open relative block rounded-2xl overflow-hidden bg-black mb-1 w-[188px] max-w-full aspect-[9/16] cursor-pointer">'
                                        . '<span class="absolute inset-0 grid place-items-center bg-black/15 hover:bg-black/25 transition">'
                                        . '<span class="w-10 h-10 rounded-full bg-black/55 grid place-items-center shadow-soft"><svg viewBox="0 0 24 24" class="w-4.5 h-4.5 text-white translate-x-[1px]" fill="currentColor"><path d="M8 5.5v13l11-6.5z"/></svg></span>'
                                        . '</span>'
                                        . ($rcap !== ''
                                            ? '<div class="absolute inset-x-0 bottom-0 pt-8 pb-2.5 px-2.5 bg-gradient-to-t from-black/75 to-transparent pointer-events-none"><div class="text-[11.5px] text-white font-medium leading-snug line-clamp-3 drop-shadow">' . e($rcap) . '</div></div>'
                                            : '')
                                        . '<span class="absolute left-2 top-2 w-5 h-5 rounded-full bg-black/55 grid place-items-center pointer-events-none"><svg viewBox="0 0 24 24" class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><path d="M3 8.5h18M9 3l3 5.5M15 3l3 5.5"/><path d="m10.5 12.5 4 2.2-4 2.3z" fill="currentColor" stroke="none"/></svg></span>'
                                        . '</a>';
                                }
                                // No permalink at all — nothing to open, so keep the label row.
                                return '<div class="flex items-center gap-2.5 rounded-2xl border border-paper-200 bg-paper-50 px-3 py-2.5 mb-1 min-w-[190px] max-w-[240px]">' . $icon
                                    . '<div class="min-w-0"><div class="text-[12.5px] font-medium truncate">' . e(__('Shared a reel')) . '</div>'
                                    . '<div class="text-[10.5px] text-ink-400 truncate">' . e(__('Reels shared in chat open in the Instagram app')) . '</div></div></div>';
                            }
                            if ($u) {
                                return '<a href="' . $url . '" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-[12px] underline mb-1">' . e(ucfirst(str_replace('_', ' ', $t))) . '</a>';
                            }
                            return '<div class="text-[11.5px] italic opacity-80 mb-1">' . e(ucfirst(str_replace('_', ' ', $t))) . '</div>';
                        };

                        // ── Per-message reactions (Team-Inbox parity, Instagram theme) ──
                        // Instagram's reaction API accepts ANY emoji, stored verbatim. Legacy
                        // rows saved the old keyword (love/like/…) — map those so they still
                        // render; everything else is already the emoji itself.
                        $igLegacyEmoji = ['like' => '👍', 'love' => '❤️', 'haha' => '😂', 'wow' => '😮', 'sad' => '😢', 'angry' => '😡'];
                        $igToEmoji = fn ($v) => $igLegacyEmoji[(string) $v] ?? (string) $v;
                        // Instagram-style hover actions: react (smiley) · reply (arrow) ·
                        // more (3-dot), in a horizontal row beside the bubble — out →
                        // the row sits on the LEFT of the operator bubble, in → on the
                        // RIGHT of the customer bubble (mirrors the Instagram app). Plain
                        // icons, no chips. Classes drive the same delegated JS handlers.
                        $igMsgActions = function ($out) {
                            $btn = 'w-6 h-6 grid place-items-center text-ink-400 hover:text-ink-700 transition';
                            $smiley = '<button type="button" class="ig-react-trigger ' . $btn . '" title="' . __('React') . '" aria-label="' . __('React') . '"><svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="1" fill="currentColor" stroke="none"/><path d="M8.5 14a4.5 4.5 0 0 0 7 0"/></svg></button>';
                            $reply  = '<button type="button" class="ig-reply-trigger ' . $btn . '" title="' . __('Reply') . '" aria-label="' . __('Reply') . '"><svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 7 4 12l5 5M4 12h9a5 5 0 0 1 5 5v1"/></svg></button>';
                            $more   = '<button type="button" class="ig-msg-menu ' . $btn . '" title="' . __('More') . '" aria-label="' . __('More') . '"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="currentColor"><circle cx="8" cy="3" r="1.35"/><circle cx="8" cy="8" r="1.35"/><circle cx="8" cy="13" r="1.35"/></svg></button>';
                            $pos = $out ? 'right-full mr-1' : 'left-full ml-1';
                            return '<div class="ig-msg-actions absolute top-1/2 -translate-y-1/2 ' . $pos . ' flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition">'
                                . ($out ? $more . $reply . $smiley : $smiley . $reply . $more) . '</div>';
                        };
                        // WhatsApp-style corner reaction pip — bottom corner of the bubble,
                        // out → bottom-left, in → bottom-right (matches Team Inbox reactionPip).
                        // Rendered hidden when empty so the JS can fill/clear it optimistically.
                        $igReactPip = function ($value, $out) use ($igToEmoji) {
                            $emoji = $igToEmoji($value);
                            $has = $emoji !== '';
                            return '<span class="ig-react-pip absolute -bottom-2 ' . ($out ? 'left-2' : 'right-2') . ' bg-paper-0 border border-paper-200 rounded-full px-1.5 py-0.5 text-[13px] shadow-sm leading-none' . ($has ? '' : ' hidden') . '" title="' . __('Reaction') . '"' . ($has ? ' data-reaction="' . e($emoji) . '"' : '') . '>' . e($emoji) . '</span>';
                        };
                        // (The 3-dot "More" trigger now lives inside $igMsgActions above —
                        //  Forward / Copy / Translate / Pin / Report menu is built in JS.)
                        // Pin badge — always visible when the message is pinned (JS toggles it).
                        $igPinBadge = function ($out, $pinned) {
                            return '<span class="ig-pin-badge absolute -top-2.5 ' . ($out ? 'right-2' : 'left-2') . ' w-4 h-4 rounded-full bg-ig-pink text-white grid place-items-center shadow-sm' . ($pinned ? '' : ' hidden') . '" title="' . __('Pinned') . '">'
                                . '<svg viewBox="0 0 24 24" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2h8M11 2l1 7-3 2v2h6v-2l-3-2 1-7M12 15v7"/></svg>'
                                . '</span>';
                        };
                        // Interactive payload of a SENT template (quick replies / buttons /
                        // carousel) → render below the text so the thread shows what was
                        // actually sent, matching the composer preview + what the customer sees.
                        $igTplBlock = function ($meta) {
                            $meta = (array) $meta;
                            $tpl  = (string) ($meta['tpl'] ?? '');
                            if ($tpl === 'quick_replies' || $tpl === 'buttons') {
                                $btns = array_values((array) ($meta['buttons'] ?? []));
                                if (empty($btns)) return '';
                                $rows = '';
                                foreach ($btns as $b) {
                                    $title = e((string) ($b['title'] ?? 'Button'));
                                    $icon = !empty($b['url'])
                                        ? '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6.5 9.5 9.5 6.5M7 4.5 8.5 3a2.5 2.5 0 0 1 3.5 3.5L10.5 8M9 11.5 7.5 13A2.5 2.5 0 0 1 4 9.5L5.5 8"/></svg>'
                                        : '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13 8a5 5 0 1 1-1.5-3.5M13 3v2h-2"/></svg>';
                                    $rows .= '<div class="bg-white/95 rounded-xl text-center text-[12px] text-ig-pink font-medium py-2 flex items-center justify-center gap-1.5">' . $icon . '<span class="truncate">' . $title . '</span></div>';
                                }
                                return '<div class="mt-2 space-y-1.5">' . $rows . '</div>';
                            }
                            if ($tpl === 'carousel') {
                                $cards = array_values((array) ($meta['cards'] ?? []));
                                if (empty($cards)) return '';
                                $slides = '';
                                foreach ($cards as $c) {
                                    $img = (string) ($c['image'] ?? '');
                                    $thumb = $img !== ''
                                        ? '<div class="h-20 bg-center bg-cover" style="background-image:url(\'' . e($img) . '\')"></div>'
                                        : '<div class="h-20 bg-black/10"></div>';
                                    $sub = e((string) ($c['subtitle'] ?? ''));
                                    $slides .= '<div class="w-32 shrink-0 rounded-xl overflow-hidden bg-[#ffffff]">' . $thumb
                                        . '<div class="px-2 py-1.5"><div class="text-[11px] font-semibold text-[#262626] truncate">' . e((string) ($c['title'] ?? '')) . '</div>'
                                        . ($sub !== '' ? '<div class="text-[10px] text-[#8e8e8e] truncate">' . $sub . '</div>' : '') . '</div></div>';
                                }
                                return '<div class="mt-2 flex gap-2 overflow-x-auto pb-1">' . $slides . '</div>';
                            }
                            return '';
                        };
                    @endphp
                    @foreach ($messages as $msg)
                        {{-- Skip leftover blank bubbles (empty message, no media, no template) — unsent/unsupported. --}}
                        @if (trim((string) $msg->body) === '' && !$msg->attachment_type && !$msg->meta) @continue @endif
                        {{-- Day separator, Instagram-style: centred, quiet, once per day.
                             Rendered browser-LOCAL via .ig-daystamp (server time is UTC —
                             a stamp that says a different day than the bubble times under
                             it is worse than none). --}}
                        @php $day = optional($msg->created_at)->format('Y-m-d'); @endphp
                        @if ($day && $day !== $lastDay)
                            <div class="flex justify-center py-1.5">
                                <span class="ig-daystamp mono text-[10px] text-ink-400 tracking-[0.06em] px-2.5 py-1 rounded-full bg-paper-100/70"
                                      data-ts="{{ $msg->created_at->toIso8601String() }}">{{ $msg->created_at->format('d/m/Y, H:i') }}</span>
                            </div>
                            @php $lastDay = $day; @endphp
                        @endif

                        {{-- Media with no caption gets NO bubble — Instagram renders the
                             photo/video itself, not a padded card wrapped around it. The
                             wrapper was drawing a big tinted panel behind every image. --}}
                        @php
                            $bare = in_array($msg->attachment_type, ['image', 'video', 'ig_reel', 'reel', 'story_mention'], true)
                                    && trim((string) $msg->body) === '' && !$msg->meta;
                        @endphp

                        @if ($msg->direction === 'out')
                            @php $auto = $msg->source && $msg->source !== 'manual'; @endphp
                            <div class="flex justify-end ig-msg-row group"@if ($msg->mid) data-mid="{{ $msg->mid }}" data-body="{{ $msg->body }}"@endif>
                                <div class="{{ $bare ? 'max-w-[64%] relative' : ($auto ? 'bubble-auto' : 'bubble-out').' rounded-[22px] px-3.5 py-2 max-w-[64%] shadow-sm relative' }}">
                                    {!! $renderIgMedia($msg) !!}
                                    @if (trim((string) $msg->body) !== '')<div class="ig-msg-text text-[13.5px] whitespace-pre-wrap break-words">{{ $msg->body }}</div>@endif
                                    {!! $igTplBlock($msg->meta) !!}
                                    {{-- Auto-sent marker is now a tiny star tucked beside the time
                                         (a full uppercase header row made the bubble twice as tall). --}}
                                    <div class="flex items-center justify-end gap-1 {{ $bare ? 'mt-1' : 'mt-1.5' }}">
                                        @if ($auto)<svg viewBox="0 0 12 12" class="w-2 h-2 shrink-0 {{ $bare ? 'text-ink-400' : 'text-white/70' }}" fill="currentColor"><title>{{ __('auto') }} · {{ $msg->source }}</title><path d="M6 1l1.5 3 3 .5-2 2 .5 3L6 11 3 9.5l.5-3-2-2 3-.5z"/></svg>@endif
                                        <span class="text-[9px] {{ $bare ? 'text-ink-400' : 'text-white/70' }} mono ig-localtime" data-ts="{{ optional($msg->created_at)->toIso8601String() }}">{{ optional($msg->created_at)->format('H:i') }}</span>
                                    </div>
                                    @if ($msg->mid){!! $igReactPip($msg->reaction, true) !!}{!! $igMsgActions(true) !!}{!! $igPinBadge(true, (bool) $msg->pinned_at) !!}@endif
                                </div>
                            </div>
                        @else
                            <div class="flex ig-msg-row group"@if ($msg->mid) data-mid="{{ $msg->mid }}" data-body="{{ $msg->body }}"@endif>
                                <div class="{{ $bare ? 'max-w-[62%] relative' : 'bubble-in rounded-[22px] px-3.5 py-2 max-w-[62%] shadow-sm relative' }}">
                                    {!! $renderIgMedia($msg) !!}
                                    @if (trim((string) $msg->body) !== '')<div class="ig-msg-text text-[13.5px] whitespace-pre-wrap break-words">{{ $msg->body }}</div>@endif
                                    <div class="text-[9px] text-ink-400 mono text-right mt-1.5 ig-localtime" data-ts="{{ optional($msg->created_at)->toIso8601String() }}">{{ optional($msg->created_at)->format('H:i') }}</div>
                                    @if ($msg->mid){!! $igReactPip($msg->reaction, false) !!}{!! $igMsgActions(false) !!}{!! $igPinBadge(false, (bool) $msg->pinned_at) !!}@endif
                                </div>
                            </div>
                        @endif
                    @endforeach

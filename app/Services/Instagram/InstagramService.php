<?php

namespace App\Services\Instagram;

use App\Models\InstagramAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over the official Instagram Graph API (v21). Mirrors how
 * MetaAdsService wraps the Marketing API: Bearer token, versioned base,
 * error translation. All sends go through here so policy/limits live in
 * one place. Exact payloads documented in
 * D:\Vault\kapil\Wasnap - Instagram Automation.md §1b.
 */
class InstagramService
{
    private string $base;

    public function __construct(private InstagramAccount $account)
    {
        $v = (string) InstagramGate::setting('instagram_graph_version', 'v25.0');
        // FB-Login path uses graph.facebook.com; IG-Login uses graph.instagram.com.
        $host = $account->login_type === 'instagram' ? 'graph.instagram.com' : 'graph.facebook.com';
        $this->base = "https://{$host}/{$v}";
    }

    private function token(): string
    {
        return (string) $this->account->access_token;
    }

    private function igId(): string
    {
        return (string) $this->account->ig_user_id;
    }

    /**
     * The node to POST /messages against. Meta's Instagram-Login docs specify
     * `/me/messages` (resolved from the user token) — and while a plain text
     * send is tolerated on `/{ig-id}/messages`, INTERACTIVE payloads (quick
     * replies, templates) are silently dropped there: Meta returns 200 but the
     * chips never render. `me` is the documented, reliable target. Facebook-
     * Login accounts keep using the explicit id.
     */
    private function sendTarget(): string
    {
        return $this->account->login_type === 'instagram' ? 'me' : $this->igId();
    }

    /** POST to the account's /messages endpoint with a built message object. */
    private function send(array $recipient, array $message): array
    {
        try {
            $isInteractive = isset($message['quick_replies']) || isset($message['attachment']);

            // Base payload. `messaging_type: RESPONSE` marks an in-window reply so
            // Meta doesn't reject an ordinary reply as "outside the window"
            // (error 10 / subcode 2534022). BUT Meta's Instagram quick-reply /
            // template examples OMIT messaging_type, and including it makes Meta
            // return 200 while silently dropping the buttons. So we send it only
            // for plain text; interactive payloads go without it.
            $payload = ['recipient' => $recipient, 'message' => $message];
            if (!$isInteractive) {
                $payload['messaging_type'] = 'RESPONSE';
            }

            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->post("{$this->base}/{$this->sendTarget()}/messages", $payload);
            if (!$r->successful()) {
                Log::warning('[IG-SEND] failed', ['account' => $this->account->id, 'status' => $r->status(), 'error' => $r->json('error', [])]);
                return ['ok' => false, 'error' => $this->englishError((array) $r->json('error', []))];
            }
            // Interactive success diagnostics — a send can return 200 while Meta
            // silently drops chips/buttons (wrong endpoint, messaging_type, or an
            // unsupported button). Log EXACTLY what reached Instagram so a
            // "4 buttons but only 2 showed" can be traced to the payload vs a
            // Meta-side render cap (generic template = max 3 buttons; QR = max 13).
            if ($isInteractive) {
                $sentButtons = $message['attachment']['payload']['elements'][0]['buttons'] ?? [];
                Log::info('[IG-SEND] interactive ok', [
                    'account'       => $this->account->id,
                    'login_type'    => $this->account->login_type,
                    'target'        => $this->sendTarget(),
                    'quick_replies' => count($message['quick_replies'] ?? []),
                    'buttons'       => count($sentButtons),
                    'button_types'  => array_map(fn ($b) => $b['type'] ?? '?', $sentButtons),
                    'mid'           => (string) ($r->json('message_id') ?? ''),
                ]);
            }
            return ['ok' => true, 'mid' => (string) ($r->json('message_id') ?? '')];
        } catch (\Throwable $e) {
            Log::error('[IG-SEND] threw: ' . $e->getMessage(), ['account' => $this->account->id]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Instagram localises error.message to the ACCOUNT's language (we saw
     * Russian), which then surfaced verbatim in the UI toast. Map the Graph API
     * error code/subcode to a FIXED English sentence so the operator always sees
     * a consistent, readable reason regardless of the account locale. Unknown
     * codes fall back to a generic English line carrying the numeric code.
     */
    private function englishError(array $err): string
    {
        $code = (int) ($err['code'] ?? 0);
        $sub  = (int) ($err['error_subcode'] ?? 0);

        $byPair = [
            '10:2534022'  => "Can't reply — the 24-hour messaging window has closed. This person needs to message you again before you can send.",
            '10:2534014'  => "This message type isn't allowed outside the 24-hour window. Use an approved message tag or wait for the person to reply.",
            '100:2534001' => 'Invalid or unavailable recipient — this person can\'t be messaged from this account.',
        ];
        $byCode = [
            10  => "Can't send right now — you're outside the allowed messaging window for this conversation.",
            100 => 'Instagram rejected the request — an invalid parameter or recipient.',
            190 => 'The Instagram access token has expired or is invalid — reconnect the account.',
            200 => 'Missing permission to send messages — reconnect the account and grant messaging access.',
            613 => 'Rate limit reached — too many messages sent. Try again shortly.',
            551 => "This person isn't available to receive messages right now.",
            10900 => "This person isn't available to receive messages right now.",
        ];

        if ($code && isset($byPair["$code:$sub"])) return $byPair["$code:$sub"];
        if ($code && isset($byCode[$code]))        return $byCode[$code];

        // Unknown code: never echo Instagram's localised message (could be any
        // language). A generic English line + the numeric code is enough to act on.
        return $code
            ? "Instagram couldn't send this message (error {$code}" . ($sub ? "/{$sub}" : '') . ').'
            : "Instagram couldn't send this message. Please try again.";
    }

    /** Plain text DM. */
    public function sendDm(string $igsid, string $text): array
    {
        return $this->send(['id' => $igsid], ['text' => mb_substr($text, 0, 1000)]);
    }

    /** DM with tap-to-choose quick replies: [['title'=>,'payload'=>], …]. */
    public function sendQuickReplies(string $igsid, string $text, array $replies): array
    {
        // Instagram quick-reply CHIPS render ONLY inside the Instagram mobile app,
        // never on web/desktop — so a chips send looked empty for anyone reading on
        // IG web. Render the options as POSTBACK buttons in a generic card instead:
        // those show EVERYWHERE (web + app), and a tap still fires the same payload.
        // Cap 3 (IG generic-template button limit). Both the WaDesk team-inbox send
        // (via the bridge) and the IgDesk native inbox reply route through here,
        // so this one change fixes both — the WaDesk sending code stays untouched.
        // Tolerate BOTH shapes: objects [{title,payload}] (WaDesk dispatcher +
        // native inbox) and bare strings ['Yes','No'] — a string entry sets both
        // title and payload, so a caller that forgot to wrap them no longer yields
        // empty (title-less) buttons in the app.
        $buttons = array_map(function ($r) {
            $title   = is_array($r) ? (string) ($r['title'] ?? '') : (string) $r;
            $payload = is_array($r) ? (string) ($r['payload'] ?? ($r['title'] ?? $title)) : $title;
            return [
                'type'    => 'postback',
                'title'   => mb_substr($title, 0, 20),
                'payload' => $payload !== '' ? $payload : $title,
            ];
        }, array_slice($replies, 0, 3));
        return $this->sendButtonTemplate($igsid, $text, $buttons);
    }

    /**
     * DM with tappable CTA buttons: [['type'=>'web_url|postback','title'=>,'url'|'payload'=>], …].
     *
     * Instagram does NOT support the Messenger "button" template — sending
     * template_type:button to an IG user is rejected as an unsupported message
     * type. IG DOES support the GENERIC template, so we render a single-element
     * generic card carrying the buttons. A generic title is capped at 80 chars,
     * so a longer prompt is sent as a lead-in DM and the card keeps a short
     * heading. web_url + postback are both valid IG generic-template buttons.
     */
    public function sendButtonTemplate(string $igsid, string $text, array $buttons): array
    {
        $btns = array_values(array_map(function ($b) {
            $type = ($b['type'] ?? 'postback') === 'web_url' ? 'web_url' : 'postback';
            $out  = ['type' => $type, 'title' => mb_substr((string) ($b['title'] ?? ''), 0, 20)];
            if ($type === 'web_url') $out['url'] = (string) ($b['url'] ?? '');
            else $out['payload'] = (string) ($b['payload'] ?? ($b['title'] ?? ''));
            return $out;
        }, array_slice($buttons, 0, 3)));

        $text = trim($text);
        $title = $text;
        if (mb_strlen($text) > 80) {
            // Prompt too long for a generic title — deliver it as its own DM,
            // then show the buttons under a short heading.
            $this->sendDm($igsid, $text);
            $title = mb_substr($text, 0, 77) . '…';
        }
        // A generic element requires a non-empty title.
        if ($title === '') $title = '—';

        return $this->send(['id' => $igsid], ['attachment' => ['type' => 'template', 'payload' => [
            'template_type' => 'generic',
            'elements'      => [[
                'title'   => mb_substr($title, 0, 80),
                'buttons' => $btns,
            ]],
        ]]]);
    }

    /** Private reply to a comment (comment → DM). 7-day window. */
    public function privateReply(string $commentId, string $text): array
    {
        return $this->send(['comment_id' => $commentId], ['text' => mb_substr($text, 0, 1000)]);
    }

    /** Public reply under a comment. */
    public function replyComment(string $commentId, string $text): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->post("{$this->base}/{$commentId}/replies", ['message' => mb_substr($text, 0, 2000)]);
            return $r->successful()
                ? ['ok' => true, 'id' => (string) ($r->json('id') ?? '')]
                : ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'reply failed')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Leave a top-level comment on a media object. */
    public function commentOnMedia(string $mediaId, string $text): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->post("{$this->base}/{$mediaId}/comments", ['message' => mb_substr($text, 0, 2000)]);
            if ($r->successful()) {
                return ['ok' => true, 'id' => (string) ($r->json('id') ?? '')];
            }
            // Surface Meta's reason — the caller shows it verbatim. Swallowing
            // it left the UI with a generic "rejected" and nothing to act on.
            Log::info('[IG-COMMENT] media comment refused', [
                'media' => $mediaId, 'http' => $r->status(),
                'body'  => mb_substr($r->body(), 0, 300),
            ]);
            return ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'comment failed')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Hide / unhide a comment. */
    public function hideComment(string $commentId, bool $hide = true): array
    {
        try {
            $r = Http::withToken($this->token())->asForm()->timeout(15)
                ->post("{$this->base}/{$commentId}", ['hide' => $hide ? 'true' : 'false']);
            return ['ok' => $r->successful()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Account profile (username, followers, name). */
    public function getProfile(): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}", ['fields' => 'username,name,profile_picture_url,followers_count']);
            return $r->successful() ? (array) $r->json() : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Profile of a MESSAGING user (someone who DMed us), by their IGSID.
     * IG's User Profile API: GET /{igsid}?fields=name,username,profile_pic —
     * available with instagram_business_manage_messages once the user has
     * messaged the account. `username` isn't guaranteed on every account tier;
     * callers fall back to name → igsid. Returns [] on any failure.
     */
    public function getSenderProfile(string $igsid): array
    {
        $igsid = trim($igsid);
        if ($igsid === '') return [];
        try {
            // Ask for the avatar, but NEVER let it cost us the name/username.
            // `profile_pic` is documented on the User Profile API, yet some tiers
            // reject it with "Tried accessing nonexisting field (profile_pic)" —
            // and Graph 400s the WHOLE call, so a previous fix dropped the field
            // entirely and every thread lost its picture. Two-step instead: try
            // with it, and on ANY failure retry with the fields we know exist.
            $r = Http::withToken($this->token())->acceptJson()->timeout(12)
                ->get("{$this->base}/{$igsid}", ['fields' => 'name,username,profile_pic']);

            if ($r->successful()) {
                $out = (array) $r->json();
                // A 200 can still arrive WITHOUT profile_pic (Meta silently masks
                // the field on some tiers instead of 400ing). If we got a handle
                // but no picture, recover the avatar via Business Discovery.
                if (empty($out['profile_pic'])) {
                    $pic = $this->discoverAvatar((string) ($out['username'] ?? ''));
                    if ($pic !== '') $out['profile_pic'] = $pic;
                }
                return $out;
            }

            Log::info('[IG-PROFILE] sender fetch with profile_pic failed — retrying without it', [
                'igsid' => $igsid, 'http' => $r->status(),
                'body'  => mb_substr($r->body(), 0, 300),
            ]);

            $r = Http::withToken($this->token())->acceptJson()->timeout(12)
                ->get("{$this->base}/{$igsid}", ['fields' => 'name,username']);
            if (!$r->successful()) {
                Log::info('[IG-PROFILE] sender fetch failed', [
                    'igsid' => $igsid, 'http' => $r->status(),
                    'body'  => mb_substr($r->body(), 0, 300),
                ]);
                return [];
            }
            // profile_pic was rejected outright — last resort is Business
            // Discovery on the handle we just resolved (FB-Login accounts only).
            $out = (array) $r->json();
            $pic = $this->discoverAvatar((string) ($out['username'] ?? ''));
            if ($pic !== '') $out['profile_pic'] = $pic;
            return $out;
        } catch (\Throwable $e) {
            Log::info('[IG-PROFILE] sender fetch threw: ' . $e->getMessage(), ['igsid' => $igsid]);
            return [];
        }
    }

    /**
     * Best-effort avatar for a handle via Business Discovery (profile_picture_url).
     * FB-Login only (graph.facebook.com), public Business/Creator targets only —
     * returns '' for Instagram-Login accounts or any target Meta won't disclose.
     * Used as a fallback when the messaging User Profile API omits `profile_pic`.
     */
    private function discoverAvatar(string $username): string
    {
        $username = ltrim(trim($username), '@');
        if ($username === '' || $this->account->login_type === 'instagram') return '';
        try {
            $bd = $this->businessDiscovery($username);
            return (string) ($bd['profile_picture_url'] ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * DIAGNOSTIC — run the sender-profile fetch and return the RAW Graph result
     * (host, login type, status, body) for each field variant, so a "no avatar"
     * symptom is diagnosable without shell access to the live logs. Never throws.
     */
    public function probeSenderProfile(string $igsid): array
    {
        $igsid = trim($igsid);
        $out = [
            'host'       => $this->base,
            'login_type' => (string) $this->account->login_type,
            'igsid'      => $igsid,
            'attempts'   => [],
        ];
        if ($igsid === '') { $out['error'] = 'empty igsid'; return $out; }

        $try = function (string $label, array $fields) use ($igsid, &$out) {
            try {
                $r = Http::withToken($this->token())->acceptJson()->timeout(12)
                    ->get("{$this->base}/{$igsid}", ['fields' => implode(',', $fields)]);
                $out['attempts'][] = [
                    'label'  => $label,
                    'fields' => implode(',', $fields),
                    'status' => $r->status(),
                    'ok'     => $r->successful(),
                    'body'   => mb_substr($r->body(), 0, 500),
                ];
            } catch (\Throwable $e) {
                $out['attempts'][] = ['label' => $label, 'error' => $e->getMessage()];
            }
        };
        $try('with_profile_pic', ['name', 'username', 'profile_pic']);
        $try('name_username',    ['name', 'username']);
        $try('profile_pic_only', ['profile_pic']);
        return $out;
    }

    /**
     * Publish a feed photo — official 2-step container flow:
     *   1) POST /{ig-id}/media       (image_url + caption) → creation_id
     *   2) POST /{ig-id}/media_publish (creation_id)        → published media id
     * Image containers are ready immediately (no status polling). The image
     * URL must be a public HTTPS JPEG.
     */
    public function publishImage(string $imageUrl, string $caption = '', array $productTags = []): array
    {
        try {
            $params = array_filter([
                'image_url' => $imageUrl,
                'caption'   => $caption !== '' ? mb_substr($caption, 0, 2200) : null,
            ]);
            // Shoppable post — product tagging. Each tag is {product_id, x, y}
            // (x/y 0–1). Requires the account to be approved for Instagram
            // Shopping with the catalog connected; if it isn't, Meta rejects the
            // container create and the caller surfaces the error (normal publish
            // without tags is unaffected).
            if (!empty($productTags)) {
                $params['product_tags'] = json_encode(array_values($productTags));
            }
            $c = Http::withToken($this->token())->acceptJson()->timeout(30)
                ->post("{$this->base}/{$this->igId()}/media", $params);
            $creationId = (string) ($c->json('id') ?? '');
            if (!$c->successful() || $creationId === '') {
                return ['ok' => false, 'error' => (string) ($c->json('error.message') ?? 'container create failed')];
            }
            $p = Http::withToken($this->token())->acceptJson()->timeout(30)
                ->post("{$this->base}/{$this->igId()}/media_publish", ['creation_id' => $creationId]);
            $mediaId = (string) ($p->json('id') ?? '');
            if (!$p->successful() || $mediaId === '') {
                return ['ok' => false, 'error' => (string) ($p->json('error.message') ?? 'publish failed'), 'creation_id' => $creationId];
            }
            return ['ok' => true, 'media_id' => $mediaId];
        } catch (\Throwable $e) {
            Log::error('[IG-PUBLISH] threw: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Account-level insights. Web-verified (2025): account insights now REQUIRE
     * `metric_type`. `reach` is the only safe time-series metric — `impressions`
     * is deprecated (v22+/all versions since 2025-04-21) and `profile_views` was
     * deprecated at account level effective 2025-01-08. So we pull `reach` as a
     * day series + a `total_value` pass for engagement counters.
     * Returns ['reach' => [['t'=>iso,'v'=>int], …], '_totals' => ['likes'=>int, …]].
     * (First arg kept for backward-compat with old callers; it is ignored.)
     */
    public function accountInsights(array $metrics = [], int $days = 14): array
    {
        $out = [];
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}/insights", [
                    'metric'      => 'reach',
                    'metric_type' => 'time_series',
                    'period'      => 'day',
                    'since'       => now()->subDays($days)->timestamp,
                    'until'       => now()->timestamp,
                ]);
            foreach ((array) $r->json('data', []) as $m) {
                $name = (string) ($m['name'] ?? '');
                if ($name === '') continue;
                $out[$name] = array_map(fn ($v) => [
                    't' => (string) ($v['end_time'] ?? ''),
                    'v' => (int) ($v['value'] ?? 0),
                ], (array) ($m['values'] ?? []));
            }
        } catch (\Throwable $e) {
            Log::warning('[IG-INSIGHTS] reach threw: ' . $e->getMessage());
        }
        // Engagement counters (total_value) over the same window.
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}/insights", [
                    'metric'      => 'accounts_engaged,total_interactions,likes,comments,shares,saves,views',
                    'metric_type' => 'total_value',
                    'period'      => 'day',
                    'since'       => now()->subDays($days)->timestamp,
                    'until'       => now()->timestamp,
                ]);
            $totals = [];
            foreach ((array) $r->json('data', []) as $m) {
                $name = (string) ($m['name'] ?? '');
                if ($name === '') continue;
                $totals[$name] = (int) ($m['total_value']['value'] ?? 0);
            }
            if ($totals) $out['_totals'] = $totals;
        } catch (\Throwable $e) {
            Log::warning('[IG-INSIGHTS] totals threw: ' . $e->getMessage());
        }
        return $out;
    }

    // ───────────── Send API: media + generic template + actions ─────────────

    /** DM a media attachment (image|video|audio|file). ≤25MB; public HTTPS url. */
    public function sendMediaDm(string $igsid, string $type, string $url, bool $reusable = true): array
    {
        $type = in_array($type, ['image', 'video', 'audio', 'file'], true) ? $type : 'image';
        $payload = ['url' => $url];
        // `is_reusable` is documented for the Messenger / FB-Login attachment
        // payload, NOT the pure IG-Login messaging payload (which is {url} only),
        // where it can be rejected as an unknown field. Only send it on FB-Login.
        if ($reusable && $this->account->login_type !== 'instagram') $payload['is_reusable'] = true;
        return $this->send(['id' => $igsid], ['attachment' => ['type' => $type, 'payload' => $payload]]);
    }

    /**
     * DM a generic (media/carousel) template. Up to 10 horizontally-scrollable
     * elements, 3 buttons each (web_url|postback). title/subtitle ≤80 chars.
     * elements: [['title','image_url','subtitle','url','buttons'=>[...]], …]
     */
    public function sendGenericTemplate(string $igsid, array $elements): array
    {
        $els = array_map(function ($e) {
            $out = ['title' => mb_substr((string) ($e['title'] ?? ''), 0, 80)];
            if (!empty($e['image_url'])) $out['image_url'] = (string) $e['image_url'];
            if (!empty($e['subtitle']))  $out['subtitle']  = mb_substr((string) $e['subtitle'], 0, 80);
            if (!empty($e['url']))       $out['default_action'] = ['type' => 'web_url', 'url' => (string) $e['url']];
            $btns = array_map(function ($b) {
                $type = ($b['type'] ?? 'postback') === 'web_url' ? 'web_url' : 'postback';
                $o = ['type' => $type, 'title' => mb_substr((string) ($b['title'] ?? ''), 0, 20)];
                if ($type === 'web_url') $o['url'] = (string) ($b['url'] ?? '');
                else $o['payload'] = (string) ($b['payload'] ?? ($b['title'] ?? ''));
                return $o;
            }, array_slice($e['buttons'] ?? [], 0, 3));
            if ($btns) $out['buttons'] = array_values($btns);
            return $out;
        }, array_slice($elements, 0, 10));
        return $this->send(['id' => $igsid], ['attachment' => ['type' => 'template', 'payload' => [
            'template_type' => 'generic', 'elements' => array_values($els),
        ]]]);
    }

    /**
     * DM a native Product Template — Meta renders live price/availability from
     * the connected catalog. $productIds are the CATALOG product ids (not our
     * retailer_id). Requires the catalog to be connected to the account's
     * commerce; if it isn't, Meta rejects the send — callers should fall back to
     * sendGenericTemplate (our cached product carousel), which always works.
     * Up to 10 elements.
     */
    public function sendProductTemplate(string $igsid, array $productIds): array
    {
        $els = array_values(array_filter(array_map(
            fn ($id) => ($id = trim((string) $id)) !== '' ? ['id' => $id] : null,
            array_slice($productIds, 0, 10)
        )));
        if (empty($els)) return ['ok' => false, 'error' => 'no product ids'];
        return $this->send(['id' => $igsid], ['attachment' => ['type' => 'template', 'payload' => [
            'template_type' => 'product', 'elements' => $els,
        ]]]);
    }

    /**
     * sender_action post — reactions + typing + seen all ride the SAME
     * /messages endpoint but carry `sender_action` (no `message`), so the
     * normal send() helper can't express them.
     */
    private function senderAction(string $igsid, string $action, ?array $payload = null): array
    {
        $body = ['recipient' => ['id' => $igsid], 'sender_action' => $action];
        if ($payload !== null) $body['payload'] = $payload;
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->post("{$this->base}/{$this->sendTarget()}/messages", $body);
            return $r->successful()
                ? ['ok' => true]
                : ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'action failed')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * React to a message. Instagram's API expects the literal emoji (e.g. ❤️ 😂);
     * a keyword like 'love' still works for legacy reasons, but 'like'/'haha'/etc.
     * return "Invalid reaction" — always pass the actual emoji.
     */
    public function sendReaction(string $igsid, string $messageId, string $reaction = '❤️'): array
    {
        return $this->senderAction($igsid, 'react', ['message_id' => $messageId, 'reaction' => $reaction]);
    }

    /** Remove our reaction from a message. */
    public function removeReaction(string $igsid, string $messageId): array
    {
        return $this->senderAction($igsid, 'unreact', ['message_id' => $messageId]);
    }

    /**
     * Read the account's configured ice breakers (starter questions shown when a
     * user opens a NEW conversation). Returns the raw call_to_actions array.
     */
    public function getIceBreakers(): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}/messenger_profile", ['fields' => 'ice_breakers']);
            if (!$r->successful()) return [];
            $row = (array) ($r->json('data.0.ice_breakers') ?? []);
            // ice_breakers is an array of locale groups; flatten the first group's CTAs.
            return (array) ($row[0]['call_to_actions'] ?? []);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Set the account's ice breakers. $questions = [['question'=>..,'payload'=>..], ...]
     * (Instagram allows up to 4, each question ≤ 80 chars). Empty list clears them.
     * Returns ['ok'=>bool, 'error'=>?string].
     */
    public function setIceBreakers(array $questions, string $locale = 'default'): array
    {
        $cta = [];
        foreach (array_slice(array_values($questions), 0, 4) as $q) {
            $question = trim((string) ($q['question'] ?? ''));
            if ($question === '') continue;
            $payload = trim((string) ($q['payload'] ?? ''));
            $cta[] = [
                'question' => mb_substr($question, 0, 80),
                'payload'  => $payload !== '' ? $payload : $question,   // tapped payload routes to keyword/flow automations
            ];
        }
        if (empty($cta)) return $this->deleteIceBreakers();
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->post("{$this->base}/{$this->igId()}/messenger_profile", [
                    'platform'     => 'instagram',
                    'ice_breakers' => [[
                        'call_to_actions' => $cta,
                        'locale'          => $locale,
                    ]],
                ]);
            if ($r->successful()) return ['ok' => true];
            Log::warning('[IG-ICEBREAKER] set failed', ['account' => $this->account->id, 'err' => $r->json('error.message')]);
            return ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'Instagram rejected the ice breakers.')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Remove all ice breakers from the account. */
    public function deleteIceBreakers(): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->delete("{$this->base}/{$this->igId()}/messenger_profile", ['fields' => ['ice_breakers']]);
            return ['ok' => (bool) $r->successful()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Set the account's persistent menu — the always-open menu in the DM
     * composer. $items = [['type'=>'postback'|'web_url','title'=>,'payload'|'url'=>], …]
     * (IG allows up to 5). A postback tap arrives as a webhook postback whose
     * payload routes to keyword/flow automations — same as ice breakers. Empty
     * list clears it. Returns ['ok'=>bool,'error'=>?string].
     */
    public function setPersistentMenu(array $items, string $locale = 'default'): array
    {
        $cta = [];
        foreach (array_slice(array_values($items), 0, 20) as $it) {   // Meta persistent-menu cap = 20
            $title = trim((string) ($it['title'] ?? ''));
            if ($title === '') continue;
            $type = ($it['type'] ?? 'postback') === 'web_url' ? 'web_url' : 'postback';
            $o = ['type' => $type, 'title' => mb_substr($title, 0, 30)];
            if ($type === 'web_url') {
                $url = trim((string) ($it['url'] ?? ''));
                if (!preg_match('#^https://#i', $url)) continue;   // IG requires HTTPS
                $o['url'] = $url;
                $o['webview_height_ratio'] = 'full';
            } else {
                $p = trim((string) ($it['payload'] ?? ''));
                $o['payload'] = $p !== '' ? $p : $title;           // routes to keyword/flow automations
            }
            $cta[] = $o;
        }
        if (empty($cta)) return $this->deletePersistentMenu();
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->post("{$this->base}/{$this->igId()}/messenger_profile", [
                    'platform'        => 'instagram',
                    'persistent_menu' => [[
                        'locale'                  => $locale,
                        'composer_input_disabled' => false,
                        'call_to_actions'         => $cta,
                    ]],
                ]);
            if ($r->successful()) return ['ok' => true];
            Log::warning('[IG-MENU] set failed', ['account' => $this->account->id, 'err' => $r->json('error.message')]);
            return ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'Instagram rejected the menu.')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Remove the persistent menu from the account. */
    public function deletePersistentMenu(): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->delete("{$this->base}/{$this->igId()}/messenger_profile", ['fields' => ['persistent_menu']]);
            return ['ok' => (bool) $r->successful()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function typingOn(string $igsid): array  { return $this->senderAction($igsid, 'typing_on'); }
    public function typingOff(string $igsid): array { return $this->senderAction($igsid, 'typing_off'); }
    public function markSeen(string $igsid): array  { return $this->senderAction($igsid, 'mark_seen'); }

    /**
     * Human-agent tagged DM — the ONLY message tag IG supports. Extends the
     * 24h window to 7 days for genuine human support. Requires the Human Agent
     * feature approved; use ONLY for real operator replies, never bots.
     */
    public function sendHumanAgent(string $igsid, string $text): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->post("{$this->base}/{$this->sendTarget()}/messages", [
                    'recipient'      => ['id' => $igsid],
                    'messaging_type' => 'MESSAGE_TAG',
                    'tag'            => 'HUMAN_AGENT',
                    'message'        => ['text' => mb_substr($text, 0, 1000)],
                ]);
            return $r->successful()
                ? ['ok' => true, 'mid' => (string) ($r->json('message_id') ?? '')]
                : ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'send failed')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ───────────── Content Publishing: all media types ─────────────

    /** GET a container's status_code (EXPIRED|ERROR|FINISHED|IN_PROGRESS|PUBLISHED). */
    public function containerStatus(string $containerId): string
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$containerId}", ['fields' => 'status_code']);
            return (string) ($r->json('status_code') ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** Poll a container until FINISHED (Meta: once/min, ≤5 min). Returns true when ready. */
    private function pollContainer(string $containerId, int $maxSeconds = 90): bool
    {
        $deadline = time() + max(5, $maxSeconds);
        while (true) {
            $s = $this->containerStatus($containerId);
            if ($s === 'FINISHED') return true;
            // PUBLISHED here means it's already live — don't let the caller
            // re-publish (the API rejects a double publish). ERROR/EXPIRED fail.
            if ($s === 'PUBLISHED' || $s === 'ERROR' || $s === 'EXPIRED') return false;
            if (time() >= $deadline) return false;
            sleep(3);
        }
    }

    /** POST /media → creation_id. */
    private function createContainer(array $params): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(60)
                ->post("{$this->base}/{$this->igId()}/media", $params);
            $id = (string) ($r->json('id') ?? '');
            return ($r->successful() && $id !== '')
                ? ['ok' => true, 'id' => $id]
                : ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'container create failed')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** POST /media_publish → published media id. */
    private function publishContainer(string $creationId): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(60)
                ->post("{$this->base}/{$this->igId()}/media_publish", ['creation_id' => $creationId]);
            $id = (string) ($r->json('id') ?? '');
            return ($r->successful() && $id !== '')
                ? ['ok' => true, 'media_id' => $id]
                : ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'publish failed'), 'creation_id' => $creationId];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Publish a Reel / single video. video_url = public HTTPS MP4/MOV (H264/HEVC,
     * AAC, 3s–15min, 9:16, ≤300MB). opts: cover_url, thumb_offset(ms),
     * share_to_feed(bool), audio_name, location_id, max_wait. (No licensed music
     * via API — audio_name only renames original audio.) Polls to FINISHED first.
     */
    public function publishReel(string $videoUrl, string $caption = '', array $opts = []): array
    {
        $params = array_filter([
            'media_type'    => 'REELS',
            'video_url'     => $videoUrl,
            'caption'       => $caption !== '' ? mb_substr($caption, 0, 2200) : null,
            'cover_url'     => $opts['cover_url'] ?? null,
            'thumb_offset'  => $opts['thumb_offset'] ?? null,
            'share_to_feed' => array_key_exists('share_to_feed', $opts) ? ($opts['share_to_feed'] ? 'true' : 'false') : null,
            'audio_name'    => $opts['audio_name'] ?? null,
            'location_id'   => $opts['location_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $c = $this->createContainer($params);
        if (empty($c['ok'])) return $c;
        if (!$this->pollContainer($c['id'], (int) ($opts['max_wait'] ?? 90))) {
            return ['ok' => false, 'error' => 'video still processing / failed (container ' . $c['id'] . ')', 'creation_id' => $c['id']];
        }
        return $this->publishContainer($c['id']);
    }

    /** Publish a Story (image or video). */
    public function publishStory(string $url, bool $isVideo = false): array
    {
        $params = $isVideo
            ? ['media_type' => 'STORIES', 'video_url' => $url]
            : ['media_type' => 'STORIES', 'image_url' => $url];
        $c = $this->createContainer($params);
        if (empty($c['ok'])) return $c;
        if ($isVideo && !$this->pollContainer($c['id'], 90)) {
            return ['ok' => false, 'error' => 'story video still processing', 'creation_id' => $c['id']];
        }
        return $this->publishContainer($c['id']);
    }

    /**
     * Publish a carousel (2-10 items). items: [['type'=>'image'|'video','url'=>], …].
     * Caption lives on the parent only. Counts as ONE post.
     */
    public function publishCarousel(array $items, string $caption = ''): array
    {
        $items = array_slice(array_values($items), 0, 10);
        if (count($items) < 2) return ['ok' => false, 'error' => 'carousel needs 2-10 items'];
        $childIds = [];
        foreach ($items as $it) {
            $isVid = ($it['type'] ?? 'image') === 'video';
            $p = $isVid
                ? ['media_type' => 'VIDEO', 'video_url' => (string) ($it['url'] ?? ''), 'is_carousel_item' => 'true']
                : ['image_url' => (string) ($it['url'] ?? ''), 'is_carousel_item' => 'true'];
            $c = $this->createContainer($p);
            if (empty($c['ok'])) return ['ok' => false, 'error' => 'carousel child failed: ' . ($c['error'] ?? '')];
            if ($isVid) $this->pollContainer($c['id'], 90);
            $childIds[] = $c['id'];
        }
        $parent = $this->createContainer(array_filter([
            'media_type' => 'CAROUSEL',
            'children'   => implode(',', $childIds),
            'caption'    => $caption !== '' ? mb_substr($caption, 0, 2200) : null,
        ], fn ($v) => $v !== null && $v !== ''));
        if (empty($parent['ok'])) return $parent;
        $this->pollContainer($parent['id'], 60);
        return $this->publishContainer($parent['id']);
    }

    /** Remaining publishing quota. Reads quota_total from the live response (don't hardcode). */
    public function publishingLimit(): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}/content_publishing_limit", ['fields' => 'config,quota_usage']);
            $row = (array) ($r->json('data.0') ?? []);
            return [
                'used'   => (int) ($row['quota_usage'] ?? 0),
                'total'  => (int) ($row['config']['quota_total'] ?? 0),
                'window' => (int) ($row['config']['quota_duration'] ?? 86400),
            ];
        } catch (\Throwable $e) {
            return ['used' => 0, 'total' => 0, 'window' => 86400];
        }
    }

    // ───────────── Comments + insights + discovery ─────────────

    /** Permanently delete a comment (media owner only). */
    public function deleteComment(string $commentId): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->delete("{$this->base}/{$commentId}");
            return ['ok' => $r->successful(), 'error' => $r->successful() ? null : (string) ($r->json('error.message') ?? 'delete failed')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Per-media insights. Metrics differ by media type (web-verified 2025):
     * media-level uses `views` (not plays/video_views) and `saved` (not saves).
     * Albums (CAROUSEL_ALBUM) have NO insights — returns [] without calling.
     */
    public function mediaInsights(string $mediaId, string $mediaType = ''): array
    {
        $t = strtoupper(trim($mediaType));
        if ($t === 'CAROUSEL_ALBUM') return [];
        $metrics = match (true) {
            str_contains($t, 'REEL') => 'reach,likes,comments,saved,shares,views,total_interactions,ig_reels_avg_watch_time,ig_reels_video_view_total_time',
            $t === 'STORY'           => 'reach,views,replies,shares,total_interactions',
            default                  => 'reach,likes,comments,saved,shares,views,total_interactions',
        };
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$mediaId}/insights", ['metric' => $metrics]);
            if (!$r->successful()) return [];
            $out = [];
            foreach ((array) $r->json('data', []) as $m) {
                $name = (string) ($m['name'] ?? '');
                if ($name === '') continue;
                $out[$name] = (int) ($m['values'][0]['value'] ?? $m['total_value']['value'] ?? 0);
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Competitor lookup by username (public Business/Creator only; FB-Login + Public Content Access). */
    public function businessDiscovery(string $username): array
    {
        if ($this->account->login_type === 'instagram') {
            // Business Discovery is Facebook-Login only (graph.facebook.com). An
            // Instagram-Login account CANNOT use it — this is the #1 reason
            // discovery "returns nothing": the connected account is the wrong type.
            Log::warning('[IG-DISCOVERY] skipped — account connected via Instagram Login; Business Discovery needs a Facebook-linked IG Business account', [
                'account' => $this->account->id, 'login_type' => $this->account->login_type,
            ]);
            return [];
        }
        $username = preg_replace('/[^A-Za-z0-9._]/', '', $username);
        if ($username === '') return [];
        $fields = "business_discovery.username({$username}){username,name,biography,followers_count,follows_count,media_count,profile_picture_url,website,media.limit(12){id,caption,media_type,media_url,permalink,timestamp,like_count,comments_count}}";
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}", ['fields' => $fields]);
            if (!$r->successful()) {
                // Log the REAL Graph error (expired token, missing
                // instagram_manage_insights, target not a Business/Creator, etc.)
                // so "no result" is diagnosable instead of a silent [].
                Log::warning('[IG-DISCOVERY] Graph call failed', [
                    'account'  => $this->account->id,
                    'username' => $username,
                    'status'   => $r->status(),
                    'error'    => $r->json('error', []),
                ]);
                return [];
            }
            $bd = (array) ($r->json('business_discovery') ?? []);
            if (empty($bd)) {
                Log::info('[IG-DISCOVERY] empty result — target @' . $username . ' is not a public Business/Creator account (or the field was masked by Meta)', [
                    'account' => $this->account->id,
                ]);
            }
            return $bd;
        } catch (\Throwable $e) {
            Log::error('[IG-DISCOVERY] threw: ' . $e->getMessage(), [
                'account' => $this->account->id, 'username' => $username,
            ]);
            return [];
        }
    }

    /** Hashtag → id (FB-Login only; 30 unique tags / rolling 7 days). */
    public function hashtagId(string $q): string
    {
        if ($this->account->login_type === 'instagram') {
            Log::warning('[IG-HASHTAG] skipped — account connected via Instagram Login; hashtag search needs a Facebook-linked IG Business account', [
                'account' => $this->account->id, 'login_type' => $this->account->login_type,
            ]);
            return '';
        }
        $q = ltrim(trim($q), '#');
        if ($q === '') return '';
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/ig_hashtag_search", ['user_id' => $this->igId(), 'q' => $q]);
            if (!$r->successful()) {
                // Real Graph error — expired token, missing permission, or the
                // 30-unique-tags / rolling-7-days quota being hit.
                Log::warning('[IG-HASHTAG] search failed', [
                    'account' => $this->account->id,
                    'q'       => $q,
                    'status'  => $r->status(),
                    'error'   => $r->json('error', []),
                ]);
                return '';
            }
            return (string) ($r->json('data.0.id') ?? '');
        } catch (\Throwable $e) {
            Log::error('[IG-HASHTAG] threw: ' . $e->getMessage(), ['account' => $this->account->id, 'q' => $q]);
            return '';
        }
    }

    /** Hashtag media: kind = top_media | recent_media (recent = last 24h only). */
    public function hashtagMedia(string $hashtagId, string $kind = 'top_media', int $limit = 25): array
    {
        if ($this->account->login_type === 'instagram') {
            Log::warning('[IG-HASHTAG] media skipped — account connected via Instagram Login; hashtag media needs a Facebook-linked IG Business account', [
                'account' => $this->account->id, 'login_type' => $this->account->login_type,
            ]);
            return [];
        }
        $edge = $kind === 'recent_media' ? 'recent_media' : 'top_media';
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$hashtagId}/{$edge}", [
                    'user_id' => $this->igId(),
                    'fields'  => 'id,media_type,caption,permalink,timestamp,like_count,comments_count',
                    'limit'   => $limit,
                ]);
            if (!$r->successful()) {
                Log::warning('[IG-HASHTAG] media fetch failed', [
                    'account'    => $this->account->id,
                    'hashtag_id' => $hashtagId,
                    'kind'       => $edge,
                    'status'     => $r->status(),
                    'error'      => $r->json('error', []),
                ]);
                return [];
            }
            return (array) $r->json('data', []);
        } catch (\Throwable $e) {
            Log::error('[IG-HASHTAG] media threw: ' . $e->getMessage(), ['account' => $this->account->id, 'hashtag_id' => $hashtagId]);
            return [];
        }
    }

    /** List DM conversation threads (also the source of message_id for reactions). */
    public function getConversations(int $limit = 20): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}/conversations", [
                    'platform' => 'instagram',
                    'fields'   => 'id,updated_time,participants',
                    'limit'    => $limit,
                ]);
            return $r->successful() ? (array) $r->json('data', []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Messages inside a conversation thread. */
    public function getMessages(string $conversationId, int $limit = 25): array
    {
        $lim = max(1, min(50, $limit));
        // Enriched read: `shares` carries a shared reel/post's permalink and
        // `story` a story reply/mention. Neither returns the media itself (a
        // documented Instagram limitation — reels shared in DM only ship their
        // video through the message webhook), but the permalink lets the inbox
        // link out. If Meta rejects the enriched field set on this API version
        // we fall back to the basic read so the whole inbox sync never breaks.
        foreach ([
            "messages.limit({$lim}){id,from,to,message,created_time,attachments,shares,story}",
            "messages.limit({$lim}){id,from,to,message,created_time,attachments}",
        ] as $fields) {
            try {
                $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                    ->get("{$this->base}/{$conversationId}", ['fields' => $fields]);
                if ($r->successful()) return (array) ($r->json('messages.data') ?? []);
            } catch (\Throwable $e) {
                // fall through to the basic field set / empty
            }
        }
        return [];
    }

    /**
     * List the account's own recent media (for analytics/comment moderation
     * pickers). GET /{ig-user-id}/media.
     */
    public function getMedia(int $limit = 25): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}/media", [
                    'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,comments_count,like_count',
                    'limit'  => max(1, min(50, $limit)),
                ]);
            return $r->successful() ? (array) $r->json('data', []) : [];
        } catch (\Throwable $e) {
            Log::warning('[IG-MEDIA] ' . $e->getMessage(), ['account' => $this->account->id]);
            return [];
        }
    }

    /**
     * The account's currently-ACTIVE stories (the last 24h). Meta exposes these
     * only via the dedicated /{ig-user-id}/stories edge — they are NOT returned
     * by /media, and expired stories are gone. VIDEO stories carry both a
     * media_url (the mp4) and thumbnail_url (a poster frame); IMAGE stories put
     * the picture in media_url. Reading your OWN stories needs the insights
     * permission you already hold — no extra App Review.
     */
    /** Set when getStories() cannot return data, so callers can surface why. */
    public ?string $lastStoriesError = null;

    public function getStories(): array
    {
        $this->lastStoriesError = null;
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}/stories", [
                    'fields' => 'id,media_type,media_url,thumbnail_url,permalink,timestamp',
                    'limit'  => 50,
                ]);
            if ($r->successful()) {
                return (array) $r->json('data', []);
            }
            // Surface Meta's own message (permission / unsupported edge / token).
            $err = $r->json('error.message') ?: ('HTTP ' . $r->status());
            $code = $r->json('error.code');
            $this->lastStoriesError = trim($err . ($code ? " (code {$code})" : ''));
            Log::warning('[IG-STORIES] ' . $this->lastStoriesError, ['account' => $this->account->id, 'body' => \Illuminate\Support\Str::limit($r->body(), 400)]);
            return [];
        } catch (\Throwable $e) {
            $this->lastStoriesError = $e->getMessage();
            Log::warning('[IG-STORIES] ' . $e->getMessage(), ['account' => $this->account->id]);
            return [];
        }
    }

    /**
     * List comments on one of the account's media (for live moderation).
     *
     * Throws RuntimeException on a Graph refusal instead of returning [] — an
     * empty array is indistinguishable from "this post has no comments", which
     * is how a broken fetch masqueraded as an empty thread. The caller turns
     * this into a visible error.
     */
    public function getComments(string $mediaId, int $limit = 50): array
    {
        // Ask for `from{id,username}` too — the commenter's handle + IGSID.
        // Meta often leaves the top-level `username` null on a comment (it
        // rendered as "instagram user"), but carries the handle under `from`.
        // Some IG-Login tiers reject the `from` sub-field and 400 the WHOLE
        // call, so fall back to the basic field set rather than lose the thread.
        $withFrom = 'id,text,username,timestamp,hidden,like_count,from{id,username},replies{id,text,username,timestamp,from{id,username}}';
        $basic    = 'id,text,username,timestamp,hidden,like_count,replies{id,text,username,timestamp}';

        $r = Http::withToken($this->token())->acceptJson()->timeout(15)
            ->get("{$this->base}/{$mediaId}/comments", ['fields' => $withFrom, 'limit' => max(1, min(100, $limit))]);
        if (!$r->successful()) {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$mediaId}/comments", ['fields' => $basic, 'limit' => max(1, min(100, $limit))]);
        }

        if ($r->successful()) {
            $data = (array) $r->json('data', []);
            // Meta's comments edge returns a reply BOTH as a top-level entry AND
            // nested under its parent's `replies`, so the same reply rendered
            // twice (once as "@you hiii" at top level, once as a nested
            // "instagram user hiii"). Drop any top-level comment whose id is
            // already a reply of another comment, so the thread nests exactly
            // like Instagram: top-level comments only, replies indented beneath.
            $replyIds = [];
            foreach ($data as $c) {
                foreach ((array) ($c['replies']['data'] ?? []) as $rep) {
                    if (!empty($rep['id'])) $replyIds[(string) $rep['id']] = true;
                }
            }
            $data = array_values(array_filter(
                $data,
                fn ($c) => is_array($c) && empty($replyIds[(string) ($c['id'] ?? '')])
            ));
            return $this->enrichCommentAuthors($data);
        }

        Log::warning('[IG-COMMENTS] Graph refused', [
            'account' => $this->account->id, 'media' => $mediaId,
            'http'    => $r->status(), 'body' => mb_substr($r->body(), 0, 300),
        ]);
        throw new \RuntimeException((string) ($r->json('error.message') ?? 'Instagram refused the comments request.'));
    }

    /**
     * Fill each comment's author name + (best-effort) avatar for display.
     *
     * NAME: prefer the top-level `username`, else the handle under `from`. This
     * is what turns "instagram user" into the real @handle.
     *
     * AVATAR: the comments edge exposes NO profile picture. We resolve the
     * commenter's IGSID (`from.id`) through the User Profile API — the same one
     * the DM inbox uses — which DOES return `profile_pic`. That only works for
     * people the messaging API can see (typically those who've also DMed the
     * account); a pure commenter often resolves to nothing, so their avatar
     * stays a silhouette (a Meta limitation, not a bug). Cached 6h + capped so a
     * big thread can't fan out into dozens of profile calls.
     */
    private function enrichCommentAuthors(array $comments): array
    {
        // Comments/replies authored by the CONNECTED account itself: Meta often
        // leaves `username` null on the business's own comments (especially on
        // replies), which rendered as "instagram user". Detect self by IG id and
        // substitute the account's own handle + avatar — the data we already have.
        $selfIgsid = (string) ($this->account->ig_user_id ?? '');
        $selfName  = (string) ($this->account->username ?? '');
        $selfPic   = (string) ($this->account->profile_pic_url ?? '');

        $budget = 15;                 // max distinct profile lookups per load
        $picFor = function (string $igsid) use (&$budget): string {
            if ($igsid === '' || $budget <= 0) return '';
            $key = 'ig-commenter-pic:' . $this->account->id . ':' . $igsid;
            $cached = Cache::get($key);
            if (is_string($cached)) return $cached;   // '' is a valid "no pic" cache
            $budget--;
            $pic = '';
            try {
                $p = $this->getSenderProfile($igsid);
                $pic = (string) ($p['profile_pic'] ?? '');
            } catch (\Throwable $e) { /* leave blank */ }
            Cache::put($key, $pic, now()->addHours(6));
            return $pic;
        };

        $one = function (array $c) use ($picFor, $selfIgsid, $selfName, $selfPic): array {
            $from  = is_array($c['from'] ?? null) ? $c['from'] : [];
            $igsid = (string) ($from['id'] ?? '');

            // FB-login accounts: fill the handle from `from.username` if present.
            if (empty($c['username']) && !empty($from['username'])) {
                $c['username'] = (string) $from['username'];
            }
            // Explicit self match by IG id when `from` is available.
            if ($selfIgsid !== '' && $igsid === $selfIgsid) {
                if ($selfName !== '') $c['username']  = $selfName;
                if ($selfPic  !== '') $c['avatar_url'] = $selfPic;
                return $c;
            }
            // Instagram OMITS the connected account's OWN username on its
            // comments/replies (there's no `from` on the IG comments edge — it's
            // a Facebook-only field). Other users' usernames ARE returned, so a
            // still-empty username here reliably means the comment is OURS. Use
            // the account's handle + avatar. This is what fixed the nested reply
            // rendering as "instagram user" instead of the account name.
            if (empty($c['username'])) {
                if ($selfName !== '') $c['username']   = $selfName;
                if ($selfPic  !== '') $c['avatar_url'] = $selfPic;
                return $c;
            }
            // Someone else → best-effort avatar via their IGSID (when we have it).
            if ($igsid !== '') {
                $pic = $picFor($igsid);
                if ($pic !== '') $c['avatar_url'] = $pic;
            }
            return $c;
        };

        return array_map(function ($c) use ($one) {
            if (!is_array($c)) return $c;
            $c = $one($c);
            if (isset($c['replies']['data']) && is_array($c['replies']['data'])) {
                $c['replies']['data'] = array_map(fn ($r) => is_array($r) ? $one($r) : $r, $c['replies']['data']);
            }
            return $c;
        }, $comments);
    }

    /**
     * Subscribe the account to the webhook fields we handle so events actually
     * flow after connect. Best-effort: logs and returns ['ok'=>false] on error,
     * never throws (so a failed subscribe never blocks the connect flow).
     */
    public function subscribeWebhooks(): array
    {
        // Per the official IG webhooks reference — 'messaging_reactions' is NOT a
        // valid field (the reaction field is 'message_reactions'); an unknown field
        // makes Meta reject the whole subscribe (error 100) and nothing flows in.
        $fields = 'messages,messaging_postbacks,message_reactions,comments,live_comments,mentions';
        // FB-Login subscribes the Page; IG-Login subscribes the IG user node.
        $node = ($this->account->login_type === 'instagram' || empty($this->account->page_id))
            ? $this->igId()
            : (string) $this->account->page_id;
        // Lead Ads (`leadgen`) is a PAGE-only field — add it when we're subscribing
        // the Page (FB-Login). IG-Login has no Page, so lead-ad capture isn't
        // available there and the field would be rejected.
        if ($this->account->login_type !== 'instagram' && !empty($this->account->page_id)) {
            $fields .= ',leadgen';
        }
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->asForm()->post("{$this->base}/{$node}/subscribed_apps", ['subscribed_fields' => $fields]);
            if ($r->successful()) return ['ok' => true];
            Log::warning('[IG-SUBSCRIBE] failed', ['account' => $this->account->id, 'error' => $r->json('error.message')]);
            return ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'subscribe failed')];
        } catch (\Throwable $e) {
            Log::warning('[IG-SUBSCRIBE] ' . $e->getMessage(), ['account' => $this->account->id]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Read back what Meta currently has this account subscribed to — the live
     * `subscribed_apps` binding. Powers the "Check webhook" diagnostic: if the
     * returned `fields` is empty, Meta will NOT deliver DM/comment webhooks to
     * us (even though the inbox still polls messages in). Never throws.
     *
     * @return array{ok:bool, fields:string[], apps:int, error:?string, node:string}
     */
    public function subscribedApps(): array
    {
        $node = ($this->account->login_type === 'instagram' || empty($this->account->page_id))
            ? $this->igId()
            : (string) $this->account->page_id;
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$node}/subscribed_apps");
            if (!$r->successful()) {
                return ['ok' => false, 'fields' => [], 'apps' => 0, 'node' => $node,
                        'error' => (string) ($r->json('error.message') ?? 'read failed')];
            }
            // Response shape: { data: [ { subscribed_fields: [...] , ... } ] }.
            // Fields may arrive as plain strings OR as {name:..} objects depending
            // on API version — normalise both to a flat string list.
            $data   = (array) $r->json('data', []);
            $fields = [];
            foreach ($data as $app) {
                foreach ((array) ($app['subscribed_fields'] ?? []) as $f) {
                    $fields[] = is_array($f) ? (string) ($f['name'] ?? '') : (string) $f;
                }
            }
            $fields = array_values(array_unique(array_filter($fields)));
            return ['ok' => true, 'fields' => $fields, 'apps' => count($data), 'node' => $node, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'fields' => [], 'apps' => 0, 'node' => $node, 'error' => $e->getMessage()];
        }
    }

    /**
     * Resolve the account's own MESSAGING igsid (e.g. 1784…) — the id Instagram
     * puts in webhook entry.id / recipient.id, which differs from ig_user_id (the
     * Graph node, 2744…). The account is the ONE participant present in every
     * conversation, so we find it by frequency. Returns '' if undeterminable
     * (e.g. no conversations yet). Persisted to meta_json.self_igsid so webhooks
     * can match the account. Never throws.
     */
    public function resolveSelfIgsid(): string
    {
        try {
            $convos = $this->getConversations(30);
            $freq = [];
            foreach ((array) $convos as $c) {
                foreach ((array) ($c['participants']['data'] ?? []) as $p) {
                    $id = (string) ($p['id'] ?? '');
                    if ($id !== '') $freq[$id] = ($freq[$id] ?? 0) + 1;
                }
            }
            arsort($freq);
            return (string) (array_key_first($freq) ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** Refresh a long-lived IG token (IG-Login), valid 60 days, refreshable after 24h. */
    public function refreshLongLivedToken(): array
    {
        try {
            $r = Http::acceptJson()->timeout(15)->get('https://graph.instagram.com/refresh_access_token', [
                'grant_type'   => 'ig_refresh_token',
                'access_token' => $this->token(),
            ]);
            if ($r->successful() && $r->json('access_token')) {
                return ['ok' => true, 'access_token' => (string) $r->json('access_token'), 'expires_in' => (int) $r->json('expires_in', 0)];
            }
            return ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'refresh failed')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Infer the AI provider from a model id so EVERY provider works in the
     * IG AI nodes (not just OpenAI). Mirrors FlowNodeActionsController::aiCall.
     */
    public static function providerForModel(string $model): string
    {
        $m = strtolower(trim($model));
        return str_starts_with($m, 'claude') ? 'anthropic'
            : (str_starts_with($m, 'gemini') ? 'gemini'
            : ((str_starts_with($m, 'mistral') || str_starts_with($m, 'ministral') || str_starts_with($m, 'open-mistral') || str_starts_with($m, 'open-mixtral')) ? 'mistral'
            : 'openai'));
    }

    // ── Static OAuth helpers (no account instance needed) ──────────────

    /** Exchange an OAuth code for an access token (FB-Login path). */
    public static function exchangeCode(string $code, string $redirectUri): array
    {
        $v      = (string) InstagramGate::setting('instagram_graph_version', 'v25.0');
        $appId  = (string) InstagramGate::setting('instagram_app_id', '');
        $secret = (string) InstagramGate::setting('instagram_app_secret', '');
        try {
            $r = Http::acceptJson()->timeout(15)->get("https://graph.facebook.com/{$v}/oauth/access_token", [
                'client_id'     => $appId,
                'client_secret' => $secret,
                'redirect_uri'  => $redirectUri,
                'code'          => $code,
            ]);
            return $r->successful() ? (array) $r->json() : ['error' => $r->json('error.message', 'token exchange failed')];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Upgrade a short-lived FB user token to a 60-day long-lived token
     * (grant_type=fb_exchange_token). Also used to *refresh* a long-lived
     * token before it expires (re-exchanging resets the 60-day window).
     */
    public static function extendFacebookToken(string $token): array
    {
        $v      = (string) InstagramGate::setting('instagram_graph_version', 'v25.0');
        $appId  = (string) InstagramGate::setting('instagram_app_id', '');
        $secret = (string) InstagramGate::setting('instagram_app_secret', '');
        if ($appId === '' || $secret === '' || $token === '') {
            return ['error' => 'missing app credentials or token'];
        }
        try {
            $r = Http::acceptJson()->timeout(15)->get("https://graph.facebook.com/{$v}/oauth/access_token", [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => $appId,
                'client_secret'     => $secret,
                'fb_exchange_token' => $token,
            ]);
            if ($r->successful() && $r->json('access_token')) {
                return ['ok' => true, 'access_token' => (string) $r->json('access_token'), 'expires_in' => (int) $r->json('expires_in', 5184000)];
            }
            return ['error' => (string) ($r->json('error.message') ?? 'long-lived exchange failed')];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * IG-Login OAuth code exchange (Instagram-Login, not FB). Two steps:
     * POST api.instagram.com/oauth/access_token → short token + user_id, then
     * GET graph.instagram.com/access_token?grant_type=ig_exchange_token → 60-day.
     */
    public static function exchangeCodeInstagram(string $code, string $redirectUri): array
    {
        $appId  = (string) InstagramGate::setting('instagram_ig_app_id', InstagramGate::setting('instagram_app_id', ''));
        $secret = (string) InstagramGate::setting('instagram_ig_app_secret', InstagramGate::setting('instagram_app_secret', ''));
        if ($appId === '' || $secret === '') return ['error' => 'Instagram-Login app id/secret not configured'];
        try {
            $r = Http::asForm()->acceptJson()->timeout(15)->post('https://api.instagram.com/oauth/access_token', [
                'client_id'     => $appId,
                'client_secret' => $secret,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $redirectUri,
                'code'          => $code,
            ]);
            if (!$r->successful() || !$r->json('access_token')) {
                return ['error' => (string) ($r->json('error_message') ?? $r->json('error.message') ?? 'IG token exchange failed')];
            }
            $short  = (string) $r->json('access_token');
            $userId = (string) ($r->json('user_id') ?? $r->json('permissions') ?? '');
            // Upgrade to a 60-day long-lived token.
            $l = Http::acceptJson()->timeout(15)->get('https://graph.instagram.com/access_token', [
                'grant_type'    => 'ig_exchange_token',
                'client_secret' => $secret,
                'access_token'  => $short,
            ]);
            $token = ($l->successful() && $l->json('access_token')) ? (string) $l->json('access_token') : $short;
            $exp   = (int) ($l->json('expires_in') ?? 0);
            return ['ok' => true, 'access_token' => $token, 'user_id' => $userId, 'expires_in' => $exp ?: 5184000];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ── Commerce: Catalog API (Phase 1) ──────────────────────────────────
    // Catalog reads always go through graph.facebook.com — the IG-Login host
    // (graph.instagram.com) does not serve catalog edges — so build the URL
    // explicitly rather than reuse $this->base.

    /**
     * One page of catalog products. Returns ['data' => [...], 'after' => cursor|null,
     * 'error' => string|null]. Caller paginates via $after until it's null.
     */
    public function getCatalogProducts(string $catalogId, ?string $after = null): array
    {
        $v    = (string) InstagramGate::setting('instagram_graph_version', 'v25.0');
        $url  = "https://graph.facebook.com/{$v}/{$catalogId}/products";
        $args = [
            'fields' => 'id,retailer_id,name,description,image_url,url,price,currency,availability',
            'limit'  => 100,
        ];
        if ($after) $args['after'] = $after;

        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(30)->get($url, $args);
            if (! $r->successful()) {
                return ['data' => [], 'after' => null,
                    'error' => (string) ($r->json('error.message') ?? ('HTTP ' . $r->status()))];
            }
            return [
                'data'  => (array) ($r->json('data') ?? []),
                'after' => $r->json('paging.cursors.after'),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['data' => [], 'after' => null, 'error' => $e->getMessage()];
        }
    }

    // ── Lead Ads: instant-form retrieval (Phase 5) ───────────────────────────

    /**
     * Fetch a submitted Lead Ad instant form by its leadgen_id (from the Page
     * `leadgen` webhook). Lead retrieval lives on graph.facebook.com and needs
     * `leads_retrieval`. Returns the raw lead (id/created_time/ad_id/form_id/
     * field_data) or [] on any failure — the caller logs + skips.
     */
    public function getLeadgen(string $leadgenId): array
    {
        $leadgenId = trim($leadgenId);
        if ($leadgenId === '') return [];
        $v = (string) InstagramGate::setting('instagram_graph_version', 'v25.0');
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("https://graph.facebook.com/{$v}/{$leadgenId}", [
                    'fields' => 'id,created_time,ad_id,adgroup_id,form_id,field_data',
                ]);
            if (!$r->successful()) {
                Log::warning('[IG-LEADGEN] fetch failed', [
                    'account' => $this->account->id, 'leadgen' => $leadgenId,
                    'status'  => $r->status(), 'error' => $r->json('error.message'),
                ]);
                return [];
            }
            return (array) $r->json();
        } catch (\Throwable $e) {
            Log::warning('[IG-LEADGEN] fetch threw: ' . $e->getMessage(), ['leadgen' => $leadgenId]);
            return [];
        }
    }
}

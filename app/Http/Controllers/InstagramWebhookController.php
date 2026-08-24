<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Models\InstagramAutomation;
use App\Services\Instagram\InstagramGate;
use App\Services\Instagram\InstagramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Platform webhook — ONE endpoint for everything.
 *   GET  /webhooks/instagram  → subscription verify (echo hub.challenge).
 *   POST /webhooks/instagram  → events (HMAC-verified), fanned out:
 *       - DM          → dm_keyword automations (auto-reply)
 *       - comment     → comment_to_dm automations (public reply + DM)
 *       - (mentions/story-reply hooks land here too — extend as needed)
 * Mirrors our WABA webhook (verify token + X-Hub-Signature-256).
 */
class InstagramWebhookController extends Controller
{
    /** GET — Meta subscription handshake. */
    public function verify(Request $request)
    {
        $expected = (string) InstagramGate::setting('instagram_webhook_verify_token', '');
        // DIAGNOSTIC — proves Meta reached the endpoint during subscription setup.
        Log::info('[IG-HOOK] verify handshake', [
            'mode'        => $request->query('hub_mode'),
            'token_set'   => $expected !== '',
            'token_match' => $expected !== '' && hash_equals($expected, (string) $request->query('hub_verify_token')),
        ]);
        if ($request->query('hub_mode') === 'subscribe'
            && $expected !== ''
            && hash_equals($expected, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200);
        }
        return response('forbidden', 403);
    }

    /** POST — events. */
    public function handle(Request $request)
    {
        // Continue any flow whose Wait node has elapsed. Project policy is
        // no-cron (see routes/console.php) — periodic work rides an existing
        // hot endpoint. This webhook is the best host available: Meta calls it
        // on every inbound DM/comment for EVERY connected account, so it ticks
        // far more often than an operator having the inbox open. Cache-gated so
        // a burst of webhooks doesn't run the sweep dozens of times a second,
        // and wrapped so a sweep failure can never break webhook ingestion.
        try {
            if (\Illuminate\Support\Facades\Cache::add('ig_flow_delay_sweep', 1, 20)) {
                \App\Services\Instagram\IgFlowRunner::sweepDue();
            }
        } catch (\Throwable $e) {
            Log::warning('[IG-FLOW] delay sweep failed: ' . $e->getMessage());
        }

        // DIAGNOSTIC — log EVERY inbound POST so you can confirm Meta is actually
        // calling the webhook. If you DM the account and DON'T see this line in
        // storage/logs/laravel.log, Meta never hit us → it's a subscription /
        // dev-mode-tester / reachability issue, NOT our code. (Body truncated.)
        Log::info('[IG-HOOK] POST received', [
            'has_sig' => $request->hasHeader('X-Hub-Signature-256'),
            'len'     => strlen($request->getContent()),
            'body'    => mb_substr($request->getContent(), 0, 1500),
        ]);

        // Signature check (sha256=HMAC of the raw body with the app secret).
        // FAIL CLOSED: the Meta/IG app secret is a platform setting that must
        // always be present. If it is genuinely unset we refuse to process the
        // (unverifiable) payload rather than trusting attacker-supplied entry.id
        // to drive DMs/comments/flows for a victim's connected account. The
        // Instagram-Login OAuth path exchanges tokens with instagram_ig_app_secret,
        // so a webhook may be signed by either app — accept a valid signature
        // under EITHER configured secret before refusing.
        $sig     = (string) $request->header('X-Hub-Signature-256', '');
        $secrets = array_values(array_unique(array_filter([
            (string) InstagramGate::setting('instagram_app_secret', ''),
            (string) InstagramGate::setting('instagram_ig_app_secret', ''),
        ])));
        if (empty($secrets)) {
            // No secret configured anywhere — cannot verify. Refuse to process
            // any state-changing effects. Ack 200 so Meta doesn't retry-storm,
            // but do NOT touch accounts/flows/AI.
            Log::warning('[IG-HOOK] instagram_app_secret NOT configured — refusing to process unverifiable webhook (set the Meta App Secret in Instagram settings)');
            return response('signature verification not configured', 200);
        }
        if ($sig === '') {
            Log::warning('[IG-HOOK] missing X-Hub-Signature-256 (secret is set)');
            return response('missing signature', 403);
        }
        $sigOk = false;
        foreach ($secrets as $secret) {
            if (hash_equals('sha256=' . hash_hmac('sha256', $request->getContent(), $secret), $sig)) {
                $sigOk = true;
                break;
            }
        }
        if (!$sigOk) {
            Log::warning('[IG-HOOK] bad signature');
            return response('bad signature', 403);
        }

        $body = $request->all();
        Log::info('[IG-HOOK] signature OK — parsing', [
            'object'  => (string) ($body['object'] ?? ''),
            'entries' => count((array) ($body['entry'] ?? [])),
        ]);
        foreach ((array) ($body['entry'] ?? []) as $ei => $entry) {
            $igId    = (string) ($entry['id'] ?? '');
            // Trace EXACTLY what shape this entry carries so a mismatch between
            // the messaging[] (FB-Login) vs changes[] (IG-Login) channel is obvious.
            Log::info('[IG-HOOK] entry ' . $ei, [
                'entry_id'      => $igId,
                'top_keys'      => array_keys((array) $entry),
                'messaging_ct'  => count((array) ($entry['messaging'] ?? [])),
                'changes_flds'  => array_map(fn ($c) => (string) ($c['field'] ?? '?'), (array) ($entry['changes'] ?? [])),
            ]);
            // Instagram delivers webhooks keyed by the account's MESSAGING igsid
            // (entry.id / recipient.id, e.g. 1784…) which is DIFFERENT from the Graph
            // node id we store in ig_user_id (e.g. 2744…, used for subscribed_apps +
            // API calls). Matching only ig_user_id therefore dropped every DM. Match
            // either id, plus page_id (FB-Login) and the messaging igsid we persist
            // in meta_json.self_igsid (from inbox sync / Check-webhook / self-heal).
            $account = $igId
                ? InstagramAccount::where('status', 'connected')
                    ->where(function ($q) use ($igId) {
                        $q->where('ig_user_id', $igId)
                          ->orWhere('page_id', $igId)
                          ->orWhere('meta_json->self_igsid', $igId);
                    })->first()
                : null;
            if (!$account) {
                // DEEP DIAG — the #1 reason inbound "does nothing": entry.id (the
                // IG id Meta sends) doesn't match any CONNECTED account row. Dump
                // what we DO have so the mismatch (wrong id stored, or a
                // disconnected/duplicate row) is visible at a glance.
                $anyRow = $igId ? InstagramAccount::where('ig_user_id', $igId)->first() : null;
                Log::warning('[IG-HOOK] no CONNECTED account for ig_user_id — inbound dropped', [
                    'incoming_ig_id'    => $igId,
                    'row_with_that_id'  => $anyRow ? ['id' => $anyRow->id, 'status' => $anyRow->status, 'ws' => $anyRow->workspace_id] : null,
                    'connected_accounts'=> InstagramAccount::where('status', 'connected')
                        ->get(['id', 'ig_user_id', 'username', 'workspace_id'])
                        ->map(fn ($a) => [
                            'id'         => $a->id,
                            'ig_user_id' => (string) $a->ig_user_id,
                            'username'   => (string) $a->username,
                            'ws'         => $a->workspace_id,
                        ])->all(),
                    'all_accounts_ct'   => InstagramAccount::count(),
                ]);
                continue;
            }
            Log::info('[IG-HOOK] entry matched account', ['account' => $account->id, 'ig_user_id' => $igId, 'ws' => $account->workspace_id]);

            // Self-heal: entry.id IS this account's messaging igsid. Persist it so
            // every future webhook matches instantly (even if a sync never runs).
            if ($igId !== '' && (string) ($account->meta_json['self_igsid'] ?? '') !== $igId) {
                try {
                    $meta = (array) $account->meta_json;
                    $meta['self_igsid'] = $igId;
                    $account->forceFill(['meta_json' => $meta])->save();
                } catch (\Throwable $e) { Log::info('[IG-HOOK] self_igsid heal failed: ' . $e->getMessage()); }
            }

            // DMs + postbacks + reactions + reads + referrals (messaging channel).
            // Web-verified shapes: reaction/read/referral are TOP-LEVEL keys on
            // the messaging entry; postback too (handled inside onDm).
            foreach ((array) ($entry['messaging'] ?? []) as $m) {
                if (isset($m['reaction'])) { $this->onReaction($account, $m); continue; }
                if (isset($m['read']))     { $this->onRead($account, $m); continue; } // messaging_seen — read receipt
                if (isset($m['referral']) && !isset($m['message']) && !isset($m['postback'])) {
                    Log::info('[IG-HOOK] referral', ['account' => $account->id, 'ref' => $m['referral']['ref'] ?? '']);
                    continue;
                }
                $this->onDm($account, $m); // text, quick_reply, postback, story reply/mention
            }
            // Comments / live comments / mentions (the "changes" channel).
            foreach ((array) ($entry['changes'] ?? []) as $c) {
                $field = (string) ($c['field'] ?? '');
                // Instagram API w/ Instagram Login (the "no FB Page" path) delivers
                // DMs under changes[].field='messages' — NOT entry.messaging[] like
                // the Facebook/Messenger path. The value carries the same
                // {sender,recipient,message,postback} shape, so route it to onDm.
                if ($field === 'messages')                                $this->onDm($account, (array) ($c['value'] ?? []));
                elseif ($field === 'comments' || $field === 'live_comments') $this->onComment($account, (array) ($c['value'] ?? []));
                elseif ($field === 'mentions')                            $this->onMention($account, (array) ($c['value'] ?? []));
                elseif ($field === 'leadgen')                             $this->onLeadgen($account, (array) ($c['value'] ?? []));
            }
        }
        return response('ok', 200); // always 200 so Meta doesn't retry-storm
    }

    /**
     * Razorpay webhook for in-DM Instagram order payment links. Verifies the
     * signature (inside applyWebhook, against the merchant's storefront secret)
     * and flips the order to paid. Always 200 so Razorpay doesn't retry-storm.
     */
    public function razorpayWebhook(Request $request)
    {
        $raw     = $request->getContent();
        $sig     = $request->header('X-Razorpay-Signature');
        $payload = json_decode($raw, true) ?: [];
        $event   = (string) ($payload['event'] ?? '');
        if (! in_array($event, ['payment_link.paid', 'payment.captured'], true)) {
            return response('ignored', 200);
        }
        try {
            app(\App\Services\Instagram\InstagramPaymentService::class)->applyWebhook($raw, $sig, (array) $payload);
        } catch (\Throwable $e) {
            Log::warning('[IG-PAY] webhook error: ' . $e->getMessage());
        }
        return response('ok', 200);
    }

    /**
     * Meta Lead Ad submission (Page `leadgen` webhook). Fetch the full form by
     * leadgen_id, store it as an instagram_lead (source='lead_ad'), and drop it
     * into the pipeline. Idempotent — the unique leadgen_id de-dups Meta retries.
     */
    private function onLeadgen(InstagramAccount $account, array $value): void
    {
        $leadgenId = (string) ($value['leadgen_id'] ?? '');
        if ($leadgenId === '') return;
        if (\App\Models\InstagramLead::where('leadgen_id', $leadgenId)->exists()) return;

        Log::info('[IG-HOOK] leadgen in', ['account' => $account->id, 'leadgen' => $leadgenId, 'form' => $value['form_id'] ?? '']);

        $lead = (new InstagramService($account))->getLeadgen($leadgenId);
        if (empty($lead)) {
            Log::info('[IG-HOOK] leadgen fetch empty — check leads_retrieval permission + Page token', ['leadgen' => $leadgenId]);
            return;
        }
        app(\App\Services\Instagram\InstagramLeadService::class)->captureFromLeadAd($account, $value, $lead);
    }

    /** Inbound DM → match dm_keyword automations and auto-reply. */
    private function onDm(InstagramAccount $account, array $m): void
    {
        $igsid = (string) ($m['sender']['id'] ?? '');
        // TRACE — proves onDm was reached + shows the exact payload shape so a
        // missing sender / unexpected field (why "nothing happens") is visible.
        Log::info('[IG-HOOK] onDm enter', [
            'account'  => $account->id,
            'from'     => $igsid,
            'keys'     => array_keys($m),
            'has_msg'  => isset($m['message']),
            'has_text' => isset($m['message']['text']),
            'has_pb'   => isset($m['postback']),
            'is_echo'  => !empty($m['message']['is_echo']),
        ]);
        // UNSEND (web-verified): when the customer deletes a message, Meta sends
        // message.is_deleted=true with the original mid and NO text/attachment.
        // The old code logged that as a fresh empty inbound row → a blank bubble.
        // Instead, flip the ORIGINAL message to a "deleted" state so the inbox
        // shows "This message was deleted" (and never a blank bubble). If we
        // never stored the original, do nothing — no phantom bubble.
        if (!empty($m['message']['is_deleted'])) {
            $delMid = (string) ($m['message']['mid'] ?? '');
            if ($delMid !== '') {
                \App\Models\InstagramMessage::where('instagram_account_id', $account->id)
                    ->where('mid', $delMid)
                    ->update(['body' => '', 'attachment_type' => 'deleted', 'attachment_url' => null]);
            }
            Log::info('[IG-HOOK] message unsent', ['account' => $account->id, 'mid' => $delMid]);
            return;
        }
        // BUG FIX (web-verified): Button-Template / CTA / icebreaker taps arrive
        // as a TOP-LEVEL `postback` (siblings .payload/.title/.mid) — NOT inside
        // `message`. The old code only read message.text + message.quick_reply,
        // so every button tap was dropped. Treat a postback like a quick-reply:
        // its payload (fallback to the visible title) drives flow/keyword routing.
        $isPostback = isset($m['postback']);
        $text = $isPostback
            ? (string) ($m['postback']['payload'] ?? $m['postback']['title'] ?? '')
            : (string) ($m['message']['text'] ?? $m['message']['quick_reply']['payload'] ?? '');
        $mid = (string) ($m['message']['mid'] ?? $m['postback']['mid'] ?? '');
        // Extract inbound MEDIA (image / video / reel / audio / file / share /
        // story) so the inbox renders the actual attachment instead of an empty
        // "[media]" bubble. Meta delivers these as message.attachments[] with
        // {type, payload.url}. Keep the first attachment; type drives the renderer.
        $attachType = null; $attachUrl = null;
        // Some shares carry the media under a different payload key (or none —
        // reels/posts shared in DM sometimes ship only a title/reel_video_id).
        // Keep the attachment for those types anyway so the inbox renders a
        // "Shared a reel" card instead of dropping the bubble.
        $keepWithoutUrl = ['story_mention', 'share', 'ig_reel', 'reel'];
        $attachMeta = null;
        foreach ((array) ($m['message']['attachments'] ?? []) as $att) {
            $t = (string) ($att['type'] ?? '');
            $u = (string) ($att['payload']['url'] ?? $att['payload']['link'] ?? '');
            if ($t !== '' && ($u !== '' || in_array($t, $keepWithoutUrl, true))) {
                $attachType = $t;            // image|video|audio|file|share|story_mention|ig_reel
                // Copy real media off Meta's expiring CDN to our own disk so the
                // bubble renders now AND later (shares keep their permalink).
                $attachUrl  = $u !== '' ? \App\Services\Instagram\InstagramMediaFetcher::localize($u, $t) : null;
                // A shared reel ships its CAPTION as payload.title (plus
                // reel_video_id). We were dropping both, which is why reel
                // bubbles had nothing to say — Instagram prints that caption
                // over the video. Keep them; the renderer uses `title`.
                $title = trim((string) ($att['payload']['title'] ?? ''));
                $reelId = trim((string) ($att['payload']['reel_video_id'] ?? ''));
                if ($title !== '' || $reelId !== '') {
                    $attachMeta = array_filter([
                        'title'         => $title !== '' ? $title : null,
                        'reel_video_id' => $reelId !== '' ? $reelId : null,
                    ]);
                }
                break;
            }
        }
        // Story reply → attach the replied-to story's thumbnail so the inbox
        // shows a "Replied to your story" preview like the IG app. Meta delivers
        // it under message.reply_to.story (url = the story's media).
        if ($attachType === null && !empty($m['message']['reply_to']['story'])) {
            $storyUrl   = (string) ($m['message']['reply_to']['story']['url'] ?? $m['message']['reply_to']['story']['link'] ?? '');
            $attachType = 'story_reply';
            $attachUrl  = $storyUrl !== '' ? \App\Services\Instagram\InstagramMediaFetcher::localize($storyUrl, 'image') : null;
        }
        // Ignore echoes of our own outbound messages (echoes carry message.is_echo).
        if (!empty($m['message']['is_echo']) || $igsid === '' || $igsid === $account->ig_user_id) {
            Log::info('[IG-HOOK] DM skipped', [
                'echo'      => !empty($m['message']['is_echo']),
                'no_sender' => $igsid === '',
                'self'      => $igsid !== '' && $igsid === $account->ig_user_id,
            ]);
            return;
        }

        Log::info('[IG-HOOK] ' . ($isPostback ? 'postback' : 'DM') . ' in', ['account' => $account->id, 'from' => $igsid, 'len' => strlen($text), 'media' => $attachType]);
        // messaging entries carry Meta's own ms-epoch `timestamp` — keep it, so a
        // DM that reaches us late still sorts by when it was actually sent.
        \App\Models\InstagramMessage::log($account, $igsid, 'in', $text, $isPostback ? 'postback' : null, $mid, $attachType, $attachUrl, $attachMeta, $m['timestamp'] ?? null);
        // Resolve WHO this is — fetch + store their @username/name/avatar the
        // first time we see this IGSID, so the inbox labels the thread by the
        // sender (not our own account handle). Best-effort; never blocks.
        try { \App\Models\InstagramContact::touchFromInbound($account, $igsid); }
        catch (\Throwable $e) { Log::info('[IG-CONTACT] touch failed: ' . $e->getMessage()); }

        // Mirror this DM into a connected WaDesk install's unified team inbox.
        // Runs AFTER the contact touch so the pushed thread carries the sender's
        // resolved name/avatar. Best-effort — a down/unlinked WaDesk never blocks
        // Instagram processing (no-op when no connection is configured).
        try {
            \App\Services\Instagram\WadeskPushService::pushInbound(
                $account, $igsid, $attachType ?: 'text', $text, $attachUrl, $mid
            );
        } catch (\Throwable $e) { Log::info('[WADESK-PUSH] hook failed: ' . $e->getMessage()); }

        // A flow paused at a quick-reply / button node? This tap resumes it
        // from the matched branch — takes priority over fresh triggers.
        try {
            // Ask the Node engine first — if IT parked this conversation (quick
            // reply / Ask), Node owns the resume and its in-memory session is
            // the only place that knows where the walk stopped.
            if (\App\Services\Instagram\IgFlowNodeBridge::resume($account, $igsid, $text)) return;
            // Otherwise the PHP runner may hold a session (Node was down when
            // the flow started, or this is a pre-existing parked flow).
            if (\App\Services\Instagram\IgFlowRunner::resumeFor($account, $igsid, $text)) return;
        } catch (\Throwable $e) { Log::warning('[IG-FLOW] resume failed: ' . $e->getMessage()); }

        // In-DM ordering: an "Order" tap (postback ORDER_<retailer_id>) or an
        // in-progress order conversation — handled before keyword rules.
        try {
            if (app(\App\Services\Instagram\InstagramOrderingService::class)->handle($account, $igsid, $text)) return;
        } catch (\Throwable $e) { Log::warning('[IG-ORDER] handle failed: ' . $e->getMessage()); }

        // Zero-config storefront: "shop" / "catalog" / "menu" → instantly send
        // the product carousel (opt-in, throttled). No flow needed.
        try {
            if (app(\App\Services\Instagram\InstagramCommerceService::class)->maybeAutoShop($account, $igsid, $text)) return;
        } catch (\Throwable $e) { Log::warning('[IG-SHOP] auto failed: ' . $e->getMessage()); }

        // Story reply / story mention (both arrive as DMs, per Meta docs).
        // A REPLY (they reply to your story) fires story_reply rules. A MENTION
        // (they @-tag you in THEIR story) fires story_mention rules first, then
        // falls back to story_reply so pre-split setups keep working.
        $isReply   = !empty($m['message']['reply_to']['story']);
        $isMention = false;
        foreach ((array) ($m['message']['attachments'] ?? []) as $att) {
            if (($att['type'] ?? '') === 'story_mention') $isMention = true;
        }
        if ($isReply || $isMention) {
            $storyTypes = $isMention ? ['story_mention', 'story_reply'] : ['story_reply'];
            foreach (InstagramAutomation::where('instagram_account_id', $account->id)
                ->whereIn('type', $storyTypes)->where('is_active', true)
                // story_mention rules win over story_reply for a mention.
                ->orderByRaw("FIELD(type, 'story_mention', 'story_reply'), id")->get() as $rule) {
                if (!$rule->matches($text)) continue;
                if ($rule->flow_id) {
                    if ($this->runFlow($account, (int) $rule->flow_id, ['igsid' => $igsid, 'text' => $text])) { $rule->increment('fired_count'); return; }
                    continue;
                }
                $storyMsg = $rule->pickDmMessage();   // random variation (ManyChat: up to 6)
                $r = (new InstagramService($account))->sendDm($igsid, $storyMsg);
                if (!empty($r['ok'])) {
                    $rule->increment('fired_count');
                    \App\Models\InstagramMessage::log($account, $igsid, 'out', $storyMsg, 'story', $r['mid'] ?? null);
                    return;
                }
            }
        }

        $rules = InstagramAutomation::where('instagram_account_id', $account->id)
            ->where('type', 'dm_keyword')->where('is_active', true)->orderBy('id')->get();

        foreach ($rules as $rule) {
            if (!$rule->matches($text)) continue;
            $svc = new InstagramService($account);
            $kwMsg = $rule->pickDmMessage();   // random variation (or the single message)
            $r = $svc->sendDm($igsid, $kwMsg);
            if (!empty($r['ok'])) {
                $rule->increment('fired_count');
                \App\Models\InstagramMessage::log($account, $igsid, 'out', $kwMsg, 'keyword', $r['mid'] ?? null);
            } else Log::warning('[IG-HOOK] DM reply failed', ['rule' => $rule->id, 'err' => $r['error'] ?? '']);
            return; // first keyword match wins
        }

        // Flow automations — run a visual Instagram flow when matched.
        foreach (InstagramAutomation::where('instagram_account_id', $account->id)
            ->where('type', 'flow')->where('is_active', true)->orderBy('id')->get() as $rule) {
            if (!$rule->matches($text)) continue;
            if ($this->runFlow($account, (int) $rule->flow_id, ['igsid' => $igsid, 'text' => $text])) {
                $rule->increment('fired_count');
                return;
            }
        }

        // Flows whose OWN Trigger node carries the keyword. On WhatsApp the
        // builder mirrors that keyword into a keyword_replies row and the Node
        // matcher fires it; Instagram has no such matcher, so without this an
        // operator who set Channel=Instagram + keywords in the builder would
        // see nothing happen unless they ALSO hand-made an automation row.
        // trigger_device_id is the instagram_accounts id for these flows;
        // NULL means "any account in this workspace".
        if ($text !== '') {
            $flows = $this->scopeFlowsToOwner(InstagramGate::flows(), $account)
                ->where('flow_type', 'instagram')
                ->where('trigger_kind', 'keyword')
                ->where('is_published', true)->where('is_active', true)
                ->whereNotNull('trigger_keywords')
                ->where(fn ($q) => $q->where('trigger_device_id', $account->id)->orWhereNull('trigger_device_id'))
                ->orderBy('id')->get();
            foreach ($flows as $f) {
                if (!$this->keywordHits((string) $f->trigger_keywords, $text)) continue;
                Log::info('[IG-FLOW] trigger-node keyword matched', ['flow' => $f->id, 'account' => $account->id]);
                if ($this->runFlow($account, (int) $f->id, ['igsid' => $igsid, 'text' => $text])) return;
            }
        }

        // Diagnostic — if we reach here NO keyword/flow/story rule matched.
        // Surface WHY nothing auto-replied so "auto-reply isn't working" is
        // debuggable from the log. The usual cause is simply that there is no
        // ACTIVE automation (an empty map below == none set up / all paused).
        $activeCounts = InstagramAutomation::where('instagram_account_id', $account->id)
            ->where('is_active', true)
            ->selectRaw('type, COUNT(*) AS c')->groupBy('type')->pluck('c', 'type')->all();
        Log::info('[IG-HOOK] no keyword rule matched → trying AI agent', [
            'account'            => $account->id,
            'text'               => mb_substr($text, 0, 40),
            // e.g. {"dm_keyword":2,"ai_agent":1}; {} means NO active automation
            // exists for this account — nothing can auto-reply until one is added.
            'active_automations' => $activeCounts,
        ]);

        // No keyword/flow rule matched → AI agent (if one is configured).
        $this->aiReply($account, $igsid, $text);
    }

    /**
     * Inbound message reaction (message_reactions field). Web-verified shape:
     * top-level `reaction` with action=react|unreact; react carries reaction
     * (name, e.g. "love") + emoji. We log it onto the thread so the inbox shows
     * "❤️ reacted". (Subscribe the app/account to message_reactions.)
     */
    private function onReaction(InstagramAccount $account, array $m): void
    {
        $igsid = (string) ($m['sender']['id'] ?? '');
        if ($igsid === '' || $igsid === $account->ig_user_id) return;
        $reaction = (array) ($m['reaction'] ?? []);
        $action   = (string) ($reaction['action'] ?? '');
        $emoji    = (string) ($reaction['emoji'] ?? $reaction['reaction'] ?? '');
        Log::info('[IG-HOOK] reaction', ['account' => $account->id, 'from' => $igsid, 'action' => $action, 'reaction' => $emoji]);
        \App\Models\InstagramMessage::log(
            $account, $igsid, 'in',
            $action === 'unreact' ? 'removed reaction' : trim('reacted ' . $emoji),
            'reaction', (string) ($reaction['mid'] ?? ''),
            // Reaction entries carry the same ms-epoch `timestamp` as a DM.
            null, null, null, $m['timestamp'] ?? null
        );
    }

    /**
     * Read receipt (messaging_seen) — mark this contact's already-sent bulk-DM
     * rows as read. Instagram is one of the few Meta channels that reports read
     * receipts for DMs (it does NOT report "delivered"), so this is the only
     * post-send engagement signal a bulk DM can surface.
     */
    private function onRead(InstagramAccount $account, array $m): void
    {
        $igsid = (string) ($m['sender']['id'] ?? '');
        if ($igsid === '' || $igsid === $account->ig_user_id) return;
        $bcIds = \App\Models\InstagramBroadcast::where('instagram_account_id', $account->id)->pluck('id');
        if ($bcIds->isEmpty()) return;
        $n = \App\Models\InstagramBroadcastRecipient::whereIn('broadcast_id', $bcIds)
            ->where('igsid', $igsid)->where('status', 'sent')->whereNull('read_at')
            ->update(['read_at' => now()]);
        if ($n) Log::info('[IG-HOOK] read receipt', ['account' => $account->id, 'from' => $igsid, 'marked' => $n]);
    }

    /**
     * Does any keyword in a comma-separated trigger list appear in $text?
     *
     * WHOLE-WORD, matching the WhatsApp matcher — a bare str_contains made
     * "hi" fire on "this", which is the substring bug already fixed on the
     * WhatsApp side. `*` / `.*` means catch-all.
     */
    private function keywordHits(string $list, string $text): bool
    {
        $hay = mb_strtolower(trim($text));
        if ($hay === '') return false;
        foreach (preg_split('/[\r\n,]+/', $list) ?: [] as $kw) {
            $kw = mb_strtolower(trim((string) $kw));
            if ($kw === '') continue;
            if (in_array($kw, ['*', '.*', '.+'], true)) return true;
            if (preg_match('/(?<!\p{L})' . preg_quote($kw, '/') . '(?!\p{L})/u', $hay)) return true;
        }
        return false;
    }

    /**
     * Load a flow_type=instagram flow and run it.
     *
     * PREFERS THE NODE ENGINE (node/services/instagramFlowService.js): Node is a
     * long-lived process, so a Wait node just awaits a timer instead of parking
     * in the DB and waiting for later traffic to sweep it — the same model the
     * Baileys flow engine has always used.
     *
     * Falls back to the in-process PHP runner when Node is unconfigured or
     * unreachable, so a flow is never lost just because pm2 is down.
     */
    /**
     * Scope a flows query to the account's OWNER.
     *
     * WaDesk is multi-tenant on workspace_id, so there a connected account and
     * its flows share a non-zero workspace_id. IgDesk (standalone) has no
     * workspaces — every row is workspace_id=0/NULL and ownership lives on
     * user_id (connect + flow store both stamp Auth::id()). Gating on
     * workspace_id there matched nothing, so builder flows never fired. Pick the
     * key that is actually meaningful for this install.
     */
    private function scopeFlowsToOwner($query, InstagramAccount $account)
    {
        if ((int) $account->workspace_id > 0) {
            return $query->where('workspace_id', $account->workspace_id);
        }
        return $query->where('user_id', (int) $account->user_id);
    }

    private function runFlow(InstagramAccount $account, int $flowId, array $ctx): bool
    {
        if ($flowId <= 0) return false;
        $flow = $this->scopeFlowsToOwner(InstagramGate::flows(), $account)->where('id', $flowId)->first();
        if (!$flow) return false;
        $data = $flow->decoded_flow_data;
        if (!is_array($data) || !\App\Services\Instagram\IgFlowRunner::nodesOf($data)) return false;

        try {
            if (\App\Services\Instagram\IgFlowNodeBridge::start(
                $account, $flow,
                (string) ($ctx['igsid'] ?? ''),
                (string) ($ctx['text'] ?? ''),
                (string) ($ctx['comment_id'] ?? '')
            )) {
                Log::info('[IG-FLOW] handed to the Node engine', ['flow' => $flowId, 'account' => $account->id]);
                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('[IG-FLOW] Node hand-off errored, using the PHP runner: ' . $e->getMessage());
        }

        try { (new \App\Services\Instagram\IgFlowRunner($account))->run($data, $ctx, $flowId); }
        catch (\Throwable $e) { Log::warning('[IG-FLOW] run failed: ' . $e->getMessage()); return false; }
        return true;
    }

    /**
     * AI auto-reply: when an `ai_agent` automation exists, generate a reply
     * with the same AiAgentService + AI-Training knowledge base the chat /
     * call flows use, and DM it back. Honours the 24h messaging window (the
     * webhook only fires within it on inbound).
     */
    private function aiReply(InstagramAccount $account, string $igsid, string $text): void
    {
        // Per-conversation handoff + agent selection (Team-Inbox style): a human
        // can pause AI on this thread, OR pin a SPECIFIC ai_agent to it.
        $contact = \App\Models\InstagramContact::where('instagram_account_id', $account->id)
            ->where('igsid', $igsid)->first();
        if ($contact && $contact->ai_enabled === false) {
            Log::info('[IG-HOOK] AI paused for thread (human handoff)', ['account' => $account->id, 'igsid' => $igsid]);
            return;
        }

        // Prefer the agent assigned to THIS conversation; fall back to the
        // account's default (first active) agent when none is pinned or the
        // pinned one was paused/deleted.
        $rule = null;
        if ($contact && $contact->ai_agent_id) {
            $rule = InstagramAutomation::where('instagram_account_id', $account->id)
                ->where('type', 'ai_agent')->where('is_active', true)
                ->whereKey($contact->ai_agent_id)->first();
        }
        if (!$rule) {
            $rule = InstagramAutomation::where('instagram_account_id', $account->id)
                ->where('type', 'ai_agent')->where('is_active', true)->orderBy('id')->first();
        }
        if (!$rule) return;

        $system = trim((string) $rule->dm_message) ?: 'You are a helpful Instagram assistant for this business. Reply briefly and warmly.';

        // Optional AI-Training knowledge base.
        $assistantId = (int) ($rule->meta_json['assistant_id'] ?? 0);
        if ($assistantId > 0) {
            $assistant = InstagramGate::aiAssistant((int) $account->workspace_id, $assistantId);
            if ($assistant) {
                try {
                    $kb = app(\App\Services\AiChat\AiChatService::class)->contextFor($assistant);
                    if (trim($kb) !== '') $system .= "\n\n--- Knowledge base ---\n" . $kb . "\n--- End knowledge base ---";
                } catch (\Throwable $e) { /* best-effort */ }
            }
        }

        // Conversation memory — last few turns of this thread become context.
        $history = \App\Models\InstagramMessage::where('instagram_account_id', $account->id)
            ->where('igsid', $igsid)->orderByDesc('id')->limit(8)->get()->reverse();
        $transcript = '';
        foreach ($history as $h) {
            $who = $h->direction === 'in' ? 'Customer' : 'You';
            $transcript .= $who . ': ' . trim((string) $h->body) . "\n";
        }
        $userPrompt = $transcript !== ''
            ? "Conversation so far:\n{$transcript}\nCustomer just said: {$text}\nReply as You:"
            : $text;

        $model = (string) ($rule->meta_json['model'] ?? 'gpt-4o-mini');
        // Rich agent settings from the inbox create modal (all optional).
        $maxTok = (int) ($rule->meta_json['max_tokens'] ?? 0) ?: 300;
        // Form stores temperature 0–10 (0=focused, 10=creative) → provider wants 0–2.
        $temp   = isset($rule->meta_json['temperature'])
            ? max(0.0, min(2.0, ((int) $rule->meta_json['temperature']) / 5))
            : 0.6;
        $tone   = trim((string) ($rule->meta_json['tone'] ?? ''));
        if ($tone !== '') $system = ucfirst($tone) . " tone. " . $system;
        try {
            $reply = app(\App\Services\AiAgentService::class)->callProvider(
                provider:     \App\Services\Instagram\InstagramService::providerForModel($model),
                model:        $model,
                workspaceId:  (int) $account->workspace_id,
                systemPrompt: $system,
                userPrompt:   $userPrompt,
                maxTokens:    $maxTok,
                temperature:  $temp,
            );
        } catch (\Throwable $e) {
            Log::warning('[IG-HOOK] AI reply threw: ' . $e->getMessage());
            $reply = null;
        }
        if ($reply === null || trim($reply) === '') return;

        $r = (new InstagramService($account))->sendDm($igsid, trim($reply));
        if (!empty($r['ok'])) {
            $rule->increment('fired_count');
            \App\Models\InstagramMessage::log($account, $igsid, 'out', trim($reply), 'ai', $r['mid'] ?? null);
        }
    }

    /** Inbound comment → comment_to_dm automations (public reply + private DM). */
    private function onComment(InstagramAccount $account, array $value): void
    {
        // Comment-id key inconsistency (web-verified): the Examples page uses
        // `comment_id`, the Graph reference schema uses `id`. Accept both.
        $commentId = (string) ($value['comment_id'] ?? $value['id'] ?? '');
        $text      = (string) ($value['text'] ?? '');
        $postId    = (string) ($value['media']['id'] ?? '');
        $fromId    = (string) ($value['from']['id'] ?? '');
        // Skip our own comments.
        if ($commentId === '' || $fromId === $account->ig_user_id) return;

        Log::info('[IG-HOOK] comment in', ['account' => $account->id, 'post' => $postId, 'comment' => $commentId, 'from' => $fromId, 'text' => \Illuminate\Support\Str::limit($text, 80)]);

        $rules = InstagramAutomation::where('instagram_account_id', $account->id)
            ->where('type', 'comment_to_dm')->where('is_active', true)->orderBy('id')->get();

        Log::info('[IG-COMMENT] active comment_to_dm rules', ['account' => $account->id, 'count' => $rules->count(), 'ids' => $rules->pluck('id')->all()]);

        foreach ($rules as $rule) {
            // Optional per-post scoping.
            if ($rule->post_id && $postId && $rule->post_id !== $postId) {
                Log::info('[IG-COMMENT] rule skipped — post filter mismatch', ['rule' => $rule->id, 'rule_post' => $rule->post_id, 'comment_post' => $postId]);
                continue;
            }
            if (!$rule->matches($text)) {
                Log::info('[IG-COMMENT] rule skipped — keyword no match', ['rule' => $rule->id, 'mode' => $rule->match_mode, 'keyword' => $rule->trigger_keyword, 'text' => \Illuminate\Support\Str::limit($text, 80)]);
                continue;
            }

            Log::info('[IG-COMMENT] rule MATCHED — sending public reply + DM', ['rule' => $rule->id]);
            $svc = new InstagramService($account);
            if ($rule->public_reply) {
                // Rotate: pick one of the saved public-reply variations at random.
                $pr = $svc->replyComment($commentId, $rule->pickPublicReply());
                Log::info('[IG-COMMENT] public reply result', ['rule' => $rule->id, 'ok' => !empty($pr['ok']), 'err' => $pr['error'] ?? null]);
            }
            if ($rule->dm_message) {
                $dmMsg = $rule->pickDmMessage();   // random variation (or the single message)
                $r = $svc->privateReply($commentId, $dmMsg);
                Log::info('[IG-COMMENT] private reply (DM) result', ['rule' => $rule->id, 'ok' => !empty($r['ok']), 'err' => $r['error'] ?? null]);
                if (!empty($r['ok'])) {
                    $rule->increment('fired_count');
                    if ($fromId !== '') {
                        \App\Models\InstagramMessage::log($account, $fromId, 'in', $text, 'comment');
                        \App\Models\InstagramMessage::log($account, $fromId, 'out', $dmMsg, 'comment', $r['mid'] ?? null);
                    }
                } else Log::warning('[IG-HOOK] private reply failed', ['rule' => $rule->id, 'err' => $r['error'] ?? '']);
            }
            return; // first match wins
        }
        Log::info('[IG-COMMENT] no comment_to_dm rule matched — nothing sent', ['account' => $account->id]);

        // Flow automations on comments — run a visual flow (comment → DM sequence).
        foreach (InstagramAutomation::where('instagram_account_id', $account->id)
            ->where('type', 'flow')->where('is_active', true)->orderBy('id')->get() as $rule) {
            if ($rule->post_id && $postId && $rule->post_id !== $postId) continue;
            if (!$rule->matches($text)) continue;
            if ($fromId && $this->runFlow($account, (int) $rule->flow_id, ['igsid' => $fromId, 'text' => $text, 'comment_id' => $commentId])) {
                $rule->increment('fired_count');
                return;
            }
        }
    }

    /**
     * Inbound @mention (the `mentions` webhook field). Payload carries
     * media_id and, for a comment mention, comment_id. We post a public
     * reply on the mention — DMing isn't possible here (no IGSID in payload).
     */
    private function onMention(InstagramAccount $account, array $value): void
    {
        $commentId = (string) ($value['comment_id'] ?? '');
        $mediaId   = (string) ($value['media_id'] ?? '');
        if ($commentId === '' && $mediaId === '') return;
        Log::info('[IG-HOOK] mention in', ['account' => $account->id, 'media' => $mediaId, 'comment' => $commentId]);

        $svc = new InstagramService($account);
        foreach (InstagramAutomation::where('instagram_account_id', $account->id)
            ->where('type', 'mention')->where('is_active', true)->orderBy('id')->get() as $rule) {
            $reply = trim((string) ($rule->public_reply ?: $rule->dm_message));
            if ($reply === '') continue;
            if ($commentId !== '') { $svc->replyComment($commentId, $reply); $rule->increment('fired_count'); return; }
            // Caption mention (no comment) → leave a comment on the media.
            if ($mediaId !== '') { $svc->commentOnMedia($mediaId, $reply); $rule->increment('fired_count'); return; }
        }
    }
}

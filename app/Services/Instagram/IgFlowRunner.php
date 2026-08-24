<?php

namespace App\Services\Instagram;

use App\Models\InstagramAccount;
use App\Models\InstagramFlowSession;
use App\Models\InstagramMessage;
use Illuminate\Support\Facades\Log;

/**
 * Executes a visual Instagram flow (flow_type 'instagram') against inbound
 * events. Reads the RAW builder JSON (nodes {id,type,data}, edges
 * {source,sourceHandle,target}). Linear walk from the Trigger down the
 * primary output edge; send-nodes go through InstagramService.
 *
 * Quick-reply / Button nodes PAUSE the walk and persist an
 * instagram_flow_sessions row; the next inbound DM (the tap) resumes from
 * the matched branch — so multi-turn button flows work.
 */
class IgFlowRunner
{
    /** @var array<string,array> id => node */
    private array $nodes = [];
    /** @var array<int,array> */
    private array $edges = [];
    private array $vars = [];
    private int $flowId = 0;

    public function __construct(private InstagramAccount $account) {}

    /**
     * The React builder persists `flowNodes` / `flowEdges` — NOT `nodes` /
     * `edges`. Reading only the short keys meant $this->nodes was always empty,
     * so entryNode() returned null and EVERY Instagram flow silently did
     * nothing. Accept both spellings: long keys are what's on disk, short keys
     * keep hand-authored/admin-template JSON working.
     */
    public static function nodesOf(?array $flow): array
    {
        return (array) ($flow['flowNodes'] ?? $flow['nodes'] ?? []);
    }

    // =====================================================================
    // Entry points for the NODE flow engine.
    //
    // node/services/instagramFlowService.js walks the graph and sends the
    // DMs, but calls back into Laravel for anything needing the DB or
    // platform keys. These wrappers expose that logic without duplicating
    // it — one implementation, two callers (PHP runner + Node engine).
    // See App\Http\Controllers\Api\InstagramFlowNodeController.
    // =====================================================================

    /** AI node → the generated reply text. */
    public static function aiReplyFor(InstagramAccount $account, array $nodeData, array $vars): string
    {
        $r = new self($account);
        $r->vars = $vars;
        return $r->aiReply($nodeData);
    }

    /** Webhook node → the variables it produced (SSRF guard applies). */
    public static function webhookFor(array $nodeData, array $vars): array
    {
        // No account needed — runWebhook only touches vars. A throwaway model
        // instance keeps the constructor contract without hitting the DB.
        $r = new self(new InstagramAccount());
        $r->vars = $vars;
        $r->runWebhook($nodeData);
        return $r->vars;
    }

    /** {{var}} substitution, for callers outside this class. */
    public static function substFor(string $s, array $vars): string
    {
        $r = new self(new InstagramAccount());
        $r->vars = $vars;
        return $r->subst($s);
    }

    private function load(array $flow, int $flowId = 0): void
    {
        $this->nodes = [];
        foreach (self::nodesOf($flow) as $n) {
            if (!empty($n['id'])) $this->nodes[$n['id']] = $n;
        }
        $this->edges = (array) ($flow['flowEdges'] ?? $flow['edges'] ?? []);
        $this->flowId = $flowId;
    }

    /** Fresh run from the Trigger. @param array $ctx {igsid, text, comment_id?} */
    public function run(array $flow, array $ctx, int $flowId = 0): void
    {
        $this->load($flow, $flowId);
        $igsid = (string) ($ctx['igsid'] ?? '');
        // Pre-load what we already know about this contact (name/email/phone +
        // custom attributes) so a returning customer's {{name}} resolves without
        // asking again — WaDesk-style attribute pre-fill.
        $this->vars = array_merge($this->contactVars($igsid), [
            'text'       => (string) ($ctx['text'] ?? ''),
            'igsid'      => $igsid,
            'comment_id' => (string) ($ctx['comment_id'] ?? ''),
        ]);
        $this->clearSession($igsid);

        $start = $this->entryNode();
        if (!$start) { Log::info('[IG-FLOW] no trigger node'); return; }
        $this->walk($this->next($start['id'], 'out'), (string) ($ctx['igsid'] ?? ''));
    }

    /**
     * Resume a paused flow when a tap (quick-reply payload / postback) arrives.
     * @return bool true if a session was found and resumed.
     */
    public static function resumeFor(InstagramAccount $account, string $igsid, string $text): bool
    {
        $sess = InstagramFlowSession::where('instagram_account_id', $account->id)->where('igsid', $igsid)->first();
        if (!$sess) return false;
        if (!$sess->isLive()) { $sess->delete(); return false; }

        // A session parked on a timed Wait is NOT waiting for this message —
        // it belongs to sweepDue(). Leave it be (so the Wait still fires on
        // schedule) and let normal inbound handling take this message.
        // Without this, any reply during the wait would cut it short.
        if ($sess->resume_at) return false;

        // Owner-scope like the webhook matcher: WaDesk keys on workspace_id, but
        // IgDesk rows are workspace_id=0 and belong to a user_id — filtering
        // on workspace_id=0 there matched nothing, so a paused Ask/quick-reply
        // never resumed.
        $flowQuery = InstagramGate::flows()->where('id', $sess->flow_id);
        if ((int) $account->workspace_id > 0) {
            $flowQuery->where('workspace_id', $account->workspace_id);
        } else {
            $flowQuery->where('user_id', (int) $account->user_id);
        }
        $flow = $flowQuery->first();
        if (!$flow) { $sess->delete(); return false; }
        $data = $flow->decoded_flow_data;
        if (!is_array($data) || !self::nodesOf($data)) { $sess->delete(); return false; }

        // `static`, not `self` — late static binding, so a subclass that
        // overrides sender() is honoured on the RESUME path too, not just on
        // the initial run(). Production always calls this as
        // IgFlowRunner::resumeFor(), where static === self.
        $r = new static($account);
        $r->load($data, (int) $sess->flow_id);
        $r->vars = is_array($sess->vars) ? $sess->vars : [];
        $r->vars['text']  = $text;
        $r->vars['igsid'] = $igsid;

        $paused = $r->nodes[$sess->node_id] ?? null;
        if (!$paused) { $sess->delete(); return false; }

        // Decide which branch the tap takes.
        $port  = 'out';
        $pType = (string) ($paused['type'] ?? '');
        $pData = (array) ($paused['data'] ?? []);
        $t     = mb_strtolower(trim($text));

        // "Ask a question" nodes: the reply IS the answer — capture it into the
        // configured variable, then continue. ig_ask saves under `save`, the
        // shared chat `ask` node under `var`.
        if ($pType === 'ig_ask' || $pType === 'ask') {
            $saveKey = trim((string) ($pData['save'] ?? $pData['var'] ?? ''));
            if ($saveKey !== '') $r->vars[$saveKey] = $text;

            // WaDesk-style: also persist the answer onto the contact when the
            // node opts in via "Save answer to attribute" (name/email/phone or a
            // custom attribute key), so it survives beyond this flow run.
            $attrKey = trim((string) ($pData['saveAttribute'] ?? ''));
            if ($attrKey !== '') $r->persistAttribute($igsid, $attrKey, $text);
        }

        // Shared chat `ask`: with expected answers configured the builder emits
        // p0..pN plus an 'else' catch-all, so route the reply to the matching
        // branch instead of always taking 'out' (which wouldn't exist).
        if ($pType === 'ask') {
            $expected = array_values(array_filter((array) ($pData['options'] ?? []), fn ($o) => trim((string) $o) !== ''));
            if ($expected) {
                $port = 'else';
                foreach ($expected as $i => $o) {
                    if ($t === mb_strtolower(trim((string) $o))) { $port = 'p' . $i; break; }
                }
            }
        }

        if ($pType === 'ig_quick') {
            $opts = (array) ($pData['options'] ?? []);
            $idx = null;
            foreach ($opts as $i => $o) {
                $p = mb_strtolower(trim((string) ($o['payload'] ?? '')));
                $ti = mb_strtolower(trim((string) ($o['title'] ?? '')));
                if (($p !== '' && $t === $p) || ($ti !== '' && $t === $ti)) { $idx = $i; break; }
            }
            if ($idx === null) { $sess->delete(); return false; } // no match → let normal handling run
            $port = 'p' . $idx;
        }

        // Shared chat `buttons` node — options are plain strings, ports p0..pN.
        // A quick-reply tap arrives as message.quick_reply.payload (our
        // "OPT_<i>"), NOT the visible title, so match the payload FIRST and
        // only fall back to the title for someone who typed the label out.
        if ($pType === 'buttons') {
            $opts = self::chatOptionsToQuickReplies($pData);
            $idx = null;
            foreach ($opts as $i => $o) {
                if ($t === mb_strtolower($o['payload']) || $t === mb_strtolower(trim((string) $o['title']))) { $idx = $i; break; }
            }
            if ($idx === null) { $sess->delete(); return false; } // not a tap → fall through to normal handling
            $port = 'p' . $idx;
            $saveKey = trim((string) ($pData['var'] ?? ''));
            if ($saveKey !== '') $r->vars[$saveKey] = $text;
        }

        $sess->delete(); // consumed
        $r->walk($r->next($sess->node_id, $port), $igsid);
        return true;
    }

    /**
     * The Send-API client this walk uses. Overridable so the flow walker can
     * be exercised (branching, pause/resume, variable capture) without any
     * real DM leaving the building — production always gets the real client.
     */
    protected function sender(): InstagramService
    {
        return new InstagramService($this->account);
    }

    /** What we already know about this contact, as flow variables. */
    private function contactVars(string $igsid): array
    {
        if ($igsid === '') return [];
        try {
            $c = \App\Models\InstagramContact::where('instagram_account_id', $this->account->id)
                ->where('igsid', $igsid)->first();
            return $c ? $c->attributeMap() : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Persist a captured answer onto the contact (name/email/phone or custom). */
    private function persistAttribute(string $igsid, string $key, string $value): void
    {
        if ($igsid === '' || $key === '' || trim($value) === '') return;
        try {
            $c = \App\Models\InstagramContact::firstOrNew([
                'instagram_account_id' => $this->account->id,
                'igsid'                => $igsid,
            ]);
            if (!$c->exists) {
                $c->workspace_id = $this->account->workspace_id;
                $c->save();
            }
            $c->setAttributeValue($key, $value);
        } catch (\Throwable $e) {
            Log::info('[IG-FLOW] attribute persist failed: ' . $e->getMessage());
        }
    }

    /** The walk loop. Pauses (saves a session) at quick/button nodes. */
    private function walk(?string $current, string $igsid): void
    {
        $svc = $this->sender();
        $guard = 0;

        while ($current && $guard++ < 50) {
            $node = $this->nodes[$current] ?? null;
            if (!$node) break;
            $type = (string) ($node['type'] ?? '');
            $d    = (array) ($node['data'] ?? []);
            $port = 'out';

            switch ($type) {
                // ---- Shared WhatsApp nodes, run over Instagram DM ----------
                // Same node types the chat builder uses, so one flow design
                // works on both channels. Only the message primitives port:
                // template / list / cta / location / poll / wa_form are
                // WhatsApp-protocol features with no Instagram equivalent and
                // are NOT offered in the Instagram palette.
                case 'message':
                    if ($igsid) {
                        $body = $this->subst($d['text'] ?? '');
                        if (trim($body) !== '') {
                            $rr = $svc->sendDm($igsid, $body);
                            if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $body, 'flow', $rr['mid'] ?? null);
                        }
                    }
                    break;

                case 'media':
                    if ($igsid) {
                        // Builder stores {kind, url, filename, caption}. A
                        // relative "/storage/…" can't be fetched by Meta, so
                        // absolutise it the same way the Node runtime does.
                        $url = trim($this->subst($d['url'] ?? $d['mediaUrl'] ?? ''));
                        if ($url !== '' && !preg_match('#^(https?:)?//#i', $url) && !str_starts_with($url, 'data:')) {
                            $url = url($url);
                        }
                        // Instagram accepts image / video / audio attachments
                        // only — there is no document type, so a "doc" node
                        // degrades to sending the link as text rather than
                        // failing silently.
                        $kind = strtolower(trim((string) ($d['kind'] ?? $d['mediaType'] ?? 'image')));
                        if ($url !== '' && in_array($kind, ['image', 'video', 'audio'], true)) {
                            $rr = $svc->sendMediaDm($igsid, $kind, $url);
                            if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', '[' . $kind . ']', 'flow', $rr['mid'] ?? null);
                            else Log::info('[IG-FLOW] media send failed: ' . ($rr['error'] ?? 'unknown'), ['account' => $this->account->id]);
                        } elseif ($url !== '') {
                            $rr = $svc->sendDm($igsid, $url);
                            if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $url, 'flow', $rr['mid'] ?? null);
                        }
                        // A caption is a separate DM — IG has no caption field.
                        $cap = trim($this->subst($d['caption'] ?? ''));
                        if ($cap !== '') {
                            $cr = $svc->sendDm($igsid, $cap);
                            if (!empty($cr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $cap, 'flow', $cr['mid'] ?? null);
                        }
                    }
                    break;

                case 'buttons':
                    // The chat builder's Quick replies node stores
                    // {prompt, options:['A','B'], var} — PLAIN STRINGS, where
                    // ig_quick uses {title,payload} objects. Map across so one
                    // node design works on both channels. Ports are p0..pN,
                    // matching the builder's port ids.
                    if ($igsid) {
                        $bText = $this->subst($d['prompt'] ?? $d['text'] ?? '');
                        $rr = $svc->sendQuickReplies($igsid, $bText, self::chatOptionsToQuickReplies($d));
                        if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $bText, 'flow', $rr['mid'] ?? null);
                        $this->saveSession($igsid, $current);
                    }
                    return; // pause for the tap

                case 'ask':
                    // {prompt, var, options}. With no expected options the
                    // builder gives a single 'out' port; with options it gives
                    // p0..pN plus 'else' — resumeFor() picks the branch.
                    if ($igsid) {
                        $qText = trim($this->subst($d['prompt'] ?? $d['question'] ?? $d['text'] ?? ''));
                        if ($qText !== '') {
                            $rr = $svc->sendDm($igsid, $qText);
                            if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $qText, 'flow', $rr['mid'] ?? null);
                        }
                        $this->saveSession($igsid, $current);
                    }
                    return; // pause for the answer

                case 'ai':
                    // Chat AI node stores its config under different keys than
                    // ig_ai (systemPrompt/assistantId vs prompt/assistant);
                    // normalise before reusing the same provider call.
                    $aiReply = $this->aiReply([
                        'prompt'    => $d['prompt'] ?? $d['systemPrompt'] ?? '',
                        'assistant' => $d['assistant'] ?? $d['assistantId'] ?? 0,
                        'model'     => $d['model'] ?? 'gpt-4o-mini',
                        'save'      => $d['save'] ?? '',
                    ]);
                    if ($aiReply !== '' && $igsid) {
                        $rr = $svc->sendDm($igsid, $aiReply);
                        if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $aiReply, 'ai', $rr['mid'] ?? null);
                    }
                    if (!empty($d['save'])) $this->vars[$d['save']] = $aiReply;
                    break;

                case 'ig_send_dm':
                    if ($igsid) {
                        $body = $this->subst($d['text'] ?? '');
                        $rr = $svc->sendDm($igsid, $body);
                        if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $body, 'flow', $rr['mid'] ?? null);
                    }
                    break;

                case 'ig_quick':
                    if ($igsid) {
                        $qBody = $this->subst($d['text'] ?? '');
                        $rr = $svc->sendQuickReplies($igsid, $qBody, (array) ($d['options'] ?? []));
                        // Mirror to the IgDesk inbox so the quick-reply prompt
                        // shows as a sent bubble (matches ig_send_dm / ig_ai).
                        if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $qBody, 'flow', $rr['mid'] ?? null);
                        $this->saveSession($igsid, $current);
                    }
                    return; // pause for the tap

                case 'ig_buttons':
                    if ($igsid) {
                        $bBody = $this->subst($d['text'] ?? '');
                        $rr = $svc->sendButtonTemplate($igsid, $bBody, (array) ($d['buttons'] ?? []));
                        if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $bBody, 'flow', $rr['mid'] ?? null);
                        $this->saveSession($igsid, $current);
                    }
                    return; // pause for the tap

                case 'ig_products':
                    if ($igsid) {
                        // Optional lead-in DM before the carousel.
                        $intro = trim($this->subst($d['intro'] ?? ''));
                        if ($intro !== '') {
                            $ir = $svc->sendDm($igsid, $intro);
                            if (!empty($ir['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $intro, 'flow', $ir['mid'] ?? null);
                        }
                        $pr = app(InstagramCommerceService::class)->sendProducts($this->account, $igsid, $d);
                        if (!empty($pr['ok'])) {
                            InstagramMessage::log($this->account, $igsid, 'out', '[products]', 'flow', $pr['mid'] ?? null);
                        } else {
                            Log::info('[IG-FLOW] ig_products send skipped: ' . ($pr['error'] ?? 'unknown'), ['account' => $this->account->id]);
                        }
                    }
                    break;

                case 'ig_ask':
                    if ($igsid) {
                        $qBody = trim($this->subst($d['question'] ?? ''));
                        if ($qBody !== '') {
                            $rr = $svc->sendDm($igsid, $qBody);
                            if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $qBody, 'flow', $rr['mid'] ?? null);
                        }
                        $this->saveSession($igsid, $current);
                    }
                    return; // pause for the answer

                case 'ig_lead':
                    if ($igsid) {
                        $val = function ($k) {
                            $k = trim((string) $k);
                            return $k !== '' ? trim($this->subst('{{' . $k . '}}')) : '';
                        };
                        $fields = [
                            'full_name' => $val($d['nameVar']  ?? 'lead_name'),
                            'email'     => $val($d['emailVar'] ?? 'lead_email'),
                            'phone'     => $val($d['phoneVar'] ?? 'lead_phone'),
                            'notes'     => $val($d['notesVar'] ?? ''),
                            'extra'     => $this->collectedVars(),
                        ];
                        app(InstagramLeadService::class)->capture(
                            $this->account, $igsid, $fields, ($d['createDeal'] ?? true) !== false
                        );
                        $ack = trim($this->subst($d['ack'] ?? ''));
                        if ($ack !== '') {
                            $ar = $svc->sendDm($igsid, $ack);
                            if (!empty($ar['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $ack, 'flow', $ar['mid'] ?? null);
                        }
                    }
                    break;

                case 'ig_reply_comment':
                    if (!empty($this->vars['comment_id'])) $svc->replyComment($this->vars['comment_id'], $this->subst($d['message'] ?? ''));
                    break;

                case 'ig_ai':
                    $reply = $this->aiReply($d);
                    if ($reply !== '' && $igsid) {
                        $rr = $svc->sendDm($igsid, $reply);
                        if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $reply, 'ai', $rr['mid'] ?? null);
                    }
                    if (!empty($d['save'])) $this->vars[$d['save']] = $reply;
                    break;

                case 'webhook':
                    // Same node as the chat builder's Webhook: call an external
                    // HTTPS endpoint and save the response into a variable so
                    // later nodes can use {{save}}. Without this case the node
                    // sat in the Instagram palette and was silently skipped.
                    $this->runWebhook($d);
                    break;

                case 'condition':
                    $port = $this->evalCondition($d) ? 'yes' : 'no';
                    break;

                case 'delay': {
                    // We run inside Meta's webhook request and cannot sleep, so
                    // PARK the session with a resume_at and return. sweepDue()
                    // walks on from here once the clock is up.
                    $secs = self::delaySeconds($d);
                    if ($secs <= 0 || !$igsid) break;   // 0 = no wait, just continue

                    // Instagram only allows a business-initiated reply within
                    // 24h of the customer's last message. A longer wait would
                    // park a session that can never send, so refuse it loudly
                    // and continue now rather than silently stranding the flow.
                    if ($secs > 86400) {
                        Log::warning('[IG-FLOW] Wait exceeds the 24h Instagram messaging window — continuing immediately', [
                            'flow_id' => $this->flowId, 'node' => $current, 'requested_seconds' => $secs,
                        ]);
                        break;
                    }
                    $this->saveSession($igsid, $current, now()->addSeconds($secs));
                    Log::info('[IG-FLOW] Wait parked', [
                        'flow_id' => $this->flowId, 'node' => $current, 'seconds' => $secs,
                    ]);
                    return;   // pause — sweepDue() picks it up
                }

                case 'end':
                    return;
            }

            $current = $this->next($current, $port);
        }
    }

    /**
     * Chat `buttons` options → Instagram quick-reply objects.
     *
     * The chat builder stores plain strings (`options:['Yes','No']`); the IG
     * Send API wants {title, payload}. Payload is the index so the resume side
     * can map a tap back to port pN even if two buttons share a label. Meta
     * caps quick replies at 13 and titles at 20 chars — over-long titles are
     * rejected outright, so truncate rather than let the send fail.
     */
    private static function chatOptionsToQuickReplies(array $d): array
    {
        $out = [];
        foreach ((array) ($d['options'] ?? []) as $i => $o) {
            // Tolerate object form too, in case a flow was authored with it.
            $title = is_array($o) ? (string) ($o['title'] ?? $o['label'] ?? '') : (string) $o;
            $title = trim($title);
            if ($title === '') continue;
            $out[] = ['title' => mb_substr($title, 0, 20), 'payload' => 'OPT_' . $i];
            if (count($out) >= 13) break;
        }
        return $out;
    }

    /**
     * Park the walk. $resumeAt set = a timed Wait the sweep will continue;
     * null = waiting on the customer (quick-reply tap / Ask answer).
     */
    private function saveSession(string $igsid, string $nodeId, ?\Illuminate\Support\Carbon $resumeAt = null): void
    {
        if (!$igsid || !$this->flowId) return;
        InstagramFlowSession::updateOrCreate(
            ['instagram_account_id' => $this->account->id, 'igsid' => $igsid],
            [
                'workspace_id' => $this->account->workspace_id,
                'flow_id'      => $this->flowId,
                'node_id'      => $nodeId,
                'resume_at'    => $resumeAt,
                'vars'         => $this->vars,
                'expires_at'   => now()->addHours(24),
            ]
        );
    }

    /** Wait node {amount, unit} → seconds. Unknown unit falls back to minutes. */
    public static function delaySeconds(array $d): int
    {
        $amount = (float) ($d['amount'] ?? $d['value'] ?? 0);
        if ($amount <= 0) return 0;
        $unit = strtolower(trim((string) ($d['unit'] ?? 'min')));
        $mult = match (true) {
            str_starts_with($unit, 's') => 1,
            str_starts_with($unit, 'h') => 3600,
            str_starts_with($unit, 'd') => 86400,
            default                     => 60,   // min
        };
        return (int) round($amount * $mult);
    }

    /**
     * Continue every flow whose Wait has elapsed.
     *
     * No-cron, matching project policy (see routes/console.php): this is swept
     * from the Instagram webhook (fires on ANY inbound for ANY connected
     * account) and from the inbox poll. Returns how many it resumed.
     *
     * Each session is claimed with a conditional UPDATE before the walk, so two
     * concurrent sweeps can't both resume the same one and double-send.
     */
    public static function sweepDue(int $limit = 25): int
    {
        $due = InstagramFlowSession::query()
            ->whereNotNull('resume_at')
            ->where('resume_at', '<=', now())
            ->orderBy('resume_at')
            ->limit($limit)
            ->get();
        if ($due->isEmpty()) return 0;

        $resumed = 0;
        foreach ($due as $sess) {
            // Claim it: only the sweep that flips resume_at to NULL proceeds.
            $claimed = InstagramFlowSession::where('id', $sess->id)
                ->whereNotNull('resume_at')
                ->update(['resume_at' => null]);
            if (!$claimed) continue;

            try {
                if (!$sess->isLive()) { $sess->delete(); continue; }

                $acct = InstagramAccount::find($sess->instagram_account_id);
                $flow = InstagramGate::flows()->where('workspace_id', $sess->workspace_id)
                    ->where('id', $sess->flow_id)->first();
                if (!$acct || !$flow) { $sess->delete(); continue; }

                $data = $flow->decoded_flow_data;
                if (!is_array($data) || !self::nodesOf($data)) { $sess->delete(); continue; }

                $r = new static($acct);
                $r->load($data, (int) $sess->flow_id);
                $r->vars = is_array($sess->vars) ? $sess->vars : [];

                $from = $sess->node_id;
                $sess->delete();                      // consumed before walking
                $r->walk($r->next($from, 'out'), (string) $sess->igsid);
                $resumed++;
                Log::info('[IG-FLOW] Wait resumed', ['flow_id' => $sess->flow_id, 'from_node' => $from]);
            } catch (\Throwable $e) {
                Log::warning('[IG-FLOW] Wait resume failed: ' . $e->getMessage(), ['session' => $sess->id]);
            }
        }
        return $resumed;
    }

    private function clearSession(string $igsid): void
    {
        if ($igsid) InstagramFlowSession::where('instagram_account_id', $this->account->id)->where('igsid', $igsid)->delete();
    }

    private function entryNode(): ?array
    {
        foreach ($this->nodes as $n) {
            if (($n['type'] ?? '') === 'trigger') return $n;
        }
        return null;
    }

    /** Follow the edge leaving $nodeId on $port (falls back to any out edge). */
    private function next(string $nodeId, string $port): ?string
    {
        $any = null;
        foreach ($this->edges as $e) {
            if (($e['source'] ?? null) !== $nodeId) continue;
            $any = $any ?? (string) ($e['target'] ?? '');
            $h = (string) ($e['sourceHandle'] ?? 'out');
            if ($h === $port) return (string) ($e['target'] ?? '');
        }
        return $port === 'out' ? $any : null;
    }

    private function subst(string $s): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) {
            return (string) ($this->vars[$m[1]] ?? '');
        }, $s) ?? $s;
    }

    /** Every non-internal variable collected so far — stored on the lead as field_data. */
    private function collectedVars(): array
    {
        $skip = ['text', 'igsid', 'comment_id'];
        $out = [];
        foreach ($this->vars as $k => $v) {
            if (in_array($k, $skip, true)) continue;
            if (is_scalar($v) && trim((string) $v) !== '') $out[$k] = (string) $v;
        }
        return $out;
    }

    /**
     * Webhook node — {method, url, body, contentType, headers[], save}.
     *
     * SSRF-guarded the same way FlowsController::guardSsrf() does it: http/https
     * only, and every resolved IP must be public. The URL is operator-supplied
     * and can carry {{vars}} filled from inbound text, so an unguarded call here
     * would let a flow probe the server's own metadata/loopback services.
     *
     * Failures are logged and the walk CONTINUES — a dead endpoint should not
     * strand the customer mid-conversation.
     */
    private function runWebhook(array $d): void
    {
        $url = trim($this->subst($d['url'] ?? ''));
        if ($url === '') { Log::info('[IG-FLOW] webhook node has no URL — skipped'); return; }

        $p = @parse_url($url);
        $scheme = strtolower((string) ($p['scheme'] ?? ''));
        $host   = (string) ($p['host'] ?? '');
        if (!$p || $host === '' || !in_array($scheme, ['http', 'https'], true)) {
            Log::warning('[IG-FLOW] webhook refused — bad URL/scheme', ['url' => $url]); return;
        }
        $ips = @gethostbynamel($host) ?: [];
        if (!$ips) { Log::warning('[IG-FLOW] webhook refused — host does not resolve', ['host' => $host]); return; }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                Log::warning('[IG-FLOW] webhook refused — private/reserved IP', ['host' => $host, 'ip' => $ip]);
                return;
            }
        }

        $method  = strtoupper(trim((string) ($d['method'] ?? 'POST')));
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) $method = 'POST';
        $ctype   = trim((string) ($d['contentType'] ?? 'application/json')) ?: 'application/json';
        $rawBody = $this->subst((string) ($d['body'] ?? ''));

        $headers = ['Content-Type' => $ctype];
        foreach ((array) ($d['headers'] ?? []) as $h) {
            $k = trim((string) ($h['key'] ?? $h['name'] ?? ''));
            if ($k !== '') $headers[$k] = $this->subst((string) ($h['value'] ?? ''));
        }

        try {
            $req = \Illuminate\Support\Facades\Http::withHeaders($headers)
                ->timeout(10)->connectTimeout(5);

            if ($method === 'GET') {
                $res = $req->get($url);
            } elseif (stripos($ctype, 'json') !== false) {
                // Send parsed JSON when the body IS valid JSON; otherwise pass
                // it through raw so a hand-written form/XML body still works.
                $decoded = json_decode($rawBody, true);
                $res = is_array($decoded)
                    ? $req->send($method, $url, ['json' => $decoded])
                    : $req->withBody($rawBody, $ctype)->send($method, $url);
            } else {
                $res = $req->withBody($rawBody, $ctype)->send($method, $url);
            }

            $saveKey = trim((string) ($d['save'] ?? 'response'));
            if ($saveKey !== '') {
                $text = (string) $res->body();
                // Prefer a scalar out of a JSON object so {{response}} renders
                // usefully instead of dumping a raw JSON blob into a DM.
                $j = json_decode($text, true);
                $this->vars[$saveKey] = is_array($j)
                    ? (string) json_encode($j)
                    : mb_substr($text, 0, 2000);
                if (is_array($j)) {
                    foreach ($j as $k2 => $v2) {
                        if (is_scalar($v2)) $this->vars[$saveKey . '_' . $k2] = (string) $v2;
                    }
                }
            }
            Log::info('[IG-FLOW] webhook ' . $method, ['host' => $host, 'status' => $res->status()]);
        } catch (\Throwable $e) {
            Log::warning('[IG-FLOW] webhook failed: ' . $e->getMessage(), ['host' => $host]);
        }
    }

    private function evalCondition(array $d): bool
    {
        $left  = $this->subst((string) ($d['variable'] ?? $d['left'] ?? '{{text}}'));
        $right = $this->subst((string) ($d['value'] ?? $d['right'] ?? ''));
        $op    = (string) ($d['operator'] ?? $d['op'] ?? 'contains');
        $l = mb_strtolower(trim($left)); $r = mb_strtolower(trim($right));
        return match ($op) {
            'equals', '=', '==' => $l === $r,
            'not_equals', '!='  => $l !== $r,
            'starts_with'       => str_starts_with($l, $r),
            default             => $r === '' ? true : str_contains($l, $r),
        };
    }

    private function aiReply(array $d): string
    {
        $system = trim((string) ($d['prompt'] ?? '')) ?: 'You are a helpful Instagram assistant. Reply briefly.';
        $assistantId = (int) ($d['assistant'] ?? 0);
        if ($assistantId > 0) {
            $a = InstagramGate::aiAssistant((int) $this->account->workspace_id, $assistantId);
            if ($a) {
                try { $kb = app(\App\Services\AiChat\AiChatService::class)->contextFor($a); if (trim($kb) !== '') $system .= "\n\n--- Knowledge base ---\n" . $kb; }
                catch (\Throwable $e) {}
            }
        }
        $model = (string) ($d['model'] ?? 'gpt-4o-mini');
        try {
            $reply = app(\App\Services\AiAgentService::class)->callProvider(
                provider: InstagramService::providerForModel($model),
                model: $model,
                workspaceId: (int) $this->account->workspace_id,
                systemPrompt: $system,
                userPrompt: $this->subst('{{text}}'),
                maxTokens: 300,
                temperature: 0.6,
            );
            return trim((string) $reply);
        } catch (\Throwable $e) {
            Log::warning('[IG-FLOW] AI node failed: ' . $e->getMessage());
            return '';
        }
    }
}

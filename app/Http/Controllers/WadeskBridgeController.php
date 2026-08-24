<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Models\InstagramContact;
use App\Models\InstagramMessage;
use App\Services\Instagram\InstagramService;
use App\Services\Instagram\WadeskLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The IgDesk → WaDesk provider API.
 *
 * A separate WaDesk install reads this IgDesk's Instagram threads and sends
 * replies through these endpoints so IG lands in WaDesk's UNIFIED team-inbox.
 * WaDesk's App\Services\IgDesk\InstaflowClient is the caller; the reverse
 * direction (IgDesk pushing new DMs to WaDesk) lives in WadeskPushService.
 *
 * EVERY call proves the shared secret via the X-Instaflow-Secret header — the
 * SAME secret the operator generated on the "Connect WaDesk" page. No Laravel
 * session, so these routes sit in the lockless `api` group and self-guard.
 *
 * A conversation id is "<accountId>_<igsid>" — the IG account row id plus the
 * sender's IGSID. That is what WaDesk stores (raw_jid = "ig:<id>") and hands
 * back verbatim on reply, so parseConversation() reverses it to (account, igsid).
 */
class WadeskBridgeController extends Controller
{
    /** Guard every call on the shared secret. Returns a 401 response, or null when authorized. */
    private function guard(Request $request): ?JsonResponse
    {
        $secret = WadeskLink::secret();
        $sent   = (string) $request->header('X-Instaflow-Secret', '');
        if ($secret === '' || ! hash_equals($secret, $sent)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        return null;
    }

    /** Reachability + secret proof. WaDesk's connect handshake hits this. */
    public function handshake(Request $request): JsonResponse
    {
        if ($g = $this->guard($request)) {
            return $g;
        }
        return response()->json(['ok' => true, 'service' => 'instaflow']);
    }

    /**
     * Connected IG accounts on this IgDesk — ALWAYS scoped to one owner.
     *
     * SECURITY: this endpoint must NEVER enumerate every account on the
     * platform (that leaked other tenants' Instagram accounts into WaDesk's
     * "link existing" picker). A caller must scope by one of:
     *   ?workspace=<id>      accounts already stamped with that WaDesk workspace
     *   ?owner_email=<email> accounts owned by the IgDesk user whose email
     *                        matches the WaDesk user's email (same person → link
     *                        without re-authenticating; different person → none)
     * With NEITHER, or with an email that matches no IgDesk user, the list
     * is empty — the operator then uses "Connect new" (one-time OAuth).
     */
    public function accounts(Request $request): JsonResponse
    {
        if ($g = $this->guard($request)) {
            return $g;
        }

        $workspace    = $request->query('workspace');
        $ownerEmail   = trim((string) $request->query('owner_email', ''));
        $hasWorkspace = $workspace !== null && $workspace !== '';

        // Refuse to list anything without a scope — no bare "all accounts".
        if (!$hasWorkspace && $ownerEmail === '') {
            return response()->json([]);
        }

        $q = InstagramAccount::query();
        if ($hasWorkspace) {
            $q->where('wadesk_workspace_id', (int) $workspace);
        }
        if ($ownerEmail !== '') {
            // Resolve the IgDesk user by email; no match → no accounts (never
            // fall through to every account). email is the shared identity key.
            $ownerId = \App\Models\User::where('email', $ownerEmail)->value('id');
            if (!$ownerId) {
                return response()->json([]);
            }
            $q->where('user_id', $ownerId);
        }

        $rows = $q->get()->map(fn ($a) => [
            'id'        => (string) $a->id,
            'username'  => (string) $a->username,
            'name'      => (string) ($a->name ?: $a->username),
            'avatar'    => (string) $a->profile_pic_url,
            'connected' => $a->status === 'connected',
        ])->values()->all();

        return response()->json($rows);
    }

    /**
     * DIAGNOSTIC — raw sender-profile fetch for one account + igsid, so a
     * "no avatar" symptom is diagnosable from WaDesk without shell access to the
     * live logs. Secret-guarded. ?account=<id> required; ?igsid=<igsid> optional
     * (defaults to the account's most recent contact).
     */
    public function debugProfile(Request $request): JsonResponse
    {
        if ($g = $this->guard($request)) {
            return $g;
        }
        $account = InstagramAccount::find((int) $request->query('account'));
        if (! $account) {
            return response()->json(['ok' => false, 'error' => 'unknown account'], 404);
        }
        $igsid = trim((string) $request->query('igsid', ''));
        if ($igsid === '') {
            $igsid = (string) InstagramContact::where('instagram_account_id', $account->id)
                ->orderByDesc('id')->value('igsid');
        }
        $probe = (new InstagramService($account))->probeSenderProfile($igsid);

        // Also server-side fetch a few STORED avatar URLs. This distinguishes a
        // referrer/hotlink 403 (server GET succeeds → browser fails only because
        // of the Referer header) from an expired signed URL (server GET also
        // 403s → the oe= timestamp has passed and the URL must be refreshed).
        $now = now()->timestamp;
        $avatarProbe = InstagramContact::where('instagram_account_id', $account->id)
            ->whereNotNull('avatar_url')->where('avatar_url', '!=', '')
            ->orderByDesc('id')->limit(4)->get(['igsid', 'username', 'avatar_url'])
            ->map(function ($c) use ($now) {
                $url = (string) $c->avatar_url;
                // Decode the IG CDN signed-URL expiry (oe=<hex unix ts>).
                $oe = null; $expired = null;
                if (preg_match('/[?&]oe=([0-9A-Fa-f]+)/', $url, $m)) {
                    $oe = hexdec($m[1]);
                    $expired = $oe < $now;
                }
                $status = null; $err = null;
                try {
                    $r = \Illuminate\Support\Facades\Http::timeout(10)
                        ->withoutRedirecting()->get($url);
                    $status = $r->status();
                } catch (\Throwable $e) {
                    $err = $e->getMessage();
                }
                return [
                    'igsid'          => (string) $c->igsid,
                    'username'       => (string) $c->username,
                    'is_cdn'         => str_contains($url, 'cdninstagram.com') || str_contains($url, 'fbcdn.net'),
                    'oe_expiry'      => $oe,
                    'expired'        => $expired,
                    'server_status'  => $status,
                    'server_error'   => $err,
                    'url_head'       => mb_substr($url, 0, 80),
                ];
            })->all();

        return response()->json([
            'ok'          => true,
            'account'     => $account->id,
            'username'    => (string) $account->username,
            'now'         => $now,
            'avatar_probe'=> $avatarProbe,
        ] + $probe);
    }

    /**
     * Thread list — latest message per (account, igsid). Optional ?account= filter.
     * Optional ?workspace=<id>: restrict to accounts stamped with that WaDesk
     * workspace (multi-tenant scoping). Absent = current (all-accounts) behaviour.
     */
    public function conversations(Request $request): JsonResponse
    {
        if ($g = $this->guard($request)) {
            return $g;
        }

        $accountFilter = trim((string) $request->query('account', ''));
        $workspace     = $request->query('workspace');
        $threads = InstagramMessage::query()
            ->selectRaw('MAX(id) as last_id, instagram_account_id, igsid, COUNT(*) as cnt, MAX(sent_at) as last_at')
            ->when($accountFilter !== '', function ($q) use ($accountFilter) {
                $q->whereIn('instagram_account_id', InstagramAccount::query()
                    ->where('id', $accountFilter)
                    ->orWhere('username', $accountFilter)
                    ->orWhere('ig_user_id', $accountFilter)
                    ->pluck('id'));
            })
            ->when($workspace !== null && $workspace !== '', function ($q) use ($workspace) {
                $q->whereIn('instagram_account_id', InstagramAccount::query()
                    ->where('wadesk_workspace_id', (int) $workspace)
                    ->pluck('id'));
            })
            ->groupBy('instagram_account_id', 'igsid')
            ->orderByDesc('last_id')
            ->limit(300)
            ->get();

        $contacts = InstagramContact::query()
            ->get(['instagram_account_id', 'igsid', 'username', 'name', 'avatar_url'])
            ->keyBy(fn ($c) => $c->instagram_account_id . '_' . $c->igsid);

        $out = $threads->map(function ($t) use ($contacts) {
            $c      = $contacts->get($t->instagram_account_id . '_' . $t->igsid);
            $handle = $c?->username ?: $t->igsid;
            return [
                'id'      => $t->instagram_account_id . '_' . $t->igsid,
                'account' => (string) $t->instagram_account_id,
                'name'    => (string) ($c?->name ?: ('@' . $handle)),
                'handle'  => (string) $handle,
                // Proxy url (self-healing) so WaDesk never renders an expiring
                // IG CDN link — see InstagramController::igAvatar().
                'avatar'  => (string) ($c?->avatar_proxy_url ?: ''),
                'last_at' => $t->last_at ? (string) $t->last_at : null,
            ];
        })->values()->all();

        return response()->json($out);
    }

    /** All messages in one thread, oldest first. */
    public function messages(Request $request, string $conversation): JsonResponse
    {
        if ($g = $this->guard($request)) {
            return $g;
        }
        [$accountId, $igsid] = $this->parseConversation($conversation);
        if (! $accountId) {
            return response()->json([]);
        }

        $rows = InstagramMessage::query()
            ->where('instagram_account_id', $accountId)
            ->where('igsid', $igsid)
            ->orderBy('id')
            ->limit(500)
            ->get();

        return response()->json($rows->map(function ($m) {
            $meta = is_array($m->meta) ? $m->meta : [];
            return [
                'id'        => (string) ($m->mid ?: $m->id),
                'from_me'   => $m->direction === 'out',
                'type'      => $m->attachment_type ?: 'text',
                'text'      => (string) $m->body,
                'media_url' => (string) $m->attachment_url,
                // Interactive buttons so WaDesk can render/backfill the card.
                'buttons'   => (! empty($meta['buttons']) && is_array($meta['buttons']))
                    ? array_values($meta['buttons']) : null,
                'at'        => optional($m->sent_at ?: $m->created_at)->toIso8601String(),
            ];
        })->values()->all());
    }

    /** Send a reply to an IG thread via the Graph API, then log it outbound. */
    public function reply(Request $request, string $conversation): JsonResponse
    {
        if ($g = $this->guard($request)) {
            return $g;
        }
        $data = $request->validate([
            'type'          => 'nullable|string',   // text|image|video|audio|file|quick_replies|buttons
            'text'          => 'nullable|string',
            'media_url'     => 'nullable|string',
            'quick_replies' => 'nullable|array',    // ['Yes','No', …] for type=quick_replies
            'buttons'       => 'nullable|array',     // [{title,type:web_url|postback,url|payload}] for type=buttons
        ]);

        [$accountId, $igsid] = $this->parseConversation($conversation);
        $account = $accountId ? InstagramAccount::find($accountId) : null;
        if (! $account) {
            return response()->json(['ok' => false, 'error' => 'unknown conversation'], 404);
        }

        $type     = strtolower(trim((string) ($data['type'] ?? '')));
        $text     = trim((string) ($data['text'] ?? ''));
        $mediaUrl = trim((string) ($data['media_url'] ?? ''));

        // Only a media_url and no type → treat as an image attachment.
        if ($type === '' && $mediaUrl !== '' && $text === '') $type = 'image';
        if ($type === '') $type = 'text';

        if ($text === '' && $mediaUrl === '' && ! in_array($type, ['quick_replies', 'buttons'], true)) {
            return response()->json(['ok' => false, 'error' => 'empty message'], 422);
        }

        $svc = new InstagramService($account);
        // Interactive payload to stamp on the logged message so the sent
        // template renders with its buttons in the inbox thread — WITHOUT this
        // a WaDesk-originated template showed as plain text (the native
        // inboxReply path already logs this; the bridge path did not).
        $logMeta = null;
        try {
            switch ($type) {
                case 'image':
                case 'video':
                case 'audio':   // voice notes ride the audio attachment type
                case 'file':
                    if ($mediaUrl === '') {
                        return response()->json(['ok' => false, 'error' => "media_url required for type $type"], 422);
                    }
                    $res     = $svc->sendMediaDm($igsid, $type, $mediaUrl);
                    $logBody = $mediaUrl;
                    break;
                case 'quick_replies':
                    $qr      = (array) ($data['quick_replies'] ?? []);
                    $res     = $svc->sendQuickReplies($igsid, $text ?: '—', $qr);
                    $logBody = $text;
                    $logMeta = ['tpl' => 'quick_replies', 'buttons' => array_values(array_map(
                        fn ($r) => ['title' => (string) (is_array($r) ? ($r['title'] ?? '') : $r)], $qr))];
                    break;
                case 'buttons':
                    $btns    = (array) ($data['buttons'] ?? []);
                    $res     = $svc->sendButtonTemplate($igsid, $text ?: '—', $btns);
                    $logBody = $text;
                    $logMeta = ['tpl' => 'buttons', 'buttons' => array_values(array_map(
                        fn ($b) => ['title' => (string) ($b['title'] ?? ''), 'url' => $b['url'] ?? null], $btns))];
                    break;
                default: // text
                    $res     = $svc->sendDm($igsid, $text);
                    $logBody = $text;
            }
        } catch (\Throwable $e) {
            Log::warning('[WADESK-BRIDGE] reply send threw', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }

        if (empty($res['ok'])) {
            return response()->json(['ok' => false, 'error' => (string) ($res['error'] ?? 'send failed')], 502);
        }

        $mid = $res['mid'] ?? null;
        InstagramMessage::log($account, $igsid, 'out', $logBody, 'wadesk', $mid, null, null, $logMeta);

        return response()->json(['ok' => true, 'message_id' => $mid]);
    }

    /** React to a message with an emoji (default ❤️). Body: {message_id, reaction?}. */
    public function react(Request $request, string $conversation): JsonResponse
    {
        if ($g = $this->guard($request)) return $g;
        $data = $request->validate(['message_id' => 'required|string', 'reaction' => 'nullable|string']);
        [$accountId, $igsid] = $this->parseConversation($conversation);
        $account = $accountId ? InstagramAccount::find($accountId) : null;
        if (! $account) return response()->json(['ok' => false, 'error' => 'unknown conversation'], 404);

        try {
            $res = (new InstagramService($account))->sendReaction($igsid, (string) $data['message_id'], (string) ($data['reaction'] ?? '❤️'));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
        return response()->json(['ok' => ! empty($res['ok']), 'error' => $res['error'] ?? null]);
    }

    /** Sender action: typing_on | typing_off | mark_seen. Body: {action}. */
    public function action(Request $request, string $conversation): JsonResponse
    {
        if ($g = $this->guard($request)) return $g;
        $data = $request->validate(['action' => 'required|in:typing_on,typing_off,mark_seen']);
        [$accountId, $igsid] = $this->parseConversation($conversation);
        $account = $accountId ? InstagramAccount::find($accountId) : null;
        if (! $account) return response()->json(['ok' => false, 'error' => 'unknown conversation'], 404);

        $svc = new InstagramService($account);
        try {
            $res = match ($data['action']) {
                'typing_on'  => $svc->typingOn($igsid),
                'typing_off' => $svc->typingOff($igsid),
                'mark_seen'  => $svc->markSeen($igsid),
            };
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
        return response()->json(['ok' => ! empty($res['ok']), 'error' => $res['error'] ?? null]);
    }

    /** List the account's active flows so WaDesk can pick one to trigger. ?account=<id> */
    public function flows(Request $request): JsonResponse
    {
        if ($g = $this->guard($request)) return $g;
        $account = InstagramAccount::find((int) $request->query('account')) ?: InstagramAccount::query()->first();
        if (! $account) return response()->json(['ok' => false, 'error' => 'unknown account'], 404);

        $flows = \App\Services\Instagram\InstagramGate::flows()
            ->where('user_id', $account->user_id)
            ->where('is_active', true)
            ->get(['id', 'flow_name'])
            ->map(fn ($f) => ['id' => (int) $f->id, 'name' => (string) $f->flow_name])
            ->all();

        return response()->json(['ok' => true, 'flows' => $flows]);
    }

    /**
     * List the account's Instagram TEMPLATES (reusable DM snippets) so WaDesk can
     * pull them into its own template library. Scoped to the account's workspace.
     * Shape: [{id, name, type: text|quick_replies|buttons, body, items[]}].
     */
    public function templates(Request $request): JsonResponse
    {
        if ($g = $this->guard($request)) return $g;
        $account = InstagramAccount::find((int) $request->query('account')) ?: InstagramAccount::query()->first();
        if (! $account) return response()->json(['ok' => false, 'error' => 'unknown account'], 404);

        $rows = \App\Models\InstagramTemplate::query()
            ->where('workspace_id', (int) $account->workspace_id)
            ->orderByDesc('id')
            ->get(['id', 'name', 'type', 'body', 'items'])
            ->map(fn ($t) => [
                'id'    => (int) $t->id,
                'name'  => (string) $t->name,
                'type'  => (string) ($t->type ?: 'text'),
                'body'  => (string) ($t->body ?? ''),
                'items' => is_array($t->items) ? $t->items : [],
            ])->all();

        return response()->json(['ok' => true, 'templates' => $rows]);
    }

    /**
     * Mint a signed, single-use, short-TTL ticket for the WaDesk-initiated
     * "connect a new Instagram account" popup. Body: {workspace_id, return_url}.
     *
     * The ticket carries {workspace_id, return_url, nonce, exp} — the exp caps it
     * at 10 minutes and the nonce (cached, one-time) makes it single-use, so a
     * leaked URL can't be replayed. It is signed with the SAME shared secret this
     * whole bridge proves on every call: base64url(json) . '.' . hmac_sha256(...).
     * InstagramConnectController::start() verifies + consumes it, then runs the
     * normal Meta OAuth and stamps wadesk_workspace_id on the saved account.
     */
    public function connectStart(Request $request): JsonResponse
    {
        if ($g = $this->guard($request)) {
            return $g;
        }
        $data = $request->validate([
            'workspace_id' => 'required|integer',
            'return_url'   => 'required|string|max:2048',
        ]);

        $ws  = (int) $data['workspace_id'];
        $url = (string) $data['return_url'];

        $nonce   = bin2hex(random_bytes(16));
        $payload = [
            'workspace_id' => $ws,
            'return_url'   => $url,
            'nonce'        => $nonce,
            'exp'          => now()->getTimestamp() + 600,
        ];
        $b64    = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig    = hash_hmac('sha256', $b64, WadeskLink::secret());
        $ticket = $b64 . '.' . $sig;

        // One-time: the nonce is the proof the ticket has not already been spent.
        // start() forgets it on consume, so a second use (or a replay) fails.
        Cache::put('wd_ig_ticket:' . $nonce, $ws, 600);

        $connectUrl = rtrim((string) config('app.url'), '/') . '/instagram/connect?wd_ticket=' . urlencode($ticket);

        return response()->json(['ok' => true, 'url' => $connectUrl]);
    }

    /** Trigger a flow for this conversation. Body: {flow_id, text?}. */
    public function runFlow(Request $request, string $conversation): JsonResponse
    {
        if ($g = $this->guard($request)) return $g;
        $data = $request->validate(['flow_id' => 'required|integer', 'text' => 'nullable|string']);
        [$accountId, $igsid] = $this->parseConversation($conversation);
        $account = $accountId ? InstagramAccount::find($accountId) : null;
        if (! $account) return response()->json(['ok' => false, 'error' => 'unknown conversation'], 404);

        $flow = \App\Services\Instagram\InstagramGate::flows()
            ->where('user_id', $account->user_id)
            ->where('id', (int) $data['flow_id'])
            ->first();
        if (! $flow) return response()->json(['ok' => false, 'error' => 'unknown flow'], 404);

        $decoded = $flow->decoded_flow_data;
        if (! is_array($decoded) || ! \App\Services\Instagram\IgFlowRunner::nodesOf($decoded)) {
            return response()->json(['ok' => false, 'error' => 'flow has no nodes'], 422);
        }

        $ctx = ['igsid' => $igsid, 'text' => (string) ($data['text'] ?? '')];
        try {
            // Prefer the Node engine (same path the live webhook uses); fall back to the PHP runner.
            $handed = false;
            try {
                $handed = \App\Services\Instagram\IgFlowNodeBridge::start($account, $flow, $igsid, $ctx['text'], '');
            } catch (\Throwable $e) {
                Log::warning('[WADESK-BRIDGE] flow node hand-off errored: ' . $e->getMessage());
            }
            if (! $handed) {
                (new \App\Services\Instagram\IgFlowRunner($account))->run($decoded, $ctx, (int) $flow->id);
            }
        } catch (\Throwable $e) {
            Log::warning('[WADESK-BRIDGE] runFlow threw', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }

        return response()->json(['ok' => true, 'flow_id' => (int) $flow->id]);
    }

    /**
     * WaDesk → IgDesk flow SYNC. WaDesk's flow builder and IgDesk's share
     * the same React format ({flowNodes,flowEdges,vars}), so a flow authored in
     * WaDesk is stored here verbatim — no translation. Keyed by wadesk_flow_id
     * so a re-save UPDATES not duplicates. Setting trigger_kind/keywords fires
     * the model's saved()→syncTriggerAutomation(), which wires the keyword
     * automation so the flow auto-runs on a matching inbound DM.
     *
     * Body: {account, wadesk_flow_id, name, flow_data{}, trigger_kind?,
     *        trigger_keywords?, is_published?}
     */
    /**
     * Return ONE flow WITH its full node data so WaDesk can import + edit it in
     * its own builder. `?adopt=<wadeskFlowId>` links this IgDesk flow to that
     * WaDesk flow id — so when WaDesk saves the edited copy (storeFlow keyed by
     * wadesk_flow_id) it UPDATES this same flow instead of creating a duplicate.
     */
    public function showFlow(Request $request, int $flow): JsonResponse
    {
        if ($g = $this->guard($request)) return $g;

        $model = \App\Services\Instagram\InstagramGate::flowModel();
        $row = $model::query()->whereKey($flow)->first();
        if (! $row) return response()->json(['ok' => false, 'error' => 'unknown flow'], 404);

        $adopt = (int) $request->query('adopt', 0);
        if ($adopt > 0 && (int) ($row->wadesk_flow_id ?? 0) !== $adopt) {
            $row->forceFill(['wadesk_flow_id' => $adopt])->save();
        }

        return response()->json([
            'ok'   => true,
            'flow' => [
                'id'               => (int) $row->id,
                'name'             => (string) $row->flow_name,
                'flow_data'        => $row->decoded_flow_data ?: ['flowNodes' => [], 'flowEdges' => []],
                'trigger_kind'     => (string) ($row->trigger_kind ?? 'keyword'),
                'trigger_keywords' => (string) ($row->trigger_keywords ?? ''),
                'is_published'     => (bool) ($row->is_published ?? false),
            ],
        ]);
    }

    public function storeFlow(Request $request): JsonResponse
    {
        if ($g = $this->guard($request)) return $g;
        $data = $request->validate([
            'account'          => 'required',
            'wadesk_flow_id'   => 'required|integer',
            'name'             => 'required|string|max:255',
            'flow_data'        => 'required|array',
            'trigger_kind'     => 'nullable|string|max:32',
            'trigger_keywords' => 'nullable|string|max:2000',
            'is_published'     => 'nullable|boolean',
        ]);

        $account = InstagramAccount::find((int) $data['account']) ?: InstagramAccount::query()->first();
        if (! $account) return response()->json(['ok' => false, 'error' => 'unknown account'], 404);

        $model = \App\Services\Instagram\InstagramGate::flowModel();
        $flow  = $model::query()
            ->where('user_id', $account->user_id)
            ->where('wadesk_flow_id', (int) $data['wadesk_flow_id'])
            ->first() ?: new $model();

        // forceFill: wadesk_flow_id / user_id may sit outside $fillable.
        $flow->forceFill([
            'user_id'           => $account->user_id,
            'wadesk_flow_id'    => (int) $data['wadesk_flow_id'],
            'flow_name'         => (string) $data['name'],
            'flow_type'         => 'instagram',
            'provider'          => 'instagram',
            // flow_data cast is `encrypted` (a string) — store the JSON string;
            // decoded_flow_data json_decodes it back for the runner.
            'flow_data'         => json_encode($data['flow_data']),
            'trigger_kind'      => (string) ($data['trigger_kind'] ?? 'keyword'),
            'trigger_keywords'  => $data['trigger_keywords'] ?? null,
            'trigger_device_id' => $account->id,
            'is_active'         => true,
            'is_published'      => (bool) ($data['is_published'] ?? true),
        ])->save();

        Log::info('[WADESK-BRIDGE] storeFlow', [
            'account' => $account->id, 'wadesk_flow_id' => $data['wadesk_flow_id'], 'flow_id' => $flow->id,
        ]);

        return response()->json(['ok' => true, 'flow_id' => (int) $flow->id]);
    }

    /**
     * Receive a WaDesk-authored Instagram template and store it as a native
     * Instaflow InstagramTemplate (the reverse of templates(), which serves
     * them DOWN to WaDesk). Keyed by (workspace, name) so a re-push from WaDesk
     * updates the same row instead of duplicating. Mirrors storeFlow().
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        if ($g = $this->guard($request)) return $g;
        $data = $request->validate([
            'account' => 'required',
            'name'    => 'required|string|max:255',
            'type'    => 'nullable|string|in:text,quick_replies,buttons',
            'body'    => 'nullable|string|max:2000',
            'items'   => 'nullable|array',
        ]);

        $account = InstagramAccount::find((int) $data['account']) ?: InstagramAccount::query()->first();
        if (! $account) return response()->json(['ok' => false, 'error' => 'unknown account'], 404);

        $tpl = \App\Models\InstagramTemplate::updateOrCreate(
            ['workspace_id' => (int) $account->workspace_id, 'name' => (string) $data['name']],
            [
                'type'  => (string) ($data['type'] ?? 'text'),
                'body'  => (string) ($data['body'] ?? ''),
                'items' => is_array($data['items'] ?? null) ? array_values($data['items']) : [],
            ],
        );

        Log::info('[WADESK-BRIDGE] storeTemplate', [
            'account' => $account->id, 'workspace' => $account->workspace_id, 'template_id' => $tpl->id, 'name' => $tpl->name,
        ]);

        return response()->json(['ok' => true, 'template_id' => (int) $tpl->id]);
    }

    /** "<accountId>_<igsid>" → [int accountId|null, string igsid]. */
    private function parseConversation(string $conversation): array
    {
        $parts = explode('_', $conversation, 2);
        if (count($parts) < 2 || ! ctype_digit($parts[0]) || $parts[1] === '') {
            return [null, ''];
        }
        return [(int) $parts[0], $parts[1]];
    }
}

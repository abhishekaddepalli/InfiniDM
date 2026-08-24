<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstagramAccount;
use App\Models\InstagramMessage;
use App\Services\Instagram\IgFlowRunner;
use App\Services\Instagram\InstagramGate;
use App\Services\Instagram\InstagramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Node-facing endpoints for the Instagram flow engine.
 *
 * The Node walker (node/services/instagramFlowService.js) owns the graph walk,
 * the delays and the Graph API sends. Anything needing the database or platform
 * keys stays here so there is exactly ONE implementation of AI / catalog / lead
 * capture — Node calls back instead of duplicating it.
 *
 * Both routes are Node-bridge only, authed by the X-Node-Token shared secret and
 * FAIL CLOSED when the token is unset (matching every other Node-bridge route —
 * an open endpoint here would let anyone send DMs from a connected account).
 */
class InstagramFlowNodeController extends Controller
{
    private function unauthorized(Request $request): bool
    {
        $expected = InstagramGate::nodeToken();
        $got      = (string) $request->header('X-Node-Token', '');
        return $expected === '' || !hash_equals($expected, $got);
    }

    /**
     * POST /api/instagram/flow-log
     * Node reports a message it just sent so it lands in the IgDesk inbox
     * exactly like a PHP-sent one.
     */
    public function log(Request $request): JsonResponse
    {
        if ($this->unauthorized($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $accountId = (int) $request->input('accountId');
        $igsid     = (string) $request->input('igsid');
        if (!$accountId || $igsid === '') {
            return response()->json(['ok' => false, 'error' => 'accountId and igsid required'], 422);
        }

        $account = InstagramAccount::find($accountId);
        if (!$account) {
            return response()->json(['ok' => false, 'error' => 'account not found'], 404);
        }

        try {
            InstagramMessage::log(
                $account,
                $igsid,
                (string) $request->input('direction', 'out'),
                (string) $request->input('body', ''),
                (string) $request->input('source', 'flow'),
                $request->input('mid')
            );
        } catch (\Throwable $e) {
            Log::warning('[IG-FLOW-NODE] flow-log insert failed: ' . $e->getMessage());
            return response()->json(['ok' => false], 500);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/instagram/flow-node
     * Execute one business node on Laravel's behalf and hand back any variables
     * it produced. Node keeps walking with the result.
     */
    public function node(Request $request): JsonResponse
    {
        if ($this->unauthorized($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $action    = (string) $request->input('action');
        $data      = (array) $request->input('node', []);
        $vars      = (array) $request->input('vars', []);
        $accountId = (int) $request->input('accountId');
        $igsid     = (string) $request->input('igsid');

        $account = $accountId ? InstagramAccount::find($accountId) : null;

        try {
            switch ($action) {
                case 'ai':
                    if (!$account) return response()->json(['ok' => false, 'error' => 'account required'], 422);
                    return response()->json([
                        'ok'    => true,
                        'reply' => IgFlowRunner::aiReplyFor($account, $data, $vars),
                    ]);

                case 'webhook':
                    // Laravel owns this because the SSRF guard (http/https only,
                    // every resolved IP public) lives here and MUST apply — the
                    // URL is operator-supplied and can carry {{vars}} from
                    // inbound text.
                    return response()->json([
                        'ok'   => true,
                        'vars' => IgFlowRunner::webhookFor($data, $vars),
                    ]);

                case 'ig_products':
                    if (!$account) return response()->json(['ok' => false, 'error' => 'account required'], 422);
                    $res = app(\App\Services\Instagram\InstagramCommerceService::class)
                        ->sendProducts($account, $igsid, $data);
                    if (!empty($res['ok'])) {
                        InstagramMessage::log($account, $igsid, 'out', '[products]', 'flow', $res['mid'] ?? null);
                    } else {
                        Log::info('[IG-FLOW-NODE] ig_products skipped: ' . ($res['error'] ?? 'unknown'));
                    }
                    return response()->json(['ok' => true]);

                case 'ig_lead':
                    if (!$account) return response()->json(['ok' => false, 'error' => 'account required'], 422);
                    $val = function ($k) use ($vars) {
                        $k = trim((string) $k);
                        return $k !== '' ? trim((string) ($vars[$k] ?? '')) : '';
                    };
                    // Everything the flow collected, minus the internal keys —
                    // stored on the lead as field_data.
                    $extra = collect($vars)
                        ->except(['text', 'igsid', 'comment_id'])
                        ->filter(fn ($v) => is_scalar($v) && trim((string) $v) !== '')
                        ->map(fn ($v) => (string) $v)->all();

                    app(\App\Services\Instagram\InstagramLeadService::class)->capture($account, $igsid, [
                        'full_name' => $val($data['nameVar']  ?? 'lead_name'),
                        'email'     => $val($data['emailVar'] ?? 'lead_email'),
                        'phone'     => $val($data['phoneVar'] ?? 'lead_phone'),
                        'notes'     => $val($data['notesVar'] ?? ''),
                        'extra'     => $extra,
                    ], ($data['createDeal'] ?? true) !== false);

                    $ack = trim(IgFlowRunner::substFor((string) ($data['ack'] ?? ''), $vars));
                    if ($ack !== '') {
                        $r = (new InstagramService($account))->sendDm($igsid, $ack);
                        if (!empty($r['ok'])) {
                            InstagramMessage::log($account, $igsid, 'out', $ack, 'flow', $r['mid'] ?? null);
                        }
                    }
                    return response()->json(['ok' => true]);

                case 'ig_reply_comment':
                    if (!$account) return response()->json(['ok' => false, 'error' => 'account required'], 422);
                    $commentId = (string) ($request->input('commentId') ?: ($vars['comment_id'] ?? ''));
                    if ($commentId !== '') {
                        (new InstagramService($account))->replyComment(
                            $commentId,
                            IgFlowRunner::substFor((string) ($data['message'] ?? ''), $vars)
                        );
                    }
                    return response()->json(['ok' => true]);

                case 'ig_gallery':
                    if (!$account) return response()->json(['ok' => false, 'error' => 'account required'], 422);
                    $svc   = new InstagramService($account);
                    $subst = fn ($s) => trim(IgFlowRunner::substFor((string) $s, $vars));
                    // Optional intro DM sent just before the carousel.
                    $intro = $subst($data['intro'] ?? '');
                    if ($intro !== '') {
                        $ri = $svc->sendDm($igsid, $intro);
                        if (!empty($ri['ok'])) InstagramMessage::log($account, $igsid, 'out', $intro, 'flow', $ri['mid'] ?? null);
                    }
                    // Build up to 10 generic-template elements from the cards.
                    // sendGenericTemplate enforces Meta's caps (10 cards, 3 buttons,
                    // 80-char title/subtitle) — we just shape + substitute vars here.
                    $elements = [];
                    foreach (array_slice((array) ($data['cards'] ?? []), 0, 10) as $card) {
                        $btns = [];
                        foreach (array_slice((array) ($card['buttons'] ?? []), 0, 3) as $b) {
                            $bt = $subst($b['title'] ?? '');
                            if ($bt === '') continue;
                            $type = ($b['type'] ?? 'web_url') === 'postback' ? 'postback' : 'web_url';
                            if ($type === 'web_url') {
                                $u = $subst($b['url'] ?? '');
                                if ($u === '') continue;               // a URL button with no URL is invalid
                                $btns[] = ['type' => 'web_url', 'title' => $bt, 'url' => $u];
                            } else {
                                $btns[] = ['type' => 'postback', 'title' => $bt, 'payload' => $subst($b['payload'] ?? '') ?: $bt];
                            }
                        }
                        // Meta requires a non-empty title on every element.
                        $elements[] = [
                            'title'     => $subst($card['title'] ?? '') ?: ' ',
                            'image_url' => $subst($card['image'] ?? ''),
                            'subtitle'  => $subst($card['subtitle'] ?? ''),
                            'buttons'   => $btns,
                        ];
                    }
                    if ($elements) {
                        $rg = $svc->sendGenericTemplate($igsid, $elements);
                        if (!empty($rg['ok'])) {
                            InstagramMessage::log($account, $igsid, 'out', '[gallery]', 'flow', $rg['mid'] ?? null);
                        } else {
                            Log::info('[IG-FLOW-NODE] ig_gallery skipped: ' . ($rg['error'] ?? 'unknown'));
                        }
                    }
                    return response()->json(['ok' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning("[IG-FLOW-NODE] action {$action} failed: " . $e->getMessage());
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 200);
        }

        return response()->json(['ok' => false, 'error' => "unknown action {$action}"], 422);
    }
}

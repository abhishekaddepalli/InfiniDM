<?php

namespace App\Services\Instagram;

use App\Models\InstagramAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Hands an Instagram flow off to the NODE engine.
 *
 * Why: the PHP runner (IgFlowRunner) executes inside Meta's webhook request and
 * cannot sleep, so a Wait node had to park in the DB and wait for later traffic
 * to sweep it. Node is a long-lived process — exactly like the Baileys flow
 * engine — so it can just await the timer and keep going.
 *
 * FAIL-SAFE BY DESIGN: if Node is unreachable, not configured, or refuses the
 * hand-off, the caller falls back to the in-process PHP runner. A flow must
 * never be lost because pm2 happens to be down.
 */
class IgFlowNodeBridge
{
    /**
     * Node's base URL, or '' when the bridge isn't configured.
     *
     * InstagramGate::nodeUrl() defers to the host's wd_node_url() wherever it
     * exists, so inside WaDesk this still resolves through the project's
     * canonical resolver — DB (SystemSetting baileys_server_url) authoritative,
     * .env the fallback — exactly like every other Laravel→Node caller.
     */
    public static function baseUrl(): string
    {
        return rtrim(trim(InstagramGate::nodeUrl()), '/');
    }

    public static function enabled(): bool
    {
        return self::baseUrl() !== '' && InstagramGate::nodeToken() !== '';
    }

    /**
     * The Graph credentials Node needs to send — same {base, ig, token} shape
     * igScheduler already receives, so IgGraphClient-style consumers work.
     */
    public static function authFor(InstagramAccount $account): array
    {
        $version = (string) (InstagramGate::setting('instagram_graph_version', 'v25.0') ?: 'v25.0');
        return [
            'base'  => 'https://graph.facebook.com/' . ltrim($version, '/'),
            'ig'    => (string) $account->ig_user_id,
            // Decrypted here, sent over the server-to-server hop, never stored
            // by Node — it lives only for the duration of the walk.
            'token' => (string) $account->access_token,
        ];
    }

    /**
     * Start a flow on the Node engine.
     *
     * $flow is typed as Model, not Flow, because the flows table is owned by
     * core's Flow in WaDesk and by App\Instagram\Models\InstagramFlow
     * standalone — see InstagramGate::flowModel(). Only decoded_flow_data and
     * id are touched, and both classes carry them.
     *
     * @return bool true when Node accepted it (caller must NOT also run PHP).
     */
    public static function start(InstagramAccount $account, Model $flow, string $igsid, string $text, string $commentId = ''): bool
    {
        $data = $flow->decoded_flow_data;
        if (!is_array($data) || !IgFlowRunner::nodesOf($data)) return false;

        return self::post('start', [
            'accountId'   => (int) $account->id,
            'workspaceId' => (int) $account->workspace_id,
            'igsid'       => $igsid,
            'text'        => $text,
            'commentId'   => $commentId,
            'auth'        => self::authFor($account),
            'flow'        => $data,
            'flowId'      => (int) $flow->id,
            'appDomain'   => rtrim((string) config('app.url'), '/'),
        ]);
    }

    /**
     * Offer an inbound message to Node in case a flow is parked on it.
     * @return bool true when Node consumed it (a parked node resumed).
     */
    public static function resume(InstagramAccount $account, string $igsid, string $text): bool
    {
        return self::post('resume', [
            'accountId'   => (int) $account->id,
            'workspaceId' => (int) $account->workspace_id,
            'igsid'       => $igsid,
            'text'        => $text,
            'auth'        => self::authFor($account),
            'appDomain'   => rtrim((string) config('app.url'), '/'),
        ]);
    }

    private static function post(string $mode, array $payload): bool
    {
        if (!self::enabled()) return false;

        try {
            // Short timeout: Node answers BEFORE running the flow (it responds
            // 202 then walks detached), so this call is only the hand-off. A
            // long wait here would put us right back in the webhook-blocking
            // problem this whole bridge exists to avoid.
            $res = Http::withHeaders(['X-Node-Token' => InstagramGate::nodeToken()])
                ->timeout(8)->connectTimeout(4)
                ->post(self::baseUrl() . '/api/instagram-flow/inbound', $payload);

            if (!$res->successful()) {
                Log::warning('[IG-FLOW-BRIDGE] Node refused the hand-off', [
                    'mode' => $mode, 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 200),
                ]);
                return false;
            }

            $consumed = (bool) ($res->json('consumed') ?? false);
            Log::info('[IG-FLOW-BRIDGE] hand-off ' . ($consumed ? 'ACCEPTED' : 'declined'), [
                'mode' => $mode, 'node_mode' => $res->json('mode'),
                'account' => $payload['accountId'] ?? null,
            ]);
            return $consumed;
        } catch (\Throwable $e) {
            // Node down / unreachable — caller falls back to the PHP runner.
            Log::warning('[IG-FLOW-BRIDGE] Node unreachable, falling back to the PHP runner: ' . $e->getMessage());
            return false;
        }
    }
}

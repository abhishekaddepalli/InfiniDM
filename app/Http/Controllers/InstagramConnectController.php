<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Services\Instagram\InstagramGate;
use App\Services\Instagram\InstagramService;
use App\Services\Instagram\WadeskLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Instagram account OAuth connect (Facebook-Login-for-Business path).
 *   GET /instagram/connect   → redirect to Meta's OAuth dialog
 *   GET /instagram/callback  → exchange code, resolve IG account, store
 *   DELETE /instagram/{id}   → disconnect
 */
class InstagramConnectController extends Controller
{
    private function redirectUri(): string
    {
        // Auto-managed: this app's own callback. It must be registered EXACTLY in the
        // Meta app (Instagram → OAuth redirect URIs) — copy it from the settings page.
        return url('/instagram/callback');
    }

    /** Kick off OAuth. */
    public function start(Request $request)
    {
        // WaDesk-initiated connect: a WaDesk admin opened this in a popup with a
        // signed one-time ticket. Verify + consume it and stash the target WaDesk
        // workspace + return URL in the session, then fall through to the normal
        // Meta OAuth below. The account gets stamped with the workspace in
        // callback(). NOTE: this route is behind ['web','auth'] and callback()
        // requires Auth::check(), so the operator's popup must be signed into
        // IgDesk (the admin who configured the WaDesk connection) — the ticket
        // does not create a session. If they aren't, the auth middleware routes
        // them through login first (intended-URL preserves the ticket, and the
        // 10-minute TTL covers the round trip).
        if ($request->filled('wd_ticket')) {
            $verified = $this->consumeWadeskTicket((string) $request->query('wd_ticket'));
            if ($verified === null) {
                return redirect('/instagram')->withErrors(['instagram' => 'This WaDesk connect link is invalid or has expired. Start again from WaDesk Devices.']);
            }
            session([
                'wd_ig_workspace' => $verified['workspace_id'],
                'wd_ig_return'    => $verified['return_url'],
            ]);
        }

        // IG-Login (Instagram-Login, graph.instagram.com) path — admin-selected.
        if ((string) InstagramGate::setting('instagram_login_type', 'facebook') === 'instagram') {
            $igAppId = (string) InstagramGate::setting('instagram_ig_app_id', InstagramGate::setting('instagram_app_id', ''));
            if ($igAppId === '') {
                return back()->withErrors(['instagram' => 'Instagram-Login app id is not configured at /admin/settings/instagram.']);
            }
            $params = [
                'client_id'     => $igAppId,
                'redirect_uri'  => $this->redirectUri(),
                'response_type' => 'code',
                'scope'         => 'instagram_business_basic,instagram_business_manage_messages,instagram_business_manage_comments,instagram_business_content_publish',
                'state'         => csrf_token(),
            ];
            return redirect('https://www.instagram.com/oauth/authorize?' . http_build_query($params));
        }

        $appId    = (string) InstagramGate::setting('instagram_app_id', '');
        $configId = (string) InstagramGate::setting('instagram_config_id', '');
        $v        = (string) InstagramGate::setting('instagram_graph_version', 'v25.0');
        if ($appId === '') {
            return back()->withErrors(['instagram' => 'Instagram is not configured. Ask the platform admin to set the App ID at /admin/settings/instagram.']);
        }
        // Insights → analytics page; content_publish → composer/reels; manage_metadata → webhook subscribe.
        $scope = 'instagram_basic,instagram_manage_messages,instagram_manage_comments,instagram_manage_insights,instagram_content_publish,instagram_manage_contents,pages_show_list,pages_messaging,pages_read_engagement,pages_manage_metadata,business_management';
        $params = [
            'client_id'     => $appId,
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => $scope,
            'state'         => csrf_token(),
        ];
        if ($configId !== '') $params['config_id'] = $configId;
        return redirect('https://www.facebook.com/' . $v . '/dialog/oauth?' . http_build_query($params));
    }

    /** OAuth callback → store the connected account. */
    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            return redirect('/instagram')->withErrors(['instagram' => (string) $request->string('error_description')]);
        }
        $code = (string) $request->string('code');
        // In standalone IgDesk the tenant IS workspace 0 (reads + writes both use
        // it), so wsId 0 is VALID — only a missing code or lost login is a real error.
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        if ($code === '' || ! Auth::check()) {
            return redirect('/instagram')->withErrors(['instagram' => 'Missing authorization code, or your session expired — please click Connect again.']);
        }

        // IG-Login path → Instagram-Login token exchange + graph.instagram.com profile.
        if ((string) InstagramGate::setting('instagram_login_type', 'facebook') === 'instagram') {
            return $this->callbackInstagram($code, $wsId);
        }

        $tok = InstagramService::exchangeCode($code, $this->redirectUri());
        if (empty($tok['access_token'])) {
            Log::warning('[IG-CONNECT] token exchange failed', ['err' => $tok['error'] ?? '']);
            return redirect('/instagram')->withErrors(['instagram' => 'Token exchange failed: ' . ($tok['error'] ?? 'unknown')]);
        }
        $token   = (string) $tok['access_token'];
        $expires = isset($tok['expires_in']) ? now()->addSeconds((int) $tok['expires_in']) : null;

        // Upgrade the short-lived (~1-2h) token to a 60-day long-lived token,
        // otherwise the account flips to "needs re-auth" almost immediately.
        $long = InstagramService::extendFacebookToken($token);
        if (!empty($long['ok'])) {
            $token   = (string) $long['access_token'];
            $expires = now()->addSeconds((int) ($long['expires_in'] ?: 5184000));
        } else {
            Log::warning('[IG-CONNECT] long-lived exchange failed: ' . ($long['error'] ?? 'unknown'));
        }

        $res = $this->storeFacebookAccount($token, $expires, $wsId);
        if (! $res['ok']) {
            return redirect('/instagram')->withErrors(['instagram' => $res['message']]);
        }
        if (! empty($res['account']) && ($resp = $this->maybeWadeskReturn($res['account']))) {
            return $resp;
        }
        return redirect('/instagram')->with('status', 'Instagram account @' . $res['handle'] . ' connected.');
    }

    /**
     * Resolve the IG Professional account behind the user's Pages and upsert it.
     * Shared by the redirect callback AND the embedded-signup (SDK popup) endpoint.
     *
     * @return array{ok:bool, account?:InstagramAccount, handle?:string, message?:string}
     */
    private function storeFacebookAccount(string $token, $expires, int $wsId): array
    {
        $v = (string) InstagramGate::setting('instagram_graph_version', 'v25.0');
        $igId = ''; $username = ''; $name = ''; $pageId = ''; $pic = ''; $followers = null;
        try {
            $pages = Http::withToken($token)->acceptJson()->timeout(15)
                ->get("https://graph.facebook.com/{$v}/me/accounts", ['fields' => 'id,name,instagram_business_account'])
                ->json('data', []);
            foreach ((array) $pages as $p) {
                if (!empty($p['instagram_business_account']['id'])) {
                    $pageId = (string) $p['id'];
                    $igId   = (string) $p['instagram_business_account']['id'];
                    break;
                }
            }
            if ($igId) {
                $prof = Http::withToken($token)->acceptJson()->timeout(15)
                    ->get("https://graph.facebook.com/{$v}/{$igId}", ['fields' => 'username,name,profile_picture_url,followers_count'])
                    ->json();
                $username  = (string) ($prof['username'] ?? '');
                $name      = (string) ($prof['name'] ?? '');
                $pic       = (string) ($prof['profile_picture_url'] ?? '');
                $followers = isset($prof['followers_count']) ? (int) $prof['followers_count'] : null;
            }
        } catch (\Throwable $e) {
            Log::warning('[IG-CONNECT] account resolve failed: ' . $e->getMessage());
        }

        if ($igId === '') {
            return ['ok' => false, 'message' => 'No Instagram Professional account is linked to your Facebook Page. Link one in the Instagram app, then retry.'];
        }
        if (($cap = $this->accountLimitCap($wsId, $igId)) !== null) {
            return ['ok' => false, 'message' => 'Your current plan allows ' . $cap . ' connected Instagram account(s). Upgrade your plan to connect more.'];
        }

        $account = InstagramAccount::updateOrCreate(
            ['workspace_id' => $wsId, 'ig_user_id' => $igId],
            [
                'user_id'          => Auth::id(),
                'username'         => $username,
                'name'             => $name,
                'profile_pic_url'  => $pic,
                'page_id'          => $pageId,
                'login_type'       => 'facebook',
                'access_token'     => $token,
                'token_expires_at' => $expires,
                'scopes'           => ['instagram_basic', 'instagram_manage_messages', 'instagram_manage_comments', 'instagram_manage_insights', 'instagram_content_publish', 'instagram_manage_contents'],
                'status'           => 'connected',
                'followers_count'  => $followers,
                'last_error'       => null,
            ]
        );

        // Subscribe the account to webhook fields so DMs/comments flow in (best-effort).
        try {
            (new InstagramService($account))->subscribeWebhooks();
        } catch (\Throwable $e) {
            Log::warning('[IG-CONNECT] subscribe failed: ' . $e->getMessage());
        }

        return ['ok' => true, 'account' => $account, 'handle' => ($username ?: $igId)];
    }

    /**
     * Embedded signup (Facebook-Login-for-Business JS SDK popup). The browser
     * ran FB.login() with the Business Login config_id + IG_API_ONBOARDING and
     * POSTs the resulting server-side `code` here. We exchange it WITHOUT a
     * redirect_uri (the SDK flow does not use one), extend to a long-lived
     * token, then reuse storeFacebookAccount(). Returns JSON for the popup JS.
     */
    public function connectEmbedded(Request $request): \Illuminate\Http\JsonResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'Missing authorization code.'], 422);
        }

        $appId  = (string) InstagramGate::setting('instagram_app_id', '');
        $secret = (string) InstagramGate::setting('instagram_app_secret', '');
        $v      = (string) InstagramGate::setting('instagram_graph_version', 'v25.0');
        if ($appId === '' || $secret === '') {
            return response()->json(['ok' => false, 'message' => 'Instagram Meta App credentials are not configured.'], 422);
        }

        // SDK code flow → exchange WITHOUT redirect_uri (the popup handled it).
        try {
            $r = Http::acceptJson()->timeout(15)->get("https://graph.facebook.com/{$v}/oauth/access_token", [
                'client_id'     => $appId,
                'client_secret' => $secret,
                'code'          => $code,
            ]);
            if (! $r->successful() || ! $r->json('access_token')) {
                return response()->json(['ok' => false, 'message' => 'Token exchange failed: ' . ($r->json('error.message') ?? $r->status())], 422);
            }
            $token   = (string) $r->json('access_token');
            $expires = $r->json('expires_in') ? now()->addSeconds((int) $r->json('expires_in')) : null;
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Token exchange error: ' . $e->getMessage()], 500);
        }

        // Upgrade to a 60-day long-lived token.
        $long = InstagramService::extendFacebookToken($token);
        if (! empty($long['ok'])) {
            $token   = (string) $long['access_token'];
            $expires = now()->addSeconds((int) ($long['expires_in'] ?: 5184000));
        }

        $res = $this->storeFacebookAccount($token, $expires, $wsId);
        if (! $res['ok']) {
            return response()->json(['ok' => false, 'message' => $res['message']], 422);
        }
        $redirect = ! empty($res['account']) && ($resp = $this->maybeWadeskReturn($res['account']))
            ? $resp->getTargetUrl()
            : '/instagram';
        return response()->json(['ok' => true, 'redirect' => $redirect, 'handle' => $res['handle']]);
    }

    /** IG-Login (graph.instagram.com) connect — short→long token + IG profile. */
    private function callbackInstagram(string $code, int $wsId)
    {
        $tok = InstagramService::exchangeCodeInstagram($code, $this->redirectUri());
        if (empty($tok['ok'])) {
            return redirect('/instagram')->withErrors(['instagram' => 'Instagram-Login token exchange failed: ' . ($tok['error'] ?? 'unknown')]);
        }
        $token   = (string) $tok['access_token'];
        $igId    = (string) ($tok['user_id'] ?? '');
        $expires = now()->addSeconds((int) ($tok['expires_in'] ?: 5184000));

        $username = ''; $name = ''; $pic = ''; $followers = null;
        try {
            $prof = Http::withToken($token)->acceptJson()->timeout(15)
                ->get('https://graph.instagram.com/me', ['fields' => 'user_id,username,name,profile_picture_url,followers_count'])->json();
            $username  = (string) ($prof['username'] ?? '');
            $name      = (string) ($prof['name'] ?? '');
            $pic       = (string) ($prof['profile_picture_url'] ?? '');
            $followers = isset($prof['followers_count']) ? (int) $prof['followers_count'] : null;
            if ($igId === '') $igId = (string) ($prof['user_id'] ?? '');
        } catch (\Throwable $e) {
            Log::warning('[IG-CONNECT] IG-login profile fetch failed: ' . $e->getMessage());
        }
        if ($igId === '') {
            return redirect('/instagram')->withErrors(['instagram' => 'Could not resolve the Instagram account.']);
        }

        if (($cap = $this->accountLimitCap($wsId, $igId)) !== null) {
            return redirect('/instagram')->withErrors(['instagram' => 'Your current plan allows ' . $cap . ' connected Instagram account(s). Upgrade your plan to connect more.']);
        }

        $account = InstagramAccount::updateOrCreate(
            ['workspace_id' => $wsId, 'ig_user_id' => $igId],
            [
                'user_id'          => Auth::id(),
                'username'         => $username,
                'name'             => $name,
                'profile_pic_url'  => $pic,
                'page_id'          => null,
                'login_type'       => 'instagram',
                'access_token'     => $token,
                'token_expires_at' => $expires,
                'scopes'           => ['instagram_business_basic', 'instagram_business_manage_messages', 'instagram_business_manage_comments', 'instagram_business_content_publish'],
                'status'           => 'connected',
                'followers_count'  => $followers,
                'last_error'       => null,
            ]
        );
        try { (new InstagramService($account))->subscribeWebhooks(); } catch (\Throwable $e) {}

        if ($resp = $this->maybeWadeskReturn($account)) {
            return $resp;
        }

        return redirect('/instagram')->with('status', 'Instagram account @' . ($username ?: $igId) . ' connected.');
    }

    /**
     * Verify + CONSUME a WaDesk connect ticket (see WadeskBridgeController::connectStart).
     * base64url(json).hmac_sha256 signed with the shared secret; exp-bounded and
     * single-use via a cached nonce. Returns [workspace_id,return_url] or null.
     */
    private function consumeWadeskTicket(string $ticket): ?array
    {
        $secret = WadeskLink::secret();
        if ($secret === '') {
            return null;
        }
        $parts = explode('.', $ticket, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }
        [$b64, $sig] = $parts;

        $expected = hash_hmac('sha256', $b64, $secret);
        if (! hash_equals($expected, $sig)) {
            return null;
        }

        $json = base64_decode(strtr($b64, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }
        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            return null;
        }

        if ((int) ($payload['exp'] ?? 0) < now()->getTimestamp()) {
            return null;
        }
        $nonce = (string) ($payload['nonce'] ?? '');
        if ($nonce === '') {
            return null;
        }

        // Single-use: the nonce must still be cached (not already spent).
        $cacheKey = 'wd_ig_ticket:' . $nonce;
        $cachedWs = Cache::get($cacheKey);
        if ($cachedWs === null) {
            return null;
        }
        Cache::forget($cacheKey);

        return [
            'workspace_id' => (int) ($payload['workspace_id'] ?? $cachedWs),
            'return_url'   => (string) ($payload['return_url'] ?? ''),
        ];
    }

    /**
     * If this OAuth run was WaDesk-initiated (session set by start()), stamp the
     * account with the WaDesk workspace and render the tiny "connected" page that
     * postMessages the opener (the WaDesk /devices tab) and closes — falling back
     * to redirecting the top window to return_url?ig_account=<id>&wadesk=1 when
     * there is no opener. Returns the response, or null when it wasn't WaDesk-led.
     */
    private function maybeWadeskReturn(InstagramAccount $account)
    {
        if (! session()->has('wd_ig_workspace')) {
            return null;
        }
        $ws     = (int) session('wd_ig_workspace');
        $return = (string) session('wd_ig_return', '');

        $account->wadesk_workspace_id = $ws;
        $account->save();

        session()->forget(['wd_ig_workspace', 'wd_ig_return']);
        Log::info('[IG-CONNECT] account linked to WaDesk workspace', ['account' => $account->id, 'workspace' => $ws]);

        return response()->view('instagram.wadesk_connected', [
            'accountId' => (int) $account->id,
            'returnUrl' => $return,
        ]);
    }

    /**
     * Plan cap on connected accounts. A re-auth of an already-linked account
     * never counts — only a brand-new connection can be blocked. Returns the
     * plan's cap when the connection should be blocked, or null to allow.
     * InstagramGate handles enforce_plans OFF (unlimited) and admins (uncapped),
     * so this is a no-op unless enforcement is switched on.
     */
    private function accountLimitCap(int $wsId, string $igId): ?int
    {
        if (InstagramAccount::where('workspace_id', $wsId)->where('ig_user_id', $igId)->exists()) {
            return null;
        }
        $used = (int) InstagramAccount::where('workspace_id', $wsId)->count();

        return InstagramGate::exceeded('instagram_accounts', $used)
            ? InstagramGate::limit('instagram_accounts')
            : null;
    }

    /** Refresh an account's profile stats (username / name / followers / avatar). */
    public function refresh(int $id)
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $acc = InstagramAccount::where('workspace_id', $wsId)->where('id', $id)->first();
        if (!$acc) return back()->withErrors(['instagram' => 'Account not found.']);
        $p = (new InstagramService($acc))->getProfile();
        if (!empty($p)) {
            if (!empty($p['username'])) $acc->username = (string) $p['username'];
            if (!empty($p['name'])) $acc->name = (string) $p['name'];
            if (!empty($p['profile_picture_url'])) $acc->profile_pic_url = (string) $p['profile_picture_url'];
            if (isset($p['followers_count'])) $acc->followers_count = (int) $p['followers_count'];
            $acc->save();
            return back()->with('status', 'Profile refreshed.');
        }
        return back()->withErrors(['instagram' => 'Could not refresh profile (token may need re-auth).']);
    }

    public function disconnect(int $id)
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $acc  = InstagramAccount::where('workspace_id', $wsId)->where('id', $id)->first();
        if (! $acc) {
            return back()->with('status', 'Instagram account disconnected.');
        }

        // Removing an account must remove ITS data too — otherwise the messages,
        // contacts, automations etc. linger and keep showing on the dashboard,
        // inbox and analytics even with zero accounts connected. Everything is
        // keyed by instagram_account_id, so one id purges it everywhere.
        \Illuminate\Support\Facades\DB::transaction(function () use ($acc) {
            $accId = $acc->id;
            $perAccount = [
                'instagram_messages', 'instagram_contacts', 'instagram_automations',
                'instagram_scheduled_posts', 'instagram_commerce', 'instagram_reposter',
                'instagram_orders', 'instagram_posts',
            ];
            foreach ($perAccount as $tbl) {
                if (\Illuminate\Support\Facades\Schema::hasTable($tbl)
                    && \Illuminate\Support\Facades\Schema::hasColumn($tbl, 'instagram_account_id')) {
                    \Illuminate\Support\Facades\DB::table($tbl)
                        ->where('instagram_account_id', $accId)->delete();
                }
            }
            $acc->delete();
        });

        return back()->with('status', 'Instagram account removed — its data was cleared.');
    }

    /**
     * Re-run the account's webhook subscription (subscribed_apps → messages,
     * postbacks, reactions, comments, …). The "Fix inbound" button — auto-reply
     * only fires from the webhook, so a silently-failed subscribe at connect
     * time means Instagram never calls /webhooks/instagram and nothing
     * auto-replies (even though the inbox still PULLS messages via the API).
     * NOTE: this fixes the per-account subscription; the App-level callback URL
     * (+ `messages` field) must also be set in the Meta App dashboard.
     */
    public function resubscribe(int $id)
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $account = InstagramAccount::where('workspace_id', $wsId)->where('id', $id)->first();
        if (!$account) return back()->withErrors(['instagram' => 'Account not found.']);

        $r = (new InstagramService($account))->subscribeWebhooks();
        \Illuminate\Support\Facades\Log::info('[IG-HOOK] manual resubscribe', [
            'account' => $account->id, 'ok' => $r['ok'] ?? false, 'error' => $r['error'] ?? null,
        ]);

        if (!empty($r['ok'])) {
            return back()->with('status', 'Instagram webhooks re-subscribed. Send a test DM — you should now see an [IG-HOOK] log and auto-reply fire (if an automation is active).');
        }
        return back()->withErrors(['instagram' => 'Re-subscribe failed: ' . ($r['error'] ?? 'unknown')
            . ' — also set the Instagram webhook Callback URL + verify token in your Meta App dashboard and subscribe the "messages" field.']);
    }

    /**
     * "Check webhook" diagnostic — reads back the LIVE state so you can see
     * exactly why webhooks aren't arriving, in one place:
     *   - which fields Meta has this account subscribed to (live GET),
     *   - the Callback URL + verify token to paste in the Meta App dashboard,
     *   - whether the App Secret is configured (handle() fails closed without it).
     * Flashes it into the session so the dashboard renders a diagnostics panel.
     */
    public function webhookStatus(int $id)
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $account = InstagramAccount::where('workspace_id', $wsId)->where('id', $id)->first();
        if (!$account) return back()->withErrors(['instagram' => 'Account not found.']);

        $svc = new InstagramService($account);

        // Heal the account's messaging igsid NOW so inbound webhooks match it —
        // Instagram keys webhooks on this id (entry.id, e.g. 1784…), NOT on the
        // Graph node id we store in ig_user_id (e.g. 2744…). One-time write.
        $selfIgsid = $svc->resolveSelfIgsid();
        if ($selfIgsid !== '' && (string) ($account->meta_json['self_igsid'] ?? '') !== $selfIgsid) {
            $meta = (array) $account->meta_json;
            $meta['self_igsid'] = $selfIgsid;
            $account->forceFill(['meta_json' => $meta])->save();
            Log::info('[IG-HOOK] self_igsid stored via check', ['account' => $account->id, 'self_igsid' => $selfIgsid]);
        }

        $sub = $svc->subscribedApps();

        $fields   = $sub['fields'] ?? [];
        $hasMsgs  = in_array('messages', $fields, true);
        $token    = (string) InstagramGate::setting('instagram_webhook_verify_token', '');
        $secret   = trim((string) InstagramGate::setting('instagram_app_secret', ''))
                 !== '' || trim((string) InstagramGate::setting('instagram_ig_app_secret', '')) !== '';
        $callback = url('/webhooks/instagram');

        // Self-reachability probe — hit OUR OWN public callback URL exactly the way
        // Meta does for the GET handshake. If we get our challenge echoed back with
        // 200, the URL is internet-valid (DNS + SSL + the /public/ path + the verify
        // token all line up). A failure here is THE usual reason Meta's "Verify and
        // Save" fails silently and no webhook is ever delivered.
        $selftest = ['ran' => false, 'ok' => false, 'status' => 0, 'note' => ''];
        if ($token !== '') {
            $challenge = 'selftest_' . substr(md5($account->id . $token), 0, 10);
            $probeUrl  = $callback . '?hub.mode=subscribe&hub.verify_token=' . rawurlencode($token) . '&hub.challenge=' . $challenge;
            try {
                $probe = Http::timeout(10)->withoutRedirecting()->get($probeUrl);
                $selftest['ran']    = true;
                $selftest['status'] = $probe->status();
                $selftest['ok']     = $probe->status() === 200 && trim($probe->body()) === $challenge;
                if (!$selftest['ok']) {
                    $selftest['note'] = $probe->status() === 200
                        ? 'Reached the URL but the challenge did not echo back — verify-token mismatch.'
                        : ('The URL returned HTTP ' . $probe->status() . ($probe->redirect() ? ' (redirect to ' . $probe->header('Location') . ')' : '') . ' — Meta cannot verify it. Check the /public path.');
                }
            } catch (\Throwable $e) {
                $selftest['ran']  = true;
                $selftest['note'] = 'Server could not reach the callback URL: ' . $e->getMessage();
            }
        }

        Log::info('[IG-HOOK] webhook status checked', [
            'account' => $account->id, 'subscribe_ok' => $sub['ok'] ?? false,
            'fields'  => $fields, 'has_messages' => $hasMsgs, 'node' => $sub['node'] ?? '',
            'selftest' => $selftest,
        ]);

        return back()->with('ig_webhook_diag', [
            'username'      => (string) ($account->username ?: $account->ig_user_id),
            'login_type'    => (string) $account->login_type,
            'subscribe_ok'  => (bool) ($sub['ok'] ?? false),
            'subscribe_err' => $sub['error'] ?? null,
            'fields'        => $fields,
            'has_messages'  => $hasMsgs,
            'callback_url'  => $callback,
            'verify_set'    => $token !== '',
            'verify_token'  => $token,
            'secret_set'    => $secret,
            'selftest'      => $selftest,
        ]);
    }
}

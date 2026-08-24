<?php

/**
 * Instagram routes — the complete surface, in one file.
 *
 * Loaded by BOTH products:
 *   - WaDesk, via the extension route loader in bootstrap/app.php, which only
 *     requires this file while the extension is installed and enabled;
 *   - the standalone Instagram product, which requires it from its own bootstrap.
 *
 * The file declares its own middleware groups rather than assuming a wrapper,
 * because it registers three different kinds of endpoint: session-authed pages,
 * public Meta webhooks, and Node-bridge API calls that must NOT get a session.
 *
 * Every authed route carries an InstagramFeature gate. In standalone mode
 * InstagramGate lets them all through, so these annotations cost nothing there
 * — the same file genuinely serves both.
 */

use App\Http\Controllers\Api\InstagramFlowNodeController;
use App\Http\Controllers\Api\InstagramNodeController;
use App\Http\Controllers\Instagram\AdsController;
use App\Http\Controllers\InstagramCommerceController;
use App\Http\Controllers\InstagramConnectController;
use App\Http\Controllers\InstagramController;
use App\Http\Controllers\InstagramLeadsController;
use App\Http\Controllers\InstagramOrdersController;
use App\Http\Controllers\InstagramReposterController;
use App\Http\Controllers\InstagramTemplateController;
use App\Http\Controllers\InstagramWebhookController;
use App\Http\Middleware\InstagramFeature;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

/** Shorthand: InstagramFeature::class . ':inbox' */
$gate = fn (string ...$f) => InstagramFeature::class . ':' . implode(',', $f);

/*
|--------------------------------------------------------------------------
| Public webhooks — Meta Platform + payment callbacks
|--------------------------------------------------------------------------
|
| No auth and no plan gate: Meta calls these, not a signed-in user. Both are
| HMAC-verified inside the controller, which is the actual security boundary.
|
| CSRF is dropped per-route rather than via the core exclusion list, so the
| extension stays self-contained. This also fixes a real bug: in core only
| `webhooks/instagram` was excluded — `webhooks/instagram-pay` was not, so
| every Razorpay payment callback was being rejected with a 419.
*/
Route::middleware('web')->withoutMiddleware([VerifyCsrfToken::class])->group(function () {
    Route::get('/webhooks/instagram',  [InstagramWebhookController::class, 'verify'])->name('instagram.webhook.verify');
    Route::post('/webhooks/instagram', [InstagramWebhookController::class, 'handle'])->name('instagram.webhook.handle');

    Route::post('/webhooks/instagram-pay', [InstagramWebhookController::class, 'razorpayWebhook'])
        ->name('instagram.pay.webhook');

    // Checkout gateway return/callback — CSRF-exempt + no auth: gateways redirect
    // back cross-site and may drop the session cookie.
    Route::match(['get', 'post'], '/payment/callback/{gateway}', [\App\Http\Controllers\CheckoutController::class, 'callback'])
        ->name('payment.callback');

    // Symlink-free public server for outbound DM media. Instagram fetches the
    // attachment from here (unauthenticated), so it must NOT be behind auth and
    // must not rely on the public/storage symlink (missing/unfollowed on many
    // shared hosts). Locked to the instagram-dm upload dir in the controller.
    Route::get('/instagram/dm-media/{path}', [InstagramController::class, 'dmMedia'])
        ->where('path', '.*')->name('instagram.dm-media');

    // Self-healing avatar proxy. IG profile_pic URLs expire (~1–2 days) and 403
    // once stale; this stable url re-fetches + caches so the inbox and the
    // WaDesk mirror never render an expiring CDN link. Public (no session) so
    // WaDesk can load it cross-domain. igsid 'self' = the account's own avatar.
    Route::get('/ig-avatar/{account}/{igsid}', [InstagramController::class, 'igAvatar'])
        ->whereNumber('account')->where('igsid', '[A-Za-z0-9]+')->name('instagram.ig-avatar');
});

/*
|--------------------------------------------------------------------------
| Node bridge — stateless
|--------------------------------------------------------------------------
|
| The `api` group deliberately has no StartSession, so a browser holding the
| session lock cannot block Node. Auth is X-Node-Token, checked in-controller,
| fail-closed. No plan gate: Node is finishing work a gated request already
| started, and blocking it here would strand half-sent jobs.
*/
Route::middleware('api')->prefix('api')->group(function () {
    Route::get('/instagram/jobs/due',            [InstagramNodeController::class, 'due'])->name('ig.node.due');
    Route::post('/instagram/jobs/claim',         [InstagramNodeController::class, 'claim'])->name('ig.node.claim');
    Route::post('/instagram/jobs/result',        [InstagramNodeController::class, 'result'])->name('ig.node.result');
    Route::post('/instagram/reposter/enqueue',   [InstagramNodeController::class, 'enqueue'])->name('ig.node.enqueue');
    Route::post('/instagram/reposter/cleanup',   [InstagramNodeController::class, 'cleanup'])->name('ig.node.cleanup');
    Route::post('/instagram/refresh-tokens',     [InstagramNodeController::class, 'refreshTokens'])->name('ig.node.refresh-tokens');

    Route::post('/instagram/flow-log',  [InstagramFlowNodeController::class, 'log'])->name('ig.flow.log');
    Route::post('/instagram/flow-node', [InstagramFlowNodeController::class, 'node'])->name('ig.flow.node');
});

/*
|--------------------------------------------------------------------------
| WaDesk bridge — Instaflow serves the unified inbox
|--------------------------------------------------------------------------
|
| A separate WaDesk install reads this Instaflow's Instagram threads and sends
| replies through these endpoints (its App\Services\Instaflow\InstaflowClient is
| the caller), so IG lands in WaDesk's UNIFIED team inbox. The controller
| self-guards on the X-Instaflow-Secret shared secret — no session — so they sit
| in the lockless `api` group like every other machine-to-machine route here.
*/
Route::middleware('api')->prefix('api/wadesk')->group(function () {
    Route::get('/handshake',                             [\App\Http\Controllers\WadeskBridgeController::class, 'handshake'])->name('wadesk.handshake');
    Route::get('/accounts',                              [\App\Http\Controllers\WadeskBridgeController::class, 'accounts'])->name('wadesk.accounts');
    // DIAGNOSTIC — raw sender-profile fetch (why an avatar isn't resolving).
    Route::get('/debug-profile',                         [\App\Http\Controllers\WadeskBridgeController::class, 'debugProfile'])->name('wadesk.debug-profile');
    Route::get('/conversations',                         [\App\Http\Controllers\WadeskBridgeController::class, 'conversations'])->name('wadesk.conversations');
    Route::get('/conversations/{conversation}/messages', [\App\Http\Controllers\WadeskBridgeController::class, 'messages'])->name('wadesk.messages');
    Route::post('/conversations/{conversation}/reply',   [\App\Http\Controllers\WadeskBridgeController::class, 'reply'])->name('wadesk.reply');
    Route::post('/conversations/{conversation}/react',    [\App\Http\Controllers\WadeskBridgeController::class, 'react'])->name('wadesk.react');
    Route::post('/conversations/{conversation}/action',   [\App\Http\Controllers\WadeskBridgeController::class, 'action'])->name('wadesk.action');
    Route::post('/conversations/{conversation}/run-flow', [\App\Http\Controllers\WadeskBridgeController::class, 'runFlow'])->name('wadesk.run-flow');
    Route::get('/flows',                                  [\App\Http\Controllers\WadeskBridgeController::class, 'flows'])->name('wadesk.flows');
    // Single flow WITH its full node data — lets WaDesk import + edit an
    // Instaflow-authored flow in its own builder (`?adopt=<wadeskFlowId>` links
    // it so subsequent WaDesk saves UPDATE this flow rather than duplicating).
    Route::get('/flows/{flow}',                           [\App\Http\Controllers\WadeskBridgeController::class, 'showFlow'])->whereNumber('flow')->name('wadesk.flows.show');
    // Instagram templates (reusable DM snippets) — WaDesk pulls these into its
    // own template library via the "Sync from Instaflow" button.
    Route::get('/templates',                              [\App\Http\Controllers\WadeskBridgeController::class, 'templates'])->name('wadesk.templates');
    // WaDesk pushes a template it authored UP into Instaflow's own library.
    Route::post('/templates',                             [\App\Http\Controllers\WadeskBridgeController::class, 'storeTemplate'])->name('wadesk.templates.store');
    Route::post('/flows',                                 [\App\Http\Controllers\WadeskBridgeController::class, 'storeFlow'])->name('wadesk.flows.store');
    // WaDesk "connect a new IG account" popup — mints a signed one-time ticket.
    Route::post('/connect/start',                        [\App\Http\Controllers\WadeskBridgeController::class, 'connectStart'])->name('wadesk.connect-start');
});

/*
|--------------------------------------------------------------------------
| Admin — platform Meta app credentials
|--------------------------------------------------------------------------
|
| Registered here rather than in core's routes/admin.php so the settings page
| leaves with the extension. Middleware is spelled out because this file is not
| loaded inside core's admin group. No plan gate: this is platform-operator
| configuration, not a tenant entitlement.
|
| Only registered when the admin middleware aliases exist — the standalone
| product has no WaDesk admin surface and must not try to reference them.
*/
if (app('router')->getMiddleware()['admin'] ?? null) {
    Route::middleware(['web', 'auth', 'admin', 'ip.allowlist'])
        ->prefix('admin/settings')
        ->name('admin.settings.')
        ->group(function () {
            Route::get('/instagram',  [\App\Http\Controllers\Admin\InstagramSettingsController::class, 'index'])->name('instagram');
            Route::post('/instagram', [\App\Http\Controllers\Admin\InstagramSettingsController::class, 'update'])->name('instagram.update');
        });
}

/*
|--------------------------------------------------------------------------
| WaDesk connection — operator config ("Connect WaDesk" page)
|--------------------------------------------------------------------------
|
| Standalone Instaflow links to a WaDesk install here: generate a shared secret,
| set the WaDesk URL + target workspace. In the addon build the IG data already
| lives in WaDesk's own inbox, so this bridge is only truly meaningful for a
| self-hosted Instaflow — but the page is registered in both builds. It is
| admin-gated where the host provides an admin surface; otherwise (standalone)
| the sole authenticated operator configures it. NOT plan-gated — operator config.
*/
// Admin-only: lives in the Instaflow ADMIN panel (own layout + sidebar), never in
// the user Instagram shell. admin.gate 404s any non-admin.
Route::middleware(['web', 'auth', 'admin.gate'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/wadesk-connection',           [\App\Http\Controllers\WadeskConnectionController::class, 'index'])->name('wadesk-connection');
    Route::post('/wadesk-connection',          [\App\Http\Controllers\WadeskConnectionController::class, 'update'])->name('wadesk-connection.update');
    Route::post('/wadesk-connection/generate', [\App\Http\Controllers\WadeskConnectionController::class, 'generate'])->name('wadesk-connection.generate');
    Route::post('/wadesk-connection/test',     [\App\Http\Controllers\WadeskConnectionController::class, 'test'])->name('wadesk-connection.test');
});

/*
|--------------------------------------------------------------------------
| Plans / pricing — signed-in, but NOT plan-gated
|--------------------------------------------------------------------------
|
| The user-facing plan picker. Deliberately outside the master feature gate
| below: a tenant on an expired or locked plan must still be able to reach this
| page to upgrade, so gating it behind the very entitlement it sells would trap
| them. Auth only.
*/
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/instagram/plans', [\App\Http\Controllers\PricingController::class, 'index'])->name('instagram.plans');

    // One-time checkout (all gateways) → order → activates the plan window.
    Route::get('/checkout/{package}',      [\App\Http\Controllers\CheckoutController::class, 'show'])->whereNumber('package')->name('checkout.show');
    Route::post('/checkout/{package}',     [\App\Http\Controllers\CheckoutController::class, 'pay'])->whereNumber('package')->name('checkout.pay');
    Route::get('/checkout/{order}/cancel', [\App\Http\Controllers\CheckoutController::class, 'cancel'])->whereNumber('order')->name('checkout.cancel');

    // Account (tabbed) + invoices.
    Route::get('/account',            [\App\Http\Controllers\AccountController::class, 'index'])->name('account.index');
    Route::patch('/account/profile',  [\App\Http\Controllers\AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::patch('/account/password', [\App\Http\Controllers\AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::patch('/account/ai-keys',  [\App\Http\Controllers\AccountController::class, 'updateAiKeys'])->name('account.ai-keys.update');
    Route::get('/invoices/{order}',   [\App\Http\Controllers\InvoiceController::class, 'show'])->whereNumber('order')->name('invoices.show');
});

/*
|--------------------------------------------------------------------------
| The Instaflow suite — signed in, plan gated
|--------------------------------------------------------------------------
|
| The whole group sits behind the master switch; individual features add their
| own gate on top. A GET still renders with the paywall overlay (matching core
| behaviour); a POST/PUT/DELETE is refused outright, server-side.
*/
Route::middleware(['web', 'auth', $gate('master')])->group(function () use ($gate) {

    // The shared flow-builder bundle (from WaDesk) probes these WhatsApp-only
    // endpoints on load to fill tag/agent/team/deal/template pickers. Instaflow
    // has no such tables, so answer empty instead of 404 — keeps the console
    // clean and those node inspectors render "none configured" gracefully.
    Route::get('/team-inbox/api/tags',      fn () => response()->json(['success' => true, 'data' => [], 'tags' => []]));
    Route::get('/team-inbox/api/ai-agents', fn () => response()->json(['success' => true, 'data' => [], 'agents' => [], 'ai_agents' => []]));
    Route::get('/team-inbox/api/teams',     fn () => response()->json(['success' => true, 'data' => [], 'teams' => []]));
    Route::get('/deals/stages',             fn () => response()->json(['success' => true, 'data' => [], 'stages' => []]));
    Route::get('/templates/api/list',       fn () => response()->json(['success' => true, 'data' => [], 'templates' => []]));

    // Dashboard + account connection. Connection is intentionally NOT gated
    // beyond the master switch — a tenant must be able to connect or remove an
    // account even on a plan where most features are locked, or they cannot
    // clean up after a downgrade.
    Route::get('/instagram', [InstagramController::class, 'dashboard'])->name('instagram.dashboard');

    Route::get('/instagram/connect',  [InstagramConnectController::class, 'start'])->name('instagram.connect');
    Route::get('/instagram/callback', [InstagramConnectController::class, 'callback'])->name('instagram.callback');
    // Embedded signup (Facebook-Login-for-Business JS SDK popup) → posts the code.
    Route::post('/instagram/connect/embedded', [InstagramConnectController::class, 'connectEmbedded'])->name('instagram.connect.embedded');
    Route::post('/instagram/account/{id}/refresh',        [InstagramConnectController::class, 'refresh'])->whereNumber('id')->name('instagram.account.refresh');
    Route::delete('/instagram/account/{id}',              [InstagramConnectController::class, 'disconnect'])->whereNumber('id')->name('instagram.disconnect');
    Route::post('/instagram/account/{id}/resubscribe',    [InstagramConnectController::class, 'resubscribe'])->whereNumber('id')->name('instagram.resubscribe');
    Route::get('/instagram/account/{id}/webhook-status',  [InstagramConnectController::class, 'webhookStatus'])->whereNumber('id')->name('instagram.webhook-status');

    // ---- Inbox (DMs) -----------------------------------------------------
    Route::middleware($gate('inbox'))->group(function () {
        Route::get('/instagram/inbox',                   [InstagramController::class, 'inbox'])->name('instagram.inbox');
        Route::get('/instagram/message-history',         [InstagramController::class, 'messageHistory'])->name('instagram.message-history');
        Route::post('/instagram/inbox/reply',            [InstagramController::class, 'inboxReply'])->name('instagram.inbox.reply');
        Route::get('/instagram/inbox/older',             [InstagramController::class, 'inboxOlder'])->name('instagram.inbox.older');
        Route::get('/instagram/inbox/refresh-avatars',   [InstagramController::class, 'inboxRefreshAvatars'])->name('instagram.inbox.refresh-avatars');
        Route::get('/instagram/inbox/sync',              [InstagramController::class, 'inboxSync'])->name('instagram.inbox.sync');
        Route::get('/instagram/inbox/poll',              [InstagramController::class, 'inboxPoll'])->name('instagram.inbox.poll');
        Route::get('/instagram/inbox/list-poll',         [InstagramController::class, 'inboxListPoll'])->name('instagram.inbox.list-poll');
        // Drives the rail dot and the floating new-message pill on other pages.
        Route::get('/instagram/inbox/unread-summary',    [InstagramController::class, 'inboxUnreadSummary'])->name('instagram.inbox.unread');
        Route::post('/instagram/inbox/react',            [InstagramController::class, 'inboxReact'])->name('instagram.inbox.react');
        Route::get('/instagram/inbox/forward-targets',   [InstagramController::class, 'inboxForwardTargets'])->name('instagram.inbox.forward-targets');
        Route::post('/instagram/inbox/forward',          [InstagramController::class, 'inboxForward'])->name('instagram.inbox.forward');
        Route::post('/instagram/inbox/translate',        [InstagramController::class, 'inboxTranslate'])->name('instagram.inbox.translate');
        Route::post('/instagram/inbox/pin',              [InstagramController::class, 'inboxPin'])->name('instagram.inbox.pin');
        Route::post('/instagram/inbox/report',           [InstagramController::class, 'inboxReport'])->name('instagram.inbox.report');
        // Per-conversation controls (WaDesk team-inbox parity) — local flags.
        Route::post('/instagram/inbox/mute',             [InstagramController::class, 'inboxMute'])->name('instagram.inbox.mute');
        Route::post('/instagram/inbox/archive',          [InstagramController::class, 'inboxArchive'])->name('instagram.inbox.archive');
        Route::post('/instagram/inbox/delete-thread',    [InstagramController::class, 'inboxDeleteThread'])->name('instagram.inbox.delete-thread');
        Route::get('/instagram/inbox/gif-search',        [InstagramController::class, 'inboxGifSearch'])->name('instagram.inbox.gif-search');
        Route::get('/instagram/inbox/media/{message}',   [InstagramController::class, 'inboxMedia'])->whereNumber('message')->name('instagram.inbox.media');
        Route::get('/instagram/notifications',           [InstagramController::class, 'notifications'])->name('instagram.notifications');
        // Landing page for the tools the rail no longer carries.
        Route::get('/instagram/more',                    [InstagramController::class, 'more'])->name('instagram.more');
        Route::get('/instagram/language',                [InstagramController::class, 'language'])->name('instagram.language');
        Route::get('/instagram/theme',                   [InstagramController::class, 'theme'])->name('instagram.theme');
        Route::post('/instagram/theme',                  [InstagramController::class, 'saveTheme'])->name('instagram.theme.save');
    });

    // ---- AI agent in the inbox ------------------------------------------
    Route::middleware($gate('ai'))->group(function () {
        Route::post('/instagram/inbox/ai-toggle',    [InstagramController::class, 'inboxAiToggle'])->name('instagram.inbox.ai-toggle');
        Route::post('/instagram/inbox/assign-agent', [InstagramController::class, 'inboxAssignAgent'])->name('instagram.inbox.assign-agent');
        Route::post('/instagram/inbox/create-agent', [InstagramController::class, 'inboxCreateAgent'])->name('instagram.inbox.create-agent');
    });

    // ---- Composer / publishing ------------------------------------------
    Route::middleware($gate('composer'))->group(function () {
        Route::get('/instagram/composer',  [InstagramController::class, 'composer'])->name('instagram.composer');
        Route::post('/instagram/composer', [InstagramController::class, 'composerPublish'])->name('instagram.composer.publish');

        // AI composer tools — JSON endpoints backing the "AI composer tools"
        // card. Same-origin CSRF POSTs; each degrades gracefully to
        // { ok:false, error } when no AI key is configured.
        $AIC = \App\Http\Controllers\Instagram\ComposerAiController::class;
        Route::post('/instagram/composer/ai/caption',   [$AIC, 'caption'])->name('instagram.composer.ai.caption');
        Route::post('/instagram/composer/ai/repurpose', [$AIC, 'repurpose'])->name('instagram.composer.ai.repurpose');
        Route::post('/instagram/composer/ai/review',    [$AIC, 'review'])->name('instagram.composer.ai.review');
        Route::post('/instagram/composer/ai/best-time', [$AIC, 'bestTime'])->name('instagram.composer.ai.best-time');
        Route::post('/instagram/composer/ai/image',     [$AIC, 'image'])->name('instagram.composer.ai.image');
    });

    // ---- Files / personal media library ---------------------------------
    // Media-adjacent, so it rides the composer entitlement: everything the
    // composer uploads or the AI image tool generates also surfaces here.
    // Owner-scoped by auth()->id() in the controller (Instaflow is ws-less).
    Route::middleware($gate('composer'))->group(function () {
        $FL = \App\Http\Controllers\Instagram\FilesController::class;
        Route::get('/instagram/files',                 [$FL, 'index'])->name('instagram.files.index');
        Route::post('/instagram/files/upload',         [$FL, 'upload'])->name('instagram.files.upload');
        Route::post('/instagram/files/bulk-delete',    [$FL, 'bulkDestroy'])->name('instagram.files.bulk-delete');
        Route::get('/instagram/files/{id}/download',   [$FL, 'download'])->whereNumber('id')->name('instagram.files.download');
        Route::post('/instagram/files/{id}/rename',    [$FL, 'rename'])->whereNumber('id')->name('instagram.files.rename');
        Route::post('/instagram/files/{id}/move',       [$FL, 'move'])->whereNumber('id')->name('instagram.files.move');
        Route::delete('/instagram/files/{id}',         [$FL, 'destroy'])->whereNumber('id')->name('instagram.files.destroy');
    });

    // ---- Watermark (content protection) ---------------------------------
    // A publish-time image overlay, so it rides the composer entitlement.
    // Owner-scoped by auth()->id() in the controller (Instaflow is ws-less).
    Route::middleware($gate('composer'))->group(function () {
        $WM = \App\Http\Controllers\Instagram\WatermarkController::class;
        Route::get('/instagram/watermark',              [$WM, 'index'])->name('instagram.watermark.index');
        Route::post('/instagram/watermark',             [$WM, 'save'])->name('instagram.watermark.save');
        Route::post('/instagram/watermark/{id}/toggle', [$WM, 'toggle'])->whereNumber('id')->name('instagram.watermark.toggle');
        Route::delete('/instagram/watermark/{id}',      [$WM, 'destroy'])->whereNumber('id')->name('instagram.watermark.destroy');
    });

    // ---- Scheduler + calendar -------------------------------------------
    Route::middleware($gate('scheduler'))->group(function () {
        Route::get('/instagram/calendar',                [InstagramController::class, 'calendar'])->name('instagram.calendar');
        Route::post('/instagram/scheduled/{id}',         [InstagramController::class, 'scheduledUpdate'])->whereNumber('id')->name('instagram.scheduled.update');
        Route::delete('/instagram/scheduled/{id}',       [InstagramController::class, 'scheduledDestroy'])->whereNumber('id')->name('instagram.scheduled.destroy');
        Route::post('/instagram/scheduled/{id}/publish', [InstagramController::class, 'scheduledPublishNow'])->whereNumber('id')->name('instagram.scheduled.publish');
    });

    // ---- Broadcast -------------------------------------------------------
    Route::middleware($gate('broadcast'))->group(function () {
        Route::get('/instagram/broadcast',        [InstagramController::class, 'broadcast'])->name('instagram.broadcast');
        Route::get('/instagram/broadcast/create', [InstagramController::class, 'broadcastCreate'])->name('instagram.broadcast.create');
        Route::get('/instagram/broadcast/stats',  [InstagramController::class, 'broadcastStats'])->name('instagram.broadcast.stats');
        Route::post('/instagram/broadcast',       [InstagramController::class, 'broadcastSend'])->name('instagram.broadcast.send');
        Route::get('/instagram/broadcast/{id}',   [InstagramController::class, 'broadcastShow'])->whereNumber('id')->name('instagram.broadcast.show');
    });

    // ---- Analytics -------------------------------------------------------
    Route::middleware($gate('analytics'))->group(function () {
        Route::get('/instagram/analytics',                [InstagramController::class, 'analytics'])->name('instagram.analytics');
        Route::get('/instagram/analytics/post/{mediaId}',  [InstagramController::class, 'postInsights'])->name('instagram.analytics.post');
    });

    // ---- My posts --------------------------------------------------------
    Route::middleware($gate('posts'))->group(function () {
        Route::get('/instagram/posts',              [InstagramController::class, 'posts'])->name('instagram.posts');
        Route::get('/instagram/posts/api/comments', [InstagramController::class, 'apiPostComments'])->name('instagram.posts.comments');
        Route::post('/instagram/posts/api/reply',   [InstagramController::class, 'apiPostReply'])->name('instagram.posts.reply');
        Route::post('/instagram/posts/api/hide',    [InstagramController::class, 'apiPostHideComment'])->name('instagram.posts.hide');
    });

    // ---- Comment moderation ---------------------------------------------
    Route::middleware($gate('comments'))->group(function () {
        Route::get('/instagram/comments',                [InstagramController::class, 'comments'])->name('instagram.comments');
        Route::post('/instagram/comments/reply',         [InstagramController::class, 'commentReply'])->name('instagram.comments.reply');
        Route::post('/instagram/comments/private-reply', [InstagramController::class, 'commentPrivateReply'])->name('instagram.comments.private-reply');
        Route::post('/instagram/comments/create',        [InstagramController::class, 'commentCreate'])->name('instagram.comments.create');
        Route::post('/instagram/comments/hide',          [InstagramController::class, 'commentHide'])->name('instagram.comments.hide');
        Route::delete('/instagram/comments',             [InstagramController::class, 'commentDelete'])->name('instagram.comments.delete');
    });

    // ---- Auto-comments ---------------------------------------------------
    Route::middleware($gate('auto_comments'))->group(function () {
        Route::get('/instagram/auto-comments', [InstagramController::class, 'autoComments'])->name('instagram.auto-comments');
    });

    // ---- Automations -----------------------------------------------------
    Route::middleware($gate('automations'))->group(function () {
        Route::get('/instagram/automations',              [InstagramController::class, 'automations'])->name('instagram.automations');
        Route::get('/instagram/automations/create',       [InstagramController::class, 'automationsCreate'])->name('instagram.automations.create');
        Route::get('/instagram/automations/media',         [InstagramController::class, 'automationMedia'])->name('instagram.automations.media');
        Route::get('/instagram/automations/stories',       [InstagramController::class, 'automationStories'])->name('instagram.automations.stories');
        Route::get('/instagram/automations/analytics',    [InstagramController::class, 'automationsAnalytics'])->name('instagram.automations.analytics');
        Route::get('/instagram/automations/{id}/edit',    [InstagramController::class, 'automationEdit'])->whereNumber('id')->name('instagram.automations.edit');
        Route::post('/instagram/automations',             [InstagramController::class, 'automationStore'])->name('instagram.automations.store');
        Route::put('/instagram/automations/{id}',         [InstagramController::class, 'automationUpdate'])->whereNumber('id')->name('instagram.automations.update');
        Route::post('/instagram/automations/{id}/toggle', [InstagramController::class, 'automationToggle'])->whereNumber('id')->name('instagram.automations.toggle');
        Route::delete('/instagram/automations/{id}',      [InstagramController::class, 'automationDestroy'])->whereNumber('id')->name('instagram.automations.destroy');
        Route::post('/instagram/ice-breakers',            [InstagramController::class, 'iceBreakersSave'])->name('instagram.ice-breakers.save');
        Route::post('/instagram/persistent-menu',         [InstagramController::class, 'persistentMenuSave'])->name('instagram.persistent-menu.save');
        Route::post('/instagram/faq',                     [InstagramController::class, 'faqSave'])->name('instagram.faq.save');
    });

    // ---- Saved DM templates ----------------------------------------------
    Route::middleware($gate('templates'))->group(function () {
        Route::get('/instagram/templates',           [InstagramTemplateController::class, 'index'])->name('instagram.templates');
        Route::get('/instagram/templates/create',    [InstagramTemplateController::class, 'create'])->name('instagram.templates.create');
        Route::get('/instagram/templates/list',      [InstagramTemplateController::class, 'listJson'])->name('instagram.templates.list');
        Route::get('/instagram/templates/{id}/edit', [InstagramTemplateController::class, 'edit'])->whereNumber('id')->name('instagram.templates.edit');
        Route::post('/instagram/templates',          [InstagramTemplateController::class, 'store'])->name('instagram.templates.store');
        Route::put('/instagram/templates/{id}',      [InstagramTemplateController::class, 'update'])->whereNumber('id')->name('instagram.templates.update');
        Route::delete('/instagram/templates/{id}',   [InstagramTemplateController::class, 'destroy'])->whereNumber('id')->name('instagram.templates.destroy');
    });

    // ---- Discovery -------------------------------------------------------
    Route::middleware($gate('discovery'))->group(function () {
        Route::get('/instagram/discovery',          [InstagramController::class, 'discovery'])->name('instagram.discovery');
        Route::post('/instagram/discovery',         [InstagramController::class, 'discoverySearch'])->name('instagram.discovery.search');
        Route::post('/instagram/discovery/hashtag', [InstagramController::class, 'hashtagSearch'])->name('instagram.discovery.hashtag');
    });

    // ---- Reels Autopilot -------------------------------------------------
    Route::middleware($gate('reposter'))->group(function () {
        Route::get('/instagram/reposter',             [InstagramReposterController::class, 'index'])->name('instagram.reposter');
        Route::post('/instagram/reposter',            [InstagramReposterController::class, 'save'])->name('instagram.reposter.save');
        Route::post('/instagram/reposter/now',        [InstagramReposterController::class, 'repostNow'])->name('instagram.reposter.now');
        Route::get('/instagram/reposter/{id}',        [InstagramReposterController::class, 'show'])->whereNumber('id')->name('instagram.reposter.show');
        Route::put('/instagram/reposter/{id}',        [InstagramReposterController::class, 'update'])->whereNumber('id')->name('instagram.reposter.update');
        Route::post('/instagram/reposter/{id}/retry', [InstagramReposterController::class, 'retry'])->whereNumber('id')->name('instagram.reposter.retry');
        Route::delete('/instagram/reposter/{id}',     [InstagramReposterController::class, 'destroy'])->whereNumber('id')->name('instagram.reposter.destroy');
    });

    // ---- Ads (Meta / Instagram) — full CRUD + analytics ------------------
    // Literal segments declared BEFORE the /{id} catch-alls; {id} is number-
    // constrained so create/analytics/connect/etc. can never be swallowed.
    Route::middleware($gate('ads'))->group(function () {
        Route::get('/instagram/ads',             [AdsController::class, 'index'])->name('instagram.ads');
        Route::get('/instagram/ads/create',      [AdsController::class, 'create'])->name('instagram.ads.create');
        Route::post('/instagram/ads',            [AdsController::class, 'store'])->name('instagram.ads.store');
        Route::get('/instagram/ads/analytics',   [AdsController::class, 'analytics'])->name('instagram.ads.analytics');
        Route::get('/instagram/ads/connect',     [AdsController::class, 'connect'])->name('instagram.ads.connect');
        Route::post('/instagram/ads/keys',       [AdsController::class, 'saveKeys'])->name('instagram.ads.keys');
        Route::post('/instagram/ads/disconnect', [AdsController::class, 'disconnect'])->name('instagram.ads.disconnect');
        Route::post('/instagram/ads/boost',      [AdsController::class, 'boost'])->name('instagram.ads.boost');
        Route::post('/instagram/ads/ai-generate', [AdsController::class, 'apiAiGenerate'])->name('instagram.ads.ai-generate');

        Route::get('/instagram/ads/{id}',          [AdsController::class, 'show'])->whereNumber('id')->name('instagram.ads.show');
        Route::get('/instagram/ads/{id}/edit',     [AdsController::class, 'edit'])->whereNumber('id')->name('instagram.ads.edit');
        Route::put('/instagram/ads/{id}',          [AdsController::class, 'update'])->whereNumber('id')->name('instagram.ads.update');
        Route::delete('/instagram/ads/{id}',       [AdsController::class, 'destroy'])->whereNumber('id')->name('instagram.ads.destroy');
        Route::post('/instagram/ads/{id}/refresh', [AdsController::class, 'refresh'])->whereNumber('id')->name('instagram.ads.refresh');
        Route::post('/instagram/ads/{id}/retry',   [AdsController::class, 'retry'])->whereNumber('id')->name('instagram.ads.retry');
        Route::post('/instagram/ads/{id}/toggle',  [AdsController::class, 'toggleStatus'])->whereNumber('id')->name('instagram.ads.toggle');
        Route::get('/instagram/ads/{id}/estimate', [AdsController::class, 'estimate'])->whereNumber('id')->name('instagram.ads.estimate');
    });

    // ---- Commerce --------------------------------------------------------
    Route::middleware($gate('commerce'))->group(function () {
        Route::get('/instagram/commerce',               [InstagramCommerceController::class, 'index'])->name('instagram.commerce');
        Route::post('/instagram/commerce/catalog',      [InstagramCommerceController::class, 'saveCatalog'])->name('instagram.commerce.catalog');
        Route::post('/instagram/commerce/sync',         [InstagramCommerceController::class, 'sync'])->name('instagram.commerce.sync');
        Route::post('/instagram/commerce/shop-auto',    [InstagramCommerceController::class, 'saveShopAuto'])->name('instagram.commerce.shop-auto');
        Route::get('/instagram/commerce/products.json', [InstagramCommerceController::class, 'productsJson'])->name('instagram.commerce.products');
    });

    // ---- Leads -----------------------------------------------------------
    Route::middleware($gate('leads'))->group(function () {
        Route::get('/instagram/leads',                [InstagramLeadsController::class, 'index'])->name('instagram.leads');
        Route::post('/instagram/leads/{lead}/status', [InstagramLeadsController::class, 'setStatus'])->name('instagram.leads.status');
        Route::post('/instagram/inbox/capture-lead',  [InstagramController::class, 'captureLead'])->name('instagram.inbox.capture-lead');
    });

    // ---- Orders ----------------------------------------------------------
    Route::middleware($gate('orders'))->group(function () {
        Route::get('/instagram/orders',                 [InstagramOrdersController::class, 'index'])->name('instagram.orders');
        Route::post('/instagram/orders/{order}/status', [InstagramOrdersController::class, 'setStatus'])->name('instagram.orders.status');
    });

    /*
    |----------------------------------------------------------------------
    | Flow builder — STANDALONE ONLY
    |----------------------------------------------------------------------
    |
    | Inside WaDesk these are NOT registered: WaDesk already owns /flows, and
    | its builder handles Instagram flows end-to-end (verified — the Trigger
    | node's instagram:<id> sender parses to trigger_device_id + provider, and
    | the managed instagram_automations row is created on save). Registering a
    | second builder there would be two UIs writing the same table.
    |
    | Standalone has no WaDesk and therefore no /flows at all, so the extension
    | supplies its own — same table, same runtime contract, Instagram-only.
    */
    if (! \App\Services\Instagram\InstagramGate::isWaDesk()) {
        $C = \App\Http\Controllers\Instagram\InstagramFlowController::class;

        Route::middleware($gate('flows'))->group(function () use ($C) {
            // Pages.
            Route::get('/flows',                [$C, 'index'])->name('instagram.flows.index');
            Route::get('/flows/create',         [$C, 'builder'])->name('instagram.flows.create');
            Route::get('/flows/{id}/edit',      [$C, 'builder'])->whereNumber('id')->name('instagram.flows.edit');
            // "Use this template" — clone an admin FlowTemplate into a new flow.
            Route::post('/flows/templates/{id}/clone', [$C, 'cloneTemplate'])->whereNumber('id')->name('instagram.flows.clone-template');

            // API — URIs and payload shapes mirror core's FlowsController
            // exactly, because the SAME builder bundle calls both. Renaming
            // any of these to something tidier silently breaks Save.
            Route::post('/flows/api/save',      [$C, 'apiSave'])->name('instagram.flows.save');
            Route::post('/flows/api/publish',   [$C, 'apiPublish'])->name('instagram.flows.publish');
            Route::post('/flows/api/unpublish', [$C, 'apiUnpublish'])->name('instagram.flows.unpublish');
            Route::get('/flows/api/list',       [$C, 'apiIndex'])->name('instagram.flows.list');
            Route::get('/flows/api/picker',     [$C, 'apiPicker'])->name('instagram.flows.picker');
            // Cached builder bundles compute a wrong base under the subfolder and
            // hit e.g. /flows/create/flows/api/picker. Alias any such path so the
            // picker resolves regardless of the base-path bug in a stale bundle.
            Route::get('/flows/{stale}/flows/api/picker', [$C, 'apiPicker'])->where('stale', '.*');
            Route::post('/flows/{stale}/flows/api/upload-media', [$C, 'apiUploadMedia'])->where('stale', '.*');
            Route::get('/flows/api/default',    [$C, 'apiDefault'])->name('instagram.flows.default');
            Route::post('/flows/api/upload-media', [$C, 'apiUploadMedia'])->name('instagram.flows.upload');
            Route::post('/flows/api/test-webhook', [$C, 'apiTestWebhook'])->name('instagram.flows.test-webhook');

            // Backed by tables standalone does not ship — answered empty so the
            // inspector still renders. See the controller for why not 404.
            Route::get('/flows/api/ai-models',      [$C, 'apiAiModels'])->name('instagram.flows.ai-models');
            Route::get('/flows/api/ai-assistants',  [$C, 'apiAiAssistants'])->name('instagram.flows.ai-assistants');
            Route::post('/flows/api/ai-generate',   [$C, 'apiAiGenerate'])->name('instagram.flows.ai-generate');
            Route::get('/flows/api/commerce/stores', [$C, 'commerceStores'])->name('instagram.flows.stores');
            Route::get('/flows/api/commerce/stores/{storeId}/products', [$C, 'commerceProducts'])->name('instagram.flows.products');

            // Contact attributes (WaDesk-style): management page + the builder's
            // Ask-node "Save answer to" picker. Root-absolute picker path + a
            // stale-bundle alias, exactly like /flows/api/picker.
            $A = \App\Http\Controllers\Instagram\InstagramAttributeController::class;
            Route::get('/instagram/attributes',         [$A, 'index'])->name('instagram.attributes.index');
            Route::post('/instagram/attributes',        [$A, 'store'])->name('instagram.attributes.store');
            Route::put('/instagram/attributes/{id}',    [$A, 'update'])->whereNumber('id')->name('instagram.attributes.update');
            Route::delete('/instagram/attributes/{id}', [$A, 'destroy'])->whereNumber('id')->name('instagram.attributes.destroy');
            Route::get('/flows/api/attributes',         [$A, 'apiList'])->name('instagram.flows.attributes');
            Route::get('/flows/{stale}/flows/api/attributes', [$A, 'apiList'])->where('stale', '.*');

            // Contacts CRUD — list people + set their attribute VALUES by hand.
            $CT = \App\Http\Controllers\Instagram\InstagramContactController::class;
            Route::get('/instagram/contacts',            [$CT, 'index'])->name('instagram.contacts.index');
            Route::get('/instagram/contacts/{id}/edit',  [$CT, 'edit'])->whereNumber('id')->name('instagram.contacts.edit');
            Route::put('/instagram/contacts/{id}',       [$CT, 'update'])->whereNumber('id')->name('instagram.contacts.update');

            // {id} LAST — it is a catch-all and would otherwise swallow
            // /flows/api/save, /list, /picker and every other named endpoint.
            Route::get('/flows/api/{id}',    [$C, 'apiShow'])->whereNumber('id')->name('instagram.flows.show');
            Route::delete('/flows/api/{id}', [$C, 'apiDestroy'])->whereNumber('id')->name('instagram.flows.destroy');
        });
    }
});

<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Instagram\Models\InstagramFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Flow builder API for the STANDALONE Instagram product.
 *
 * Never routed inside WaDesk — see the guard in routes/instagram.php. WaDesk's
 * FlowsController owns these URIs there, and two controllers answering the same
 * endpoint is how the trigger columns drift apart.
 *
 * ── Contract ────────────────────────────────────────────────────────────────
 * The URIs and response shapes here are NOT a design choice. They mirror core's
 * FlowsController exactly, because the SAME builder bundle
 * (user-flows-builder.js) talks to both. It posts to /flows/api/save, expects
 * {success: bool, ...}, and reads flow_data as flowNodes/flowEdges.
 *
 * An earlier version of this file exposed tidy REST routes (POST /flows,
 * POST /flows/{id}/publish). The builder never calls those, so Save 404'd
 * silently. Matching the caller beats matching a convention.
 *
 * ── Deliberately empty responses ────────────────────────────────────────────
 * Endpoints backed by WaDesk-only tables (AI keys, AI assistants, commerce
 * stores) return valid EMPTY payloads rather than 404. The builder renders an
 * empty picker and the operator sees "none available"; a 404 makes the whole
 * inspector panel fail to load. Degrading beats breaking.
 */
class InstagramFlowController extends Controller
{
    // ───────────────────────────── pages ─────────────────────────────

    public function index()
    {
        // Starter templates an admin published at /admin/flow-templates. Guarded
        // so a fresh install with no templates table still renders.
        $templates = collect();
        try {
            $templates = \App\Models\FlowTemplate::query()
                ->where('is_active', true)
                ->whereIn('channel', ['instagram', 'all'])
                ->orderBy('sort')->orderBy('name')
                ->get();
        } catch (\Throwable $e) {
        }

        return view('instagram.flows.index', [
            'flows'     => $this->scope()->orderByDesc('updated_at')->paginate(20),
            'templates' => $templates,
        ]);
    }

    /**
     * "Use this template" — clone an admin-published FlowTemplate into a new
     * editable flow for this user, then open the builder on it. Mirrors
     * WaDesk's FlowsController::cloneTemplate. This is the user-side consumer
     * the admin /admin/flow-templates page previously had no counterpart for.
     */
    public function cloneTemplate(Request $request, int $id)
    {
        $tpl = \App\Models\FlowTemplate::where('is_active', true)->findOrFail($id);

        // Same per-plan flow cap new saves hit.
        $used = (int) $this->scope()->count();
        if (\App\Services\Instagram\InstagramGate::exceeded('instagram_flows', $used)) {
            return back()->withErrors(['template' => __('Your current plan allows :n flow(s). Upgrade to build more.', [
                'n' => \App\Services\Instagram\InstagramGate::limit('instagram_flows'),
            ])]);
        }

        $flowData = json_decode((string) $tpl->flow_data, true);
        if (! is_array($flowData) || empty($flowData['flowNodes'])) {
            $flowData = ['flowNodes' => [], 'flowEdges' => []];
        }

        $flow = $this->scope()->create([
            'flow_name'    => $tpl->name,
            'category'     => $tpl->category,
            'flow_data'    => json_encode($flowData),
            'user_id'      => auth()->id(),
            'workspace_id' => $this->workspaceId(),
            'is_active'    => true,
            'is_published' => false,
        ] + $this->trigger($flowData));

        return redirect()->route('instagram.flows.edit', $flow->id)
            ->with('status', __('Template imported — customise it, then publish.'));
    }

    public function builder(Request $request, ?int $id = null)
    {
        $flow = $id ? $this->scope()->findOrFail($id) : null;

        return view('instagram.flows.builder', [
            'flowId'      => $flow?->id,
            'flowName'    => $flow?->flow_name ?? '',
            'category'    => $flow?->category ?? '',
            'isPublished' => (bool) ($flow?->is_published ?? false),
            'flowJson'    => $this->decode($flow?->flow_data),
        ]);
    }

    // ───────────────────────────── api ───────────────────────────────

    /** POST /flows/api/save — create or update. Mirrors FlowsController@apiSave. */
    public function apiSave(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'flow_name' => 'required|string|max:255',
            'flow_data' => 'required|array',
            'flow_id'   => 'nullable|integer',
            'category'  => 'nullable|string|max:64',
            // Accepted for wire compatibility with the shared builder, then
            // ignored — the model forces 'instagram' on save regardless.
            'flow_type' => 'nullable|in:chat,call,instagram',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $flowData = $request->input('flow_data');
        $trigger  = $this->trigger($flowData);

        $payload = [
            'flow_name' => $request->string('flow_name')->toString(),
            'category'  => $request->string('category')->toString() ?: null,
            'flow_data' => json_encode($flowData),
        ] + $trigger;

        if ($request->filled('flow_id')) {
            $flow = $this->scope()->find($request->integer('flow_id'));
            if (! $flow) {
                return response()->json(['success' => false, 'message' => 'Flow not found'], 404);
            }
            $flow->update($payload);
        } else {
            // Plan cap on the number of flows (new flows only; editing is free).
            // No-op unless enforcement is on — the gate returns unlimited otherwise.
            $used = (int) $this->scope()->count();
            if (\App\Services\Instagram\InstagramGate::exceeded('instagram_flows', $used)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your current plan allows ' . \App\Services\Instagram\InstagramGate::limit('instagram_flows') . ' flow(s). Upgrade your plan to build more.',
                ], 403);
            }
            $flow = $this->scope()->create($payload + [
                'user_id'      => auth()->id(),
                'workspace_id' => $this->workspaceId(),
                'is_active'    => true,
                'is_published' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Flow saved',
            'data'    => ['id' => $flow->id, 'flow_id' => $flow->id],
        ]);
    }

    public function apiPublish(Request $request)
    {
        return $this->setPublished($request, true);
    }

    public function apiUnpublish(Request $request)
    {
        return $this->setPublished($request, false);
    }

    private function setPublished(Request $request, bool $published)
    {
        $validator = Validator::make($request->all(), ['flow_id' => 'required|integer']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $flow = $this->scope()->find($request->integer('flow_id'));
        if (! $flow) {
            return response()->json(['success' => false, 'message' => 'Flow not found'], 404);
        }

        $flow->update([
            'is_published' => $published,
            'published_at' => $published ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $published ? 'Flow published' : 'Flow unpublished',
            'data'    => $flow,
        ]);
    }

    /** GET /flows/api/list — used by the "Run another flow" node. */
    public function apiIndex()
    {
        return response()->json([
            'success' => true,
            'data'    => $this->scope()->orderByDesc('updated_at')
                ->get(['id', 'flow_name', 'is_published', 'flow_type']),
        ]);
    }

    public function apiShow(int $id)
    {
        $flow = $this->scope()->find($id);
        if (! $flow) {
            return response()->json(['success' => false, 'message' => 'Flow not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $flow->id,
                'flow_name'    => $flow->flow_name,
                'category'     => $flow->category,
                'flow_type'    => $flow->flow_type,
                'is_published' => (bool) $flow->is_published,
                'flow_data'    => $this->decode($flow->flow_data),
            ],
        ]);
    }

    public function apiDestroy(int $id)
    {
        $flow = $this->scope()->find($id);
        if (! $flow) {
            return response()->json(['success' => false, 'message' => 'Flow not found'], 404);
        }
        $flow->delete();

        return response()->json(['success' => true, 'message' => 'Flow deleted']);
    }

    public function apiDefault()
    {
        $flow = $this->scope()->where('is_published', true)->orderByDesc('updated_at')->first();
        if (! $flow) {
            return response()->json(['success' => false, 'message' => 'No default flow found'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => ['id' => $flow->id, 'flow_name' => $flow->flow_name],
        ]);
    }

    /**
     * GET /flows/api/picker — what the Trigger node offers as a sender.
     *
     * Instagram accounts only. tags / groups / devices are returned as empty
     * arrays rather than omitted, because the builder indexes into them
     * unconditionally and a missing key throws in the inspector.
     */
    public function apiPicker()
    {
        $accounts = [];
        if (Schema::hasTable('instagram_accounts')) {
            // STRICT per-user isolation: a user only ever sees the CONNECTED
            // accounts they own. Scope by user_id ONLY — NOT workspace_id,
            // because in IgDesk every row has workspace_id=0, so an
            // "OR workspace_id" would leak every other user's accounts. Connect
            // always stamps user_id => Auth::id(), so this is the right key.
            $accounts = \App\Models\InstagramAccount::query()
                ->where('user_id', (int) auth()->id())
                ->where('status', 'connected')
                ->orderBy('username')
                ->get(['id', 'username', 'ig_user_id', 'status'])
                ->map(fn ($a) => [
                    'key'      => 'instagram:' . $a->id,
                    'id'       => (int) $a->id,
                    'label'    => '@' . ($a->username ?: $a->ig_user_id),
                    'username' => (string) $a->username,
                    'status'   => (string) $a->status,
                ])->values();
        }

        return response()->json([
            'ok'        => true,
            'success'   => true,
            'tags'      => [],
            'groups'    => [],
            'devices'   => [],
            'instagram' => $accounts,
        ]);
    }

    /**
     * POST /flows/api/upload-media — media for Send-media nodes.
     *
     * Stored on the default public disk. Standalone has no cloud-storage
     * admin screen, so there is nothing to resolve a custom disk from.
     */
    public function apiUploadMedia(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:25600',   // 25 MB
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $path = $request->file('file')->store('flows', 'public');

        return response()->json([
            'success' => true,
            'url'     => Storage::disk('public')->url($path),
            'path'    => $path,
        ]);
    }

    /**
     * AI + commerce endpoints.
     *
     * Backed by tables standalone does not ship (`admin_ai_keys`,
     * `ai_assistants`, the shop integrations). Answered as empty rather than
     * 404 so the inspector still renders — the operator sees an empty picker
     * and a clear "none configured", instead of a panel that fails to load.
     *
     * If standalone later sells AI nodes, these become real: add the tables and
     * fill these three in. Nothing else has to change.
     */
    public function apiAiModels()
    {
        return response()->json(['success' => true, 'data' => [], 'models' => []]);
    }

    public function apiAiAssistants()
    {
        return response()->json(['success' => true, 'data' => [], 'assistants' => []]);
    }

    public function commerceStores()
    {
        return response()->json(['success' => true, 'data' => [], 'stores' => []]);
    }

    public function commerceProducts()
    {
        return response()->json(['success' => true, 'data' => [], 'products' => []]);
    }

    public function apiAiGenerate()
    {
        // Explicit, actionable refusal — not a silent empty string, which would
        // look like the AI "worked" and returned nothing.
        return response()->json([
            'success' => false,
            'message' => 'AI generation is not available in the standalone Instagram product.',
        ], 501);
    }

    /**
     * POST /flows/api/test-webhook — fire the Webhook node's URL once.
     *
     * SSRF is the whole risk here: the URL is operator-supplied and the server
     * makes the request. HTTPS + public-host only, mirroring core's guard.
     */
    public function apiTestWebhook(Request $request)
    {
        $url = trim((string) $request->input('url', ''));

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with(strtolower($url), 'https://')) {
            return response()->json(['success' => false, 'message' => 'URL must be a valid https:// address.'], 422);
        }

        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $ip   = gethostbyname($host);
        if ($ip !== $host && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return response()->json(['success' => false, 'message' => 'That host resolves to a private address.'], 422);
        }

        try {
            $res = \Illuminate\Support\Facades\Http::timeout(8)
                ->withHeaders((array) $request->input('headers', []))
                ->send(strtoupper((string) $request->input('method', 'POST')), $url, [
                    'body' => (string) $request->input('body', ''),
                ]);

            return response()->json([
                'success' => true,
                'status'  => $res->status(),
                'body'    => mb_substr($res->body(), 0, 2000),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }
    }

    // ──────────────────────────── helpers ────────────────────────────

    private function workspaceId(): ?int
    {
        return auth()->user()->current_workspace_id ?? null;
    }

    /**
     * Scoped once, here, so no action can forget the tenant filter.
     *
     * STRICT per-user isolation: standalone has no workspaces, so every flow
     * row carries workspace_id = NULL/0 and the real owner key is user_id.
     * Filtering on workspace_id here would be a no-op (when() skips a null) and
     * leak every other user's flows — scope by user_id, which apiSave always
     * stamps to auth()->id().
     */
    private function scope()
    {
        return InstagramFlow::query()->where('user_id', (int) auth()->id());
    }

    /**
     * Trigger columns out of the builder payload.
     *
     * Reads `flowNodes` — NOT `nodes`. The builder has always serialised as
     * flowNodes/flowEdges, and reading the wrong key is exactly the bug that
     * once left Instagram flows saved, published, and silently never firing.
     */
    private function trigger(array $json): array
    {
        $node = null;
        foreach (($json['flowNodes'] ?? []) as $n) {
            if (($n['type'] ?? null) === 'trigger') { $node = $n; break; }
        }
        $d = is_array($node['data'] ?? null) ? $node['data'] : [];

        $raw  = trim((string) ($d['deviceId'] ?? ''));
        $acct = null;
        if ($raw !== '') {
            $acct = str_contains($raw, ':')
                ? ((int) explode(':', $raw, 2)[1] ?: null)
                : ((int) $raw ?: null);
        }

        $mode = strtolower(trim((string) ($d['keywordMode'] ?? '')));

        return [
            'trigger_kind'      => (string) ($d['kind'] ?? 'keyword'),
            'trigger_keywords'  => $mode === 'any'
                ? 'any'
                : (trim((string) ($d['keywords'] ?? '')) ?: null),
            'trigger_device_id' => $acct,
        ];
    }

    private function decode($raw): array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? $decoded : ['flowNodes' => [], 'flowEdges' => []];
    }
}

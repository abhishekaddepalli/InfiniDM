<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Models\InstagramAccount;
use App\Models\MetaCampaign;
use App\Services\Instagram\InstagramAdsClient;
use App\Services\Instagram\InstagramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Meta Ads (Instagram) controller — IgDesk standalone edition.
 *
 * Full CRUD + analytics for Instagram ads, ported from WaDesk's
 * MetaAdsController and adapted to a workspace-less app:
 *   - everything scoped by user_id (the signed-in operator),
 *   - the Marketing API client is built from an InstagramAccount, not a
 *     WaProviderConfig,
 *   - the destination is Instagram (Click-to-Instagram-DM / traffic / boost),
 *     never WhatsApp.
 *
 * All Graph work is best-effort: a failed push saves meta_last_error and keeps
 * the row (status=FAILED) so /retry can replay it.
 */
class AdsController extends Controller
{
    private function userId(): int
    {
        return (int) (Auth::id() ?? 0);
    }

    /** All Instagram accounts the signed-in operator owns. */
    private function accounts()
    {
        return InstagramAccount::where('user_id', $this->userId())->orderBy('id')->get();
    }

    /** A user-owned account by id (or the first connected + ad-ready one). */
    private function resolveAccount(?int $id = null): ?InstagramAccount
    {
        $q = InstagramAccount::where('user_id', $this->userId());
        if ($id) return (clone $q)->where('id', $id)->first();

        // Prefer a connected account that already carries an ad account.
        return (clone $q)->whereNotNull('ad_account_id')->where('ad_account_id', '!=', '')
                ->orderByRaw("CASE WHEN status = 'connected' THEN 0 ELSE 1 END")->orderByDesc('id')->first()
            ?? (clone $q)->where('status', 'connected')->orderByDesc('id')->first()
            ?? (clone $q)->orderByDesc('id')->first();
    }

    /** Marketing API client for a campaign (its own account, else the primary). */
    private function graphFor(MetaCampaign $c): InstagramAdsClient
    {
        $acc = $c->instagram_account_id
            ? InstagramAccount::where('user_id', $this->userId())->where('id', $c->instagram_account_id)->first()
            : null;
        return new InstagramAdsClient($acc ?? $this->resolveAccount());
    }

    /** True when at least one of the operator's accounts has an ad account wired. */
    private function hasAnyAdAccount($accounts): bool
    {
        return $accounts->contains(fn ($a) => trim((string) $a->ad_account_id) !== '');
    }

    // =================================================================
    // Pages
    // =================================================================

    public function index(Request $request)
    {
        $userId    = $this->userId();
        $status    = $request->string('status')->toString()    ?: 'all';
        $objective = $request->string('objective')->toString() ?: 'all';
        $search    = $request->string('q')->toString();

        $campaigns = MetaCampaign::query()->forUser($userId)
            ->withStatus($status)->withObjective($objective)
            ->orderByDesc('created_at')->get();
        $campaigns = MetaCampaign::filterByName($campaigns, $search);

        $accounts   = $this->accounts();
        $connected  = $accounts->where('status', 'connected');
        $hasAd      = $this->hasAnyAdAccount($accounts);

        // Recent posts for the "boost an existing post" strip.
        $boostAcc   = $connected->first(fn ($a) => trim((string) $a->ad_account_id) !== '') ?: $connected->first();
        $boostMedia = [];
        if ($boostAcc) {
            try {
                $boostMedia = Cache::remember("ig_media_{$boostAcc->id}", 600, fn () => (new InstagramService($boostAcc))->getMedia(9));
            } catch (\Throwable $e) {
                $boostMedia = [];
            }
        }

        return view('instagram.ads.index', [
            'campaigns'    => $campaigns,
            'accounts'     => $accounts,
            'connected'    => $connected,
            'hasAdAccount' => $hasAd,
            'boostAcc'     => $boostAcc,
            'boostMedia'   => $boostMedia,
            'statusCounts' => $this->statusCounts($userId),
            'totals'       => $this->totals($userId),
            'currentStatus'    => $status,
            'currentObjective' => $objective,
            'currentSearch'    => $search,
        ]);
    }

    public function create(Request $request)
    {
        $accounts = $this->accounts();
        if (!$this->hasAnyAdAccount($accounts)) {
            return redirect()->route('instagram.ads.connect')
                ->with('status', __('Connect your ad account to create a campaign.'));
        }
        // Only accounts that can actually run ads (ad account wired).
        $adAccounts = $accounts->filter(fn ($a) => trim((string) $a->ad_account_id) !== '')->values();

        return view('instagram.ads.create', [
            'accounts'  => $adAccounts,
            'adType'    => in_array($request->query('ad_type'), MetaCampaign::AD_TYPES, true)
                            ? $request->query('ad_type') : MetaCampaign::AD_TYPE_IG_DIRECT,
        ]);
    }

    public function edit(int $id)
    {
        $campaign  = MetaCampaign::query()->forUser($this->userId())->findOrFail($id);
        $accounts  = $this->accounts()->filter(fn ($a) => trim((string) $a->ad_account_id) !== '')->values();
        return view('instagram.ads.edit', ['campaign' => $campaign, 'accounts' => $accounts]);
    }

    public function show(int $id)
    {
        $campaign = MetaCampaign::query()->forUser($this->userId())->findOrFail($id);
        $graph    = $this->graphFor($campaign);
        return view('instagram.ads.show', [
            'campaign' => $campaign,
            'ready'    => $graph->isConfigured(),
        ]);
    }

    public function analytics(Request $request)
    {
        $userId = $this->userId();
        $id     = (int) $request->integer('id');

        $picker = MetaCampaign::query()->forUser($userId)
            ->orderByDesc('status')->orderByDesc('id')
            ->get(['id', 'name', 'status', 'optimization_goal']);

        if ($id > 0) {
            $campaign = MetaCampaign::query()->forUser($userId)->find($id);
            if ($campaign && $campaign->facebook_id) {
                $stale = !$campaign->meta_synced_at || $campaign->meta_synced_at->lt(now()->subMinutes(10));
                if ($stale) {
                    try { if ($this->syncFromMeta($campaign)) $campaign->refresh(); }
                    catch (\Throwable $e) { Log::warning('[IG-ADS] analytics auto-sync failed', ['id' => $id, 'e' => $e->getMessage()]); }
                }
            }
            return view('instagram.ads.analytics', [
                'mode' => 'campaign', 'campaign' => $campaign, 'picker' => $picker, 'aggregate' => null,
            ]);
        }

        return view('instagram.ads.analytics', [
            'mode' => 'global', 'campaign' => null, 'picker' => $picker,
            'aggregate' => $this->aggregateInsights($userId),
        ]);
    }

    // =================================================================
    // Connect flow — wire an ad account onto an Instagram account
    // =================================================================

    public function connect(Request $request)
    {
        $accounts = $this->accounts();
        $connected = $accounts->where('status', 'connected')->values();

        // Discover ad accounts for the selected (or first connected) account.
        $selId = (int) $request->integer('account');
        $account = ($selId ? $connected->firstWhere('id', $selId) : null) ?: $connected->first();

        $discovered = ['ad_accounts' => [], 'pages' => [], 'error' => null];
        if ($account) {
            try {
                $res = (new InstagramAdsClient($account))->discoverAssets();
                $discovered = [
                    'ad_accounts' => $res['ad_accounts'] ?? [],
                    'pages'       => $res['pages'] ?? [],
                    'error'       => ($res['ok'] ?? false) ? null : ($res['error'] ?? null),
                ];
            } catch (\Throwable $e) {
                $discovered['error'] = $e->getMessage();
            }
        }

        return view('instagram.ads.connect', [
            'accounts'   => $connected,
            'account'    => $account,
            'discovered' => $discovered,
        ]);
    }

    public function saveKeys(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'instagram_account_id' => ['required', 'integer'],
            'ad_account_id'        => ['nullable', 'string', 'max:64'],  // radio pick
            'ad_account_id_manual' => ['nullable', 'string', 'max:64'],  // manual entry
        ]);

        $account = InstagramAccount::where('user_id', $this->userId())
            ->where('id', (int) $data['instagram_account_id'])->first();
        if (!$account) {
            return back()->withErrors(['instagram_account_id' => __('Account not found.')]);
        }

        // A radio pick wins; fall back to the manual field.
        $raw  = trim((string) ($data['ad_account_id'] ?? '')) ?: trim((string) ($data['ad_account_id_manual'] ?? ''));
        $adId = preg_replace('/^act_/', '', $raw);

        // Auto-adopt when the operator left it blank and the token sees exactly one.
        if ($adId === '') {
            try {
                $assets = (new InstagramAdsClient($account))->discoverAssets();
                $accts  = $assets['ad_accounts'] ?? [];
                if (count($accts) === 1) $adId = preg_replace('/^act_/', '', (string) $accts[0]['id']);
            } catch (\Throwable $e) {
                // fall through — nothing to adopt
            }
        }

        if ($adId === '') {
            return back()->withErrors(['ad_account_id' => __('Choose or paste an ad account id.')]);
        }

        $account->ad_account_id = $adId;
        $account->save();

        return redirect()->route('instagram.ads.create')
            ->with('status', __('Ad account connected.'));
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $data = $request->validate(['instagram_account_id' => ['required', 'integer']]);
        $account = InstagramAccount::where('user_id', $this->userId())
            ->where('id', (int) $data['instagram_account_id'])->first();
        if ($account) {
            $account->ad_account_id = null;
            $account->save();
        }
        return redirect()->route('instagram.ads.connect')->with('status', __('Ad account removed.'));
    }

    // =================================================================
    // Mutations
    // =================================================================

    public function store(Request $request): RedirectResponse
    {
        $data    = $this->validateCampaign($request);
        $account = InstagramAccount::where('user_id', $this->userId())
            ->where('id', (int) $data['instagram_account_id'])->first();
        if (!$account || trim((string) $account->ad_account_id) === '') {
            return back()->withInput()->withErrors(['instagram_account_id' => __('Pick an account with a connected ad account.')]);
        }

        $imagePath = null;
        if ($request->hasFile('creative_image_file')) {
            $imagePath = $request->file('creative_image_file')->store('meta-campaigns', 'public');
        }

        $columns = $this->mapValidatedToColumns($data);
        $chosenStatus = strtoupper((string) ($columns['status'] ?? 'PAUSED'));
        $columns['status'] = $chosenStatus === 'DRAFT' ? 'DRAFT' : 'PAUSED';

        $campaign = MetaCampaign::create(array_merge($columns, [
            'user_id'              => $this->userId(),
            'instagram_account_id' => $account->id,
            'creative_image'       => $imagePath,
            'insights'             => [],
        ]));

        $synced = false;
        if ($chosenStatus !== 'DRAFT') {
            $this->syncToMeta($campaign);
            $synced = (bool) $campaign->facebook_id;
            if ($synced && $chosenStatus === 'ACTIVE') {
                if ($this->graphFor($campaign)->setStatusCascade($campaign, 'ACTIVE')) {
                    $campaign->status = 'ACTIVE';
                    $campaign->save();
                }
            }
        }

        $msg = match (true) {
            $chosenStatus === 'DRAFT'                   => __('Campaign saved as draft.'),
            $synced && $campaign->status === 'ACTIVE'   => __('Campaign launched live on Meta.'),
            $synced                                     => __('Campaign created on Meta (paused — flip it live when ready).'),
            default                                     => __('Campaign saved. ') . mb_substr((string) $campaign->meta_last_error, 0, 200),
        };
        return redirect()->route('instagram.ads.show', $campaign->id)->with('status', $msg);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $campaign = MetaCampaign::query()->forUser($this->userId())->findOrFail($id);
        $data     = $this->validateCampaign($request, updating: true);
        if (!array_key_exists('ad_type', $data) || ($data['ad_type'] ?? null) === null) {
            $data['ad_type'] = $campaign->ad_type ?: MetaCampaign::AD_TYPE_IG_DIRECT;
        }

        if ($request->hasFile('creative_image_file')) {
            if ($campaign->creative_image) {
                Storage::disk('public')->delete($campaign->creative_image);
            }
            $campaign->creative_image = $request->file('creative_image_file')->store('meta-campaigns', 'public');
        }

        $campaign->fill($this->mapValidatedToColumns($data))->save();

        // Rebuild the Meta tree when the campaign already exists there.
        if ($campaign->facebook_id && $campaign->status !== 'DRAFT') {
            $graph = $this->graphFor($campaign);
            if ($graph->isConfigured()) {
                $graph->deleteCascade($campaign);
                $campaign->update([
                    'facebook_id' => null, 'meta_adset_id' => null,
                    'meta_creative_id' => null, 'meta_ad_id' => null, 'meta_image_hash' => null,
                ]);
                $this->syncToMeta($campaign);
            }
        }

        $msg = __('Campaign updated.')
            . ($campaign->wasChanged('meta_last_error') && $campaign->meta_last_error
                ? ' ' . mb_substr((string) $campaign->meta_last_error, 0, 200) : '');
        return redirect()->route('instagram.ads.show', $campaign->id)->with('status', $msg);
    }

    public function destroy(Request $request, int $id)
    {
        $c = MetaCampaign::query()->forUser($this->userId())->findOrFail($id);
        if ($c->facebook_id || $c->meta_ad_id) {
            $graph = $this->graphFor($c);
            if ($graph->isConfigured()) $graph->deleteCascade($c);
        }
        if ($c->creative_image) {
            Storage::disk('public')->delete($c->creative_image);
        }
        $c->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'data' => ['id' => $id], 'meta' => $this->statusCounts($this->userId())]);
        }
        return redirect()->route('instagram.ads')->with('status', __('Campaign deleted.'));
    }

    public function toggleStatus(Request $request, int $id)
    {
        $c = MetaCampaign::query()->forUser($this->userId())->findOrFail($id);
        $next  = $c->status === 'ACTIVE' ? 'PAUSED' : 'ACTIVE';
        $graph = $this->graphFor($c);

        // Activating an ad that was only saved locally → publish the tree first.
        if ($next === 'ACTIVE' && !$c->facebook_id && $c->status !== 'DRAFT' && $graph->isConfigured()) {
            $this->syncToMeta($c);
            $c->refresh();
        }
        if (($c->facebook_id || $c->meta_ad_id) && $graph->isConfigured()) {
            $graph->setStatusCascade($c, $next);
        }
        $c->status = $next;
        $c->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'data' => [
                'id' => $c->id, 'status' => $c->status,
                'facebook_id' => $c->facebook_id, 'meta_last_error' => $c->meta_last_error,
            ], 'meta' => $this->statusCounts($this->userId())]);
        }
        return back()->with('status', __('Campaign set to ') . strtolower($c->status) . '.');
    }

    public function refresh(Request $request, int $id)
    {
        $c = MetaCampaign::query()->forUser($this->userId())->findOrFail($id);
        if ($c->facebook_id) $this->syncFromMeta($c);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'meta_status' => $c->status,
                'insights'    => $c->insights ?? [],
                'last_synced' => optional($c->meta_synced_at)->diffForHumans(),
                'last_error'  => $c->meta_last_error,
                'meta_ids'    => ['campaign' => $c->facebook_id, 'adset' => $c->meta_adset_id, 'creative' => $c->meta_creative_id, 'ad' => $c->meta_ad_id],
            ]);
        }
        return back()->with('status', __('Insights refreshed.'));
    }

    public function retry(Request $request, int $id)
    {
        $c = MetaCampaign::query()->forUser($this->userId())->findOrFail($id);
        if ($c->facebook_id || $c->meta_ad_id) {
            $graph = $this->graphFor($c);
            if ($graph->isConfigured()) $graph->deleteCascade($c);
            $c->update([
                'facebook_id' => null, 'meta_adset_id' => null,
                'meta_creative_id' => null, 'meta_ad_id' => null, 'meta_image_hash' => null,
            ]);
        }
        $this->syncToMeta($c);
        $c->refresh();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => (bool) $c->facebook_id,
                'status' => $c->status,
                'last_error' => $c->meta_last_error,
                'meta_ids' => ['campaign' => $c->facebook_id, 'adset' => $c->meta_adset_id, 'creative' => $c->meta_creative_id, 'ad' => $c->meta_ad_id],
            ]);
        }
        return back()->with($c->facebook_id ? 'status' : 'error',
            $c->facebook_id ? __('Campaign re-published to Meta.') : (__('Retry failed: ') . mb_substr((string) $c->meta_last_error, 0, 200)));
    }

    public function estimate(int $id): JsonResponse
    {
        $c = MetaCampaign::query()->forUser($this->userId())->findOrFail($id);
        try {
            return response()->json($this->graphFor($c)->deliveryEstimate($c));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Boost an existing IG post — builds a paused engagement ad + records it locally. */
    public function boost(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'instagram_account_id' => 'required|integer',
            'media_id'             => 'required|string|max:64',
            'daily_budget'         => 'required|numeric|min:1',
            'days'                 => 'required|integer|min:1|max:30',
            'caption'              => 'nullable|string|max:255',
        ]);
        $account = InstagramAccount::where('user_id', $this->userId())->where('id', (int) $d['instagram_account_id'])->first();
        if (!$account) return back()->withErrors(['instagram' => __('Account not found.')]);

        $graph = new InstagramAdsClient($account);
        if (!$graph->isConfigured()) {
            return redirect()->route('instagram.ads.connect')->withErrors(['instagram' => __('Connect an ad account first.')]);
        }
        if ($account->ig_user_id) $graph->withInstagramUserId((string) $account->ig_user_id);

        $res = $graph->boostInstagramMedia($d['media_id'], (float) $d['daily_budget'], (int) $d['days']);
        if (empty($res['ok'])) {
            return back()->withErrors(['instagram' => __('Boost failed: ') . ($res['error'] ?? 'unknown')]);
        }

        // Record the boost locally so it appears in the campaign list.
        MetaCampaign::create([
            'user_id'              => $this->userId(),
            'instagram_account_id' => $account->id,
            'name'                 => 'IG Boost · ' . mb_substr((string) ($d['caption'] ?? $d['media_id']), 0, 40),
            'ad_type'              => MetaCampaign::AD_TYPE_BOOST,
            'objective'            => 'OUTCOME_ENGAGEMENT',
            'optimization_goal'    => 'POST_ENGAGEMENT',
            'status'               => 'PAUSED',
            'daily_budget'         => (float) $d['daily_budget'],
            'publisher_platforms'  => ['instagram'],
            'facebook_id'          => $res['campaign_id'] ?? null,
            'meta_adset_id'        => $res['adset_id'] ?? null,
            'meta_creative_id'     => $res['creative_id'] ?? null,
            'meta_ad_id'           => $res['ad_id'] ?? null,
            'meta_synced_at'       => now(),
            'insights'             => [],
        ]);

        return back()->with('status', __('Boost created (paused) — review + activate it. Ad ID ') . ($res['ad_id'] ?? '') . '.');
    }

    // =================================================================
    // Meta sync (push + pull)
    // =================================================================

    private function syncToMeta(MetaCampaign $c): void
    {
        $graph = $this->graphFor($c);
        if (!$graph->isConfigured()) {
            $c->update(['meta_last_error' => 'Meta Ads is not configured. Connect an ad account on the Ads page.']);
            return;
        }

        // Instagram identity — a real IG account or a Page-Backed Instagram Account.
        if ($c->wantsInstagram() || $c->adType() === MetaCampaign::AD_TYPE_IG_DIRECT) {
            $igId = $graph->instagramUserId() ?: $graph->ensurePbia();
            if ($igId) {
                $graph->withInstagramUserId($igId);
                if ((string) $c->instagram_user_id !== (string) $igId) {
                    $c->update(['instagram_user_id' => $igId]);
                }
            }
            if (!$graph->isInstagramReady()) {
                $c->update(['status' => 'FAILED', 'meta_last_error' => 'Instagram ads need a connected Facebook Page + an Instagram professional account (or a Page-backed IG account).', 'meta_synced_at' => now()]);
                return;
            }
        } elseif ($graph->pageId() === null) {
            $c->update(['status' => 'FAILED', 'meta_last_error' => 'This ad needs a connected Facebook Page on the Instagram account.', 'meta_synced_at' => now()]);
            return;
        }

        if (empty($c->creative_image)) {
            $c->update(['status' => 'FAILED', 'meta_last_error' => 'Ad creative requires an uploaded image (1080×1080+).', 'meta_synced_at' => now()]);
            return;
        }

        $imagePath  = storage_path('app/public/' . ltrim($c->creative_image, '/'));
        $campaignId = $adSetId = $creativeId = $adId = $imageHash = null;

        try {
            $imageHash  = $graph->uploadImage($imagePath);
            $campaignId = $graph->createCampaign($c);
            $adSetId    = $graph->createAdSet($c, $campaignId);
            $creativeId = $graph->createAdCreative($c, $imageHash);
            $adId       = $graph->createAd($c, $adSetId, $creativeId);

            $c->update([
                'facebook_id'      => $campaignId,
                'meta_adset_id'    => $adSetId,
                'meta_creative_id' => $creativeId,
                'meta_ad_id'       => $adId,
                'meta_image_hash'  => $imageHash,
                'meta_synced_at'   => now(),
                'meta_last_error'  => null,
                'status'           => $c->status === 'FAILED' ? 'PAUSED' : $c->status,
            ]);
            Log::info('[IG-ADS] sync ok', ['id' => $c->id, 'fb' => $campaignId, 'ad' => $adId]);
        } catch (\Throwable $e) {
            $errMsg = $e->getMessage();
            Log::warning('[IG-ADS] sync failed — rolling back', ['id' => $c->id, 'error' => $errMsg, 'meta_last' => $graph->lastError]);

            $c->forceFill([
                'meta_ad_id'       => $adId       ?: $c->meta_ad_id,
                'meta_creative_id' => $creativeId ?: $c->meta_creative_id,
                'meta_adset_id'    => $adSetId    ?: $c->meta_adset_id,
                'facebook_id'      => $campaignId ?: $c->facebook_id,
            ]);
            try { $graph->deleteCascade($c); } catch (\Throwable $_) {}

            $c->update([
                'status'           => 'FAILED',
                'meta_last_error'  => mb_substr($errMsg, 0, 500),
                'meta_synced_at'   => now(),
                'facebook_id'      => $campaignId,
                'meta_adset_id'    => $adSetId,
                'meta_creative_id' => $creativeId,
                'meta_ad_id'       => $adId,
                'meta_image_hash'  => $imageHash,
            ]);
        }
    }

    private function syncFromMeta(MetaCampaign $c): bool
    {
        if (!$c->facebook_id) return false;
        $graph = $this->graphFor($c);
        if (!$graph->isConfigured()) return false;

        $insights = $graph->fetchInsights($c->facebook_id);
        $adsets   = $graph->fetchAdSets($c->facebook_id);
        $ads      = $graph->fetchAds($c->facebook_id);

        $ins = !empty($insights) ? $insights : (array) ($c->insights ?? []);
        if ($adsets) $ins['adsets'] = $adsets;
        if ($ads) {
            $ins['ads'] = $ads;
            foreach ($ads as $a) {
                if (!empty($a['image_url'])) { $ins['creative_image_url'] = $a['image_url']; break; }
            }
        }
        $daily = $graph->fetchDailyInsights($c->facebook_id);
        if ($daily) $ins['daily'] = $daily;

        $patch = ['insights' => $ins, 'meta_synced_at' => now()];

        if (!empty($adsets[0]['targeting'])) {
            $norm = $graph->normalizeTargeting($adsets[0]['targeting']);
            if ($norm) $patch['targeting'] = array_merge((array) ($c->targeting ?? []), $norm);
        }
        if (empty($c->meta_adset_id)    && !empty($adsets[0]['id']))       $patch['meta_adset_id']    = $adsets[0]['id'];
        if (empty($c->meta_ad_id)       && !empty($ads[0]['id']))          $patch['meta_ad_id']       = $ads[0]['id'];
        if (empty($c->meta_creative_id) && !empty($ads[0]['creative_id'])) $patch['meta_creative_id'] = $ads[0]['creative_id'];
        if ($adsets) $patch['ad_set_count'] = count($adsets);
        if ($ads)    $patch['ad_count']     = count($ads);

        $c->update($patch);
        return true;
    }

    // =================================================================
    // Aggregates
    // =================================================================

    private function statusCounts(int $userId): array
    {
        $rows = MetaCampaign::query()->forUser($userId)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        return [
            'all'    => MetaCampaign::query()->forUser($userId)->count(),
            'ACTIVE' => (int) ($rows['ACTIVE'] ?? 0),
            'PAUSED' => (int) ($rows['PAUSED'] ?? 0),
            'DRAFT'  => (int) ($rows['DRAFT']  ?? 0),
            'FAILED' => (int) ($rows['FAILED'] ?? 0),
        ];
    }

    private function totals(int $userId): array
    {
        $items = MetaCampaign::query()->forUser($userId)->get(['status', 'insights']);
        return [
            'total'  => $items->count(),
            'active' => $items->where('status', 'ACTIVE')->count(),
            'spend'  => round($items->sum(fn ($c) => (float) ($c->insights['spend']  ?? 0)), 2),
            'clicks' => (int) $items->sum(fn ($c) => (int) ($c->insights['clicks'] ?? 0)),
        ];
    }

    private function aggregateInsights(int $userId): array
    {
        $items = MetaCampaign::query()->forUser($userId)->get(['id', 'name', 'status', 'optimization_goal', 'insights']);
        $sumI = fn (string $k) => (int) $items->sum(fn ($c) => (int) ($c->insights[$k] ?? 0));
        $spend       = round($items->sum(fn ($c) => (float) ($c->insights['spend'] ?? 0)), 2);
        $impressions = $sumI('impressions');
        $clicks      = $sumI('clicks');
        $reach       = $sumI('reach');
        $conversions = $sumI('conversions');
        $byStatus    = $items->groupBy('status')->map->count()->all();

        return [
            'total_campaigns' => $items->count(),
            'active'  => (int) ($byStatus['ACTIVE'] ?? 0),
            'paused'  => (int) ($byStatus['PAUSED'] ?? 0),
            'spend'   => $spend,
            'impressions' => $impressions,
            'reach'   => $reach,
            'clicks'  => $clicks,
            'conversions' => $conversions,
            'ctr'     => $impressions ? round($clicks / max($impressions, 1) * 100, 2) : 0,
            'cpc'     => $clicks      ? round($spend  / max($clicks,      1),      2) : 0,
            'cpl'     => $conversions ? round($spend  / max($conversions, 1),      2) : 0,
            'top'     => $items->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'status' => $c->status,
                'spend' => round((float) ($c->insights['spend'] ?? 0), 2),
                'clicks' => (int) ($c->insights['clicks'] ?? 0),
                'conversions' => (int) ($c->insights['conversions'] ?? 0),
            ])->sortByDesc('spend')->values()->take(5)->all(),
        ];
    }

    // =================================================================
    // Validation / mapping
    // =================================================================

    private function validateCampaign(Request $request, bool $updating = false): array
    {
        $rule = $updating ? 'sometimes' : 'required';

        // The form posts interests + countries as comma/newline text; normalise
        // to arrays so the array rules below pass either way.
        foreach (['interests', 'target_countries'] as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $raw = preg_split('/[\n,]+/', (string) $request->input($field)) ?: [];
                $vals = array_values(array_filter(array_map('trim', $raw), fn ($s) => $s !== ''));
                if ($field === 'target_countries') $vals = array_map(fn ($s) => strtoupper($s), $vals);
                $request->merge([$field => $vals]);
            }
        }

        return $request->validate([
            'instagram_account_id' => "{$rule}|integer",
            'name'                 => "{$rule}|string|max:255",
            'ad_type'              => 'sometimes|nullable|in:ig_direct,link',
            'optimization_goal'    => 'sometimes|nullable|in:MESSAGES,LINK_CLICKS,REACH,POST_ENGAGEMENT',
            'daily_budget'         => "{$rule}|numeric|min:1",
            'adset_name'           => 'sometimes|nullable|string|max:255',
            'creative_title'       => 'sometimes|nullable|string|max:255',
            'creative_body'        => 'sometimes|nullable|string|max:4096',
            'creative_link_url'    => 'sometimes|nullable|url|max:1024',
            'creative_image_file'  => 'sometimes|nullable|image|max:10240',
            'dm_welcome'           => 'sometimes|nullable|string|max:500',
            'dm_cta'               => 'sometimes|nullable|in:MESSAGE_PAGE,LEARN_MORE,SHOP_NOW,SIGN_UP,BOOK_TRAVEL,GET_QUOTE,CONTACT_US,SUBSCRIBE',
            'target_countries'     => 'sometimes|nullable|array',
            'target_countries.*'   => 'sometimes|string|size:2',
            'age_min'              => 'sometimes|nullable|integer|min:13|max:80',
            'age_max'              => 'sometimes|nullable|integer|min:13|max:80',
            'gender'               => 'sometimes|nullable|in:male,female,all',
            'interests'            => 'sometimes|nullable|array',
            'interests.*'          => 'sometimes|string|max:120',
            'publisher_platforms'   => 'sometimes|nullable|array',
            'publisher_platforms.*' => 'sometimes|string|in:facebook,instagram',
            'instagram_positions'   => 'sometimes|nullable|array',
            'instagram_positions.*' => 'sometimes|string|in:stream,story,reels,explore,profile_feed,ig_search',
            'budget_level'         => 'sometimes|nullable|in:adset,campaign',
            'bid_strategy'         => 'sometimes|nullable|in:LOWEST_COST_WITHOUT_CAP,LOWEST_COST_WITH_BID_CAP,COST_CAP',
            'bid_amount'           => 'sometimes|nullable|numeric|min:0',
            'special_ad_categories'   => 'sometimes|nullable|array',
            'special_ad_categories.*' => 'sometimes|string|in:HOUSING,CREDIT,EMPLOYMENT,ISSUES_ELECTIONS_POLITICS',
            'status'               => 'sometimes|nullable|in:ACTIVE,PAUSED,DRAFT',
        ]);
    }

    private function mapValidatedToColumns(array $data): array
    {
        $out = array_filter([
            'instagram_account_id' => $data['instagram_account_id'] ?? null,
            'name'                 => $data['name']              ?? null,
            'optimization_goal'    => $data['optimization_goal'] ?? null,
            'daily_budget'         => $data['daily_budget']      ?? null,
            'creative_title'       => $data['creative_title']    ?? null,
            'creative_body'        => $data['creative_body']     ?? null,
            'creative_link_url'    => $data['creative_link_url'] ?? null,
            'dm_welcome'           => $data['dm_welcome']        ?? null,
            'dm_cta'               => $data['dm_cta']            ?? null,
            'status'               => $data['status']            ?? null,
        ], fn ($v) => $v !== null);

        $adType = strtolower((string) ($data['ad_type'] ?? MetaCampaign::AD_TYPE_IG_DIRECT));
        if (!in_array($adType, [MetaCampaign::AD_TYPE_IG_DIRECT, MetaCampaign::AD_TYPE_LINK], true)) {
            $adType = MetaCampaign::AD_TYPE_IG_DIRECT;
        }
        $out['ad_type'] = $adType;

        if ($adType === MetaCampaign::AD_TYPE_IG_DIRECT) {
            $out['objective'] = 'OUTCOME_ENGAGEMENT';
        } elseif (isset($data['optimization_goal'])) {
            $out['objective'] = in_array(strtoupper((string) $data['optimization_goal']), ['REACH'], true)
                ? 'OUTCOME_AWARENESS' : 'OUTCOME_TRAFFIC';
        }

        // Placement — default Instagram-only.
        $pp = array_values(array_intersect(
            array_map('strtolower', (array) ($data['publisher_platforms'] ?? [])),
            ['facebook', 'instagram']
        ));
        $out['publisher_platforms'] = !empty($pp) ? $pp : ['instagram'];

        $ip = array_values(array_intersect(
            array_map('strtolower', (array) ($data['instagram_positions'] ?? [])),
            ['stream', 'story', 'reels', 'explore', 'profile_feed', 'ig_search']
        ));
        $out['instagram_positions'] = !empty($ip) ? $ip : null;

        // Targeting.
        $countries = null;
        if (is_array($data['target_countries'] ?? null)) {
            $countries = array_values(array_filter(array_map(fn ($x) => strtoupper(trim((string) $x)), $data['target_countries'])));
        }
        $interests = null;
        if (is_array($data['interests'] ?? null)) {
            $interests = array_values(array_unique(array_filter(array_map(fn ($x) => trim((string) $x), $data['interests']))));
        }
        $targeting = array_filter([
            'countries'   => $countries,
            'age_min'     => $data['age_min'] ?? null,
            'age_max'     => $data['age_max'] ?? null,
            'gender'      => $data['gender']  ?? null,
            'interests'   => $interests,
            '_adset_name' => $data['adset_name'] ?? null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
        if (!empty($targeting)) $out['targeting'] = $targeting;

        if (array_key_exists('budget_level', $data)) {
            $out['budget_level'] = in_array($data['budget_level'], ['adset', 'campaign'], true) ? $data['budget_level'] : 'adset';
        }
        if (array_key_exists('bid_strategy', $data)) {
            $out['bid_strategy'] = $data['bid_strategy'] ?: null;
        }
        if (array_key_exists('bid_amount', $data)) {
            $amt = (float) ($data['bid_amount'] ?? 0);
            $out['bid_amount'] = $amt > 0 ? (int) round($amt * 100) : null;
        }
        if (array_key_exists('special_ad_categories', $data)) {
            $cats = array_values(array_filter((array) ($data['special_ad_categories'] ?? [])));
            $out['special_ad_categories'] = !empty($cats) ? $cats : null;
        }

        return $out;
    }

    // =================================================================
    // Build-with-AI (optional) — ad copy generation
    // =================================================================

    public function apiAiGenerate(Request $request): JsonResponse
    {
        if (!class_exists(\App\Services\AiAgentService::class)) {
            return response()->json(['ok' => false, 'error' => 'unavailable', 'message' => __('AI copy generation is not available on this install.')], 422);
        }

        $data = $request->validate([
            'provider'        => 'required|string|in:openai,anthropic,gemini,mistral',
            'model'           => 'required|string|max:120',
            'business_name'   => 'required|string|max:191',
            'product'         => 'nullable|string|max:255',
            'objective'       => 'nullable|string|max:60',
            'audience'        => 'nullable|string|max:500',
            'countries'       => 'nullable|string|max:255',
            'tone'            => 'nullable|string|max:60',
            'dm_message'      => 'nullable|boolean',
            'custom_prompt'   => 'nullable|string|max:2000',
        ]);

        $systemPrompt = <<<'SYS'
You write Instagram advertising copy. Output STRICT JSON only — no prose, no
markdown, no code fences. Schema:

{
  "campaign_name":      "<short, max 60>",
  "adset_name":         "<short, max 60>",
  "headline":           "<max 40 chars, attention-grabbing, no clickbait>",
  "body":               "<primary text, max 280 chars, persuasive, no emoji>",
  "interests":          "<comma-separated targeting interests, max 5 phrases>",
  "dm_welcome":         "<the ice-breaker shown when someone taps into the DM, max 200, optional>",
  "suggested_age_min":  <integer 13-65>,
  "suggested_age_max":  <integer 13-65>,
  "suggested_countries":"<comma-separated ISO 3166-1 alpha-2 codes, e.g. US,GB,IN>"
}

Rules:
1. Follow Meta's ad policy — no banned health/finance claims, no discriminatory
   language, no exaggerated promises.
2. No emojis. Plain text only. Capitalise correctly (no ALL CAPS).
3. Headline must hook in under 40 characters.
4. Body should describe the offer + a clear next step.
5. Output ONLY the JSON object. No explanation. No code fences.
SYS;

        $lines = ['Business name: ' . $data['business_name']];
        if (!empty($data['product']))   $lines[] = 'Product / service: ' . $data['product'];
        if (!empty($data['objective'])) $lines[] = 'Campaign objective: ' . $data['objective'];
        if (!empty($data['audience']))  $lines[] = 'Target audience: ' . $data['audience'];
        if (!empty($data['countries'])) $lines[] = 'Markets: ' . $data['countries'];
        if (!empty($data['tone']))      $lines[] = 'Tone: ' . $data['tone'];
        if (!empty($data['dm_message'])) $lines[] = 'Include a DM welcome — the person taps the ad and lands in an Instagram DM pre-filled with this message.';
        if (!empty($data['custom_prompt'])) { $lines[] = ''; $lines[] = 'Additional notes:'; $lines[] = $data['custom_prompt']; }

        try {
            $ai  = app(\App\Services\AiAgentService::class);
            $raw = $ai->callProvider(
                provider:     $data['provider'],
                model:        $data['model'],
                workspaceId:  0,
                systemPrompt: $systemPrompt,
                userPrompt:   implode("\n", $lines),
                maxTokens:    1200,
                temperature:  0.7,
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'provider_failed', 'message' => $e->getMessage()], 502);
        }

        if (!$raw) {
            return response()->json(['ok' => false, 'error' => 'no_key', 'message' => __('No AI provider is configured. Add a key in Admin → API keys.')], 422);
        }

        $clean = trim($raw);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $clean, $m)) $clean = trim($m[1]);
        $tpl = json_decode($clean, true);
        if (!is_array($tpl)) {
            return response()->json(['ok' => false, 'error' => 'bad_json', 'message' => __('Model output was not valid JSON. Try again.'), 'raw' => mb_substr($raw, 0, 600)], 422);
        }

        return response()->json(['ok' => true, 'ad' => [
            'campaign_name'       => mb_substr((string) ($tpl['campaign_name'] ?? ''), 0, 255),
            'adset_name'          => mb_substr((string) ($tpl['adset_name'] ?? ''), 0, 255),
            'headline'            => mb_substr((string) ($tpl['headline'] ?? ''), 0, 255),
            'body'                => mb_substr((string) ($tpl['body'] ?? ''), 0, 4096),
            'interests'           => mb_substr((string) ($tpl['interests'] ?? ''), 0, 2048),
            'dm_welcome'          => mb_substr((string) ($tpl['dm_welcome'] ?? ''), 0, 500),
            'suggested_age_min'   => is_numeric($tpl['suggested_age_min'] ?? null) ? max(13, min(65, (int) $tpl['suggested_age_min'])) : null,
            'suggested_age_max'   => is_numeric($tpl['suggested_age_max'] ?? null) ? max(13, min(65, (int) $tpl['suggested_age_max'])) : null,
            'suggested_countries' => mb_substr((string) ($tpl['suggested_countries'] ?? ''), 0, 255),
        ], 'model' => $data['model']]);
    }
}

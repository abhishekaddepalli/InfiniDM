<?php

namespace App\Services\Instagram;

use App\Models\InstagramAccount;
use App\Models\MetaCampaign;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Meta Marketing Graph API client — Instagram ads for the standalone
 * IgDesk build.
 *
 * A workspace-less port of WaDesk's MetaGraphClient. The Marketing API needs
 * FIVE entities for a real ad to run:
 *
 *   1. POST /act_{id}/adimages    → image_hash
 *   2. POST /act_{id}/campaigns   → campaign_id (PAUSED)
 *   3. POST /act_{id}/adsets      → ad_set_id   (destination_type=INSTAGRAM_DIRECT for a DM ad,
 *                                                publisher_platforms=[instagram])
 *   4. POST /act_{id}/adcreatives → creative_id (object_story_spec.link_data + instagram_user_id)
 *   5. POST /act_{id}/ads         → ad_id       (links creative + adset)
 *
 * DIFFERENCES from WaDesk:
 *   - Built from an InstagramAccount, not a WaProviderConfig. Token, page and
 *     IG identity come off the account; the ad account id off account->ad_account_id.
 *   - No WhatsApp: the CTWA destination/promoted_object is replaced by
 *     INSTAGRAM_DIRECT (Click-to-Instagram-DM). There is no phone number.
 *   - The Marketing API lives ONLY on graph.facebook.com with a FB-Login page
 *     token — never graph.instagram.com — so the base is always graph.facebook.com.
 *
 * App creds + graph version come from InstagramGate::setting(). Per-step errors
 * are stashed on $lastError and translated to friendly copy by errorHint().
 */
class InstagramAdsClient
{
    public array $lastError = [];
    public ?string $lastListError = null;

    private string $version;
    private string $token;
    private string $account;          // 'act_{id}'
    private ?string $pageId;
    private ?string $instagramUserId; // IG professional account id → object_story_spec.instagram_user_id

    public function __construct(private ?InstagramAccount $ig = null)
    {
        // Default to a current Marketing API version when unset. graph.instagram.com
        // has no Marketing API, so we always target graph.facebook.com below.
        $v = (string) InstagramGate::setting('instagram_graph_version', '');
        $this->version = $v !== '' ? $v : 'v25.0';

        if ($ig) {
            $this->token   = (string) $ig->access_token;
            $this->account = 'act_' . preg_replace('/^act_/', '', (string) ($ig->ad_account_id ?? ''));
            $this->pageId  = ($p = trim((string) ($ig->page_id ?? ''))) !== '' ? $p : null;
            $this->instagramUserId = ($i = trim((string) ($ig->ig_user_id ?? ''))) !== '' ? $i : null;
        } else {
            $this->token   = '';
            $this->account = 'act_';
            $this->pageId  = null;
            $this->instagramUserId = null;
        }
    }

    // =================================================================
    // Readiness
    // =================================================================

    /** Token + ad account both present — the minimum to talk to the Marketing API. */
    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->account !== 'act_';
    }

    /** True when we have a usable token but no ad account yet — discoverAssets()
     *  can then fill it (the state right after connecting an IG account). */
    public function hasTokenButNoAdAccount(): bool
    {
        return $this->token !== '' && $this->account === 'act_';
    }

    /**
     * Everything an Instagram ad needs: a configured ad account + a Page
     * (page_id is mandatory in object_story_spec even for IG-only placements)
     * + an Instagram identity (a real IG professional account or a Page-Backed
     * Instagram Account).
     */
    public function isInstagramReady(): bool
    {
        return $this->isConfigured()
            && $this->pageId !== null
            && $this->instagramUserId !== null;
    }

    public function adAccountId(): string
    {
        return $this->account;
    }

    public function pageId(): ?string
    {
        return $this->pageId;
    }

    public function instagramUserId(): ?string
    {
        return $this->instagramUserId;
    }

    public function withInstagramUserId(?string $igUserId): self
    {
        $id = trim((string) $igUserId);
        if ($id !== '') $this->instagramUserId = $id;
        return $this;
    }

    // =================================================================
    // Asset discovery (connect flow)
    // =================================================================

    /**
     * Discover the ad accounts + Facebook Pages the account's token can see, so
     * the connect flow AUTO-FILLS the ad account instead of forcing the operator
     * to paste raw ids. A plain IG-login token often lacks ads_management — in
     * that case the lists come back empty with an error and the UI falls back to
     * manual entry.
     *
     * @return array{ok:bool,ad_accounts:array<int,array{id:string,name:string}>,pages:array<int,array{id:string,name:string}>,error:?string}
     */
    public function discoverAssets(): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'ad_accounts' => [], 'pages' => [], 'error' => 'no_token'];
        }

        $base  = 'https://graph.facebook.com/' . $this->version;
        $error = null;

        $pull = function (string $url, array $query, string $idKey) use (&$error): array {
            $out = [];
            try {
                $r = Http::withToken($this->token)->acceptJson()->timeout(20)->get($url, $query);
                if (!$r->successful()) {
                    $error = $error ?: (string) ($r->json('error.message') ?: ('HTTP ' . $r->status()));
                    return $out;
                }
                foreach (($r->json('data') ?: []) as $row) {
                    $id = (string) ($row[$idKey] ?? $row['id'] ?? '');
                    if ($id === '') continue;
                    $out[$id] = ['id' => $id, 'name' => (string) ($row['name'] ?? $id)];
                }
            } catch (\Throwable $e) {
                $error = $error ?: $e->getMessage();
            }
            return $out;
        };

        $accts = $pull($base . '/me/adaccounts', ['fields' => 'account_id,name', 'limit' => 200], 'account_id');
        $pages = $pull($base . '/me/accounts',   ['fields' => 'id,name',         'limit' => 200], 'id');

        $accts = array_values($accts);
        $pages = array_values($pages);
        $ok    = $accts !== [] || $pages !== [];
        return ['ok' => $ok, 'ad_accounts' => $accts, 'pages' => $pages, 'error' => $ok ? null : ($error ?: 'no_assets')];
    }

    /**
     * Page-Backed Instagram Account — a "shadow" IG identity derived from the
     * Facebook Page so an account WITHOUT a real IG professional id can still
     * run Instagram ads. Returns the PBIA id (created if needed), caching it as
     * this client's instagram_user_id. Best-effort: null on failure.
     */
    public function ensurePbia(): ?string
    {
        if (!$this->isConfigured() || $this->pageId === null) return null;
        try {
            $get = Http::withToken($this->token)->acceptJson()->timeout(12)
                ->get($this->endpoint("{$this->pageId}/page_backed_instagram_accounts"));
            $existing = (string) ($get->json('data.0.id') ?? '');
            if ($existing !== '') {
                $this->instagramUserId = $existing;
                return $existing;
            }
            $post = Http::withToken($this->token)->acceptJson()->timeout(15)
                ->post($this->endpoint("{$this->pageId}/page_backed_instagram_accounts"));
            $this->stash($post, 'ensurePbia', ['page_id' => $this->pageId]);
            $id = (string) ($post->json('id') ?? '');
            if ($id !== '') {
                $this->instagramUserId = $id;
                return $id;
            }
        } catch (\Throwable $e) {
            Log::warning('[IG-ADS] ensurePbia threw', ['error' => $e->getMessage()]);
        }
        return null;
    }

    // =================================================================
    // STEP 1 — Image upload
    // =================================================================

    /**
     * Upload an image to the ad account's image library, return the hash.
     * @throws RuntimeException on failure
     */
    public function uploadImage(string $localPath): string
    {
        $this->requireConfigured();
        if (!is_readable($localPath)) {
            throw new RuntimeException("Image not readable: {$localPath}");
        }

        $resp = Http::withToken($this->token)
            ->timeout(30)
            ->attach('source', file_get_contents($localPath), basename($localPath))
            ->post($this->endpoint("{$this->account}/adimages"));

        $this->stash($resp, 'uploadImage', ['path' => $localPath]);
        if (!$resp->successful()) throw new RuntimeException($this->errorHint($resp));

        $images = (array) $resp->json('images', []);
        $first  = $images ? array_values($images)[0] : null;
        $hash   = (string) ($first['hash'] ?? '');
        if ($hash === '') throw new RuntimeException('Meta uploaded the image but returned no hash.');
        return $hash;
    }

    // =================================================================
    // STEP 2 — Campaign
    // =================================================================

    public function createCampaign(MetaCampaign $c): string
    {
        $this->requireConfigured();

        $payload = [
            'name'                  => (string) $c->name,
            'objective'             => $this->normalizeObjective((string) $c->objective),
            'special_ad_categories' => $this->specialAdCategories($c),
            'status'                => 'PAUSED',
            'buying_type'           => 'AUCTION',
        ];

        // Campaign Budget Optimization — budget + bidding live on the campaign
        // and the ad set must NOT carry its own budget (createAdSet honours this).
        if ($this->isCampaignBudget($c)) {
            $payload['daily_budget'] = max(100, (int) round(((float) ($c->daily_budget ?? 5)) * 100));
            $payload['bid_strategy'] = $this->bidStrategy($c);
            if ($this->bidAmountCents($c) !== null && $this->bidStrategy($c) !== 'LOWEST_COST_WITHOUT_CAP') {
                $payload['bid_cap'] = $this->bidAmountCents($c);
            }
        }

        $resp = Http::withToken($this->token)->acceptJson()->timeout(15)
            ->post($this->endpoint("{$this->account}/campaigns"), $payload);
        $this->stash($resp, 'createCampaign', ['name' => $c->name]);
        if (!$resp->successful()) throw new RuntimeException($this->errorHint($resp));
        return (string) $resp->json('id');
    }

    // =================================================================
    // STEP 3 — Ad Set
    // =================================================================

    public function createAdSet(MetaCampaign $c, string $campaignId): string
    {
        $this->requireConfigured();

        $dailyBudgetCents = (int) round(((float) ($c->daily_budget ?? 5)) * 100);
        $t          = is_array($c->targeting) ? $c->targeting : [];
        $adsetName  = trim((string) ($t['_adset_name'] ?? '')) ?: (string) $c->name;
        $adType     = $c->adType();

        $payload = [
            'name'              => $adsetName,
            'campaign_id'       => $campaignId,
            'billing_event'     => 'IMPRESSIONS',
            'optimization_goal' => $this->adsetOptimizationGoal($c),
            'status'            => 'PAUSED',
            'start_time'        => now()->addMinutes(5)->toIso8601String(),
            'targeting'         => $this->buildTargeting($c),
        ];

        if (!$this->isCampaignBudget($c)) {
            $payload['daily_budget'] = max(100, $dailyBudgetCents);
            $payload['bid_strategy'] = $this->bidStrategy($c);
            if ($this->bidAmountCents($c) !== null && $this->bidStrategy($c) !== 'LOWEST_COST_WITHOUT_CAP') {
                $payload['bid_amount'] = $this->bidAmountCents($c);
            }
        }

        // Click-to-Instagram-DM — the tap opens an IG DM thread. Plain link /
        // boost ads carry no messaging destination.
        if ($adType === MetaCampaign::AD_TYPE_IG_DIRECT) {
            $payload['destination_type'] = 'INSTAGRAM_DIRECT';
            if ($this->pageId) $payload['promoted_object']['page_id'] = $this->pageId;
        }

        $resp = Http::withToken($this->token)->acceptJson()->timeout(15)
            ->post($this->endpoint("{$this->account}/adsets"), $payload);
        $this->stash($resp, 'createAdSet', ['campaign_id' => $campaignId]);
        if (!$resp->successful()) throw new RuntimeException($this->errorHint($resp));
        return (string) $resp->json('id');
    }

    // =================================================================
    // STEP 4 — Ad Creative
    // =================================================================

    public function createAdCreative(MetaCampaign $c, string $imageHash): string
    {
        $this->requireConfigured();
        if (!$this->pageId) {
            throw new RuntimeException('Cannot build the ad creative — connect a Facebook Page to this Instagram account first.');
        }

        $adType   = $c->adType();
        $headline = (string) ($c->creative_title ?: $c->name);
        $body     = (string) ($c->creative_body  ?: '');

        if ($adType === MetaCampaign::AD_TYPE_IG_DIRECT) {
            // The ad set's destination_type=INSTAGRAM_DIRECT routes the tap into
            // an IG DM thread; the creative carries the welcome / ice-breaker.
            $linkData = [
                'name'                 => $headline,
                'message'              => $body,
                'image_hash'           => $imageHash,
                'link'                 => (string) ($c->creative_link_url ?: 'https://www.instagram.com/'),
                'page_welcome_message' => (string) ($c->dm_welcome ?: 'Hi! Thanks for your interest — how can we help?'),
                'call_to_action'       => ['type' => (string) ($c->dm_cta ?: 'MESSAGE_PAGE')],
            ];
        } else {
            // Plain link ad (traffic) to a website / landing page.
            $cta  = (string) ($c->dm_cta ?: 'LEARN_MORE');
            $link = (string) ($c->creative_link_url ?: 'https://');
            $linkData = [
                'name'           => $headline,
                'message'        => $body,
                'image_hash'     => $imageHash,
                'link'           => $link,
                'call_to_action' => ['type' => $cta],
            ];
        }

        $objectStorySpec = ['page_id' => $this->pageId, 'link_data' => $linkData];
        if ($this->instagramUserId && $c->wantsInstagram()) {
            $objectStorySpec['instagram_user_id'] = $this->instagramUserId;
        }

        $resp = Http::withToken($this->token)->acceptJson()->timeout(15)
            ->post($this->endpoint("{$this->account}/adcreatives"), [
                'name'              => (string) $c->name . ' — Creative',
                'object_story_spec' => $objectStorySpec,
            ]);
        $this->stash($resp, 'createAdCreative', ['name' => $c->name]);
        if (!$resp->successful()) throw new RuntimeException($this->errorHint($resp));
        return (string) $resp->json('id');
    }

    // =================================================================
    // STEP 5 — Ad
    // =================================================================

    public function createAd(MetaCampaign $c, string $adSetId, string $creativeId): string
    {
        $this->requireConfigured();

        $resp = Http::withToken($this->token)->acceptJson()->timeout(15)
            ->post($this->endpoint("{$this->account}/ads"), [
                'name'     => (string) $c->name . ' — Ad',
                'adset_id' => $adSetId,
                'creative' => ['creative_id' => $creativeId],
                'status'   => 'PAUSED',
            ]);
        $this->stash($resp, 'createAd', ['adset' => $adSetId]);
        if (!$resp->successful()) throw new RuntimeException($this->errorHint($resp));
        return (string) $resp->json('id');
    }

    // =================================================================
    // Boost — promote an EXISTING Instagram post (no new creative upload)
    // =================================================================

    /**
     * Boost an existing IG media as an engagement ad. Builds the full tree
     * (campaign → adset → creative-from-media → ad), all PAUSED so the operator
     * reviews + activates. The creative references the live post via
     * `source_instagram_media_id` (no image re-upload).
     *
     * @return array{ok:bool,error?:string,campaign_id?:string,adset_id?:string,creative_id?:string,ad_id?:string}
     */
    public function boostInstagramMedia(string $igMediaId, float $dailyBudget, int $days, array $opts = []): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Connect your Meta ad account first.'];
        }
        $tag = substr($igMediaId, -6);
        try {
            $camp = Http::withToken($this->token)->acceptJson()->timeout(20)
                ->post($this->endpoint("{$this->account}/campaigns"), [
                    'name'                  => 'IG Boost ' . $tag,
                    'objective'             => $this->normalizeObjective('OUTCOME_ENGAGEMENT'),
                    'special_ad_categories' => [],
                    'status'                => 'PAUSED',
                    'buying_type'           => 'AUCTION',
                ]);
            if (!$camp->successful()) return ['ok' => false, 'error' => $this->errorHint($camp)];
            $campaignId = (string) $camp->json('id');

            $cents = max(100, (int) round($dailyBudget * 100));
            $aset = Http::withToken($this->token)->acceptJson()->timeout(20)
                ->post($this->endpoint("{$this->account}/adsets"), [
                    'name'              => 'IG Boost ' . $tag,
                    'campaign_id'       => $campaignId,
                    'daily_budget'      => $cents,
                    'billing_event'     => 'IMPRESSIONS',
                    'optimization_goal' => 'POST_ENGAGEMENT',
                    'bid_strategy'      => 'LOWEST_COST_WITHOUT_CAP',
                    'status'            => 'PAUSED',
                    'start_time'        => now()->addMinutes(5)->toIso8601String(),
                    'end_time'          => now()->addDays(max(1, $days))->toIso8601String(),
                    'targeting'         => [
                        'geo_locations'       => ['countries' => [(string) ($opts['country'] ?? 'US')]],
                        'publisher_platforms' => ['instagram'],
                        'instagram_positions' => ['stream', 'explore', 'reels'],
                    ],
                ]);
            if (!$aset->successful()) return ['ok' => false, 'error' => $this->errorHint($aset), 'campaign_id' => $campaignId];
            $adsetId = (string) $aset->json('id');

            $creativePayload = ['name' => 'IG Boost creative ' . $tag, 'source_instagram_media_id' => $igMediaId];
            if ($this->instagramUserId) $creativePayload['instagram_user_id'] = $this->instagramUserId;
            $cr = Http::withToken($this->token)->acceptJson()->timeout(20)
                ->post($this->endpoint("{$this->account}/adcreatives"), $creativePayload);
            if (!$cr->successful()) return ['ok' => false, 'error' => $this->errorHint($cr), 'campaign_id' => $campaignId, 'adset_id' => $adsetId];
            $creativeId = (string) $cr->json('id');

            $ad = Http::withToken($this->token)->acceptJson()->timeout(20)
                ->post($this->endpoint("{$this->account}/ads"), [
                    'name'     => 'IG Boost ' . $tag,
                    'adset_id' => $adsetId,
                    'creative' => ['creative_id' => $creativeId],
                    'status'   => 'PAUSED',
                ]);
            if (!$ad->successful()) return ['ok' => false, 'error' => $this->errorHint($ad), 'campaign_id' => $campaignId, 'adset_id' => $adsetId, 'creative_id' => $creativeId];

            return ['ok' => true, 'campaign_id' => $campaignId, 'adset_id' => $adsetId, 'creative_id' => $creativeId, 'ad_id' => (string) $ad->json('id')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // =================================================================
    // Lifecycle — status / delete
    // =================================================================

    public function setStatus(string $entityId, string $status): bool
    {
        if (!$this->isConfigured() || $entityId === '') return false;
        try {
            $resp = Http::withToken($this->token)->acceptJson()->timeout(10)
                ->post($this->endpoint($entityId), ['status' => $status]);
            $this->stash($resp, 'setStatus', ['id' => $entityId, 'status' => $status]);
            return $resp->successful();
        } catch (\Throwable $e) {
            Log::warning('[IG-ADS] setStatus threw', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** Flip the whole tree together (campaign + adset + ad). True if ALL legs succeeded. */
    public function setStatusCascade(MetaCampaign $c, string $status): bool
    {
        $ok = true;
        if ($c->facebook_id)   $ok = $this->setStatus($c->facebook_id, $status) && $ok;
        if ($c->meta_adset_id) $ok = $this->setStatus($c->meta_adset_id, $status) && $ok;
        if ($c->meta_ad_id)    $ok = $this->setStatus($c->meta_ad_id, $status) && $ok;
        return $ok;
    }

    /** Delete the tree in reverse order (Ad → Creative → Ad Set → Campaign). Best-effort. */
    public function deleteCascade(MetaCampaign $c): bool
    {
        $ok = true;
        foreach ([$c->meta_ad_id, $c->meta_creative_id, $c->meta_adset_id, $c->facebook_id] as $id) {
            if (!$id) continue;
            try {
                $resp = Http::withToken($this->token)->timeout(10)->delete($this->endpoint($id));
                $ok = $resp->successful() && $ok;
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    // =================================================================
    // Insights
    // =================================================================

    public function fetchInsights(string $campaignId): array
    {
        if (!$this->isConfigured() || $campaignId === '') return [];
        try {
            $resp = Http::withToken($this->token)->acceptJson()->timeout(10)
                ->get($this->endpoint("{$campaignId}/insights"), [
                    'fields'      => 'impressions,clicks,spend,reach,cpc,cpm,ctr,frequency,actions,account_currency',
                    'date_preset' => 'last_7d',
                ]);
            $this->stash($resp, 'fetchInsights', ['id' => $campaignId]);
            if (!$resp->successful()) return [];

            $row = $resp->json('data.0') ?? [];
            return [
                'spend'            => (float) ($row['spend']       ?? 0),
                'impressions'      => (int)   ($row['impressions'] ?? 0),
                'clicks'           => (int)   ($row['clicks']      ?? 0),
                'reach'            => (int)   ($row['reach']       ?? 0),
                'conversions'      => $this->extractConversations($row['actions'] ?? []),
                'ctr'              => (float) ($row['ctr']         ?? 0),
                'cpc'              => (float) ($row['cpc']         ?? 0),
                'cpm'              => (float) ($row['cpm']         ?? 0),
                'frequency'        => (float) ($row['frequency']   ?? 0),
                'revenue'          => 0.0,
                'account_currency' => strtoupper((string) ($row['account_currency'] ?? '')),
            ];
        } catch (\Throwable $e) {
            Log::warning('[IG-ADS] fetchInsights threw', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Daily time-series for the analytics trend chart.
     * @return array<int,array{date:string,spend:float,clicks:int,leads:int,impressions:int}>
     */
    public function fetchDailyInsights(string $campaignId, string $datePreset = 'last_14d'): array
    {
        if (!$this->isConfigured() || $campaignId === '') return [];
        try {
            $resp = Http::withToken($this->token)->acceptJson()->timeout(12)
                ->get($this->endpoint("{$campaignId}/insights"), [
                    'fields'         => 'impressions,clicks,spend,actions',
                    'date_preset'    => $datePreset,
                    'time_increment' => 1,
                    'limit'          => 90,
                ]);
            $this->stash($resp, 'fetchDailyInsights', ['id' => $campaignId]);
            if (!$resp->successful()) return [];
            $out = [];
            foreach (($resp->json('data') ?? []) as $row) {
                $out[] = [
                    'date'        => (string) ($row['date_start'] ?? ''),
                    'spend'       => round((float) ($row['spend'] ?? 0), 2),
                    'clicks'      => (int) ($row['clicks'] ?? 0),
                    'leads'       => $this->extractConversations($row['actions'] ?? []),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('[IG-ADS] fetchDailyInsights threw', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /** Per-ad-set breakdown (insights + targeting). Best-effort: [] on failure. */
    public function fetchAdSets(string $campaignId): array
    {
        if (!$this->isConfigured() || $campaignId === '') return [];
        try {
            $fieldSets = [
                'id,name,status,targeting,insights.date_preset(last_7d){impressions,clicks,spend,reach,cpc,ctr,actions}',
                'id,name,status,targeting',
                'id,name,status',
            ];
            $resp = null;
            foreach ($fieldSets as $i => $fields) {
                $resp = Http::withToken($this->token)->acceptJson()->timeout(12)
                    ->get($this->endpoint("{$campaignId}/adsets"), ['fields' => $fields, 'limit' => 50]);
                if ($i === 0 || !$resp->successful()) {
                    $this->stash($resp, 'fetchAdSets#' . $i, ['id' => $campaignId]);
                }
                if ($resp->successful()) break;
            }
            if (!$resp || !$resp->successful()) return [];
            $out = [];
            foreach (($resp->json('data') ?? []) as $row) {
                $ins = $row['insights']['data'][0] ?? [];
                $out[] = [
                    'id'          => (string) ($row['id']     ?? ''),
                    'name'        => (string) ($row['name']   ?? ''),
                    'status'      => (string) ($row['status'] ?? ''),
                    'targeting'   => is_array($row['targeting'] ?? null) ? $row['targeting'] : [],
                    'spend'       => (float) ($ins['spend']       ?? 0),
                    'impressions' => (int)   ($ins['impressions'] ?? 0),
                    'clicks'      => (int)   ($ins['clicks']      ?? 0),
                    'reach'       => (int)   ($ins['reach']       ?? 0),
                    'ctr'         => (float) ($ins['ctr']         ?? 0),
                    'cpc'         => (float) ($ins['cpc']         ?? 0),
                    'conversions' => $this->extractConversations($ins['actions'] ?? []),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('[IG-ADS] fetchAdSets threw', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /** Per-ad breakdown with insights + creative image url. Best-effort. */
    public function fetchAds(string $campaignId): array
    {
        if (!$this->isConfigured() || $campaignId === '') return [];
        try {
            $fieldSets = [
                'id,name,status,creative{id,image_url,thumbnail_url,object_story_spec},insights.date_preset(last_7d){impressions,clicks,spend,reach,cpc,ctr,actions}',
                'id,name,status,creative{id,image_url,thumbnail_url,object_story_spec}',
                'id,name,status',
            ];
            $resp = null;
            foreach ($fieldSets as $i => $fields) {
                $resp = Http::withToken($this->token)->acceptJson()->timeout(12)
                    ->get($this->endpoint("{$campaignId}/ads"), ['fields' => $fields, 'limit' => 50]);
                if ($i === 0 || !$resp->successful()) {
                    $this->stash($resp, 'fetchAds#' . $i, ['id' => $campaignId]);
                }
                if ($resp->successful()) break;
            }
            if (!$resp || !$resp->successful()) return [];
            $out = [];
            foreach (($resp->json('data') ?? []) as $row) {
                $ins = $row['insights']['data'][0] ?? [];
                $cr  = is_array($row['creative'] ?? null) ? $row['creative'] : [];
                $img = ($cr['image_url'] ?? '')
                    ?: ($cr['thumbnail_url'] ?? '')
                    ?: ($cr['object_story_spec']['link_data']['picture'] ?? '');
                $out[] = [
                    'id'          => (string) ($row['id']     ?? ''),
                    'name'        => (string) ($row['name']   ?? ''),
                    'status'      => (string) ($row['status'] ?? ''),
                    'creative_id' => (string) ($cr['id'] ?? ''),
                    'image_url'   => (string) $img,
                    'spend'       => (float) ($ins['spend']       ?? 0),
                    'impressions' => (int)   ($ins['impressions'] ?? 0),
                    'clicks'      => (int)   ($ins['clicks']      ?? 0),
                    'reach'       => (int)   ($ins['reach']       ?? 0),
                    'ctr'         => (float) ($ins['ctr']         ?? 0),
                    'cpc'         => (float) ($ins['cpc']         ?? 0),
                    'conversions' => $this->extractConversations($ins['actions'] ?? []),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('[IG-ADS] fetchAds threw', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * List existing campaigns in the connected ad account — powers a "Fetch
     * from Meta" import. Basic fields only; insights pulled per-campaign.
     */
    public function listCampaigns(int $limit = 100): array
    {
        $this->lastListError = null;
        if (!$this->isConfigured()) {
            $this->lastListError = 'Connect your Meta ad account first.';
            return [];
        }
        try {
            $resp = Http::withToken($this->token)->acceptJson()->timeout(25)
                ->get($this->endpoint("{$this->account}/campaigns"), [
                    'fields'           => 'id,name,objective,status,effective_status,daily_budget,lifetime_budget,created_time',
                    'effective_status' => json_encode([
                        'ACTIVE', 'PAUSED', 'CAMPAIGN_PAUSED', 'ADSET_PAUSED', 'ARCHIVED',
                        'IN_PROCESS', 'WITH_ISSUES', 'PENDING_REVIEW', 'DISAPPROVED',
                    ]),
                    'limit'            => max(1, min(500, $limit)),
                ]);
            if (!$resp->successful()) {
                $this->lastListError = (string) ($resp->json('error.message') ?? 'Meta API returned an error.');
                return [];
            }
            return (array) $resp->json('data', []);
        } catch (\Throwable $e) {
            $this->lastListError = $e->getMessage();
            return [];
        }
    }

    /** Delivery / reach estimate for a campaign's current targeting. */
    public function deliveryEstimate(MetaCampaign $c): array
    {
        if (!$this->isConfigured()) return ['ok' => false, 'error' => 'not_configured'];
        try {
            $resp = Http::withToken($this->token)->acceptJson()->timeout(12)
                ->get($this->endpoint("{$this->account}/delivery_estimate"), [
                    'optimization_goal' => $this->adsetOptimizationGoal($c),
                    'targeting_spec'    => json_encode($this->buildTargeting($c)),
                ]);
            if (!$resp->successful()) return ['ok' => false, 'error' => (string) $resp->json('error.message', 'estimate failed')];
            $row = (array) $resp->json('data.0', []);
            return [
                'ok'    => true,
                'lower' => (int) ($row['estimate_mau_lower_bound'] ?? $row['estimate_dau_lower_bound'] ?? 0),
                'upper' => (int) ($row['estimate_mau_upper_bound'] ?? $row['estimate_dau_upper_bound'] ?? 0),
                'ready' => (bool) ($row['estimate_ready'] ?? false),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // =================================================================
    // Live search (Targeting Search API) — for interest resolution
    // =================================================================

    /** Geolocation autocomplete → [{key,name,type,label}]. */
    public function searchGeo(string $q, array $locationTypes = ['city']): array
    {
        $q = trim($q);
        if ($q === '' || !$this->isConfigured()) return [];
        $types = array_values(array_intersect($locationTypes, ['country', 'region', 'city', 'zip', 'geo_market', 'country_group']));
        if (empty($types)) $types = ['city'];
        $ck = 'igads_geo:' . md5($this->version . '|' . implode(',', $types) . '|' . mb_strtolower($q));
        return Cache::remember($ck, now()->addHours(6), function () use ($q, $types) {
            try {
                $resp = Http::withToken($this->token)->acceptJson()->timeout(8)
                    ->get($this->endpoint('search'), [
                        'type'           => 'adgeolocation',
                        'location_types' => json_encode($types),
                        'q'              => $q,
                        'limit'          => 25,
                    ]);
                if (!$resp->successful()) return [];
                return array_values(array_map(function ($r) {
                    $bits = array_filter([$r['name'] ?? '', $r['region'] ?? '', $r['country_name'] ?? ($r['country_code'] ?? '')]);
                    return [
                        'key'   => (string) ($r['key'] ?? ''),
                        'name'  => (string) ($r['name'] ?? ''),
                        'type'  => (string) ($r['type'] ?? ''),
                        'label' => implode(', ', $bits),
                    ];
                }, (array) $resp->json('data', [])));
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    /** Interest autocomplete → [{id,name,label,size}]. */
    public function searchInterests(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '' || !$this->isConfigured()) return [];
        $ck = 'igads_int:' . md5($this->version . '|' . mb_strtolower($q) . '|' . $limit);
        return Cache::remember($ck, now()->addHours(6), function () use ($q, $limit) {
            try {
                $resp = Http::withToken($this->token)->acceptJson()->timeout(8)
                    ->get($this->endpoint('search'), ['type' => 'adinterest', 'q' => $q, 'limit' => $limit]);
                if (!$resp->successful()) return [];
                return array_values(array_map(fn ($r) => [
                    'id'    => (string) ($r['id'] ?? ''),
                    'name'  => (string) ($r['name'] ?? ''),
                    'label' => (string) ($r['name'] ?? '') . (isset($r['path']) && is_array($r['path']) ? ' · ' . implode(' › ', $r['path']) : ''),
                    'size'  => (int) ($r['audience_size_upper_bound'] ?? $r['audience_size'] ?? 0),
                ], (array) $resp->json('data', [])));
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    /**
     * Meta ad-set targeting object → the local {countries,age_min,age_max,
     * gender,interests,placements} shape the analytics Audience panel reads.
     */
    public function normalizeTargeting(array $t): array
    {
        if (!$t) return [];
        $countries = $t['geo_locations']['countries'] ?? [];
        $genders   = $t['genders'] ?? [];
        $gender    = 'all';
        if ($genders === [1]) $gender = 'male';
        elseif ($genders === [2]) $gender = 'female';

        $interests = [];
        foreach (($t['interests'] ?? []) as $i) {
            if (is_array($i) && !empty($i['name'])) $interests[] = $i['name'];
            elseif (is_string($i) && $i !== '')    $interests[] = $i;
        }

        $placements = [];
        foreach (($t['publisher_platforms'] ?? []) as $p) $placements[] = ucfirst((string) $p);

        return array_filter([
            'countries'  => array_values((array) $countries),
            'age_min'    => $t['age_min'] ?? null,
            'age_max'    => $t['age_max'] ?? null,
            'gender'     => $gender,
            'interests'  => array_values(array_unique($interests)),
            'placements' => $placements,
        ], fn ($v) => $v !== null && $v !== [] && $v !== false);
    }

    // =================================================================
    // Targeting builder
    // =================================================================

    private function buildTargeting(MetaCampaign $c): array
    {
        $t = is_array($c->targeting) ? $c->targeting : [];

        $geo = [];
        $regions = $this->geoKeyList($t['regions'] ?? []);
        $cities  = $this->geoCityList($t['cities'] ?? []);
        $hasGranular = $regions || $cities;
        if ($regions) $geo['regions'] = $regions;
        if ($cities)  $geo['cities']  = $cities;
        if (!$hasGranular) {
            $countries = array_values(array_map('strtoupper', (array) ($t['countries'] ?? [])));
            if (empty($countries)) $countries = ['US'];
            $geo['countries'] = $countries;
        }

        $payload = [
            'geo_locations' => $geo ?: ['countries' => ['US']],
            'age_min'       => max(13, (int) ($t['age_min'] ?: 18)),
            'age_max'       => min(65, (int) ($t['age_max'] ?: 65)),
            'genders'       => $this->genders($t['gender'] ?? null),
        ];

        $advantage = array_key_exists('advantage_audience', $t) ? (int) $t['advantage_audience'] : 1;
        $payload['targeting_automation'] = ['advantage_audience' => $advantage ? 1 : 0];

        // Interests: resolve curated NAMES to {id,name} via Targeting Search.
        $interests = $this->resolveInterests($this->stringList($t['interests'] ?? []));
        if ($interests) $payload['interests'] = $interests;

        // Placements — default to Instagram-only so IG ads never leak onto
        // Facebook unless the operator explicitly widens them.
        $platforms = array_values(array_intersect(
            array_map('strtolower', (array) ($c->publisher_platforms ?? [])),
            ['facebook', 'instagram', 'audience_network', 'messenger']
        ));
        if (empty($platforms)) $platforms = ['instagram'];
        $payload['publisher_platforms'] = $platforms;

        if (in_array('instagram', $platforms, true)) {
            $igPos = array_values(array_intersect(
                array_map('strtolower', (array) ($c->instagram_positions ?? [])),
                ['stream', 'story', 'reels', 'profile_feed', 'explore', 'ig_search']
            ));
            if ($igPos) $payload['instagram_positions'] = $igPos;
        }

        // Drop any underscore-prefixed local metadata keys.
        foreach (array_keys($payload) as $k) {
            if (is_string($k) && str_starts_with($k, '_')) unset($payload[$k]);
        }
        return $payload;
    }

    private function adsetOptimizationGoal(MetaCampaign $c): string
    {
        if ($c->isMessagingAd()) return 'CONVERSATIONS';
        if ($c->adType() === MetaCampaign::AD_TYPE_BOOST) return 'POST_ENGAGEMENT';
        return in_array(strtoupper((string) $c->optimization_goal), ['REACH', 'BRAND_AWARENESS'], true)
            ? 'REACH'
            : 'LINK_CLICKS';
    }

    private function genders($g): array
    {
        return match (strtolower((string) $g)) {
            'male'   => [1],
            'female' => [2],
            default  => [1, 2],
        };
    }

    /** Resolve interest NAMES → Meta {id,name} via Targeting Search (cached 7d). */
    private function resolveInterests(array $names): array
    {
        $out = [];
        foreach ($names as $name) {
            $key = 'igads_interest:' . md5($this->version . '|' . mb_strtolower($name));
            $hit = Cache::remember($key, now()->addDays(7), function () use ($name) {
                try {
                    $resp = Http::withToken($this->token)->acceptJson()->timeout(8)
                        ->get($this->endpoint('search'), ['type' => 'adinterest', 'q' => $name, 'limit' => 1]);
                    if (!$resp->successful()) return null;
                    $row = $resp->json('data.0');
                    if (!is_array($row) || empty($row['id'])) return null;
                    return ['id' => (string) $row['id'], 'name' => (string) ($row['name'] ?? $name)];
                } catch (\Throwable $e) {
                    return null;
                }
            });
            if (is_array($hit) && !empty($hit['id'])) $out[] = $hit;
        }
        return $out;
    }

    private function stringList($v): array
    {
        return array_values(array_filter(array_map('trim', array_map('strval', (array) $v)), fn ($s) => $s !== ''));
    }

    private function geoKeyList($v): array
    {
        $out = [];
        foreach ((array) $v as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : $row;
            if ($key !== null && (string) $key !== '') $out[] = ['key' => (string) $key];
        }
        return $out;
    }

    private function geoCityList($v): array
    {
        $out = [];
        foreach ((array) $v as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : $row;
            if ($key === null || (string) $key === '') continue;
            $city = ['key' => (string) $key];
            $radius = is_array($row) ? (float) ($row['radius'] ?? 0) : 0;
            if ($radius > 0) {
                $unit = is_array($row) ? strtolower((string) ($row['distance_unit'] ?? 'kilometer')) : 'kilometer';
                $unit = in_array($unit, ['mile', 'kilometer'], true) ? $unit : 'kilometer';
                $max  = $unit === 'mile' ? 50 : 80;
                $city['radius']        = max(1, min($max, (int) round($radius)));
                $city['distance_unit'] = $unit;
            }
            $out[] = $city;
        }
        return $out;
    }

    private function specialAdCategories(MetaCampaign $c): array
    {
        $cats = array_values(array_filter(array_map(
            fn ($x) => strtoupper(trim((string) $x)),
            (array) ($c->special_ad_categories ?? [])
        )));
        return array_values(array_intersect($cats, ['HOUSING', 'CREDIT', 'EMPLOYMENT', 'ISSUES_ELECTIONS_POLITICS']));
    }

    private function isCampaignBudget(MetaCampaign $c): bool
    {
        return strtolower((string) ($c->budget_level ?? 'adset')) === 'campaign';
    }

    private function bidStrategy(MetaCampaign $c): string
    {
        $s = strtoupper((string) ($c->bid_strategy ?? ''));
        return in_array($s, ['LOWEST_COST_WITHOUT_CAP', 'LOWEST_COST_WITH_BID_CAP', 'COST_CAP'], true)
            ? $s : 'LOWEST_COST_WITHOUT_CAP';
    }

    private function bidAmountCents(MetaCampaign $c): ?int
    {
        $amt = (int) ($c->bid_amount ?? 0);
        return $amt > 0 ? $amt : null;
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function endpoint(string $path): string
    {
        // Marketing API is ONLY on graph.facebook.com — never graph.instagram.com.
        return "https://graph.facebook.com/{$this->version}/{$path}";
    }

    private function requireConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Meta Ads is not configured — connect an ad account on the Ads page first.');
        }
    }

    private function stash(?Response $resp, string $op, array $context): void
    {
        if (!$resp) { $this->lastError = ['op' => $op, 'context' => $context]; return; }
        $this->lastError = [
            'op'      => $op,
            'status'  => $resp->status(),
            'body'    => $resp->json() ?? $resp->body(),
            'context' => $context,
        ];
    }

    private function normalizeObjective(string $objective): string
    {
        $map = [
            'LINK_CLICKS'     => 'OUTCOME_TRAFFIC',
            'MESSAGES'        => 'OUTCOME_ENGAGEMENT',
            'POST_ENGAGEMENT' => 'OUTCOME_ENGAGEMENT',
            'CONVERSIONS'     => 'OUTCOME_SALES',
            'LEAD_GENERATION' => 'OUTCOME_LEADS',
            'BRAND_AWARENESS' => 'OUTCOME_AWARENESS',
            'REACH'           => 'OUTCOME_AWARENESS',
            'VIDEO_VIEWS'     => 'OUTCOME_ENGAGEMENT',
        ];
        return $map[strtoupper($objective)] ?? strtoupper($objective);
    }

    private function extractConversations(array $actions): int
    {
        $priority = [
            'onsite_conversion.messaging_conversation_started_7d',
            'onsite_conversion.total_messaging_connection',
            'onsite_conversion.messaging_first_reply',
        ];
        foreach ($priority as $target) {
            foreach ($actions as $a) {
                if (($a['action_type'] ?? '') === $target) return (int) ($a['value'] ?? 0);
            }
        }
        foreach (['messaging_conversation_started', 'total_messaging_connection', 'messaging_first_reply'] as $needle) {
            foreach ($actions as $a) {
                if (str_contains((string) ($a['action_type'] ?? ''), $needle)) return (int) ($a['value'] ?? 0);
            }
        }
        return 0;
    }

    /** Translate Meta's typed error envelope into actionable copy. */
    private function errorHint(Response $resp): string
    {
        $err     = (array) $resp->json('error', []);
        $code    = (int) ($err['code']          ?? 0);
        $sub     = (int) ($err['error_subcode'] ?? 0);
        $msg     = (string) ($err['message']    ?? 'Unknown Meta error.');
        $userMsg = (string) ($err['error_user_msg'] ?? '');

        $hint = match (true) {
            $code === 190                     => 'Your Meta access token expired or was revoked. Reconnect the Instagram account.',
            $code === 200 && $sub === 1359047 => 'This connection is missing the ads_management permission. Reconnect and grant ads access.',
            $code === 200                     => 'Permission denied: ' . $msg . '. The token needs ads_management + pages_manage_ads scopes.',
            $code === 100                     => 'Bad parameter: ' . $msg . '. Often a missing field, wrong enum, or stale Graph API version.',
            $code === 17                      => 'Meta API rate limit reached. Wait a few minutes and retry.',
            $code === 4                       => 'Meta application request limit reached. Wait an hour.',
            $code === 32                      => 'Page-level rate limit reached for the connected Facebook Page.',
            $code === 803                     => 'Some requested fields are invalid for this Graph API version.',
            $code === 1487616                 => 'Ad image is too small. Use at least 1080×1080 px.',
            $code === 1815269                 => 'The ad creative violates Meta policy. Review the headline + body.',
            default                           => "Meta error {$code}" . ($sub ? "/{$sub}" : '') . ': ' . $msg,
        };
        return $userMsg ? ($hint . ' [' . $userMsg . ']') : $hint;
    }
}

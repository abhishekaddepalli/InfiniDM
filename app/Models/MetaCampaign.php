<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Meta Ads (Instagram) campaign — IgDesk standalone edition.
 *
 * A workspace-less port of WaDesk's MetaCampaign. PII columns (name,
 * creative copy, DM welcome, link URL, targeting) are encrypted-at-rest so
 * the database file is unreadable on its own — the InstagramAccount model
 * uses the same pattern for its access token.
 *
 * Scoping is by `user_id` (IgDesk has no workspaces). Filtering/search on
 * an encrypted column must hydrate-then-filter in PHP — a DB-side LIKE on
 * ciphertext matches nothing (see filterByName()).
 */
class MetaCampaign extends Model
{
    protected $table = 'meta_campaigns';

    protected $fillable = [
        'user_id',
        'instagram_account_id',
        'facebook_id',
        // Full Meta entity tree — each id lets us toggle/edit/delete the whole
        // hierarchy (Ad → Creative → Ad Set → Campaign).
        'meta_adset_id',
        'meta_creative_id',
        'meta_ad_id',
        'meta_image_hash',
        'meta_last_error',
        'meta_synced_at',
        'name',
        'objective',
        'optimization_goal',
        'status',
        'type',
        // ad_type drives the destination/creative shape; publisher_platforms +
        // instagram_positions drive placement; instagram_user_id is the IG
        // identity on the creative.
        'ad_type',
        'publisher_platforms',
        'instagram_positions',
        'instagram_user_id',
        'daily_budget',
        'lifetime_budget',
        // Campaign Budget Optimization + bidding + ad categories.
        'budget_level',
        'bid_strategy',
        'bid_amount',
        'special_ad_categories',
        'creative_title',
        'creative_body',
        'creative_link_url',
        'creative_image',
        // Click-to-Instagram-DM: the welcome / ice-breaker message + the CTA.
        'dm_welcome',
        'dm_cta',
        'targeting',
        'insights',
        'ad_set_count',
        'ad_count',
    ];

    protected $casts = [
        // Encrypted-at-rest. Don't LIKE/ORDER BY these at the SQL layer —
        // filterByName() works in PHP after hydration.
        'name'              => 'encrypted',
        'creative_title'    => 'encrypted',
        'creative_body'     => 'encrypted',
        'creative_link_url' => 'encrypted',
        'dm_welcome'        => 'encrypted',
        'targeting'         => 'encrypted:array',
        'meta_last_error'   => 'encrypted',

        'insights'              => 'array',
        'publisher_platforms'   => 'array',
        'instagram_positions'   => 'array',
        'special_ad_categories' => 'array',
        'daily_budget'          => 'decimal:2',
        'lifetime_budget'       => 'decimal:2',
        'bid_amount'            => 'integer',
        'ad_set_count'          => 'integer',
        'ad_count'              => 'integer',
        'meta_synced_at'        => 'datetime',
    ];

    public const STATUSES = ['ACTIVE', 'PAUSED', 'SCHEDULED', 'DRAFT', 'FAILED'];

    /**
     * ad_type — how the ad is built:
     *   ig_direct  Click-to-Instagram-DM (the tap opens an IG DM thread)
     *   link       standard link ad (traffic) to a website / landing page
     *   boost      promote an EXISTING published IG post (engagement)
     */
    public const AD_TYPE_IG_DIRECT = 'ig_direct';
    public const AD_TYPE_LINK      = 'link';
    public const AD_TYPE_BOOST     = 'boost';
    public const AD_TYPES = [self::AD_TYPE_IG_DIRECT, self::AD_TYPE_LINK, self::AD_TYPE_BOOST];

    public const OPTIMIZATION_GOALS = [
        'MESSAGES', 'LINK_CLICKS', 'REACH', 'POST_ENGAGEMENT',
    ];

    /** Normalised ad type — defaults to ig_direct. */
    public function adType(): string
    {
        $t = strtolower((string) ($this->ad_type ?: self::AD_TYPE_IG_DIRECT));
        return in_array($t, self::AD_TYPES, true) ? $t : self::AD_TYPE_IG_DIRECT;
    }

    /** A messaging ad (Click-to-Instagram-DM) — uses page_welcome_message. */
    public function isMessagingAd(): bool
    {
        return $this->adType() === self::AD_TYPE_IG_DIRECT;
    }

    /** True when the ad needs an Instagram identity — always true here (all ad
     *  types run on Instagram), but respects an explicit facebook-only placement. */
    public function wantsInstagram(): bool
    {
        $p = array_map('strtolower', (array) ($this->publisher_platforms ?? []));
        if (empty($p)) return true;                 // default IG placement
        return in_array('instagram', $p, true);
    }

    public function instagramAccount(): BelongsTo
    {
        return $this->belongsTo(InstagramAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeForUser(Builder $q, ?int $userId): Builder
    {
        return $q->where('user_id', (int) $userId);
    }

    public function scopeWithStatus(Builder $q, ?string $status): Builder
    {
        if (!$status || $status === 'all') return $q;
        return $q->where('status', strtoupper($status));
    }

    public function scopeWithObjective(Builder $q, ?string $goal): Builder
    {
        if (!$goal || $goal === 'all') return $q;
        return $q->where('optimization_goal', $goal);
    }

    public function scopeInRange(Builder $q, ?string $range): Builder
    {
        return match ($range) {
            '7d'  => $q->where('created_at', '>=', now()->subDays(7)),
            '30d' => $q->where('created_at', '>=', now()->subDays(30)),
            '90d' => $q->where('created_at', '>=', now()->subDays(90)),
            default => $q,
        };
    }

    /** Filter by encrypted name in PHP — a DB-side LIKE on ciphertext matches nothing. */
    public static function filterByName($items, ?string $term)
    {
        $term = mb_strtolower(trim((string) $term));
        if ($term === '') return $items;
        return $items->filter(fn ($c) => str_contains(mb_strtolower((string) $c->name), $term))->values();
    }

    /** Card metrics, zero-filled until the campaign is synced. */
    public function getMetricsAttribute(): array
    {
        $i = $this->insights ?? [];
        return [
            'spend'       => (float) ($i['spend']       ?? 0),
            'impressions' => (int)   ($i['impressions'] ?? 0),
            'clicks'      => (int)   ($i['clicks']      ?? 0),
            'reach'       => (int)   ($i['reach']       ?? 0),
            'conversions' => (int)   ($i['conversions'] ?? 0),
            'ctr'         => (float) ($i['ctr']         ?? 0),
            'cpc'         => (float) ($i['cpc']         ?? 0),
            'revenue'     => (float) ($i['revenue']     ?? 0),
        ];
    }
}

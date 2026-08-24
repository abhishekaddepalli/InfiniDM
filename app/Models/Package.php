<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A SaaS plan the operator sells. Limits are NULL = unlimited; features is the
 * set of enabled capability keys.
 *
 * Billing fields (plan_amount / plan_unit / plan_duration / offer_price /
 * is_highlighted / free / is_custom_quote) were added to match WaDesk's
 * pricing + checkout shape so the checkout engine and a later pricing page can
 * consume packages the same way WaDesk does. `price`/`interval`/`is_featured`
 * remain the IgDesk-native columns and are kept in sync at migration time.
 */
class Package extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'price', 'currency', 'interval', 'trial_days',
        'max_accounts', 'max_flows', 'max_automations', 'monthly_dms', 'team_seats',
        'features', 'is_active', 'is_default', 'is_featured', 'sort',
        // Billing shape (WaDesk parity — checkout + pricing).
        'plan_amount', 'offer_price', 'plan_unit', 'plan_duration',
        'is_highlighted', 'free', 'is_custom_quote',
    ];

    protected $casts = [
        'features'       => 'array',
        'price'          => 'decimal:2',
        'is_active'      => 'boolean',
        'is_default'     => 'boolean',
        'is_featured'    => 'boolean',
        // Billing shape.
        'plan_amount'    => 'decimal:2',
        'offer_price'    => 'decimal:2',
        'plan_duration'  => 'integer',
        'is_highlighted' => 'boolean',
        'free'           => 'boolean',
        'is_custom_quote'=> 'boolean',
    ];

    /**
     * Every capability a plan can toggle — key => label, for the admin form.
     * 1:1 with InstagramGate::FEATURES (minus the 'master' product switch), so
     * an operator can sell or withhold each user-side feature independently.
     */
    public const FEATURES = [
        'inbox'         => 'Unified inbox (DMs)',
        'composer'      => 'Post composer / publishing',
        'scheduler'     => 'Scheduled posts + calendar',
        'posts'         => 'My posts grid',
        'comments'      => 'Comment moderation',
        'auto_comments' => 'Auto-comment replies',
        'automations'   => 'Automations (keyword / triggers)',
        'flows'         => 'Flow builder',
        'broadcast'     => 'Bulk DM / broadcast',
        'templates'     => 'Saved DM templates',
        'ai'            => 'AI replies',
        'analytics'     => 'Analytics + insights',
        'discovery'     => 'Discovery (competitor + hashtag)',
        'reposter'      => 'Reels Autopilot (reposter)',
        'ads'           => 'Click-to-DM ads',
        'commerce'      => 'Shop / products (commerce)',
        'leads'         => 'Lead capture + Lead Ads',
        'orders'        => 'In-DM ordering + orders',
    ];

    public function hasFeature(string $key): bool
    {
        return in_array($key, (array) $this->features, true);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** The plan a new signup lands on. */
    public static function default(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first()
            ?? static::where('is_active', true)->orderBy('sort')->first();
    }

    // ── WaDesk billing parity ─────────────────────────────────────────

    /**
     * WaDesk's pricing blade + the payment drivers read `$package->pname`.
     * IgDesk's real column is `name`, so expose it under both spellings.
     */
    public function getPnameAttribute(): ?string
    {
        return $this->attributes['name'] ?? null;
    }

    /** Active plans — IgDesk gates on the boolean `is_active`. */
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /**
     * Full subscription plans. WaDesk splits plan vs. add-on via a `type`
     * column; IgDesk has no add-on packages, so every package is a plan.
     * Kept for call-site parity with WaDesk.
     */
    public function scopePlans($q)
    {
        return $q;
    }

    /**
     * The ACTUAL amount to charge / display for this plan, in the package's own
     * currency. An offer (discounted) price wins over plan_amount — the single
     * source of truth checkout, order creation and recurring renewals share.
     * Free / zero-price plans charge 0. Falls back to the native `price` column
     * when plan_amount hasn't been set.
     */
    public function chargeableAmount(): float
    {
        $base = (float) ($this->plan_amount ?? $this->price ?? 0);
        if ($this->free || $base <= 0) {
            return 0.0;
        }
        if ($this->offer_price !== null && (float) $this->offer_price > 0) {
            return (float) $this->offer_price;
        }
        return $base;
    }

    /**
     * Is this plan PRICED yearly, i.e. plan_amount already covers a full year?
     * The distinction matters because two things both call themselves "yearly":
     * a package the admin authored with plan_unit = years, and a monthly package
     * a buyer chose to pay annually via the pricing toggle. The first is charged
     * as-is; the second is monthly × 12 minus the global discount.
     */
    public function isNativelyYearly(): bool
    {
        return str_starts_with(strtolower((string) ($this->plan_unit ?? $this->interval ?? '')), 'year');
    }

    /** Human period label from plan_unit/plan_duration, e.g. "/month", "/year". */
    public function periodLabel(): string
    {
        $unit = strtolower((string) ($this->plan_unit ?: $this->interval));
        $dur  = (int) ($this->plan_duration ?: 1);
        $u = match (true) {
            str_contains($unit, 'year')  => 'year',
            str_contains($unit, 'month') => 'month',
            str_contains($unit, 'week')  => 'week',
            str_contains($unit, 'day')   => 'day',
            default                      => $unit ?: 'month',
        };
        return $dur > 1 ? "/{$dur} {$u}s" : "/{$u}";
    }
}

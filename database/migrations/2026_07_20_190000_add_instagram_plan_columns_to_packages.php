<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-feature Instagram entitlement columns on `packages`.
 *
 * Before this, Instagram had exactly one flag (`access_instagram`) which
 * nothing ever checked, plus two orphans. Eighteen distinct features were
 * therefore sold as one all-or-nothing switch that did not work. Each feature
 * now gets its own column so an operator can actually build tiers — inbox on a
 * starter plan, commerce and Lead Ads only on the top one.
 *
 * Every column is guarded with hasColumn(): `access_instagram`,
 * `access_instagram_reposter` and `access_instagram_commerce` already exist on
 * some installs, and re-adding them would fail the migration.
 *
 * Limits default to 0, which is WaDesk's stored convention for "unlimited"
 * (the package form shows 0 as a blank "∞ unlimited" field). Defaulting to a
 * real cap would silently throttle every existing plan the moment this ran.
 */
return new class extends Migration
{
    /** Feature flags — boolean, default false so nothing is granted by accident. */
    private array $features = [
        'access_instagram',
        'access_instagram_inbox',
        'access_instagram_composer',
        'access_instagram_scheduler',
        'access_instagram_broadcast',
        'access_instagram_analytics',
        'access_instagram_posts',
        'access_instagram_comments',
        'access_instagram_automations',
        'access_instagram_auto_comments',
        'access_instagram_templates',
        'access_instagram_discovery',
        'access_instagram_reposter',
        'access_instagram_ads',
        'access_instagram_commerce',
        'access_instagram_leads',
        'access_instagram_orders',
        'access_instagram_ai',
        'access_instagram_flows',
    ];

    /** Numeric caps — 0 means unlimited, matching every other limit column. */
    private array $limits = [
        'instagram_accounts_limit',
        'instagram_monthly_dms_limit',
        'instagram_monthly_broadcast_limit',
        'instagram_scheduled_posts_limit',
        'instagram_automations_limit',
        'instagram_templates_limit',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('packages')) {
            return;
        }

        Schema::table('packages', function (Blueprint $table) {
            foreach ($this->features as $col) {
                if (!Schema::hasColumn('packages', $col)) {
                    $table->boolean($col)->default(false);
                }
            }
            foreach ($this->limits as $col) {
                if (!Schema::hasColumn('packages', $col)) {
                    $table->unsignedInteger($col)->default(0);
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('packages')) {
            return;
        }

        Schema::table('packages', function (Blueprint $table) {
            foreach (array_merge($this->features, $this->limits) as $col) {
                if (Schema::hasColumn('packages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

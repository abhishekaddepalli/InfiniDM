<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meta Ads (Instagram) campaigns — Instaflow standalone edition.
 *
 * A workspace-less port of WaDesk's `meta_campaigns` table (base table +
 * full-CTWA + advanced-targeting + instagram-columns migrations collapsed
 * into one). Differences from WaDesk:
 *
 *   - user_id scoped (Instaflow has no workspaces; workspace_id = 0).
 *   - instagram_account_id — which connected IG account (token + page + ig
 *     identity + ad account) this campaign runs through.
 *   - Destination is Instagram, not WhatsApp: the CTWA ctwa_* columns are
 *     replaced by dm_* (Click-to-Instagram-DM welcome message + CTA). The
 *     WhatsApp phone column is dropped entirely.
 *
 * PII columns (campaign name, creative copy, DM welcome, link URL,
 * targeting) are TEXT + `encrypted` cast on the model so the DB is
 * unreadable at rest — same pattern as InstagramAccount.access_token.
 *
 * Idempotent + guarded so a re-run (or the older IG-columns migration that
 * shipped first) can't collide.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meta_campaigns')) {
            return;
        }

        Schema::create('meta_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            // Which connected InstagramAccount (token + page + ig identity +
            // ad account) this campaign belongs to.
            $table->unsignedBigInteger('instagram_account_id')->nullable()->index();

            // Public Meta entity ids — not PII, kept plain so we can search
            // by them + walk the tree for toggle/delete.
            $table->string('facebook_id')->nullable()->index();   // Campaign id
            $table->string('meta_adset_id', 64)->nullable();
            $table->string('meta_creative_id', 64)->nullable();
            $table->string('meta_ad_id', 64)->nullable();
            $table->string('meta_image_hash', 191)->nullable();
            $table->text('meta_last_error')->nullable();
            $table->timestamp('meta_synced_at')->nullable();

            // Encrypted-at-rest (operator-authored copy + targeting).
            $table->text('name');
            $table->text('creative_title')->nullable();
            $table->text('creative_body')->nullable();
            $table->text('creative_link_url')->nullable();
            $table->text('dm_welcome')->nullable();               // IG-DM ice-breaker / welcome
            $table->longText('targeting')->nullable();            // JSON, encrypted-array

            // Plain-text categorical columns — used in WHERE / GROUP BY.
            $table->string('objective', 32)->default('OUTCOME_ENGAGEMENT');
            $table->string('optimization_goal', 32)->default('MESSAGES');
            $table->string('status', 16)->default('PAUSED')->index();
            $table->string('type', 32)->default('campaign');
            // ig_direct (Click-to-Instagram-DM) | link (traffic) | boost
            $table->string('ad_type', 24)->default('ig_direct');
            $table->string('dm_cta', 32)->nullable();

            // Instagram placement.
            $table->json('publisher_platforms')->nullable();
            $table->json('instagram_positions')->nullable();
            $table->string('instagram_user_id', 64)->nullable();

            // Budget + bidding.
            $table->decimal('daily_budget', 12, 2)->default(0);
            $table->decimal('lifetime_budget', 12, 2)->nullable();
            $table->string('budget_level', 16)->default('adset');
            $table->string('bid_strategy', 40)->nullable();
            $table->unsignedBigInteger('bid_amount')->nullable();  // minor units (cents)
            $table->json('special_ad_categories')->nullable();

            // Metrics + structure counts — not PII.
            $table->json('insights')->nullable();
            $table->unsignedSmallInteger('ad_set_count')->default(1);
            $table->unsignedSmallInteger('ad_count')->default(1);

            $table->string('creative_image')->nullable();          // storage path

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'optimization_goal']);
            $table->index(['user_id', 'meta_ad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_campaigns');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — the Commerce Manager catalog id the merchant syncs products from.
 * Catalog auto-discovery via the linked business is version-fragile, so we let
 * the operator paste the Catalog ID (found in Commerce Manager) exactly like
 * they paste WABA credentials, then pull products from GET /{catalog_id}/products.
 * business_id is stored too when known, for a later auto-discover attempt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('instagram_accounts')) return;
        Schema::table('instagram_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('instagram_accounts', 'catalog_id')) {
                $table->string('catalog_id', 64)->nullable()->after('page_id');
            }
            if (! Schema::hasColumn('instagram_accounts', 'business_id')) {
                $table->string('business_id', 64)->nullable()->after('catalog_id');
            }
            if (! Schema::hasColumn('instagram_accounts', 'catalog_synced_at')) {
                $table->timestamp('catalog_synced_at')->nullable()->after('business_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('instagram_accounts')) return;
        Schema::table('instagram_accounts', function (Blueprint $table) {
            foreach (['catalog_id', 'business_id', 'catalog_synced_at'] as $c) {
                if (Schema::hasColumn('instagram_accounts', $c)) $table->dropColumn($c);
            }
        });
    }
};

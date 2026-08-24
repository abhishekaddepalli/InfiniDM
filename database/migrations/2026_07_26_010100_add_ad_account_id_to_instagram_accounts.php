<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Meta ad account id (act_XXXX, stored WITHOUT the `act_` prefix) the
 * connected Instagram account runs its ads through.
 *
 * Discovered from the account's own token via InstagramAdsClient::discoverAssets()
 * (GET me/adaccounts) and saved on the /instagram/ads/connect flow. Nullable —
 * an account with no ad account simply shows the "Connect your ad account"
 * banner instead of the campaign builder. Idempotent + guarded for re-runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('instagram_accounts')) {
            return;
        }
        if (Schema::hasColumn('instagram_accounts', 'ad_account_id')) {
            return;
        }

        Schema::table('instagram_accounts', function (Blueprint $table) {
            $table->string('ad_account_id', 64)->nullable()->after('page_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('instagram_accounts')) {
            return;
        }
        if (! Schema::hasColumn('instagram_accounts', 'ad_account_id')) {
            return;
        }
        Schema::table('instagram_accounts', function (Blueprint $table) {
            $table->dropColumn('ad_account_id');
        });
    }
};

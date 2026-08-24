<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The WaDesk workspace this Instagram account is linked to.
 *
 * Instaflow is workspace-less (its own accounts are user_id-scoped); a separate
 * WaDesk install ties an IG account to one of ITS workspaces by stamping this
 * column — set either when a WaDesk admin picks the account to link, or when
 * they run the popup OAuth "connect new" flow (InstagramConnectController stamps
 * it in the callback). It is the ONLY thing that scopes an Instaflow IG account
 * to a WaDesk workspace, so the /api/wadesk bridge filters on it when a caller
 * passes ?workspace=. Nullable — a standalone account with no WaDesk link keeps
 * it null and the bridge's un-scoped (return-all) behaviour is unchanged.
 * Idempotent + guarded for re-runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('instagram_accounts')) {
            return;
        }
        if (Schema::hasColumn('instagram_accounts', 'wadesk_workspace_id')) {
            return;
        }

        Schema::table('instagram_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('wadesk_workspace_id')->nullable()->index()->after('ad_account_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('instagram_accounts')) {
            return;
        }
        if (! Schema::hasColumn('instagram_accounts', 'wadesk_workspace_id')) {
            return;
        }
        Schema::table('instagram_accounts', function (Blueprint $table) {
            $table->dropColumn('wadesk_workspace_id');
        });
    }
};

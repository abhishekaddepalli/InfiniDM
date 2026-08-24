<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram Lead-Gen — make instagram_leads source-agnostic.
 *
 * Phase 0 shaped the table for Lead Ads only (leadgen_id unique, NOT NULL).
 * DM-captured leads (a flow funnel: Ask → Capture lead) have no leadgen_id, so:
 *   - leadgen_id becomes nullable (MySQL treats each NULL as distinct, so the
 *     unique index still de-dups real lead-ad ids while allowing many DM leads).
 *   - `source` distinguishes 'lead_ad' vs 'dm'.
 *   - `igsid` ties a DM lead back to the Instagram conversation.
 *   - `status` drives a light lead pipeline; `notes` holds free text.
 * All guarded so a partial / repeated run is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('instagram_leads')) return;

        // leadgen_id → nullable. Raw MODIFY (server is MySQL); guarded so a
        // re-run or a non-MySQL grid can't abort the whole migration batch.
        try {
            DB::statement('ALTER TABLE `instagram_leads` MODIFY `leadgen_id` VARCHAR(64) NULL');
        } catch (\Throwable $e) {
            // Already nullable / different grid — the added columns below still apply.
        }

        Schema::table('instagram_leads', function (Blueprint $table) {
            if (! Schema::hasColumn('instagram_leads', 'source')) {
                $table->string('source', 24)->default('lead_ad')->index()->after('instagram_account_id');
            }
            if (! Schema::hasColumn('instagram_leads', 'igsid')) {
                $table->string('igsid', 64)->nullable()->index()->after('source');
            }
            if (! Schema::hasColumn('instagram_leads', 'status')) {
                $table->string('status', 24)->default('new')->index()->after('phone');
            }
            if (! Schema::hasColumn('instagram_leads', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('instagram_leads')) return;
        Schema::table('instagram_leads', function (Blueprint $table) {
            foreach (['source', 'igsid', 'status', 'notes'] as $col) {
                if (Schema::hasColumn('instagram_leads', $col)) $table->dropColumn($col);
            }
        });
    }
};

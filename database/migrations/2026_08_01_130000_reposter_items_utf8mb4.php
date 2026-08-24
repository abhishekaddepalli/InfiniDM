<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: instagram_repost_items.caption (and the table generally) could not store
 * multi-byte characters — an em-dash / emoji / non-Latin caption threw
 * "Incorrect string value … for column caption" and 500'd the repost AFTER the
 * reel was already published to Instagram (orphaning it). Convert the reposter
 * tables to utf8mb4 so real IG captions (which almost always contain emoji) save.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['instagram_repost_items', 'instagram_reposter_settings'] as $table) {
            if (!Schema::hasTable($table)) continue;
            try {
                DB::statement("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (\Throwable $e) {
                // Non-fatal: log and continue so a partial DB never blocks the deploy.
                \Illuminate\Support\Facades\Log::warning('[utf8mb4] convert failed for ' . $table . ': ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // One-way charset widening — no safe rollback (narrowing could truncate data).
    }
};

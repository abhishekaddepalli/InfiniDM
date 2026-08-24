<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user isolation backfill.
 *
 * Standalone Instaflow has no workspaces, so historically EVERY row was written
 * under workspace_id = 0 and all users saw each other's chats. User::current_workspace_id
 * now resolves to the user's own id, so reads/writes key on workspace_id == user_id.
 * This migration re-stamps the existing workspace-0 rows onto their real owner:
 *
 *   • accounts + orders carry user_id directly.
 *   • messages / contacts / automations / scheduled posts / commerce / reposter carry
 *     instagram_account_id — the owner is that account's user_id.
 *   • flows carry user_id directly.
 *
 * Idempotent: sets workspace_id = owner id everywhere, safe to re-run.
 */
return new class extends Migration {
    public function up(): void
    {
        // 1) Tables that already have user_id → copy it straight across.
        foreach (['instagram_accounts', 'orders', 'flows'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id') && Schema::hasColumn($table, 'workspace_id')) {
                DB::statement("UPDATE `$table` SET `workspace_id` = `user_id` WHERE `user_id` IS NOT NULL AND `user_id` > 0");
            }
        }

        // 2) Tables scoped by instagram_account_id → owner is that account's user_id.
        $viaAccount = [
            'instagram_messages',
            'instagram_contacts',
            'instagram_automations',
            'instagram_scheduled_posts',
            'instagram_commerce',
            'instagram_reposter',
        ];
        foreach ($viaAccount as $table) {
            if (Schema::hasTable($table)
                && Schema::hasColumn($table, 'workspace_id')
                && Schema::hasColumn($table, 'instagram_account_id')) {
                DB::statement("
                    UPDATE `$table` t
                    JOIN `instagram_accounts` a ON a.`id` = t.`instagram_account_id`
                    SET t.`workspace_id` = a.`user_id`
                    WHERE a.`user_id` IS NOT NULL AND a.`user_id` > 0
                ");
            }
        }
    }

    public function down(): void
    {
        // No safe reverse — the old workspace-0 grouping was itself the bug.
    }
};

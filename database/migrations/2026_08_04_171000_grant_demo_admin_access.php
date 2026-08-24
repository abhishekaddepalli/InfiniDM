<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restore admin access for the demo operator on this install (user-requested).
 *  1. Flag admin@mediacity.co.in as a platform admin so /admin stops 404/403-ing.
 *  2. Remove any admin-IP allowlist (empty = no gate) that could lock the area.
 * Guarded + idempotent. Touches only this database.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_admin')) {
            DB::table('users')->where('email', 'admin@mediacity.co.in')->update(['is_admin' => 1]);
        }

        if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'key')) {
            DB::table('settings')->where('key', 'allowed_admin_ips')->delete();
        }
    }

    public function down(): void
    {
        // Access fix — not reversed.
    }
};

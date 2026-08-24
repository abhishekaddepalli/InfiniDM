<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give every existing non-admin user the default Member role, matching what new
 * registrations now do automatically. Platform admins (is_admin = 1) are left
 * with no role_id — they bypass the role system and always have full access.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The Member role = the system role that is NOT the full-access one.
        $memberId = DB::table('roles')
            ->where('is_system', 1)
            ->get()
            ->first(function ($r) {
                $perms = json_decode($r->permissions ?? '[]', true) ?: [];
                return ! in_array('*', $perms, true);
            })?->id;

        if (! $memberId) {
            return; // roles not seeded yet — nothing to backfill against
        }

        DB::table('users')
            ->whereNull('role_id')
            ->where(function ($q) {
                $q->where('is_admin', 0)->orWhereNull('is_admin');
            })
            ->update(['role_id' => $memberId]);
    }

    public function down(): void
    {
        // Non-destructive: leave role assignments in place on rollback.
    }
};

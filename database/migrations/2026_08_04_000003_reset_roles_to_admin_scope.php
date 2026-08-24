<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roles now govern ADMIN-panel access only (the customer app is plan-gated, not
 * role-gated). Realign the two seeded system roles to that model:
 *
 *   • Administrator → keeps "*" (every admin panel).
 *   • Member        → a normal end-user: NO admin access. Its old user-facing
 *                     permission keys (inbox.view, flows.view, …) are no longer
 *                     in the catalog, so we clear them and fix the description.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('is_system', 1)
            ->get()
            ->each(function ($r) {
                $perms = json_decode($r->permissions ?? '[]', true) ?: [];
                $isAdmin = in_array('*', $perms, true);

                if ($isAdmin) {
                    DB::table('roles')->where('id', $r->id)->update([
                        'permissions' => json_encode(['*']),
                        'description'  => 'Full access to every admin panel.',
                    ]);
                } else {
                    // Member / any other non-admin system role → no admin access.
                    DB::table('roles')->where('id', $r->id)->update([
                        'permissions' => json_encode([]),
                        'description'  => 'Standard user — full app access by plan, no admin panel.',
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Non-destructive.
    }
};

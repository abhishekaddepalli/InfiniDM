<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sync `packages` table billing columns (plan_amount, free, is_highlighted)
 * from native columns (price, is_featured).
 *
 * Prevents paid packages with price > 0 from having plan_amount = 0 or free = 1,
 * which caused paid plans to render as "Free" and skip checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packages')) {
            return;
        }

        foreach (DB::table('packages')->get() as $p) {
            $price = (float) ($p->price ?? 0);
            $isFree = $price <= 0;
            DB::table('packages')->where('id', $p->id)->update([
                'plan_amount'    => $price,
                'free'           => $isFree ? 1 : 0,
                'is_highlighted' => (int) ($p->is_featured ?? 0),
            ]);
        }
    }

    public function down(): void
    {
        // No-op
    }
};

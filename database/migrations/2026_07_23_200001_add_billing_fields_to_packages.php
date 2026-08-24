<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enrich `packages` to WaDesk's pricing / checkout shape.
 *
 * Instaflow's native columns (price / currency / interval / is_featured) stay;
 * these mirror them under the names the WaDesk pricing page + payment drivers
 * read. Values are backfilled from the native columns so existing plans work
 * without an admin edit:
 *   plan_amount    ← price
 *   plan_unit      ← interval  (month→months, year→years, else months)
 *   plan_duration  ← 1
 *   is_highlighted ← is_featured
 *   free           ← price <= 0
 *
 * Guarded column-by-column so a re-run is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packages')) {
            return;
        }

        Schema::table('packages', function (Blueprint $t) {
            if (! Schema::hasColumn('packages', 'plan_amount')) {
                $t->decimal('plan_amount', 12, 2)->default(0)->after('price');
            }
            if (! Schema::hasColumn('packages', 'offer_price')) {
                $t->decimal('offer_price', 12, 2)->nullable()->after('plan_amount');
            }
            if (! Schema::hasColumn('packages', 'plan_unit')) {
                $t->string('plan_unit', 16)->default('months')->after('offer_price'); // days|weeks|months|years
            }
            if (! Schema::hasColumn('packages', 'plan_duration')) {
                $t->unsignedInteger('plan_duration')->default(1)->after('plan_unit');
            }
            if (! Schema::hasColumn('packages', 'is_highlighted')) {
                $t->boolean('is_highlighted')->default(false)->after('is_featured');
            }
            if (! Schema::hasColumn('packages', 'free')) {
                $t->boolean('free')->default(false)->after('is_highlighted');
            }
            if (! Schema::hasColumn('packages', 'is_custom_quote')) {
                $t->boolean('is_custom_quote')->default(false)->after('free');
            }
        });

        // Backfill from the native columns.
        foreach (DB::table('packages')->get() as $p) {
            $interval = strtolower((string) ($p->interval ?? 'month'));
            $unit = str_contains($interval, 'year') ? 'years' : 'months';
            DB::table('packages')->where('id', $p->id)->update([
                'plan_amount'    => $p->plan_amount ?? $p->price ?? 0,
                'plan_unit'      => $unit,
                'plan_duration'  => 1,
                'is_highlighted' => (int) ($p->is_featured ?? 0),
                'free'           => ((float) ($p->price ?? 0) <= 0) ? 1 : 0,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('packages')) {
            return;
        }
        Schema::table('packages', function (Blueprint $t) {
            foreach (['plan_amount', 'offer_price', 'plan_unit', 'plan_duration', 'is_highlighted', 'free', 'is_custom_quote'] as $c) {
                if (Schema::hasColumn('packages', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};

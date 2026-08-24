<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** WaDesk's languages table orders by sort_order. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('languages') && ! Schema::hasColumn('languages', 'sort_order')) {
            Schema::table('languages', function (Blueprint $t) {
                $t->unsignedInteger('sort_order')->default(0)->after('is_default');
            });
            // Backfill a stable order by id.
            foreach (DB::table('languages')->orderBy('id')->pluck('id') as $i => $id) {
                DB::table('languages')->where('id', $id)->update(['sort_order' => $i + 1]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('languages') && Schema::hasColumn('languages', 'sort_order')) {
            Schema::table('languages', function (Blueprint $t) {
                $t->dropColumn('sort_order');
            });
        }
    }
};

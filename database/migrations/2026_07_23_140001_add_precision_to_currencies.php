<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** WaDesk's currencies table carries a per-currency decimal precision. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('currencies') && ! Schema::hasColumn('currencies', 'precision')) {
            Schema::table('currencies', function (Blueprint $t) {
                $t->unsignedTinyInteger('precision')->default(2)->after('exchange_rate');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('currencies') && Schema::hasColumn('currencies', 'precision')) {
            Schema::table('currencies', function (Blueprint $t) {
                $t->dropColumn('precision');
            });
        }
    }
};

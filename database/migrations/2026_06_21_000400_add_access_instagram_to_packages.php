<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-plan gate for the Instagram automation channel. Defaults off; admins
 * tick it on the plans that should include Instagram. The platform-wide
 * `instagram_enabled` SystemSetting is the master switch above this.
 *
 * `packages` is a WaDesk host table, so it is absent in the standalone build —
 * without the hasTable() guard this migration aborts the whole suite there and
 * no Instagram tables ever get created.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('packages')) {
            return;
        }

        Schema::table('packages', function (Blueprint $table) {
            if (!Schema::hasColumn('packages', 'access_instagram')) {
                $table->boolean('access_instagram')->default(false)->after('access_analytics');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('packages')) {
            return;
        }

        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'access_instagram')) {
                $table->dropColumn('access_instagram');
            }
        });
    }
};

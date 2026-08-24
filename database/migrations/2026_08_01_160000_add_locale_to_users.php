<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user language choice, set from the header dropdown. Nullable — a null
 * means "use the platform default", resolved in SetLocale.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('locale', 12)->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};

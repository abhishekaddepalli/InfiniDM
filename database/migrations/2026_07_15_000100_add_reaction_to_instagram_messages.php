<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('instagram_messages')) return;
        Schema::table('instagram_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_messages', 'reaction')) {
                // Operator's emoji reaction on this message (nullable = none).
                $table->string('reaction', 40)->nullable()->after('source');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('instagram_messages')) return;
        Schema::table('instagram_messages', function (Blueprint $table) {
            if (Schema::hasColumn('instagram_messages', 'reaction')) {
                $table->dropColumn('reaction');
            }
        });
    }
};

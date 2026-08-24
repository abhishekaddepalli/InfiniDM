<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('instagram_messages')) return;
        Schema::table('instagram_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_messages', 'pinned_at')) {
                // Operator pinned this message in the thread (nullable = not pinned).
                $table->timestamp('pinned_at')->nullable()->after('reaction');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('instagram_messages')) return;
        Schema::table('instagram_messages', function (Blueprint $table) {
            if (Schema::hasColumn('instagram_messages', 'pinned_at')) {
                $table->dropColumn('pinned_at');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('instagram_messages')) return;
        Schema::table('instagram_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_messages', 'meta')) {
                // Interactive payload of a sent message (quick replies / buttons /
                // carousel cards) so the thread can render what was actually sent.
                $table->json('meta')->nullable()->after('pinned_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('instagram_messages')) return;
        Schema::table('instagram_messages', function (Blueprint $table) {
            if (Schema::hasColumn('instagram_messages', 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }
};

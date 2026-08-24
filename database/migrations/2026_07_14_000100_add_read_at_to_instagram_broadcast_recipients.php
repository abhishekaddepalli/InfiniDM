<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('instagram_broadcast_recipients')) return;
        Schema::table('instagram_broadcast_recipients', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_broadcast_recipients', 'read_at')) {
                // Instagram DMs expose READ receipts (messaging_seen) — no delivered.
                $table->timestamp('read_at')->nullable()->after('sent_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('instagram_broadcast_recipients')) return;
        Schema::table('instagram_broadcast_recipients', function (Blueprint $table) {
            if (Schema::hasColumn('instagram_broadcast_recipients', 'read_at')) {
                $table->dropColumn('read_at');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('instagram_messages')) return;
        Schema::table('instagram_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_messages', 'sent_at')) {
                // Instagram's own clock for the message. The inbox is POLLING-based
                // (inboxSync() backfills over the Graph API), so rows land in the
                // API's order, not the order the messages were actually sent —
                // created_at/id are insert order and rank the thread list wrong.
                $table->timestamp('sent_at')->nullable()->after('created_at');
            }
        });
        // Legacy rows have no Instagram time; insert order is the best guess we
        // have for them, so seed it rather than leaving the sort key null.
        DB::table('instagram_messages')->whereNull('sent_at')->update(['sent_at' => DB::raw('created_at')]);

        if (!Schema::hasIndex('instagram_messages', 'igmsg_thread_sent_idx')) {
            Schema::table('instagram_messages', function (Blueprint $table) {
                $table->index(['instagram_account_id', 'igsid', 'sent_at'], 'igmsg_thread_sent_idx');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('instagram_messages')) return;
        if (Schema::hasIndex('instagram_messages', 'igmsg_thread_sent_idx')) {
            Schema::table('instagram_messages', function (Blueprint $table) {
                $table->dropIndex('igmsg_thread_sent_idx');
            });
        }
        Schema::table('instagram_messages', function (Blueprint $table) {
            if (Schema::hasColumn('instagram_messages', 'sent_at')) {
                $table->dropColumn('sent_at');
            }
        });
    }
};

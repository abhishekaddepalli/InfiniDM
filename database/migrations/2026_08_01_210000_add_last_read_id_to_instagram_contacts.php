<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-conversation "read cursor" — the highest instagram_messages.id the
 * operator has seen in this thread. A thread is UNREAD when its newest inbound
 * message id is greater than this. Set to the newest id whenever the thread is
 * opened, so the unread dot clears on open. (The schema had no read column.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('instagram_contacts') && ! Schema::hasColumn('instagram_contacts', 'last_read_id')) {
            Schema::table('instagram_contacts', function (Blueprint $table) {
                $table->unsignedBigInteger('last_read_id')->nullable()->after('igsid');
            });

            // Backfill: start every existing conversation as READ (cursor = its
            // newest message) so introducing this feature doesn't paint every
            // thread unread on first load — only messages that arrive AFTER now
            // will flag. Best-effort; skipped silently if the messages table
            // isn't there yet.
            try {
                if (Schema::hasTable('instagram_messages')) {
                    \Illuminate\Support\Facades\DB::statement(
                        'UPDATE instagram_contacts c SET last_read_id = (
                            SELECT MAX(m.id) FROM instagram_messages m
                            WHERE m.instagram_account_id = c.instagram_account_id AND m.igsid = c.igsid
                        )'
                    );
                }
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('instagram_contacts') && Schema::hasColumn('instagram_contacts', 'last_read_id')) {
            Schema::table('instagram_contacts', function (Blueprint $table) {
                $table->dropColumn('last_read_id');
            });
        }
    }
};

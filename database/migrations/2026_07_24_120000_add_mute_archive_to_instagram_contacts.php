<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-conversation inbox controls (WaDesk team-inbox parity): mute + archive.
 * Instagram's Messaging API has no mute/archive/delete endpoint, so these are
 * LOCAL organizing flags on the contact — the thread list honours them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_contacts', 'muted_at'))    $table->timestamp('muted_at')->nullable()->after('ai_enabled');
            if (!Schema::hasColumn('instagram_contacts', 'archived_at')) $table->timestamp('archived_at')->nullable()->after('muted_at');
        });
    }

    public function down(): void
    {
        Schema::table('instagram_contacts', function (Blueprint $table) {
            foreach (['muted_at', 'archived_at'] as $c) {
                if (Schema::hasColumn('instagram_contacts', $c)) $table->dropColumn($c);
            }
        });
    }
};

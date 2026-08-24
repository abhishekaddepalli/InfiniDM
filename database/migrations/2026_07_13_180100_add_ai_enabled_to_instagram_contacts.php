<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-conversation AI handoff (Team-Inbox style). When true (default) the
 * ai_agent automation auto-replies to this sender; a human taking over the
 * thread (or the operator toggling it off) flips it false so the AI pauses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_contacts', 'ai_enabled')) {
                $table->boolean('ai_enabled')->default(true)->after('avatar_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('instagram_contacts', function (Blueprint $table) {
            if (Schema::hasColumn('instagram_contacts', 'ai_enabled')) {
                $table->dropColumn('ai_enabled');
            }
        });
    }
};

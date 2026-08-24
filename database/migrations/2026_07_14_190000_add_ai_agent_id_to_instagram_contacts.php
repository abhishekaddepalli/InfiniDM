<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-conversation AI agent selection for the Instagram inbox — mirrors the
 * Team Inbox "AI Agent" picker. Points at an instagram_automations row of
 * type=ai_agent; null = the account's default (first active) agent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_contacts', 'ai_agent_id')) {
                $table->unsignedBigInteger('ai_agent_id')->nullable()->after('ai_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('instagram_contacts', function (Blueprint $table) {
            if (Schema::hasColumn('instagram_contacts', 'ai_agent_id')) {
                $table->dropColumn('ai_agent_id');
            }
        });
    }
};

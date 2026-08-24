<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an Instagram flow PAUSE on a Wait node and continue later.
 *
 * The IG runner executes inside Meta's webhook request, so it can't sleep.
 * Instead the Wait node parks the session with `resume_at` and returns; a
 * sweep (no-cron, same policy as inbox escalation) picks up due sessions and
 * walks on from the node after the Wait.
 *
 * NULL resume_at = parked for a user reply (quick-reply tap / Ask answer),
 * which is the pre-existing behaviour and stays untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_flow_sessions', function (Blueprint $t) {
            if (!Schema::hasColumn('instagram_flow_sessions', 'resume_at')) {
                $t->timestamp('resume_at')->nullable()->after('node_id');
                // The sweep queries WHERE resume_at <= now() across all
                // workspaces, so this index is what keeps it cheap.
                $t->index('resume_at', 'ifs_resume_at_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('instagram_flow_sessions', function (Blueprint $t) {
            if (Schema::hasColumn('instagram_flow_sessions', 'resume_at')) {
                $t->dropIndex('ifs_resume_at_idx');
                $t->dropColumn('resume_at');
            }
        });
    }
};

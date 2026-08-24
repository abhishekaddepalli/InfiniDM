<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The paid-plan window lives on the user. Instaflow ships no Workspace model,
 * so where WaDesk kept workspace.plan_ends_at, Instaflow keeps the buyer's plan
 * expiry on users.plan_ends_at alongside the existing package_id + trial_ends_at.
 *
 * SubscriptionService pushes this forward one cycle on each gateway renewal
 * webhook; a later phase's plan gate reads it to decide whether the paid plan is
 * still active (falling back to the default/free plan once it passes). NULL =
 * no active paid window (free / trial only).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'plan_ends_at')) {
            Schema::table('users', function (Blueprint $t) {
                $t->timestamp('plan_ends_at')->nullable()->after('trial_ends_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'plan_ends_at')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropColumn('plan_ends_at');
            });
        }
    }
};

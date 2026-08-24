<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

/**
 * Two things so plan gating actually shows up:
 *
 *  1. Turn ON standalone plan enforcement (InstagramGate::enforcing()). Until
 *     this is set, allows() returns true for everything, so no crown/redirect
 *     ever appears. Admins still bypass.
 *
 *  2. Backfill a trial window for RECENT signups that registered before the
 *     trial fix (they got a plan but no trial_ends_at). Only users created within
 *     the trial length get one, so long-standing accounts are never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            Setting::set('enforce_plans', 1, 'int');
        } catch (\Throwable $e) {
        }

        $trialDays = 14;
        try {
            $trialDays = (int) (Setting::get('registration_trial_days', 14) ?: 14);
        } catch (\Throwable $e) {
        }
        if ($trialDays <= 0) {
            return;
        }

        // Give recent signups the remainder of a trial measured from signup.
        DB::table('users')
            ->whereNotNull('package_id')
            ->whereNull('trial_ends_at')
            ->whereNull('plan_ends_at')
            ->where(function ($q) {
                $q->where('is_admin', 0)->orWhereNull('is_admin');
            })
            ->where('created_at', '>=', now()->subDays($trialDays))
            ->update([
                'trial_ends_at' => DB::raw("DATE_ADD(created_at, INTERVAL {$trialDays} DAY)"),
            ]);
    }

    public function down(): void
    {
        // Non-destructive.
    }
};

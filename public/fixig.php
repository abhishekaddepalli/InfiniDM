<?php
/**
 * One-off fixer — hit  https://mediacity.co.in/instamagic/public/fixig.php
 *
 * 1. Creates (or reuses) a normal user  user@mediacity.co.in / 12345678
 * 2. Moves EVERY Instagram account currently owned by admin@mediacity.co.in
 *    to that user (user_id + workspace_id), and re-stamps each account's
 *    messages / contacts / automations / scheduled posts so the data follows.
 *
 * Idempotent — safe to run more than once. DELETE THIS FILE afterwards.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');

$ADMIN_EMAIL = 'admin@mediacity.co.in';
$USER_EMAIL  = 'user@mediacity.co.in';
$USER_PASS   = '12345678';
$USER_NAME   = 'User';

require __DIR__ . '/../vendor/autoload.php';
$app    = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

function out($m) { echo '<div style="font:14px/1.5 monospace;margin:2px 0">' . $m . '</div>'; }

echo '<div style="max-width:760px;margin:40px auto;padding:24px;border:1px solid #eee;border-radius:12px;font-family:system-ui">';
echo '<h2 style="font-family:system-ui">InstaMagic — move accounts to a user</h2>';

try {
    DB::beginTransaction();

    // 1) Admin
    $admin = DB::table('users')->where('email', $ADMIN_EMAIL)->first();
    if (! $admin) { throw new Exception("Admin {$ADMIN_EMAIL} not found."); }
    out("✔ Admin found — id {$admin->id}");

    // 2) Create/reuse the target user
    $user = DB::table('users')->where('email', $USER_EMAIL)->first();
    if (! $user) {
        $row = [
            'name'              => $USER_NAME,
            'email'             => $USER_EMAIL,
            'password'          => Hash::make($USER_PASS),
            'is_admin'          => 0,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ];
        // Assign the Member role if the roles table exists (non-admin default).
        if (Schema::hasColumn('users', 'role_id') && Schema::hasTable('roles')) {
            $member = DB::table('roles')->where('is_system', 1)->get()
                ->first(function ($r) {
                    $p = json_decode($r->permissions ?? '[]', true) ?: [];
                    return ! in_array('*', $p, true);
                });
            if ($member) { $row['role_id'] = $member->id; }
        }
        // Give them the admin's plan so features work (optional, harmless if null).
        if (Schema::hasColumn('users', 'package_id') && isset($admin->package_id)) {
            $row['package_id'] = $admin->package_id;
        }
        $uid  = DB::table('users')->insertGetId($row);
        $user = DB::table('users')->where('id', $uid)->first();
        out("✔ Created user {$USER_EMAIL} — id {$user->id} (password: {$USER_PASS})");
    } else {
        // Reset the password to the requested one so login is guaranteed.
        DB::table('users')->where('id', $user->id)->update([
            'password'   => Hash::make($USER_PASS),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        out("✔ User {$USER_EMAIL} already existed — id {$user->id} (password reset to {$USER_PASS})");
    }

    // 3) Which accounts belong to the admin? (match user_id OR workspace_id)
    $accIds = DB::table('instagram_accounts')
        ->where(function ($q) use ($admin) {
            $q->where('user_id', $admin->id)->orWhere('workspace_id', $admin->id);
        })
        ->pluck('id')->all();

    if (! $accIds) {
        out("• No Instagram accounts owned by the admin — nothing to move.");
    } else {
        out("• Found " . count($accIds) . " account(s): #" . implode(', #', $accIds));

        // 4) Move the accounts themselves.
        $data = ['workspace_id' => $user->id];
        if (Schema::hasColumn('instagram_accounts', 'user_id')) { $data['user_id'] = $user->id; }
        DB::table('instagram_accounts')->whereIn('id', $accIds)->update($data);
        out("✔ Accounts re-owned to user {$user->id}");

        // 5) Re-stamp per-account data so it shows for the new user, not the admin.
        $perAccount = [
            'instagram_messages', 'instagram_contacts', 'instagram_automations',
            'instagram_scheduled_posts', 'instagram_commerce', 'instagram_reposter',
            'instagram_orders',
        ];
        foreach ($perAccount as $tbl) {
            if (Schema::hasTable($tbl)
                && Schema::hasColumn($tbl, 'instagram_account_id')
                && Schema::hasColumn($tbl, 'workspace_id')) {
                $n = DB::table($tbl)->whereIn('instagram_account_id', $accIds)
                    ->update(['workspace_id' => $user->id]);
                out("  · {$tbl}: {$n} row(s) re-stamped");
            }
        }
    }

    // 6) Clean the admin's LEFTOVER / demo Instagram data so the admin dashboard
    //    goes empty (the demo automations, the 24 auto-sent DMs, the 28 inbox
    //    contacts — all scoped to the admin's own workspace, tied to no real
    //    account now that the accounts were moved). Admin has 0 accounts after the
    //    move, so anything still stamped with the admin's id is orphaned/demo.
    out("• Cleaning the admin's leftover Instagram data …");
    $adminScoped = [
        'instagram_messages', 'instagram_contacts', 'instagram_automations',
        'instagram_scheduled_posts', 'instagram_commerce', 'instagram_reposter',
        'instagram_orders', 'instagram_posts',
    ];
    foreach ($adminScoped as $tbl) {
        if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'workspace_id')) {
            $n = DB::table($tbl)->where('workspace_id', $admin->id)->delete();
            if ($n) { out("  · {$tbl}: deleted {$n} admin row(s)"); }
        }
    }
    // Flows the admin owns (by workspace_id and/or user_id).
    if (Schema::hasTable('flows')) {
        $n = DB::table('flows')->where(function ($q) use ($admin) {
            $q->where('workspace_id', $admin->id);
            if (Schema::hasColumn('flows', 'user_id')) { $q->orWhere('user_id', $admin->id); }
        })->delete();
        if ($n) { out("  · flows: deleted {$n} admin row(s)"); }
    }

    DB::commit();
    echo '<p style="color:#15803d;font-weight:600;font-family:system-ui">Done ✅ &nbsp; Log in as ' . htmlspecialchars($USER_EMAIL) . ' / ' . htmlspecialchars($USER_PASS) . ' — the accounts are now under this user, and the admin dashboard is cleaned.</p>';
    echo '<p style="color:#b91c1c;font-family:system-ui"><strong>Now delete this file (fixig.php) from the server.</strong></p>';
} catch (\Throwable $e) {
    DB::rollBack();
    echo '<p style="color:#b91c1c;font-family:system-ui"><strong>Failed:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre style="font-size:11px;color:#666">' . htmlspecialchars($e->getFile() . ':' . $e->getLine()) . '</pre>';
}
echo '</div>';

<?php
/**
 * One-off — hit  https://mediacity.co.in/instamagic/public/fixdemo.php
 * Re-owns the 3 demo Instagram accounts (and all their per-account data) to
 * user@mediacity.co.in so they scope to that user's dashboard. Also DUMPS the
 * current state so we can see any mismatch. Idempotent. DELETE after use.
 */
error_reporting(E_ALL); ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function out($m) { echo '<div style="font:13px/1.5 monospace;margin:2px 0">' . $m . '</div>'; }
echo '<div style="max-width:900px;margin:36px auto;padding:24px;border:1px solid #eee;border-radius:12px;font-family:system-ui">';
echo '<h2 style="font-family:system-ui">InstaMagic — fix demo ownership</h2>';

try {
    $USER_EMAIL = 'user@mediacity.co.in';
    $user = DB::table('users')->where('email', $USER_EMAIL)->first();
    if (! $user) { throw new Exception("User {$USER_EMAIL} not found."); }
    $uid = (int) $user->id;
    out("✔ {$USER_EMAIL} — id {$uid} (dashboard scopes on workspace_id = {$uid})");

    // Diagnostic: show every Instagram account and where it currently sits.
    out('<b>Current instagram_accounts:</b>');
    foreach (DB::table('instagram_accounts')->get() as $a) {
        out("&nbsp;&nbsp;#{$a->id} @{$a->username} — workspace_id={$a->workspace_id}, user_id={$a->user_id}, status={$a->status}, connected=" . ($a->connected ?? 'n/a'));
    }

    DB::beginTransaction();

    // Target the 3 demo accounts by username (whatever they're currently owned by).
    $demoUsernames = ['bloom.studio', 'urban.threads', 'cafe.lumen'];
    $accIds = DB::table('instagram_accounts')->whereIn('username', $demoUsernames)->pluck('id')->all();

    if (! $accIds) { throw new Exception('No demo accounts found — run seed.php first.'); }

    // Re-own the accounts to the email user + force connected/live.
    DB::table('instagram_accounts')->whereIn('id', $accIds)->update([
        'workspace_id' => $uid,
        'user_id'      => $uid,
        'status'       => 'connected',
        'connected'    => 1,
    ]);
    out('✔ Re-owned ' . count($accIds) . ' accounts (#' . implode(', #', $accIds) . ') to user ' . $uid);

    // Re-stamp every per-account table's workspace_id so scoped queries match.
    $perAccount = [
        'instagram_contacts', 'instagram_messages', 'instagram_automations',
        'instagram_scheduled_posts', 'instagram_commerce', 'instagram_orders',
        'instagram_leads', 'instagram_reposter', 'instagram_posts',
    ];
    foreach ($perAccount as $tbl) {
        if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'instagram_account_id') && Schema::hasColumn($tbl, 'workspace_id')) {
            $n = DB::table($tbl)->whereIn('instagram_account_id', $accIds)->update(['workspace_id' => $uid]);
            if ($n) out("&nbsp;&nbsp;· {$tbl}: {$n} rows re-stamped");
        }
    }

    // Flows + templates are workspace-scoped only.
    foreach (['flows', 'instagram_templates'] as $tbl) {
        if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'workspace_id')) {
            $q = DB::table($tbl);
            if (Schema::hasColumn($tbl, 'flow_type')) $q->where('flow_type', 'instagram');
            elseif ($tbl === 'instagram_templates') { /* all */ }
            // only touch demo-ish rows: flows named Welcome DM/Product FAQ, templates '— demo'
            if ($tbl === 'flows') $q->whereIn('flow_name', ['Welcome DM', 'Product FAQ']);
            if ($tbl === 'instagram_templates') $q->where('name', 'like', '%— demo');
            $n = $q->update(['workspace_id' => $uid] + (Schema::hasColumn($tbl, 'user_id') ? ['user_id' => $uid] : []));
            if ($n) out("&nbsp;&nbsp;· {$tbl}: {$n} rows re-stamped");
        }
    }

    DB::commit();

    $live = DB::table('instagram_accounts')->where('workspace_id', $uid)->where('status', 'connected')->count();
    out("<b>✔ forWorkspace({$uid}) connected accounts = {$live}</b>");
    echo '<p style="color:#15803d;font-weight:600;font-family:system-ui">Done ✅ — refresh the dashboard as ' . htmlspecialchars($USER_EMAIL) . '. Then delete fixdemo.php + seed.php.</p>';
} catch (\Throwable $e) {
    DB::rollBack();
    echo '<p style="color:#b91c1c;font-family:system-ui"><strong>Failed:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '</div>';

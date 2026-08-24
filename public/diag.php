<?php
error_reporting(E_ALL); ini_set('display_errors','1'); header('Content-Type: text/plain; charset=utf-8');
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$u = DB::table('users')->where('email','user@mediacity.co.in')->first();
$ws = (int)$u->id;
echo "user id / wsId = {$ws}\n";
echo "server now = ".date('Y-m-d H:i:s')."\n\n";
foreach (['instagram_contacts','instagram_messages','instagram_automations','instagram_scheduled_posts','instagram_commerce','instagram_orders','instagram_leads'] as $t) {
    try { $c = DB::table($t)->where('workspace_id',$ws)->count(); } catch(\Throwable $e){ $c='ERR:'.$e->getMessage(); }
    echo str_pad($t,32).": {$c}\n";
}
echo "\n-- scheduled_at dates (ws {$ws}) --\n";
foreach (DB::table('instagram_scheduled_posts')->where('workspace_id',$ws)->orderBy('scheduled_at')->limit(20)->get() as $r) {
    echo "  #{$r->id} acc={$r->instagram_account_id} at={$r->scheduled_at} status={$r->status}\n";
}

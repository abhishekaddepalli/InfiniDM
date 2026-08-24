<?php
/**
 * One-off — https://mediacity.co.in/instamagic/public/calfill.php
 * (1) Spreads demo scheduled posts across the whole month (published + scheduled
 *     + a couple failed) so the calendar looks alive. (2) Seeds instagram_products
 *     (the earlier seed used the wrong table name). Idempotent. DELETE after use.
 */
error_reporting(E_ALL); ini_set('display_errors', '1'); header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function ins(string $t, array $row): void {
    if (! Schema::hasTable($t)) return;
    if (Schema::hasColumn($t, 'created_at')) $row['created_at'] = date('Y-m-d H:i:s');
    if (Schema::hasColumn($t, 'updated_at')) $row['updated_at'] = date('Y-m-d H:i:s');
    $data = [];
    foreach ($row as $k => $v) if (Schema::hasColumn($t, $k)) $data[$k] = $v;
    if ($data) { try { DB::table($t)->insert($data); } catch (\Throwable $e) {} }
}
function out($m) { echo '<div style="font:13px/1.5 monospace">' . $m . '</div>'; }
echo '<div style="max-width:760px;margin:36px auto;padding:24px;border:1px solid #eee;border-radius:12px;font-family:system-ui"><h2 style="font-family:system-ui">InstaMagic — calendar + products fill</h2>';

try {
    $u = DB::table('users')->where('email', 'user@mediacity.co.in')->first();
    $ws = (int) $u->id;
    $accIds = DB::table('instagram_accounts')->whereIn('username', ['bloom.studio', 'urban.threads', 'cafe.lumen'])->pluck('id', 'username')->all();
    if (! $accIds) throw new Exception('demo accounts not found');
    DB::beginTransaction();

    $caps = [
        'New drop is live 🌷 tap the link in bio', 'Behind the scenes today ✨', 'Weekend vibes 💐 which is your fave?',
        'Restock alert 🚨 top picks are back', 'Customer love ❤️ thank you!', 'Fresh arrivals this week 🌸',
        'How-to: styling tips 🧵', 'Limited edition — grab it fast ⚡', 'A little Monday motivation ☀️',
        'Meet the maker 👋', 'Studio tour 🎥', 'Flash sale — 24h only 🔥', 'Sneak peek of what\'s next 👀', 'Community spotlight 🌟',
    ];
    // Offsets across the month (negative = past → published, positive = future → scheduled).
    $offsets = [-20, -17, -14, -12, -9, -7, -5, -3, -1, 2, 4, 6, 9, 12];
    $types = ['image', 'reel', 'carousel', 'story'];

    // Reset demo scheduled posts for these accounts, then spread fresh ones.
    DB::table('instagram_scheduled_posts')->whereIn('instagram_account_id', array_values($accIds))->delete();
    $made = 0;
    foreach (array_values($accIds) as $ai => $accId) {
        foreach ($offsets as $k => $off) {
            $when = date('Y-m-d H:i:s', strtotime(($off >= 0 ? "+$off" : $off) . ' days ' . (9 + ($k % 8)) . ':00'));
            // past → published, with a couple failed; future → scheduled.
            $status = $off < 0 ? 'published' : 'scheduled';
            if ($off < 0 && $k % 7 === 3) $status = 'failed';
            ins('instagram_scheduled_posts', [
                'workspace_id' => $ws, 'instagram_account_id' => $accId,
                'image_url' => "https://picsum.photos/seed/cal{$accId}{$k}/800/800",
                'caption' => $caps[($ai * 3 + $k) % count($caps)],
                'media_type' => $types[($k) % 4],
                'scheduled_at' => $when, 'status' => $status,
                'pending' => $status === 'scheduled' ? 1 : 0,
                'media_id' => $status === 'published' ? ('MEDIA' . $accId . $k) : null,
                'last_error' => $status === 'failed' ? 'Media upload timed out' : null,
            ]);
            $made++;
        }
    }
    out("✔ {$made} scheduled posts spread across the month (published + scheduled + failed)");

    // Products — correct table name this time.
    if (Schema::hasTable('instagram_products')
        && ! DB::table('instagram_products')->whereIn('instagram_account_id', array_values($accIds))->exists()) {
        $products = [
            ['Spring Bouquet', 'A fresh seasonal mix of tulips and peonies.', 39.00],
            ['Everyday Roses', 'A dozen long-stem roses, your colour choice.', 29.00],
            ['Deluxe Gift Box', 'Flowers + handwritten card + chocolates.', 59.00],
            ['Mini Succulent', 'A low-maintenance desk companion.', 14.00],
            ['Signature Package', 'Custom florals for your special day.', 249.00],
        ];
        foreach (array_values($accIds) as $accId) {
            foreach ($products as $p => $pr) {
                ins('instagram_products', [
                    'workspace_id' => $ws, 'instagram_account_id' => $accId,
                    'catalog_id' => 'CAT' . $accId, 'retailer_id' => "SKU-$accId-$p", 'product_id' => "PRD$accId$p",
                    'name' => $pr[0], 'description' => $pr[1],
                    'image_url' => "https://picsum.photos/seed/prod$accId$p/600/600",
                    'currency' => 'USD', 'price' => $pr[2], 'availability' => 'in stock',
                    'url' => 'https://example.com/shop', 'synced_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        out('✔ 15 products seeded (instagram_products)');
    } else {
        out('• products already present or table missing — skipped');
    }

    DB::commit();
    echo '<p style="color:#15803d;font-weight:600;font-family:system-ui">Done ✅ — refresh the Calendar + Products pages. Delete calfill.php + diag.php + seed.php + fixdemo.php.</p>';
} catch (\Throwable $e) {
    DB::rollBack();
    echo '<p style="color:#b91c1c"><strong>Failed:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '</div>';

<?php
/**
 * One-off DEMO DATA seeder — hit  https://mediacity.co.in/instamagic/public/seed.php
 *
 * Fills user@mediacity.co.in with 2–3 connected Instagram accounts and realistic
 * demo data across EVERY page/feature: inbox chats, contacts, comment→DM +
 * keyword automations, scheduled posts, products (commerce), orders, leads,
 * flows and templates. Timestamps are spread over the last ~30 days so the
 * dashboard/analytics charts look alive.
 *
 * Idempotent — safe to run more than once (skips an account whose demo data is
 * already present). DELETE THIS FILE from the server afterwards.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');

$USER_EMAIL = 'user@mediacity.co.in';

require __DIR__ . '/../vendor/autoload.php';
$app    = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function out($m) { echo '<div style="font:13px/1.5 monospace;margin:2px 0">' . $m . '</div>'; }
$NOW = date('Y-m-d H:i:s');
$ago = function (int $days, int $hours = 0) {
    return date('Y-m-d H:i:s', strtotime("-{$days} days -{$hours} hours"));
};

/** Insert only the columns that actually exist; stamp timestamps if present. */
function seedRow(string $table, array $row): ?int {
    global $NOW;
    if (! Schema::hasTable($table)) return null;
    if (Schema::hasColumn($table, 'created_at') && ! isset($row['created_at'])) $row['created_at'] = $NOW;
    if (Schema::hasColumn($table, 'updated_at') && ! isset($row['updated_at'])) $row['updated_at'] = $NOW;
    $data = [];
    foreach ($row as $k => $v) {
        if (Schema::hasColumn($table, $k)) $data[$k] = $v;
    }
    if (! $data) return null;
    try { return (int) DB::table($table)->insertGetId($data); }
    catch (\Throwable $e) { return null; }
}

echo '<div style="max-width:820px;margin:36px auto;padding:24px;border:1px solid #eee;border-radius:12px;font-family:system-ui">';
echo '<h2 style="font-family:system-ui">InstaMagic — demo data seeder</h2>';

try {
    DB::beginTransaction();

    $user = DB::table('users')->where('email', $USER_EMAIL)->first();
    if (! $user) { throw new Exception("User {$USER_EMAIL} not found. Run fixig.php first."); }
    $wsId = (int) $user->id;           // standalone: workspace_id == user_id
    out("✔ User {$USER_EMAIL} — id {$wsId}");

    // ── Demo accounts ─────────────────────────────────────────────────────
    $accountsSpec = [
        ['username' => 'bloom.studio',  'name' => 'Bloom Studio',  'followers' => 12400, 'theme' => 'flowers'],
        ['username' => 'urban.threads', 'name' => 'Urban Threads', 'followers' => 8900,  'theme' => 'fashion'],
        ['username' => 'cafe.lumen',    'name' => 'Café Lumen',    'followers' => 5300,  'theme' => 'cafe'],
    ];

    $firstNames = ['Maya','Jonas','Priya','Kai','Aisha','Diego','Lena','Omar','Sara','Noah','Ivy','Ravi','Mia','Leo','Zoe'];
    $lastNames  = ['Reyes','Kern','Nair','West','Khan','Silva','Park','Haddad','Cole','Ahmed','Stone','Iyer','Boone','Frost','Lane'];

    foreach ($accountsSpec as $i => $spec) {
        $idx = $i + 1;
        // Upsert the account (idempotent by username within the workspace).
        $acc = DB::table('instagram_accounts')
            ->where('workspace_id', $wsId)->where('username', $spec['username'])->first();

        if (! $acc) {
            $accId = seedRow('instagram_accounts', [
                'workspace_id'    => $wsId,
                'user_id'         => $wsId,
                'ig_user_id'      => '1789' . str_pad((string) $idx, 12, '0', STR_PAD_LEFT),
                'username'        => $spec['username'],
                'name'            => $spec['name'],
                'profile_pic_url' => "https://i.pravatar.cc/200?img=" . (10 + $idx),
                'login_type'      => 'instagram',
                'status'          => 'connected',
                'connected'       => 1,
                'followers_count' => $spec['followers'],
                'scopes'          => json_encode(['instagram_business_basic', 'instagram_business_manage_messages']),
                'meta_json'       => json_encode(['demo' => true]),
            ]);
            if (! $accId) { out("✖ Account @{$spec['username']} insert FAILED — skipping"); continue; }
            out("✔ Account @{$spec['username']} created — id {$accId}");
        } else {
            $accId = (int) $acc->id;
            // Keep it live even on reseed.
            DB::table('instagram_accounts')->where('id', $accId)->update(['status' => 'connected', 'connected' => 1]);
            out("• Account @{$spec['username']} exists — id {$accId}");
        }

        // Skip per-account seeding if this account already has demo contacts.
        if (DB::table('instagram_contacts')->where('instagram_account_id', $accId)->exists()) {
            out("  · already has data — skipping @{$spec['username']}");
            continue;
        }

        // ── Contacts + inbox chats ────────────────────────────────────────
        $convos = [
            [
                ['in',  'Hi! Do you ship to Canada? 🇨🇦'],
                ['out', 'Hey! Yes — free shipping to Canada on orders over $50. Usually 3–5 days. 📦'],
                ['in',  'Amazing. Is the spring set still in stock?'],
                ['out', 'It is! Want me to drop the link right here so you can grab it? ✨'],
                ['in',  'Yes please! 🙌'],
            ],
            [
                ['in',  'What are your opening hours?'],
                ['out', "We're open 9am–7pm, Mon–Sat. How can I help today? 🙂"],
            ],
            [
                ['in',  'Loved my order, thank you! ❤️'],
                ['out', "That means a lot — thank you! Tag us in a story and we'll reshare 💫"],
            ],
            [
                ['in',  'Do you do custom orders?'],
                ['out', 'We do! Tell me what you have in mind and I\'ll send a quote.'],
                ['in',  'A bouquet for a wedding, around 50 guests.'],
            ],
            [
                ['in',  'Is the discount code still valid?'],
                ['out', 'Yes — BLOOM20 gets you 20% off this week. 🌸'],
            ],
            [
                ['in',  'How much is shipping?'],
                ['out', 'Standard is $4.90, free over $50. Express next-day is $12. 🚚'],
            ],
        ];

        $c = 0;
        foreach ($convos as $ci => $script) {
            $c++;
            $fn = $firstNames[($i * 5 + $ci) % count($firstNames)];
            $ln = $lastNames[($i * 3 + $ci) % count($lastNames)];
            $igsid = '2200' . $accId . str_pad((string) $c, 5, '0', STR_PAD_LEFT);
            $handle = strtolower($fn . '.' . $ln);
            $lastAt = $ago(28 - $ci * 3, $ci);

            seedRow('instagram_contacts', [
                'workspace_id'         => $wsId,
                'instagram_account_id' => $accId,
                'igsid'                => $igsid,
                'username'             => $handle,
                'name'                 => "$fn $ln",
                'avatar_url'           => "https://i.pravatar.cc/150?img=" . (($i * 6 + $ci) % 70 + 1),
                'last_message_at'      => $lastAt,
            ]);

            $mNo = 0;
            foreach ($script as $line) {
                $mNo++;
                seedRow('instagram_messages', [
                    'workspace_id'         => $wsId,
                    'instagram_account_id' => $accId,
                    'igsid'                => $igsid,
                    'direction'            => $line[0],
                    'body'                 => $line[1],
                    'mid'                  => 'demo_' . $accId . '_' . $c . '_' . $mNo,
                    'source'               => 'dm',
                    'created_at'           => date('Y-m-d H:i:s', strtotime($lastAt) + $mNo * 90),
                ]);
            }
        }
        out("  · {$c} contacts + chats");

        // ── Automations (comment→DM + keyword) ────────────────────────────
        seedRow('instagram_automations', [
            'workspace_id' => $wsId, 'instagram_account_id' => $accId,
            'type' => 'comment', 'name' => 'Price → DM link',
            'trigger_keyword' => 'price', 'match_mode' => 'contains', 'contains' => 1,
            'public_reply' => 'Just sent you the details in your DMs! 💌',
            'dm_message'   => 'Hi! Thanks for your interest — here is the link: https://example.com/shop 🌸',
            'is_active' => 1, 'fired_count' => rand(12, 140),
        ]);
        seedRow('instagram_automations', [
            'workspace_id' => $wsId, 'instagram_account_id' => $accId,
            'type' => 'dm', 'name' => 'Keyword: hours',
            'trigger_keyword' => 'hours', 'dm_keyword' => 'hours', 'match_mode' => 'contains', 'contains' => 1,
            'dm_message' => "We're open 9am–7pm, Mon–Sat. See you soon! 🙂",
            'is_active' => 1, 'fired_count' => rand(5, 60),
        ]);
        out("  · 2 automations");

        // ── Scheduled posts ───────────────────────────────────────────────
        $captions = [
            'New spring drop is live 🌷 tap the link in bio',
            'Behind the scenes today ✨ #studio',
            'Weekend vibes — which one is your favourite? 💐',
            'Restock alert 🚨 your top picks are back',
        ];
        foreach ($captions as $k => $cap) {
            seedRow('instagram_scheduled_posts', [
                'workspace_id' => $wsId, 'instagram_account_id' => $accId,
                'image_url'  => "https://picsum.photos/seed/{$spec['theme']}$k/800/800",
                'caption'    => $cap,
                'scheduled_at' => date('Y-m-d H:i:s', strtotime("+".($k + 1)." days")),
                'status'     => 'scheduled', 'pending' => 1,
            ]);
        }
        out("  · 4 scheduled posts");

        // ── Products (commerce) ───────────────────────────────────────────
        $products = [
            ['Spring Bouquet', 'A fresh seasonal mix of tulips and peonies.', 39.00],
            ['Everyday Roses', 'A dozen long-stem roses, your colour choice.', 29.00],
            ['Deluxe Gift Box', 'Flowers + a handwritten card + chocolates.', 59.00],
            ['Mini Succulent', 'A low-maintenance desk companion.', 14.00],
            ['Wedding Package', 'Custom florals for your special day.', 249.00],
        ];
        foreach ($products as $p => $pr) {
            seedRow('instagram_commerce', [
                'workspace_id' => $wsId, 'instagram_account_id' => $accId,
                'retailer_id' => "SKU-$accId-$p", 'product_id' => "PRD$accId$p",
                'name' => $pr[0], 'description' => $pr[1],
                'image_url' => "https://picsum.photos/seed/prod$accId$p/600/600",
                'currency' => 'USD', 'price' => $pr[2],
                'availability' => 'in stock', 'url' => 'https://example.com/shop',
                'synced_at' => $NOW,
            ]);
        }
        out("  · 5 products");

        // ── Orders (+ items) ──────────────────────────────────────────────
        for ($o = 1; $o <= 3; $o++) {
            $fn = $firstNames[($i + $o) % count($firstNames)];
            $ln = $lastNames[($i + $o) % count($lastNames)];
            $pr = $products[($o) % count($products)];
            $total = $pr[2];
            $status = ['paid', 'placed', 'dispatched'][$o % 3];
            $orderId = seedRow('instagram_orders', [
                'workspace_id' => $wsId, 'instagram_account_id' => $accId,
                'igsid' => '2200' . $accId . str_pad((string) $o, 5, '0', STR_PAD_LEFT),
                'order_ref' => 'ORD-' . $accId . '-' . (1000 + $o),
                'status' => $status, 'payment_status' => $status === 'paid' ? 'paid' : 'unpaid',
                'payment_provider' => 'stripe', 'currency' => 'USD',
                'subtotal' => $total, 'total' => $total + 4.9,
                'customer_name' => "$fn $ln", 'customer_phone' => '+1415' . rand(2000000, 8999999),
                'placed_at' => $ago(rand(2, 20)),
                'paid_at'   => $status === 'paid' ? $ago(rand(2, 20)) : null,
            ]);
            if ($orderId) {
                seedRow('instagram_order_items', [
                    'order_id' => $orderId, 'product_id' => "PRD{$accId}0",
                    'retailer_id' => "SKU-$accId-0", 'name' => $pr[0],
                    'qty' => 1, 'unit_price' => $total, 'line_total' => $total,
                ]);
            }
        }
        out("  · 3 orders");

        // ── Leads ─────────────────────────────────────────────────────────
        for ($l = 1; $l <= 4; $l++) {
            $fn = $firstNames[($i * 2 + $l) % count($firstNames)];
            $ln = $lastNames[($i * 2 + $l) % count($lastNames)];
            seedRow('instagram_leads', [
                'workspace_id' => $wsId, 'instagram_account_id' => $accId,
                'leadgen_id' => 'LEAD' . $accId . $l, 'full_name' => "$fn $ln",
                'email' => strtolower("$fn.$ln") . '@example.com',
                'phone' => '+1415' . rand(2000000, 8999999),
                'source' => 'lead_ad',
                'field_data' => json_encode(['interest' => 'spring collection']),
                'lead_created_at' => $ago(rand(1, 25)),
            ]);
        }
        out("  · 4 leads");

        // ── Flows (Instagram) ─────────────────────────────────────────────
        if (! DB::table('flows')->where('workspace_id', $wsId)->where('flow_name', 'Welcome DM')->exists()) {
            seedRow('flows', [
                'user_id' => $wsId, 'workspace_id' => $wsId,
                'flow_name' => 'Welcome DM', 'category' => 'engagement', 'flow_type' => 'instagram',
                'provider' => 'instagram',
                'flow_data' => json_encode(['nodes' => [], 'edges' => [], 'demo' => true]),
                'trigger_kind' => 'keyword', 'trigger_keywords' => json_encode(['hi', 'hello']),
                'is_published' => 1, 'is_active' => 1, 'published_at' => $NOW,
            ]);
            seedRow('flows', [
                'user_id' => $wsId, 'workspace_id' => $wsId,
                'flow_name' => 'Product FAQ', 'category' => 'support', 'flow_type' => 'instagram',
                'provider' => 'instagram',
                'flow_data' => json_encode(['nodes' => [], 'edges' => [], 'demo' => true]),
                'trigger_kind' => 'keyword', 'trigger_keywords' => json_encode(['price', 'shipping']),
                'is_published' => 1, 'is_active' => 1, 'published_at' => $NOW,
            ]);
            out("  · 2 flows");
        }
    }

    // ── Templates (workspace-level, shown on Templates page) ──────────────
    if (Schema::hasTable('instagram_templates')
        && ! DB::table('instagram_templates')->where('workspace_id', $wsId)->where('name', 'Welcome — demo')->exists()) {
        seedRow('instagram_templates', ['workspace_id' => $wsId, 'name' => 'Welcome — demo', 'type' => 'text', 'body' => 'Hi {{name}}! Thanks for reaching out 🌸 How can we help?']);
        seedRow('instagram_templates', ['workspace_id' => $wsId, 'name' => 'Shipping FAQ — demo', 'type' => 'quick_replies', 'body' => 'How can we help with your order?', 'items' => json_encode([['title' => 'Track order', 'payload' => 'TRACK'], ['title' => 'Shipping info', 'payload' => 'SHIP']])]);
        seedRow('instagram_templates', ['workspace_id' => $wsId, 'name' => 'Shop links — demo', 'type' => 'buttons', 'body' => 'Browse our latest collection 👇', 'items' => json_encode([['type' => 'web_url', 'title' => 'Shop now', 'value' => 'https://example.com/shop']])]);
        out("✔ 3 templates");
    }

    DB::commit();
    echo '<p style="color:#15803d;font-weight:600;font-family:system-ui">Done ✅ &nbsp; Log in as ' . htmlspecialchars($USER_EMAIL) . ' — every Instagram page is now populated with demo data.</p>';
    echo '<p style="color:#b91c1c;font-family:system-ui"><strong>Now delete this file (seed.php) from the server.</strong></p>';
} catch (\Throwable $e) {
    DB::rollBack();
    echo '<p style="color:#b91c1c;font-family:system-ui"><strong>Failed:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre style="font-size:11px;color:#666">' . htmlspecialchars($e->getFile() . ':' . $e->getLine()) . '</pre>';
}
echo '</div>';

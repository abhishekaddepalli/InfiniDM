<?php

use App\Models\InstagramTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds a starter library of reusable Instagram DM templates covering ALL THREE
 * shapes the inbox composer understands, so a fresh install has ready-to-use
 * snippets instead of an empty picker:
 *   text          → body only (greetings, hours, shipping, away)
 *   quick_replies → body + items[] = [{title, payload}]        (tap-to-answer chips)
 *   buttons       → body + items[] = [{type:postback|web_url, title, value}]
 *
 * Standalone Instaflow writes everything under workspace_id = 0 (no
 * current_workspace_id column), so these seed there and show for every operator.
 * Idempotent via updateOrCreate keyed on (workspace_id, name) — safe to re-run.
 */
return new class extends Migration
{
    /** All templates live under the standalone workspace. */
    private const WS = 0;

    public function up(): void
    {
        foreach ($this->templates() as $t) {
            InstagramTemplate::updateOrCreate(
                ['workspace_id' => self::WS, 'name' => $t['name']],
                ['type' => $t['type'], 'body' => $t['body'], 'items' => $t['items'] ?? null],
            );
        }
    }

    public function down(): void
    {
        try {
            $names = array_map(fn ($t) => $t['name'], $this->templates());
            InstagramTemplate::where('workspace_id', self::WS)->whereIn('name', $names)->delete();
        } catch (\Throwable $e) {
            // best effort
        }
    }

    /** Quick-reply chip: title doubles as payload when none is given. */
    private function qr(string $title, string $payload): array
    {
        return ['title' => mb_substr($title, 0, 20), 'payload' => $payload];
    }

    /** Generic-template button: web_url carries a URL, postback a payload token. */
    private function btn(string $type, string $title, string $value): array
    {
        return ['type' => $type, 'title' => mb_substr($title, 0, 20), 'value' => $value];
    }

    private function templates(): array
    {
        // A neutral URL the operator swaps for their own link — the app's own
        // public base so it always resolves during testing.
        $shop = 'https://templatecookies.com/instaflow/public';

        return [
            // ── TEXT (5) ─────────────────────────────────────────────
            [
                'name' => 'Welcome message', 'type' => 'text',
                'body' => 'Hi there! Thanks for reaching out to us. How can we help you today?',
            ],
            [
                'name' => 'Business hours', 'type' => 'text',
                'body' => "Thanks for your message! Our team is available Monday to Saturday, 10am to 7pm. We'll get back to you as soon as we're online.",
            ],
            [
                'name' => 'Thanks for your order', 'type' => 'text',
                'body' => "Thank you for your order! We're preparing it now and will keep you updated at every step.",
            ],
            [
                'name' => 'Shipping update', 'type' => 'text',
                'body' => 'Good news! Your order has been shipped and is on its way. Tracking details will follow shortly.',
            ],
            [
                'name' => 'Away message', 'type' => 'text',
                'body' => "We're away at the moment, but your message is important to us. We'll reply within a few hours.",
            ],

            // ── QUICK REPLIES (4) ────────────────────────────────────
            [
                'name' => 'How can we help?', 'type' => 'quick_replies',
                'body' => 'Hi! What can we help you with today?',
                'items' => [
                    $this->qr('Track my order', 'track_order'),
                    $this->qr('Product info', 'product_info'),
                    $this->qr('Talk to a human', 'talk_agent'),
                    $this->qr('See offers', 'see_offers'),
                ],
            ],
            [
                'name' => 'Browse categories', 'type' => 'quick_replies',
                'body' => 'What are you looking for today?',
                'items' => [
                    $this->qr('New arrivals', 'cat_new'),
                    $this->qr('Best sellers', 'cat_best'),
                    $this->qr('Sale items', 'cat_sale'),
                    $this->qr('Support', 'cat_support'),
                ],
            ],
            [
                'name' => 'Rate your experience', 'type' => 'quick_replies',
                'body' => 'How was your experience with us?',
                'items' => [
                    $this->qr('Loved it', 'rate_love'),
                    $this->qr('It was okay', 'rate_ok'),
                    $this->qr('Not great', 'rate_bad'),
                ],
            ],
            [
                'name' => 'Need more help?', 'type' => 'quick_replies',
                'body' => 'Is there anything else we can help with?',
                'items' => [
                    $this->qr('Yes, please', 'help_yes'),
                    $this->qr('No, thanks', 'help_no'),
                ],
            ],

            // ── BUTTONS (3) ──────────────────────────────────────────
            [
                'name' => 'Shop now', 'type' => 'buttons',
                'body' => 'Check out our latest collection.',
                'items' => [
                    $this->btn('web_url', 'Visit shop', $shop),
                    $this->btn('web_url', 'View catalog', $shop),
                    $this->btn('postback', 'Talk to us', 'talk_to_us'),
                ],
            ],
            [
                'name' => 'Book appointment', 'type' => 'buttons',
                'body' => 'Ready to book your slot?',
                'items' => [
                    $this->btn('web_url', 'Book now', $shop),
                    $this->btn('postback', 'Call me back', 'call_me_back'),
                ],
            ],
            [
                'name' => 'Order confirmed', 'type' => 'buttons',
                'body' => 'Your order is confirmed. Thank you for shopping with us!',
                'items' => [
                    $this->btn('web_url', 'Track order', $shop),
                    $this->btn('postback', 'Need help?', 'order_help'),
                ],
            ],
        ];
    }
};

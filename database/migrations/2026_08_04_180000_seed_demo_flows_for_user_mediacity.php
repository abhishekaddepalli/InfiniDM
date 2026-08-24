<?php

use App\Instagram\Models\InstagramFlow;
use App\Models\InstagramAccount;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed the four ready-to-test demo Instagram flows for user@mediacity.co.in's
 * connected account (the earlier demo seed targeted admin@mediacity.co.in, so
 * this user's /flows page came up empty). Same node shapes as the builder would
 * produce; idempotent by (user_id, flow_name).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Target user@mediacity.co.in's connected account; fall back to any.
        $account = null;
        try {
            $userId = optional(User::where('email', 'user@mediacity.co.in')->first())->id;
            if ($userId) {
                $account = InstagramAccount::where('user_id', $userId)
                    ->where('status', 'connected')->orderBy('id')->first();
            }
        } catch (\Throwable $e) {
            // fall through
        }
        $account = $account ?: InstagramAccount::where('status', 'connected')->orderBy('id')->first();
        if (! $account) {
            return; // nothing connected — nothing to seed
        }

        $acctId = (int) $account->id;
        $userId = (int) $account->user_id;
        $wsId   = (int) $account->workspace_id;
        $dev    = 'instagram:' . $acctId;

        $node = fn (string $id, string $type, array $data) => ['id' => $id, 'type' => $type, 'data' => $data, 'position' => ['x' => 0, 'y' => 0]];
        $edge = fn (string $s, string $h, string $t) => ['source' => $s, 'sourceHandle' => $h, 'target' => $t];
        $trig = fn (string $kw) => $node('n_trigger', 'trigger', ['kind' => 'keyword', 'keywords' => $kw, 'keywordMode' => 'contains', 'deviceId' => $dev]);

        $flows = [];

        // 1) BUTTONS — quick replies with two branches.
        $flows[] = [
            'name' => 'Demo — Buttons (menu)', 'kw' => 'menu',
            'nodes' => [
                $trig('menu'),
                $node('n_btn', 'buttons', ['prompt' => 'What do you need?', 'options' => ['Pricing', 'Support'], 'var' => 'choice']),
                $node('n_price', 'message', ['text' => 'Our Pro plan is $19/mo. Reply "signup" to get started.']),
                $node('n_supp', 'message', ['text' => 'Support is here! Reply with your question and a human will jump in.']),
            ],
            'edges' => [
                $edge('n_trigger', 'out', 'n_btn'),
                $edge('n_btn', 'p0', 'n_price'),
                $edge('n_btn', 'p1', 'n_supp'),
            ],
        ];

        // 2) CTA / URL buttons — generic template card.
        $flows[] = [
            'name' => 'Demo — CTA links (links)', 'kw' => 'links',
            'nodes' => [
                $trig('links'),
                $node('n_cta', 'ig_buttons', ['text' => 'Explore us:', 'buttons' => [
                    ['type' => 'web_url', 'title' => 'Visit site', 'url' => 'https://mediacity.co.in'],
                    ['type' => 'web_url', 'title' => 'Shop now',   'url' => 'https://mediacity.co.in/instamagic/public'],
                ]]),
            ],
            'edges' => [
                $edge('n_trigger', 'out', 'n_cta'),
            ],
        ];

        // 3) ATTRIBUTES — Ask questions that persist onto the contact.
        $flows[] = [
            'name' => 'Demo — Attributes (signup)', 'kw' => 'signup',
            'nodes' => [
                $trig('signup'),
                $node('n_ask_name', 'ask', ['prompt' => "What's your name?", 'var' => 'name', 'saveAttribute' => 'name', 'validate' => 'text', 'options' => []]),
                $node('n_ask_email', 'ask', ['prompt' => 'And your email?', 'var' => 'email', 'saveAttribute' => 'email', 'validate' => 'email', 'options' => []]),
                $node('n_thanks', 'message', ['text' => 'Thanks {{name}}! We saved your email {{email}}. We will be in touch.']),
            ],
            'edges' => [
                $edge('n_trigger', 'out', 'n_ask_name'),
                $edge('n_ask_name', 'out', 'n_ask_email'),
                $edge('n_ask_email', 'out', 'n_thanks'),
            ],
        ];

        // 4) PRE-FILL — welcome that reuses the saved {{name}}.
        $flows[] = [
            'name' => 'Demo — Welcome pre-fill (hi)', 'kw' => 'hi',
            'nodes' => [
                $trig('hi'),
                $node('n_welcome', 'message', ['text' => 'Hi {{name}}! Welcome back. Reply "menu" for options or "signup" to register.']),
            ],
            'edges' => [
                $edge('n_trigger', 'out', 'n_welcome'),
            ],
        ];

        foreach ($flows as $f) {
            if (InstagramFlow::where('user_id', $userId)->where('flow_name', $f['name'])->exists()) {
                continue;
            }

            $flow = new InstagramFlow();
            $flow->user_id           = $userId;
            $flow->workspace_id      = $wsId;
            $flow->flow_name         = $f['name'];
            $flow->flow_type         = 'instagram';
            $flow->trigger_kind      = 'keyword';
            $flow->trigger_keywords  = $f['kw'];
            $flow->trigger_device_id = $acctId;
            $flow->is_published      = true;
            $flow->is_active         = true;
            $flow->flow_data         = json_encode(['flowNodes' => $f['nodes'], 'flowEdges' => $f['edges']]);
            $flow->save();
        }
    }

    public function down(): void
    {
        // Demo seed — leave rows in place (removed via the builder if needed).
    }
};

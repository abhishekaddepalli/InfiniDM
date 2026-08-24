<?php

use App\Instagram\Models\InstagramFlow;
use Illuminate\Database\Migrations\Migration;

/**
 * Quick-reply chips don't render for this Instagram-Login account tier even
 * though the API accepts them (200) — a Meta account/app-mode limitation, not a
 * payload issue (verified: correct /me/messages endpoint, exact documented body,
 * messaging_postbacks subscribed). So rebuild the "menu" demo as NUMBERED text
 * options, which work on every account: the bot lists 1/2 and an Ask node
 * branches on the reply.
 */
return new class extends Migration
{
    public function up(): void
    {
        $flow = InstagramFlow::where('flow_name', 'Demo — Buttons (menu)')->first();
        if (!$flow) return;

        $data = $flow->decoded_flow_data;
        $dev  = null;
        foreach (($data['flowNodes'] ?? []) as $n) {
            if (($n['type'] ?? '') === 'trigger') { $dev = $n['data']['deviceId'] ?? null; break; }
        }

        $node = fn ($id, $type, $d) => ['id' => $id, 'type' => $type, 'data' => $d, 'position' => ['x' => 0, 'y' => 0]];
        $edge = fn ($s, $h, $t) => ['source' => $s, 'sourceHandle' => $h, 'target' => $t];

        $nodes = [
            $node('n_trigger', 'trigger', ['kind' => 'keyword', 'keywords' => 'menu', 'keywordMode' => 'contains', 'deviceId' => $dev]),
            $node('n_ask', 'ask', [
                'prompt'   => "What do you need?\n\n1 — Pricing\n2 — Support\n\nReply with 1 or 2.",
                'var'      => 'choice',
                'validate' => 'text',
                'options'  => ['1', '2'],
            ]),
            $node('n_price', 'message', ['text' => 'Our Pro plan is $19/mo. Reply "signup" to get started.']),
            $node('n_supp',  'message', ['text' => 'Support is here — reply with your question and a human will jump in.']),
            $node('n_else',  'message', ['text' => 'Sorry, I did not catch that. Please reply with 1 for Pricing or 2 for Support.']),
        ];
        $edges = [
            $edge('n_trigger', 'out', 'n_ask'),
            $edge('n_ask', 'p0', 'n_price'),   // "1"
            $edge('n_ask', 'p1', 'n_supp'),    // "2"
            $edge('n_ask', 'else', 'n_else'),  // anything else
        ];

        $flow->flow_data = json_encode(['flowNodes' => $nodes, 'flowEdges' => $edges]);
        $flow->is_published = true;
        $flow->is_active = true;
        $flow->save();
    }

    public function down(): void
    {
        // No structural change to reverse; the flow row remains.
    }
};

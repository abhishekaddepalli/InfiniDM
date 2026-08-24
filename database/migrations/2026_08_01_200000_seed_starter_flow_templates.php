<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A few starter Instagram flow templates so /admin/flow-templates isn't empty
 * and users have something to clone from the "Start from a template" gallery on
 * /flows. Node shapes match the builder ({flowNodes,flowEdges}; trigger + message
 * + ig_quick nodes with real x/y positions so they render on clone).
 *
 * updateOrInsert keyed by name — safe to re-run, won't duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flow_templates')) return;

        $now = now();

        $templates = [
            [
                'name'        => 'Welcome DM',
                'description' => 'Greets anyone who messages you for the first time and invites them to say what they need.',
                'category'    => 'general',
                'sort'        => 1,
                'flow_data'   => [
                    'flowNodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'position' => ['x' => 80, 'y' => 140], 'data' => ['kind' => 'keyword', 'keywords' => '', 'keywordMode' => 'any']],
                        ['id' => 'm1', 'type' => 'message', 'position' => ['x' => 420, 'y' => 140], 'data' => ['text' => "Hi {{name}}! Thanks for reaching out. How can I help you today?"]],
                    ],
                    'flowEdges' => [
                        ['id' => 'e1', 'source' => 't1', 'target' => 'm1'],
                    ],
                ],
            ],
            [
                'name'        => 'Pricing FAQ',
                'description' => 'Auto-answers common pricing questions when someone asks about price or cost.',
                'category'    => 'support',
                'sort'        => 2,
                'flow_data'   => [
                    'flowNodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'position' => ['x' => 80, 'y' => 140], 'data' => ['kind' => 'keyword', 'keywords' => 'price, pricing, cost, how much', 'keywordMode' => 'contains']],
                        ['id' => 'm1', 'type' => 'message', 'position' => ['x' => 420, 'y' => 140], 'data' => ['text' => "Great question! Our plans start from a simple monthly price. Reply PLANS and I'll send you the full breakdown."]],
                    ],
                    'flowEdges' => [
                        ['id' => 'e1', 'source' => 't1', 'target' => 'm1'],
                    ],
                ],
            ],
            [
                'name'        => 'Quick menu',
                'description' => 'Shows tappable quick-reply buttons so people can pick what they need in one tap.',
                'category'    => 'general',
                'sort'        => 3,
                'flow_data'   => [
                    'flowNodes' => [
                        ['id' => 't1', 'type' => 'trigger', 'position' => ['x' => 80, 'y' => 140], 'data' => ['kind' => 'keyword', 'keywords' => '', 'keywordMode' => 'any']],
                        ['id' => 'q1', 'type' => 'ig_quick', 'position' => ['x' => 420, 'y' => 140], 'data' => ['text' => 'What can I help you with?', 'options' => [
                            ['title' => 'Pricing', 'payload' => 'PRICING'],
                            ['title' => 'Support', 'payload' => 'SUPPORT'],
                            ['title' => 'Talk to a human', 'payload' => 'HUMAN'],
                        ]]],
                    ],
                    'flowEdges' => [
                        ['id' => 'e1', 'source' => 't1', 'target' => 'q1'],
                    ],
                ],
            ],
        ];

        foreach ($templates as $t) {
            DB::table('flow_templates')->updateOrInsert(
                ['name' => $t['name']],
                [
                    'description' => $t['description'],
                    'category'    => $t['category'],
                    'channel'     => 'instagram',
                    'flow_data'   => json_encode($t['flow_data']),
                    'is_active'   => true,
                    'sort'        => $t['sort'],
                    'updated_at'  => $now,
                    'created_at'  => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};

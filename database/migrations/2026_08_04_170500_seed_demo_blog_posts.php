<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seed a handful of published Instagram-themed blog posts when the blog is
 * empty, so the public /blog page shows real content instead of the
 * "Nothing here yet" empty state. Purely content — no schema or access changes.
 * Idempotent: skips entirely once any post exists.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('blog_posts') || DB::table('blog_posts')->count() > 0) {
            return;
        }

        $brand = (string) (DB::table('settings')->where('key', 'site_name')->value('value') ?: 'InstaMagic');
        $now   = now();

        $posts = [
            [
                'Turn every Instagram comment into a DM conversation',
                'Comment automation is the fastest way to grow your DMs. Here is how to set it up in five minutes.',
                '<p>Every comment on your posts is a raised hand. Someone is interested — and most brands leave that on the table. With comment automation you reply publicly <em>and</em> open a private DM the moment someone comments a keyword.</p><h2>Why it works</h2><p>People who comment are already engaged. Meeting them in the DMs while intent is fresh converts far better than hoping they click a link in bio later.</p><h2>A simple setup</h2><p>Pick a keyword like <strong>“price”</strong>, write a friendly public reply, and attach a DM flow that shares details and asks one qualifying question. That is it — leads on autopilot.</p>',
            ],
            [
                'Story replies are your most underrated sales channel',
                'Story mentions and replies are high-intent signals. Automate them and never miss a warm lead again.',
                '<p>When someone replies to your story, they are talking directly to you — as warm as it gets on Instagram. Yet most of those replies get buried.</p><h2>Automate the follow-up</h2><p>Set a flow that fires on any story reply: thank them, answer the obvious question, and offer the next step. You stay human, but nothing slips through.</p>',
            ],
            [
                'Building your first DM flow: a beginner’s guide',
                'A visual flow builder makes automation approachable. Here is how to design a flow that actually converts.',
                '<p>A good DM flow feels like a helpful conversation, not a robot. Start with the goal — a booking, a sale, a signup — and work backwards.</p><h2>The three-message rule</h2><p>Keep it short: a warm opener, one qualifying question with buttons, and a clear call to action. Every extra step loses people.</p><h2>Test with real replies</h2><p>Run the flow on yourself, then a friend. If it feels natural to you, it will feel natural to your audience.</p>',
            ],
            [
                'From follower to customer: mapping the Instagram funnel',
                'Likes are nice, but conversations close sales. Move people from a like to a lead to a customer.',
                '<p>Instagram growth is not about vanity metrics. It is about moving people down a funnel: <strong>reach → engagement → conversation → conversion</strong>.</p><h2>Capture</h2><p>Comment and story automation turn passive engagement into an open DM.</p><h2>Convert</h2><p>Flows qualify and recommend inside the chat. Retain with broadcasts and an AI agent that never sleeps.</p>',
            ],
            [
                'How an AI agent handles your DMs while you sleep',
                'An always-on assistant trained on your brand can answer, recommend, and hand off to a human when it matters.',
                '<p>You cannot be in the DMs 24/7 — but your customers message at all hours. An AI agent trained on your catalog and FAQs answers instantly, in your voice.</p><h2>Human when it counts</h2><p>The best setups let AI handle the routine and quietly hand off to a person the moment a conversation gets high-value or emotional.</p>',
            ],
            [
                'Broadcast the right way: re-engage without being spammy',
                'Broadcasts keep your audience warm — if you respect the rules. Here is how to do it well.',
                '<p>A broadcast is a privilege, not a megaphone. Segment your audience, send within the messaging window, and lead with value, not just offers.</p><h2>Segment first</h2><p>Message people based on what they did — bought, asked, browsed — and your open and reply rates stay high.</p>',
            ],
        ];

        foreach ($posts as $i => [$title, $excerpt, $body]) {
            DB::table('blog_posts')->insert([
                'title'        => $title,
                'slug'         => Str::slug($title),
                'excerpt'      => $excerpt,
                'body'         => $body,
                'cover_image'  => null,
                'author'       => $brand,
                'status'       => 'published',
                'published_at' => $now->copy()->subDays($i * 4),
                'views'        => ($i + 3) * 47,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Demo content seed — not reversible.
    }
};

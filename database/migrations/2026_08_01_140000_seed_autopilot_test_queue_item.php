<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ONE-OFF smoke test for the scheduled reposter (autopilot) POSTER loop: seed a
 * single `queued` repost item for account 4 pointing at a known-good public
 * vertical MP4. When autopilot is enabled, the Node worker's poster tick should
 * claim + publish it via Graph — proving the scheduled loop end-to-end without
 * depending on the (login-gated) IG scraper. Safe to leave; it just posts once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('instagram_repost_items')) return;

        DB::table('instagram_repost_items')->insert([
            'workspace_id'         => 0,
            'instagram_account_id' => 4,
            'source'               => 'library',
            'source_id'            => 'autopilot-smoke-' . now()->timestamp,
            'source_handle'        => 'autopilot smoke test',
            'caption'              => 'IgDesk autopilot scheduled test 🎬',
            'public_url'           => 'https://videos.pexels.com/video-files/7308301/7308301-hd_1080_1920_24fps.mp4',
            'status'               => 'queued',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    public function down(): void
    {
        // one-off test data; no rollback needed
    }
};

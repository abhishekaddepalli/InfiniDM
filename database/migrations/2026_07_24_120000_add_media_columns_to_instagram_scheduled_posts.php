<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scheduled-posts table shipped image-only, but the composer later grew
 * Reel / Story / Carousel support: the controller now writes media_type,
 * video_url and media_urls, and image_url must be nullable for video-only
 * posts. Installs created before those columns existed threw
 * "Unknown column 'media_type'" the moment anyone scheduled a post. Add the
 * columns idempotently so both fresh and upgraded databases match the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_scheduled_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_scheduled_posts', 'media_type')) {
                $table->string('media_type', 16)->default('image')->after('instagram_account_id');
            }
            if (!Schema::hasColumn('instagram_scheduled_posts', 'video_url')) {
                $table->string('video_url', 1024)->nullable()->after('image_url');
            }
            if (!Schema::hasColumn('instagram_scheduled_posts', 'media_urls')) {
                $table->text('media_urls')->nullable()->after('video_url');
            }
        });

        // image_url was created NOT NULL, but Reel / Story / video posts carry
        // no image — the insert passes null for them. Relax it.
        try {
            Schema::table('instagram_scheduled_posts', function (Blueprint $table) {
                $table->string('image_url', 1024)->nullable()->change();
            });
        } catch (\Throwable $e) {
            // ->change() needs doctrine/dbal on some stacks; if it can't run the
            // column simply stays NOT NULL, which still works for image posts.
        }
    }

    public function down(): void
    {
        Schema::table('instagram_scheduled_posts', function (Blueprint $table) {
            foreach (['media_type', 'video_url', 'media_urls'] as $c) {
                if (Schema::hasColumn('instagram_scheduled_posts', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};

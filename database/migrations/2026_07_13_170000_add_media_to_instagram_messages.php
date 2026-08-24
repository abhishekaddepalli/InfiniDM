<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound (and outbound) Instagram DMs can be image / video / reel / audio /
 * share / story reply — Meta delivers these as message.attachments[].payload.url
 * with a type. Until now the inbox stored only text, so a media DM showed an
 * empty bubble / "[media]". These two columns capture the media so the inbox
 * can render the actual image / video / audio / link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_messages', 'attachment_type')) {
                // image | video | reel | audio | share | story_mention | file
                $table->string('attachment_type', 24)->nullable()->after('body');
            }
            if (!Schema::hasColumn('instagram_messages', 'attachment_url')) {
                $table->text('attachment_url')->nullable()->after('attachment_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('instagram_messages', function (Blueprint $table) {
            foreach (['attachment_type', 'attachment_url'] as $col) {
                if (Schema::hasColumn('instagram_messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

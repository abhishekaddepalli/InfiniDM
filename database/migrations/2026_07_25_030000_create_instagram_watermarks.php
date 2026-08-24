<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Watermark (content protection) configs for Instaflow.
 *
 * One config per Instagram account, PLUS one optional "All accounts" row
 * (instagram_account_id = NULL) that applies to every account the operator owns
 * unless a more-specific per-account row overrides it. When a photo/carousel/
 * story image is published, WatermarkService overlays this watermark onto a COPY
 * of the image and publishes the copy — the operator's original is never touched.
 *
 * Instaflow is workspace-less, so everything is scoped by user_id (the same key
 * the account / flow / files pickers use). The unique index guarantees exactly
 * one config per (user, account) and one "all" row per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('instagram_watermarks')) {
            return;
        }

        Schema::create('instagram_watermarks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->default(0)->index();
            // NULL = "All accounts" (applies to every account the user owns).
            $table->unsignedBigInteger('instagram_account_id')->nullable()->index();

            $table->string('type', 12)->default('image');   // 'image' | 'text'
            $table->string('image_path', 500)->nullable();   // storage path of the watermark image
            $table->string('text', 200)->nullable();         // watermark text (type=text)
            $table->string('text_color', 16)->nullable();    // hex, e.g. #ffffff (type=text)

            // Anchor: tl tc tr / ml mc mr / bl bc br (3x3 grid).
            $table->string('position', 2)->default('br');
            $table->unsignedSmallInteger('size')->default(22);     // % of base image width
            $table->unsignedSmallInteger('opacity')->default(70);  // 0-100
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // One config per account, and one "all" (NULL) row, per user.
            $table->unique(['user_id', 'instagram_account_id'], 'ig_watermark_user_account_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_watermarks');
    }
};

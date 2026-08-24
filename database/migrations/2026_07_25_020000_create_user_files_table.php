<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal media library ("Files") for Instaflow.
 *
 * Every image/video an operator uploads in the Files page — and, going forward,
 * anything the composer uploads or the AI image tool generates — is recorded
 * here so it can be listed, searched and reused. Files themselves live on the
 * active media disk under `instagram-media/` (the same dir the composer already
 * writes to); this table is only the per-owner INDEX, because that dir is not
 * prefixed per user and so cannot be scoped by listing the disk alone.
 *
 * Instaflow scopes everything by user_id (workspace_id is 0); both are kept so
 * the same table also works inside WaDesk (workspace-scoped) if ever needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_files')) {
            return;
        }

        Schema::create('user_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->default(0)->index();
            $table->unsignedBigInteger('workspace_id')->default(0)->index();
            $table->string('folder', 120)->nullable()->index(); // optional grouping
            $table->string('disk', 40)->default('public');
            $table->string('path', 500);            // storage path, e.g. instagram-media/xyz.png
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0); // bytes
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_files');
    }
};

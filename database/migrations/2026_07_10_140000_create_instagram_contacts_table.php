<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-sender identity for the Instagram inbox. IG's messaging webhook only
 * gives us the sender's numeric IGSID — not their @username — so every DM
 * thread was labelled with the connected ACCOUNT's handle, making all
 * conversations look merged. One row per (account, igsid) holds the sender's
 * fetched username/name/avatar so each thread shows WHO messaged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('instagram_contacts')) return;

        Schema::create('instagram_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('instagram_account_id')->index();
            $table->string('igsid', 64);
            $table->string('username', 191)->nullable();
            $table->string('name', 191)->nullable();
            $table->text('avatar_url')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            // One contact per sender per connected account.
            $table->unique(['instagram_account_id', 'igsid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_contacts');
    }
};

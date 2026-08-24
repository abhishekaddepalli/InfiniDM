<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved reusable Instagram DM templates. Instagram has NO Meta-approval template
 * system (unlike WABA) — these are operator-composed snippets the inbox composer
 * inserts: plain text, quick replies, or a button (generic) template. `items`
 * holds the quick-reply / button rows depending on `type`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('name', 120);
            $table->string('type', 20)->default('text'); // text | quick_replies | buttons
            $table->text('body')->nullable();
            // quick_replies: [{title, payload}] ; buttons: [{type:postback|web_url, title, value}]
            $table->json('items')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_templates');
    }
};

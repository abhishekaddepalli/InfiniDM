<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuild the announcements table to WaDesk's exact schema (marquee bar):
 * text, tone, link_url, link_label, is_active, dismissible, starts_at,
 * expires_at, sort_order. The previous title/body/type/audience shape was a
 * stand-in and the table is empty, so a clean recreate is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('announcements');
        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->string('text', 500);
            $t->string('tone', 20)->default('info'); // info | promo | warning | success
            $t->string('link_url', 500)->nullable();
            $t->string('link_label', 64)->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('dismissible')->default(true);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};

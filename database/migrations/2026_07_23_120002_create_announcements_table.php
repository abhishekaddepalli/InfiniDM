<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide notices the operator shows to customers. `type` drives the
 * badge colour (info | success | warning | critical); `audience` scopes who
 * sees it ('all' by default). `starts_at` / `ends_at` NULL = always shown while
 * `is_active` is true.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('body')->nullable();
                $table->string('type')->default('info'); // info | success | warning | critical
                $table->string('audience')->default('all');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};

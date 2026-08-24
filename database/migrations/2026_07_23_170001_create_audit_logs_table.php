<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only record of notable admin/system actions. `meta` holds an optional
 * JSON payload for context; everything else is denormalised so a row remains
 * readable even after the referenced user is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('actor_name')->nullable();
                $table->string('action');
                $table->string('target')->nullable();
                $table->string('ip', 64)->nullable();
                $table->text('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

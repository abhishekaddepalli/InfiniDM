<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable flow templates the operator curates. Customers can clone one of
 * these as a starting point for their own automation flows. `flow_data` holds
 * the serialized node/edge graph (JSON); `is_active` gates catalog visibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flow_templates')) {
            Schema::create('flow_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('description')->nullable();
                $table->string('category')->default('general');
                $table->string('channel')->default('instagram');
                $table->longText('flow_data')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_templates');
    }
};

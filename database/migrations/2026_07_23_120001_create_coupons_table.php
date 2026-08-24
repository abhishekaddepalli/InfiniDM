<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discount coupons the operator hands out against subscription packages.
 * `type` is 'percent' (value = 0-100) or 'fixed' (value = flat amount off).
 * `max_redemptions` NULL = unlimited; `redeemed_count` tracks usage.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('description')->nullable();
                $table->string('type')->default('percent'); // percent | fixed
                $table->decimal('value', 10, 2)->default(0);
                $table->integer('max_redemptions')->nullable();
                $table->integer('redeemed_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};

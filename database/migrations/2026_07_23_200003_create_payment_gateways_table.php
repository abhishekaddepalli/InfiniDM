<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed payment gateway catalog. One row per gateway (Stripe, Razorpay,
 * PayPal, etc.). Credentials are encrypted at rest — the model's
 * setEncryptedCredentials() writes a Crypt ciphertext into the `credentials`
 * TEXT column; only the admin form decrypts them.
 *
 * `slug` is the registry key PaymentGatewayManager uses to resolve the driver.
 * `supported_currencies` filters the gateway to matching order currencies
 * (e.g. Razorpay = INR only). Empty / null = accept any currency.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_gateways')) {
            return;
        }
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(false);
            $table->text('credentials')->nullable();          // encrypted JSON
            $table->string('mode', 16)->default('sandbox');   // sandbox | live
            $table->json('extra_config')->nullable();
            $table->json('supported_currencies')->nullable(); // [] or null = all currencies
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};

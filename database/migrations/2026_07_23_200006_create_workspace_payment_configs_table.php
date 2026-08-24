<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Native in-chat "Direct Pay Method" config (ported from WaDesk's WhatsApp Pay
 * foundation). Stores the merchant's business-manager Direct-Pay-Method name so
 * a later phase can reference it on region-gated in-chat payment sends (India
 * only today). We can't create it via API — it's provider-side; we only store +
 * reference it.
 *
 * provider_config_id is kept as a plain nullable column: Instaflow has no
 * WhatsApp provider-config model to relate to (see WorkspacePaymentConfig).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workspace_payment_configs')) {
            return;
        }
        Schema::create('workspace_payment_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->unsignedBigInteger('provider_config_id')->nullable()->index();
            $table->string('config_name');                  // Direct-Pay-Method name (sent on every charge)
            $table->string('payment_type', 32)->default('upi'); // upi | razorpay | payu
            $table->string('country', 2)->default('IN');
            $table->string('currency', 8)->default('INR');
            $table->string('merchant_category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('meta_json')->nullable();          // encrypted:array cast in the model
            $table->timestamps();

            $table->unique(['workspace_id', 'config_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_payment_configs');
    }
};

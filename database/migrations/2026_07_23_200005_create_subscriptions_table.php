<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring subscriptions. One row per active gateway subscription. The gateway
 * charges the customer automatically every cycle and fires a renewal webhook →
 * SubscriptionService extends the buyer's users.plan_ends_at by one more period.
 * This is how paid plans auto-renew WITHOUT a Laravel scheduler — the gateway is
 * the clock, the webhook is the tick.
 *
 * gateway_subscription_id is the id the gateway returns (Stripe sub_…, PayPal
 * I-…, Razorpay sub_…) — the join key for renewal webhooks.
 *
 * workspace_id is a plain nullable column (Instaflow ships no Workspace model);
 * the real plan owner is user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            return;
        }
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('plan_id')->nullable();               // plan id/slug the buyer is on
            $table->string('gateway');                           // stripe / paypal / razorpay / …
            $table->string('gateway_subscription_id')->nullable()->index();
            $table->string('gateway_plan_id')->nullable();       // price/plan id created on the gateway
            $table->string('gateway_customer_id')->nullable();
            $table->string('billing_cycle')->default('monthly'); // monthly | yearly
            $table->string('status')->default('pending');        // pending|active|past_due|canceled|expired
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->timestamp('current_period_end')->nullable();
            $table->unsignedInteger('renewals_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'gateway_subscription_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

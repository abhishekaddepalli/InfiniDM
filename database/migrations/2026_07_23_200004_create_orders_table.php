<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order = one subscription/plan purchase attempt (NOT the Instagram storefront
 * order — that's `instagram_orders`).
 *
 * Lifecycle: pending → paid → failed → refunded.
 *
 * `currency` + `amount` capture what the customer ACTUALLY agreed to pay (after
 * currency conversion at checkout time); the full breakdown (discount / tax /
 * total) plus a billing snapshot and offline payment-proof columns live here so
 * a single row is the source of truth for invoices + admin review. Merges every
 * column the Order model references (WaDesk spread these across several
 * migrations) into one create.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            return;
        }
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedBigInteger('credit_package_id')->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('coupon_code', 64)->nullable();
            $table->string('billing_period', 16)->nullable();     // monthly | yearly
            $table->boolean('auto_renew')->default(false);
            $table->unsignedBigInteger('gateway_id')->nullable();
            $table->string('gateway_slug', 64)->nullable();
            $table->string('currency', 10);
            $table->decimal('amount', 12, 2);
            $table->decimal('discount_amount', 12, 4)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 4)->default(0);
            $table->decimal('total_amount', 12, 4)->default(0);
            $table->decimal('base_amount_usd', 12, 2)->nullable(); // amount in USD at order time
            $table->decimal('exchange_rate', 16, 6)->nullable();
            $table->string('status', 16)->default('pending');     // pending | paid | failed | refunded
            $table->string('gateway_order_id', 191)->nullable();  // gateway's own id (Razorpay order_id, Stripe session_id, …)
            $table->string('gateway_payment_id', 191)->nullable();
            $table->json('gateway_payload')->nullable();          // full last response from the gateway
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Customer + billing snapshot at order time.
            $table->string('customer_name', 191)->nullable();
            $table->string('customer_email', 191)->nullable();
            $table->string('billing_company', 191)->nullable();
            $table->string('billing_address', 255)->nullable();
            $table->string('billing_city', 120)->nullable();
            $table->string('billing_postal', 32)->nullable();
            $table->string('billing_country', 80)->nullable();
            $table->string('billing_tax_id', 64)->nullable();

            // Offline / bank-transfer payment proof + admin review.
            $table->string('payment_proof_path', 255)->nullable();
            $table->string('payment_reference', 191)->nullable();
            $table->text('proof_note')->nullable();
            $table->timestamp('proof_submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('package_id');
            $table->index('coupon_id');
            $table->index('gateway_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

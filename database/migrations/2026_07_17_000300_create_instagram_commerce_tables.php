<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram Commerce & Lead-Gen — Phase 0 foundation.
 *
 * The DM messaging primitives already exist (InstagramService: generic/product
 * templates, quick replies, ice breakers). What's missing is the BUSINESS data
 * behind them: a synced product catalog, orders, and lead-ad captures. These
 * tables are the store for Phases 1–6. All workspace-scoped, matching the rest
 * of the IG module (workspace_id + user_id like instagram_accounts).
 *
 * Guarded per-table so a partial re-run is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Catalog cache — pulled from the merchant's Commerce Manager catalog
        // via the Catalog API (Phase 1). retailer_id is Meta's product id.
        if (! Schema::hasTable('instagram_products')) {
            Schema::create('instagram_products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->unsignedBigInteger('instagram_account_id')->nullable()->index();
                $table->string('catalog_id', 64)->index();
                $table->string('retailer_id', 128)->index();     // Meta's product/retailer id
                $table->string('product_id', 64)->nullable();    // Meta's numeric product id
                $table->string('name', 512);
                $table->text('description')->nullable();
                $table->string('image_url', 1024)->nullable();
                $table->string('currency', 8)->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->string('availability', 32)->nullable();  // in stock / out of stock
                $table->string('url', 1024)->nullable();
                $table->json('raw')->nullable();                 // full Meta payload
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'catalog_id', 'retailer_id'], 'ig_prod_ws_cat_ret_unique');
            });
        }

        // Orders placed through an IG DM flow (Phase 3). Payment goes out as a
        // LINK (Meta has no in-DM checkout API), so payment_status tracks the
        // gateway webhook back.
        if (! Schema::hasTable('instagram_orders')) {
            Schema::create('instagram_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->unsignedBigInteger('instagram_account_id')->nullable()->index();
                $table->string('igsid', 64)->index();            // the customer (IG-scoped id)
                $table->string('order_ref', 40)->unique();       // our human ref
                $table->string('status', 32)->default('draft');  // draft|placed|paid|dispatched|cancelled
                $table->string('payment_status', 32)->default('unpaid'); // unpaid|paid|failed|refunded
                $table->string('payment_provider', 32)->nullable();
                $table->string('payment_ref', 128)->nullable();
                $table->string('currency', 8)->nullable();
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->text('customer_name')->nullable();
                $table->text('customer_phone')->nullable();
                $table->text('shipping_address')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('placed_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('instagram_order_items')) {
            Schema::create('instagram_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('product_id')->nullable(); // FK to instagram_products
                $table->string('retailer_id', 128)->nullable();
                $table->string('name', 512);
                $table->unsignedInteger('qty')->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('line_total', 12, 2)->default(0);
                $table->json('options')->nullable();             // size/colour/etc.
                $table->timestamps();
            });
        }

        // Lead-ad captures (Phase 5) — from the Page leadgen webhook.
        if (! Schema::hasTable('instagram_leads')) {
            Schema::create('instagram_leads', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->unsignedBigInteger('instagram_account_id')->nullable()->index();
                $table->string('leadgen_id', 64)->unique();      // Meta's lead id (dedup)
                $table->string('page_id', 64)->nullable();
                $table->string('form_id', 64)->nullable()->index();
                $table->string('ad_id', 64)->nullable();
                $table->string('full_name', 191)->nullable();
                $table->string('email', 191)->nullable();
                $table->string('phone', 64)->nullable();
                $table->json('field_data')->nullable();          // all raw fields
                $table->unsignedBigInteger('deal_id')->nullable()->index(); // linked pipeline deal
                $table->timestamp('lead_created_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_order_items');
        Schema::dropIfExists('instagram_orders');
        Schema::dropIfExists('instagram_leads');
        Schema::dropIfExists('instagram_products');
    }
};

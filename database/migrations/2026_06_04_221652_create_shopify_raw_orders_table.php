<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shopify_raw_orders')) {
            return;
        }

        Schema::create('shopify_raw_orders', function (Blueprint $table) {
            $table->id();

            // Order identifiers
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('line_item_id');
            $table->string('order_number')->nullable();

            // Product
            $table->string('product_id')->nullable();
            $table->string('variant_id')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('product_title')->nullable();

            // Quantities & pricing
            $table->integer('quantity')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            // Discount & totals
            $table->text('discount_codes')->nullable();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('net_sales', 12, 2)->default(0);    // per-line: (price×qty) − line discount
            $table->decimal('order_total', 12, 2)->nullable();  // Shopify current_total_price (what customer paid for whole order)
            $table->decimal('order_subtotal', 12, 2)->nullable(); // Shopify subtotal_price (all items before shipping)

            // Order meta
            $table->date('order_date')->nullable()->index();
            $table->string('financial_status')->nullable();
            $table->string('fulfillment_status')->nullable();
            $table->string('source_name')->nullable();
            $table->text('tags')->nullable();

            // Customer
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();

            // Shipping
            $table->string('shipping_city')->nullable();
            $table->string('shipping_country')->nullable();

            // Tracking
            $table->string('tracking_company')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url', 500)->nullable();

            $table->timestamps();

            // Unique key for upsert
            $table->unique(['order_id', 'line_item_id'], 'uq_order_line');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_raw_orders');
    }
};

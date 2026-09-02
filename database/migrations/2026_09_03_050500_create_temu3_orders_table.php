<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Temu 3 Seller Center order export (sheet-only — no Open API).
     * Every upload truncates this table.
     */
    public function up(): void
    {
        if (Schema::hasTable('temu3_orders')) {
            return;
        }

        Schema::create('temu3_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable()->index();
            $table->string('order_status')->nullable();
            $table->string('fulfillment_mode')->nullable();
            $table->string('order_item_id')->nullable();
            $table->string('order_item_status')->nullable();
            $table->text('product_name_by_customer_order')->nullable();
            $table->text('product_name')->nullable();
            $table->string('variation')->nullable();
            $table->string('contribution_sku')->nullable()->index();
            $table->string('sku_id')->nullable();
            $table->integer('quantity_purchased')->nullable();
            $table->integer('quantity_to_ship')->nullable();
            $table->integer('quantity_shipped')->nullable();
            $table->integer('quantity_canceled')->nullable();
            $table->timestamp('purchase_date')->nullable()->index();
            $table->timestamp('latest_shipping_time')->nullable();
            $table->timestamp('latest_delivery_time')->nullable();
            $table->decimal('activity_goods_base_price', 10, 2)->nullable();
            $table->decimal('base_price_total', 10, 2)->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->string('order_settlement_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temu3_orders');
    }
};

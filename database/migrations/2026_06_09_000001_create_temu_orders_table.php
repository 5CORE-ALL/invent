<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Raw order-wise data fetched from Temu bg.order.list.v2.get.
     * One row per sub-order (orderSn) = one SKU line. Full payload kept in raw_json.
     */
    public function up(): void
    {
        if (Schema::hasTable('temu_orders')) {
            return;
        }

        Schema::create('temu_orders', function (Blueprint $table) {
            $table->id();

            // Parent order (parentOrderMap)
            $table->string('parent_order_sn')->nullable();
            $table->integer('parent_order_status')->nullable();
            $table->string('parent_order_status_text')->nullable();
            $table->timestamp('parent_order_time')->nullable();
            $table->timestamp('expect_ship_latest_time')->nullable();
            $table->timestamp('parent_shipping_time')->nullable();
            $table->timestamp('latest_delivery_time')->nullable();
            $table->timestamp('order_update_time')->nullable();
            $table->integer('region_id')->nullable();
            $table->integer('site_id')->nullable();

            // Sub order (orderList[] item)
            $table->string('order_sn')->nullable();
            $table->string('sku_id')->nullable();
            $table->string('goods_id')->nullable();
            $table->string('ext_code')->nullable();        // seller SKU code (productList[].extCode)
            $table->string('product_sku_id')->nullable();
            $table->text('goods_name')->nullable();
            $table->text('spec')->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('original_order_quantity')->nullable();
            $table->integer('canceled_quantity_before_shipment')->nullable();
            $table->integer('order_status')->nullable();
            $table->string('order_status_text')->nullable();
            $table->string('fulfillment_type')->nullable();
            $table->string('order_payment_type')->nullable();
            $table->text('thumb_url')->nullable();
            $table->timestamp('order_shipping_time')->nullable();

            // Full raw payload (sub-order item + parentOrderMap) so nothing is lost
            $table->longText('raw_json')->nullable();

            $table->string('fetch_window')->nullable();     // e.g. L30 / L60 / manual
            $table->timestamp('fetched_at')->nullable();

            $table->timestamps();

            $table->unique('order_sn');
            $table->index('parent_order_sn');
            $table->index('sku_id');
            $table->index('goods_id');
            $table->index('ext_code');
            $table->index('parent_order_time');
            $table->index('order_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temu_orders');
    }
};

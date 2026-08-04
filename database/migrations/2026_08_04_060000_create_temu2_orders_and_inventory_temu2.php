<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Temu 2 Marketplace Manager: orders table + stock mapping column.
     */
    public function up(): void
    {
        if (! Schema::hasTable('temu2_orders')) {
            Schema::create('temu2_orders', function (Blueprint $table) {
                $table->id();

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

                $table->string('order_sn')->nullable();
                $table->string('sku_id')->nullable();
                $table->string('goods_id')->nullable();
                $table->string('ext_code')->nullable();
                $table->string('product_sku_id')->nullable();
                $table->text('goods_name')->nullable();
                $table->text('spec')->nullable();
                $table->integer('quantity')->nullable();
                $table->integer('original_order_quantity')->nullable();
                $table->integer('canceled_quantity_before_shipment')->nullable();
                $table->decimal('order_base_amount', 12, 2)->nullable();
                $table->decimal('order_total_amount', 12, 2)->nullable();
                $table->integer('order_status')->nullable();
                $table->string('order_status_text')->nullable();
                $table->string('fulfillment_type')->nullable();
                $table->string('order_payment_type')->nullable();
                $table->text('thumb_url')->nullable();
                $table->timestamp('order_shipping_time')->nullable();

                $table->longText('raw_json')->nullable();
                $table->longText('amount_raw_json')->nullable();
                $table->timestamp('amount_fetched_at')->nullable();

                $table->string('fetch_window')->nullable();
                $table->timestamp('fetched_at')->nullable();

                $table->string('shopify_order_id', 64)->nullable()->index();
                $table->timestamp('pushed_to_shopify_at')->nullable();
                $table->string('import_status', 32)->nullable()->index();
                $table->string('display_sku', 128)->nullable();

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

        if (Schema::hasTable('product_stock_mappings') && ! Schema::hasColumn('product_stock_mappings', 'inventory_temu2')) {
            Schema::table('product_stock_mappings', function (Blueprint $table) {
                $table->integer('inventory_temu2')->nullable()->after('inventory_temu');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_stock_mappings') && Schema::hasColumn('product_stock_mappings', 'inventory_temu2')) {
            Schema::table('product_stock_mappings', function (Blueprint $table) {
                $table->dropColumn('inventory_temu2');
            });
        }

        Schema::dropIfExists('temu2_orders');
    }
};

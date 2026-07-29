<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Raw TikTok Shop order lines from Order API (orders/search + optional detail).
     * One row per line_item; full order payload kept in raw_json.
     */
    public function up(): void
    {
        if (Schema::hasTable('tiktok_orders')) {
            return;
        }

        Schema::create('tiktok_orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_id', 64)->index();
            $table->string('line_item_id', 64)->nullable();
            $table->string('order_status', 64)->nullable()->index();
            $table->string('line_status', 64)->nullable();

            $table->string('seller_sku', 191)->nullable()->index();
            $table->string('product_id', 64)->nullable()->index();
            $table->string('sku_id', 64)->nullable();
            $table->text('product_name')->nullable();

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('original_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->decimal('seller_discount', 12, 2)->nullable();
            $table->decimal('platform_discount', 12, 2)->nullable();
            $table->string('currency', 16)->nullable();

            $table->decimal('order_amount', 12, 2)->nullable();
            $table->string('fulfillment_type', 64)->nullable();
            $table->string('delivery_type', 64)->nullable();
            $table->string('shipping_provider', 128)->nullable();
            $table->string('buyer_nickname', 191)->nullable();
            $table->string('shop_region', 16)->nullable();

            $table->timestamp('order_created_at')->nullable()->index();
            $table->timestamp('order_updated_at')->nullable();
            $table->timestamp('rts_time')->nullable();
            $table->timestamp('delivery_time')->nullable();
            $table->timestamp('collection_time')->nullable();

            $table->longText('raw_json')->nullable();
            $table->timestamp('fetched_at')->nullable();

            $table->timestamps();

            $table->unique(['order_id', 'line_item_id'], 'tiktok_orders_order_line_unique');
            $table->index(['seller_sku', 'order_created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_orders');
    }
};

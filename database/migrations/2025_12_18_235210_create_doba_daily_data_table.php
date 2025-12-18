<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('doba_daily_data', function (Blueprint $table) {
            $table->id();
            
            // Order info
            $table->string('order_no', 50)->index();
            $table->string('platform_order_no', 100)->nullable();
            $table->timestamp('order_time')->nullable();
            $table->timestamp('pay_time')->nullable();
            $table->string('order_status', 50)->nullable();
            $table->string('order_type', 50)->nullable();
            
            // Period for filtering (l30/l60)
            $table->string('period', 10)->index();
            
            // Product info
            $table->string('item_no', 100)->nullable()->index();
            $table->string('sku', 100)->nullable()->index();
            $table->string('product_name', 500)->nullable();
            $table->integer('quantity')->default(1);
            
            // Pricing
            $table->decimal('item_price', 10, 2)->nullable();
            $table->decimal('total_price', 10, 2)->nullable();
            $table->decimal('shipping_fee', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->decimal('platform_fee', 10, 2)->nullable();
            $table->decimal('anticipated_income', 10, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            
            // Shipping info
            $table->string('receiver_name', 200)->nullable();
            $table->string('receiver_phone', 50)->nullable();
            $table->string('receiver_email', 255)->nullable();
            $table->string('shipping_address1', 255)->nullable();
            $table->string('shipping_address2', 255)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_state', 50)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_country', 50)->nullable();
            
            // Fulfillment
            $table->string('shipping_method', 100)->nullable();
            $table->string('carrier_name', 50)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->timestamp('ship_time')->nullable();
            $table->timestamp('delivery_time')->nullable();
            
            // Warehouse info
            $table->string('warehouse_code', 50)->nullable();
            $table->string('warehouse_name', 100)->nullable();
            
            // Store/Shop info
            $table->string('store_name', 100)->nullable();
            $table->string('platform_name', 50)->nullable();
            
            // Seller info
            $table->string('seller_id', 50)->nullable();
            $table->string('seller_name', 100)->nullable();
            
            // Full JSON data
            $table->text('order_item_json')->nullable();
            $table->text('order_json')->nullable();
            
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['order_no', 'item_no'], 'doba_order_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doba_daily_data');
    }
};

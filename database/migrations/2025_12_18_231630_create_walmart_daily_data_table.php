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
        Schema::create('walmart_daily_data', function (Blueprint $table) {
            $table->id();
            
            // Order info
            $table->string('purchase_order_id', 50)->index();
            $table->string('customer_order_id', 50)->nullable();
            $table->timestamp('order_date')->nullable();
            $table->string('order_line_number', 20);
            
            // Period for filtering (l30/l60)
            $table->string('period', 10)->index();
            
            // Product info
            $table->string('sku', 100)->index();
            $table->string('product_name', 500)->nullable();
            $table->integer('quantity')->default(1);
            $table->string('condition', 50)->nullable();
            
            // Pricing
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->decimal('tax_amount', 10, 2)->nullable();
            
            // Status
            $table->string('status', 50)->nullable();
            $table->timestamp('status_date')->nullable();
            
            // Shipping info
            $table->string('customer_name', 200)->nullable();
            $table->string('shipping_address1', 255)->nullable();
            $table->string('shipping_address2', 255)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_state', 50)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_country', 10)->nullable();
            $table->string('shipping_method', 50)->nullable();
            $table->string('carrier_name', 50)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->timestamp('estimated_delivery_date')->nullable();
            $table->timestamp('estimated_ship_date')->nullable();
            $table->timestamp('ship_date_time')->nullable();
            
            // Fulfillment
            $table->string('fulfillment_option', 50)->nullable();
            $table->string('ship_node_type', 50)->nullable();
            $table->string('ship_node_name', 100)->nullable();
            
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['purchase_order_id', 'order_line_number'], 'walmart_order_line_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('walmart_daily_data');
    }
};

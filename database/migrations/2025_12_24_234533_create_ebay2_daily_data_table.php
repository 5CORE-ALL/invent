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
        Schema::create('ebay2_daily_data', function (Blueprint $table) {
            $table->id();
            
            // Order info
            $table->string('order_id', 50)->index();
            $table->string('legacy_order_id', 50)->nullable();
            $table->timestamp('creation_date')->nullable();
            $table->timestamp('last_modified_date')->nullable();
            $table->string('order_fulfillment_status', 50)->nullable();
            $table->string('order_payment_status', 50)->nullable();
            $table->string('sales_record_reference', 50)->nullable();
            
            // Period for filtering (l30/l60)
            $table->string('period', 10)->index();
            
            // Line item info
            $table->string('line_item_id', 50)->index();
            $table->string('sku', 100)->nullable()->index();
            $table->string('legacy_item_id', 50)->nullable();
            $table->string('legacy_variation_id', 50)->nullable();
            $table->string('title', 500)->nullable();
            $table->integer('quantity')->default(1);
            $table->string('line_item_fulfillment_status', 50)->nullable();
            
            // Pricing
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->decimal('line_item_cost', 10, 2)->nullable();
            $table->decimal('shipping_cost', 10, 2)->nullable();
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->decimal('ebay_collect_and_remit_tax', 10, 2)->nullable();
            
            // Order totals
            $table->decimal('total_price', 10, 2)->nullable();
            $table->decimal('total_fee', 10, 2)->nullable();
            $table->decimal('total_marketplace_fee', 10, 2)->nullable();
            
            // Buyer info
            $table->string('buyer_username', 100)->nullable();
            $table->string('buyer_email', 255)->nullable();
            
            // Shipping address
            $table->string('ship_to_name', 200)->nullable();
            $table->string('shipping_address1', 255)->nullable();
            $table->string('shipping_address2', 255)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_state', 50)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_country', 10)->nullable();
            $table->string('shipping_phone', 50)->nullable();
            
            // Fulfillment
            $table->string('fulfillment_instructions_type', 50)->nullable();
            $table->string('shipping_carrier', 50)->nullable();
            $table->string('shipping_service', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->timestamp('shipped_date')->nullable();
            $table->timestamp('actual_delivery_date')->nullable();
            
            // Cancellation/Refund
            $table->string('cancel_status', 50)->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            
            // Seller info
            $table->string('seller_id', 50)->nullable();
            
            // Full JSON data
            $table->text('line_item_json')->nullable();
            $table->text('order_json')->nullable();
            
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['order_id', 'line_item_id'], 'ebay2_order_line_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay2_daily_data');
    }
};

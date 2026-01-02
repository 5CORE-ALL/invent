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
            $table->string('purchase_order_id')->nullable();
            $table->string('customer_order_id')->nullable();
            $table->datetime('order_date')->nullable();
            $table->string('order_type')->nullable();
            $table->string('mart_id')->nullable();
            $table->boolean('is_replacement')->default(false);
            $table->boolean('is_premium_order')->default(false);
            $table->string('original_customer_order_id')->nullable();
            $table->string('replacement_order_id')->nullable();
            $table->string('seller_order_id')->nullable();
            $table->integer('order_line_number')->nullable();
            $table->string('period')->nullable();
            $table->string('sku')->nullable();
            $table->string('upc')->nullable();
            $table->string('gtin')->nullable();
            $table->string('item_id')->nullable();
            $table->string('product_name')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('condition')->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->string('currency')->nullable();
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->decimal('shipping_charge', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->decimal('fee_amount', 10, 2)->nullable();
            $table->string('status')->nullable();
            $table->json('all_statuses_json')->nullable();
            $table->json('order_line_json')->nullable();
            $table->datetime('status_date')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->string('refund_reason')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('shipping_address1')->nullable();
            $table->string('shipping_address2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->string('shipping_country')->nullable();
            $table->string('shipping_method')->nullable();
            $table->string('ship_method_code')->nullable();
            $table->string('carrier_name')->nullable();
            $table->string('tracking_number')->nullable();
            $table->datetime('estimated_delivery_date')->nullable();
            $table->datetime('estimated_ship_date')->nullable();
            $table->datetime('ship_date_time')->nullable();
            $table->string('fulfillment_option')->nullable();
            $table->string('ship_node_type')->nullable();
            $table->string('ship_node_name')->nullable();
            $table->string('pickup_location')->nullable();
            $table->string('partner_id')->nullable();
            $table->timestamps();
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

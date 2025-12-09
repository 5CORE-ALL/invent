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
        Schema::create('temu_daily_data', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable();
            $table->string('order_status')->nullable();
            $table->string('fulfillment_mode')->nullable();
            $table->string('logistics_service_suggestion')->nullable();
            $table->string('order_item_id')->nullable();
            $table->string('order_item_status')->nullable();
            $table->text('product_name_by_customer_order')->nullable();
            $table->text('product_name')->nullable();
            $table->string('variation')->nullable();
            $table->string('contribution_sku')->nullable();
            $table->string('sku_id')->nullable();
            $table->integer('quantity_purchased')->nullable();
            $table->integer('quantity_shipped')->nullable();
            $table->integer('quantity_to_ship')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_first_name')->nullable();
            $table->string('recipient_last_name')->nullable();
            $table->string('recipient_phone_number')->nullable();
            $table->string('ship_address_1')->nullable();
            $table->string('ship_address_2')->nullable();
            $table->string('ship_address_3')->nullable();
            $table->string('district')->nullable();
            $table->string('ship_city')->nullable();
            $table->string('ship_state')->nullable();
            $table->string('ship_postal_code')->nullable();
            $table->string('ship_country')->nullable();
            $table->timestamp('purchase_date')->nullable();
            $table->timestamp('latest_shipping_time')->nullable();
            $table->timestamp('latest_delivery_time')->nullable();
            $table->string('iphone_serial_number')->nullable();
            $table->string('virtual_email')->nullable();
            $table->decimal('activity_goods_base_price', 10, 2)->nullable();
            $table->decimal('base_price_total', 10, 2)->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->string('order_settlement_status')->nullable();
            $table->text('keep_proof_of_shipment_before_delivery')->nullable();
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('contribution_sku');
            $table->index('purchase_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temu_daily_data');
    }
};

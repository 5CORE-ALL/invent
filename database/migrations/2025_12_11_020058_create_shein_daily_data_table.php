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
        Schema::create('shein_daily_data', function (Blueprint $table) {
            $table->id();
            $table->string('order_type')->nullable();
            $table->string('order_number')->nullable();
            $table->string('exchange_order')->nullable();
            $table->string('order_status')->nullable();
            $table->string('shipment_mode')->nullable();
            $table->string('urged_or_not')->nullable();
            $table->string('is_it_lost')->nullable();
            $table->string('whether_to_stay')->nullable();
            $table->string('order_issue')->nullable();
            $table->text('product_name')->nullable();
            $table->text('product_description')->nullable();
            $table->string('specification')->nullable();
            $table->string('seller_sku')->nullable();
            $table->string('shein_sku')->nullable();
            $table->string('skc')->nullable();
            $table->string('item_id')->nullable();
            $table->string('product_status')->nullable();
            $table->string('inventory_id')->nullable();
            $table->string('exchange_id')->nullable();
            $table->string('reason_for_replacement')->nullable();
            $table->string('product_id_to_be_exchanged')->nullable();
            $table->string('locked_or_not')->nullable();
            $table->dateTime('order_processed_on')->nullable();
            $table->dateTime('collection_deadline')->nullable();
            $table->dateTime('delivery_deadline')->nullable();
            $table->dateTime('delivery_time')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('sellers_package')->nullable();
            $table->string('seller_currency')->nullable();
            $table->decimal('product_price', 10, 2)->nullable();
            $table->decimal('coupon_discount', 10, 2)->nullable();
            $table->decimal('store_campaign_discount', 10, 2)->nullable();
            $table->decimal('commission', 10, 2)->nullable();
            $table->decimal('estimated_merchandise_revenue', 10, 2)->nullable();
            $table->decimal('consumption_tax', 10, 2)->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->integer('quantity')->default(1);
            $table->timestamps();
            
            $table->index('order_number');
            $table->index('seller_sku');
            $table->index('order_processed_on');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shein_daily_data');
    }
};

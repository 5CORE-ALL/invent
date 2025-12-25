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
        Schema::create('mercari_daily_data', function (Blueprint $table) {
            $table->id();
            $table->string('item_id')->nullable();
            $table->dateTime('sold_date')->nullable();
            $table->dateTime('canceled_date')->nullable();
            $table->dateTime('completed_date')->nullable();
            $table->text('item_title')->nullable();
            $table->string('order_status')->nullable();
            $table->string('shipped_to_state')->nullable();
            $table->string('shipped_from_state')->nullable();
            $table->decimal('item_price', 10, 2)->nullable();
            $table->decimal('buyer_shipping_fee', 10, 2)->nullable();
            $table->decimal('seller_shipping_fee', 10, 2)->nullable();
            $table->decimal('mercari_selling_fee', 10, 2)->nullable();
            $table->decimal('payment_processing_fee_charged_to_seller', 10, 2)->nullable();
            $table->decimal('shipping_adjustment_fee', 10, 2)->nullable();
            $table->decimal('penalty_fee', 10, 2)->nullable();
            $table->decimal('net_seller_proceeds', 10, 2)->nullable();
            $table->decimal('sales_tax_charged_to_buyer', 10, 2)->nullable();
            $table->decimal('merchant_fees_charged_to_buyer', 10, 2)->nullable();
            $table->decimal('service_fee_charged_to_buyer', 10, 2)->nullable();
            $table->decimal('buyer_protection_charged_to_buyer', 10, 2)->nullable();
            $table->decimal('payment_processing_fee_charged_to_buyer', 10, 2)->nullable();
            $table->timestamps();
            
            $table->index('item_id');
            $table->index('sold_date');
            $table->index('order_status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mercari_daily_data');
    }
};









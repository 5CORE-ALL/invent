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
        Schema::create('reverb_daily_data', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->date('order_date')->nullable()->index();
            $table->string('period')->nullable()->index();
            $table->string('status')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('display_sku')->nullable();
            $table->text('title')->nullable();
            $table->integer('quantity')->default(1);
            
            // Price fields
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('product_subtotal', 10, 2)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('shipping_amount', 10, 2)->nullable();
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->decimal('tax_rate', 5, 4)->nullable();
            
            // Fee fields
            $table->decimal('selling_fee', 10, 2)->nullable();
            $table->decimal('bump_fee', 10, 2)->nullable();
            $table->decimal('direct_checkout_fee', 10, 2)->nullable();
            $table->decimal('payout_amount', 10, 2)->nullable();
            
            // Buyer info
            $table->string('buyer_id')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_email')->nullable();
            
            // Shipping address
            $table->text('shipping_address')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_country')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->string('buyer_phone')->nullable();
            
            // Order details
            $table->string('payment_method')->nullable();
            $table->string('order_type')->nullable();
            $table->string('order_source')->nullable();
            $table->string('shipping_method')->nullable();
            $table->string('shipment_status')->nullable();
            $table->string('order_bundle_id')->nullable();
            $table->string('product_id')->nullable();
            $table->integer('remaining_inventory')->nullable();
            $table->boolean('local_pickup')->default(false);
            
            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('created_at_api')->nullable();
            $table->timestamps();
            
            $table->index(['order_date', 'sku']);
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reverb_daily_data');
    }
};

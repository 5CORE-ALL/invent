<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L60 sales export — same structure as aliexpress_daily_data (separate table).
     */
    public function up(): void
    {
        if (Schema::hasTable('aliexpress_daily_data_l60')) {
            return;
        }

        Schema::create('aliexpress_daily_data_l60', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable()->index();
            $table->string('order_status')->nullable();
            $table->string('owner')->nullable();
            $table->string('buyer_name')->nullable();
            $table->timestamp('order_date')->nullable()->index();
            $table->timestamp('payment_time')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('supply_price', 10, 2)->nullable();
            $table->decimal('product_total', 10, 2)->nullable();
            $table->decimal('shipping_cost', 10, 2)->nullable();
            $table->decimal('estimated_vat', 10, 2)->nullable();
            $table->string('platform_collects')->nullable();
            $table->decimal('order_amount', 10, 2)->nullable();
            $table->decimal('ddp_tariff', 10, 2)->nullable();
            $table->decimal('store_promotion', 10, 2)->nullable();
            $table->decimal('store_direct_discount', 10, 2)->nullable();
            $table->decimal('platform_coupon', 10, 2)->nullable();
            $table->string('item_id')->nullable();
            $table->text('product_information')->nullable();
            $table->string('ean_code')->nullable();
            $table->string('sku_code')->nullable();
            $table->integer('quantity')->default(1);
            $table->text('order_note')->nullable();
            $table->text('complete_shipping_address')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('buyer_country')->nullable();
            $table->string('state_province')->nullable();
            $table->string('city')->nullable();
            $table->text('detailed_address')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('national_address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('shipping_method')->nullable();
            $table->timestamp('shipping_deadline')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipping_time')->nullable();
            $table->timestamp('buyer_confirmation_time')->nullable();
            $table->string('order_type')->nullable();
            $table->timestamps();

            $table->index('sku_code');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aliexpress_daily_data_l60');
    }
};

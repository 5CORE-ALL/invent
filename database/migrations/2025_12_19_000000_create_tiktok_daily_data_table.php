<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_daily_data', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable()->index();
            $table->string('order_status')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('product_name')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('platform_discount', 12, 2)->default(0);
            $table->decimal('seller_discount', 12, 2)->default(0);
            $table->decimal('platform_commission', 12, 2)->default(0);
            $table->decimal('payment_fee', 12, 2)->default(0);
            $table->decimal('net_sales', 12, 2)->default(0);
            $table->string('buyer_name')->nullable();
            $table->string('shipping_address')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('shipping_provider')->nullable();
            $table->timestamp('order_created_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('period')->nullable()->index();
            $table->timestamps();
            
            $table->unique(['order_id', 'sku'], 'tiktok_order_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_daily_data');
    }
};

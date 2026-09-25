<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('instagram_shop_sold_raw')) {
            return;
        }

        Schema::create('instagram_shop_sold_raw', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date')->nullable()->index();
            $table->string('order_name', 64);
            $table->string('sku', 191)->index();
            $table->string('url', 500)->nullable();
            $table->string('product_title', 500)->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('sold_price', 12, 2)->default(0);
            $table->decimal('gross_sales', 12, 2)->default(0);
            $table->decimal('net_sales', 12, 2)->default(0);
            $table->decimal('discounts', 12, 2)->default(0);
            $table->decimal('returns', 12, 2)->default(0);
            $table->string('sales_channel', 64)->default('Facebook & Instagram');
            $table->timestamps();

            $table->unique(['order_name', 'sku'], 'uq_ig_shop_sold_order_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_shop_sold_raw');
    }
};

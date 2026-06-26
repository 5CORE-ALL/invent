<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('facebook_marketplace_sales')) {
            return;
        }

        Schema::create('facebook_marketplace_sales', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 100)->index();
            $table->string('sku', 100)->index();
            $table->unsignedInteger('qty_sold')->default(0);
            $table->decimal('sold_price', 12, 2)->default(0);
            $table->date('order_date')->nullable()->index();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['order_number', 'sku'], 'uq_fb_market_order_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_marketplace_sales');
    }
};

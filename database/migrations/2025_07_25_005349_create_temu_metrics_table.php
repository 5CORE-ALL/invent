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
        Schema::create('temu_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->nullable();
            $table->string('sku_id', 100)->nullable();
            $table->bigInteger('quantity_purchased_l60')->nullable();
            $table->bigInteger('quantity_purchased_l30')->nullable();
            $table->string('goods_id', 100)->nullable();
            $table->decimal('base_price', 5)->nullable();
            $table->bigInteger('product_impressions_l30')->nullable();
            $table->bigInteger('product_clicks_l30')->nullable();
            $table->bigInteger('product_impressions_l60')->nullable();
            $table->bigInteger('product_clicks_l60')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temu_metrics');
    }
};

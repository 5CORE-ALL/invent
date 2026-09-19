<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('temu3_metrics')) {
            return;
        }

        Schema::create('temu3_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->nullable()->index();
            $table->string('sku_id')->nullable()->index();
            $table->string('goods_id')->nullable()->index();
            $table->decimal('base_price', 12, 2)->nullable();
            $table->integer('quantity')->nullable();
            $table->string('listing_status')->nullable()->index();
            $table->integer('quantity_purchased_l30')->nullable();
            $table->integer('quantity_purchased_l60')->nullable();
            $table->unsignedBigInteger('product_impressions_l30')->nullable();
            $table->unsignedBigInteger('product_clicks_l30')->nullable();
            $table->unsignedBigInteger('product_impressions_l60')->nullable();
            $table->unsignedBigInteger('product_clicks_l60')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temu3_metrics');
    }
};

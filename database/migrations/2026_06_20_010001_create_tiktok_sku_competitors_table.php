<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tiktok_sku_competitors')) {
            return;
        }

        Schema::create('tiktok_sku_competitors', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->string('product_id')->index();
            $table->string('marketplace')->default('tiktok');
            $table->string('region', 8)->default('US');
            $table->text('product_title')->nullable();
            $table->text('product_link')->nullable();
            $table->string('image', 1024)->nullable();
            $table->string('seller_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('min_price', 10, 2)->nullable();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('reviews')->nullable();
            $table->integer('sold_count')->nullable();
            $table->timestamps();

            $table->unique(['sku', 'product_id', 'marketplace', 'region'], 'tiktok_sku_comp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_sku_competitors');
    }
};

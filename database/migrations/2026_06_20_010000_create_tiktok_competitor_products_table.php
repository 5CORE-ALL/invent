<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tiktok_competitor_products')) {
            return;
        }

        Schema::create('tiktok_competitor_products', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace')->default('tiktok');
            $table->string('region', 8)->default('US');
            $table->string('search_query')->index();
            // TikTok Shop product identifier (analogous to ASIN).
            $table->string('product_id')->index();
            $table->text('product_link')->nullable();
            $table->text('title')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('seller_name')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            // Some providers return min/max alongside the listed price (variant range).
            $table->decimal('min_price', 10, 2)->nullable();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('reviews')->nullable();
            // sold_count is TikTok Shop's equivalent of Amazon "bought in last month".
            $table->integer('sold_count')->nullable();
            // Position within the keyword search results (page-based).
            $table->integer('position')->nullable();
            $table->string('image', 1024)->nullable();
            $table->timestamps();

            $table->index(['search_query', 'region']);
            $table->index(['marketplace', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_competitor_products');
    }
};

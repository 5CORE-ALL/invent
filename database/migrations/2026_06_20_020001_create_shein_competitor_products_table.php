<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors `amazon_competitor_asins` for the Shein repricer search
 * (/repricer/shein-search). Each row is one product surfaced for a
 * keyword via SerpApi's google_shopping engine, filtered to Shein
 * merchants only. `product_id` is Google Shopping's catalog id —
 * Shein itself does not expose its SKU through Google Shopping, so
 * that catalog id is the stable de-dup key for a search.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shein_competitor_products')) {
            return;
        }

        Schema::create('shein_competitor_products', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace')->default('shein');
            $table->string('search_query')->index();
            $table->string('product_id')->index();
            $table->text('product_link')->nullable();
            $table->text('title')->nullable();
            $table->string('source')->nullable();
            $table->string('seller_name')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('extracted_old_price', 10, 2)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('reviews')->nullable();
            $table->integer('position')->nullable();
            $table->text('image')->nullable();
            $table->json('delivery')->nullable();
            $table->json('extensions')->nullable();
            $table->timestamps();

            $table->index(['search_query', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shein_competitor_products');
    }
};

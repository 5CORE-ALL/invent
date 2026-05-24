<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_competitor_items')) {
            Schema::create('google_competitor_items', function (Blueprint $table) {
                $table->id();
                $table->string('marketplace', 50)->default('google');
                $table->string('search_query', 255)->index();
                $table->string('product_id', 100)->index();
                $table->string('source', 255)->nullable();
                $table->text('title')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->text('link')->nullable();
                $table->text('image')->nullable();
                $table->decimal('rating', 4, 2)->nullable();
                $table->unsignedInteger('reviews')->nullable();
                $table->integer('position')->nullable();
                $table->timestamps();

                $table->unique(['search_query', 'product_id', 'source'], 'google_comp_items_query_product_source_unique');
            });
        }

        if (! Schema::hasTable('google_sku_competitors')) {
            Schema::create('google_sku_competitors', function (Blueprint $table) {
                $table->id();
                $table->string('sku', 255)->index();
                $table->string('product_id', 100)->index();
                $table->string('source', 255)->nullable();
                $table->string('marketplace', 50)->default('google');
                $table->string('search_query', 255)->nullable();
                $table->text('product_link')->nullable();
                $table->text('product_title')->nullable();
                $table->text('image')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->decimal('rating', 4, 2)->nullable();
                $table->unsignedInteger('reviews')->nullable();
                $table->timestamps();

                $table->unique(['sku', 'product_id', 'source'], 'google_sku_comp_sku_product_source_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sku_competitors');
        Schema::dropIfExists('google_competitor_items');
    }
};

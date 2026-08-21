<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_listing_prices')) {
            return;
        }

        Schema::create('store_listing_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_product_id')->index();
            $table->string('listing_key', 191)->unique();
            $table->string('sku', 255)->index();
            $table->string('parent_sku', 255)->nullable()->index();
            $table->string('slug', 500)->nullable();
            $table->string('name', 500)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('special_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->text('formatted_price')->nullable();
            $table->string('special_price_type', 50)->nullable();
            $table->timestamp('special_price_start')->nullable();
            $table->timestamp('special_price_end')->nullable();
            $table->string('currency', 8)->nullable();
            $table->boolean('is_variant')->default(false);
            $table->boolean('is_default_variant')->nullable();
            $table->unsignedBigInteger('store_variant_id')->nullable()->index();
            $table->string('variant_uid', 191)->nullable();
            $table->unsignedBigInteger('product_master_id')->nullable()->index();
            $table->boolean('matched')->default(false)->index();
            $table->json('raw_json')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_listing_prices');
    }
};

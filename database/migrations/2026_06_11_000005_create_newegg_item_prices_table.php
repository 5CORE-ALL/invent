<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newegg_item_prices')) {
            return;
        }

        // Separate price table — one row per (SKU, destination country).
        Schema::create('newegg_item_prices', function (Blueprint $table) {
            $table->id();

            $table->string('seller_part_number', 100)->index();
            $table->string('newegg_item_number', 60)->nullable()->index();
            $table->string('country_code', 10)->default('USA');
            $table->string('currency', 10)->nullable();
            $table->tinyInteger('active')->nullable();
            $table->decimal('msrp', 12, 2)->nullable();
            $table->decimal('map', 12, 2)->nullable();
            $table->tinyInteger('checkout_map')->nullable();
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->tinyInteger('enable_free_shipping')->nullable();
            $table->string('on_promotion', 50)->nullable();
            $table->integer('limit_quantity')->nullable();
            $table->json('raw_json')->nullable();

            $table->timestamps();

            $table->unique(['seller_part_number', 'country_code'], 'newegg_price_sku_country_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newegg_item_prices');
    }
};

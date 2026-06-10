<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consolidate the earlier split price/inventory tables into one.
        Schema::dropIfExists('newegg_item_prices');
        Schema::dropIfExists('newegg_item_inventory');

        if (Schema::hasTable('newegg_pricing')) {
            return;
        }

        // One row per (SKU, destination country) holding BOTH price and inventory.
        Schema::create('newegg_pricing', function (Blueprint $table) {
            $table->id();

            $table->string('seller_part_number', 100)->index();
            $table->string('newegg_item_number', 60)->nullable()->index();
            $table->string('country_code', 10)->default('USA');

            // Price fields
            $table->string('currency', 10)->nullable();
            $table->tinyInteger('active')->nullable();
            $table->decimal('msrp', 12, 2)->nullable();
            $table->decimal('map', 12, 2)->nullable();
            $table->tinyInteger('checkout_map')->nullable();
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->tinyInteger('enable_free_shipping')->nullable();
            $table->string('on_promotion', 50)->nullable();
            $table->integer('limit_quantity')->nullable();

            // Inventory fields
            $table->integer('available_quantity')->nullable();
            $table->string('fulfillment_option', 20)->nullable();
            $table->tinyInteger('inventory_active')->nullable();
            $table->json('warehouse_allocation')->nullable();

            // Raw payloads for traceability
            $table->json('price_raw_json')->nullable();
            $table->json('inventory_raw_json')->nullable();

            $table->timestamps();

            $table->unique(['seller_part_number', 'country_code'], 'newegg_pricing_sku_country_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newegg_pricing');
    }
};

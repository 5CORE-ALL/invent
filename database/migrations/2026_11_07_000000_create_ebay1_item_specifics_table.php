<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per eBay 1 SKU: the item specifics and category pulled once from the live listing.
     */
    public function up(): void
    {
        if (Schema::hasTable('ebay1_item_specifics')) {
            return;
        }

        Schema::create('ebay1_item_specifics', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->unique();
            $table->string('item_id', 64)->nullable();
            $table->string('category_id', 32)->nullable();
            $table->string('category_path', 500)->nullable();
            $table->json('specifics')->nullable();
            $table->timestamp('pulled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay1_item_specifics');
    }
};

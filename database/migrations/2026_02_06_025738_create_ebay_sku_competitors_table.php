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
        Schema::create('ebay_sku_competitors', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 255)->index();
            $table->string('item_id', 100)->index();
            $table->string('marketplace', 50)->default('ebay');
            $table->text('product_link')->nullable();
            $table->text('product_title')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('shipping_cost', 10, 2)->nullable()->default(0);
            $table->decimal('total_price', 10, 2)->nullable();
            $table->timestamps();
            
            // Composite unique index for sku and item_id
            $table->unique(['sku', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_sku_competitors');
    }
};

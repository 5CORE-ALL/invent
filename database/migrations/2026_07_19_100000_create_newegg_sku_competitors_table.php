<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newegg_sku_competitors')) {
            return;
        }

        Schema::create('newegg_sku_competitors', function (Blueprint $table) {
            $table->id();
            // Shortened for utf8mb4 composite unique (same pattern as tiktok_sku_competitors).
            $table->string('sku', 191)->index();
            $table->string('product_id', 64)->index(); // Newegg Item # e.g. N82E168...
            $table->string('marketplace', 50)->default('newegg');
            $table->text('product_title')->nullable();
            $table->text('product_link')->nullable();
            $table->string('image', 1024)->nullable();
            $table->string('seller_name')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('shipping_cost', 10, 2)->nullable()->default(0);
            $table->timestamps();

            $table->unique(['sku', 'product_id', 'marketplace'], 'newegg_sku_comp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newegg_sku_competitors');
    }
};

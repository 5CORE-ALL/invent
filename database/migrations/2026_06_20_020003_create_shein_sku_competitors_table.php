<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors `amazon_sku_competitors`: a join table that pins selected
 * Shein competitor products to one or more internal SKUs from the
 * /repricer/shein-search UI. (sku, product_id, marketplace) is the
 * de-dup key so the same Shein product can be reused across SKUs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shein_sku_competitors')) {
            return;
        }

        Schema::create('shein_sku_competitors', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->string('product_id')->index();
            $table->string('marketplace')->default('shein');
            $table->text('product_title')->nullable();
            $table->string('seller_name')->nullable();
            $table->text('product_link')->nullable();
            $table->text('image')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('extracted_old_price', 10, 2)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('reviews')->nullable();
            $table->json('delivery')->nullable();
            $table->timestamps();

            $table->unique(['sku', 'product_id', 'marketplace'], 'shein_sku_pid_mp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shein_sku_competitors');
    }
};

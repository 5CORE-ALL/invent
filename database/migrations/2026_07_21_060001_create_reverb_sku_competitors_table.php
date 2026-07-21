<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reverb_sku_competitors')) {
            return;
        }

        Schema::create('reverb_sku_competitors', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 255)->index();
            $table->string('item_id', 100)->index();
            $table->string('marketplace', 50)->default('reverb');
            $table->text('product_link')->nullable();
            $table->text('image')->nullable();
            $table->text('product_title')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('ignored')->default(false);
            $table->decimal('shipping_cost', 10, 2)->nullable()->default(0);
            $table->decimal('total_price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['sku', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reverb_sku_competitors');
    }
};

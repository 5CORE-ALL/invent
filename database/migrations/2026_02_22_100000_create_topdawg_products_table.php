<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topdawg_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 255)->nullable()->index();
            $table->string('topdawg_listing_id')->nullable();
            $table->string('listing_state', 32)->nullable();
            $table->string('product_title')->nullable();
            $table->integer('r_l30')->nullable();
            $table->integer('r_l60')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('views')->nullable();
            $table->integer('remaining_inventory')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topdawg_products');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reverb_competitor_items')) {
            return;
        }

        Schema::create('reverb_competitor_items', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace', 50)->default('reverb');
            $table->string('search_query', 255)->index();
            $table->string('item_id', 100)->index();
            $table->text('link')->nullable();
            $table->text('title')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('condition', 50)->nullable();
            $table->string('seller_name', 255)->nullable();
            $table->string('seller_rating', 50)->nullable();
            $table->integer('position')->nullable();
            $table->text('image')->nullable();
            $table->decimal('shipping_cost', 10, 2)->nullable();
            $table->string('location', 255)->nullable();
            $table->timestamps();

            $table->unique(['search_query', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reverb_competitor_items');
    }
};

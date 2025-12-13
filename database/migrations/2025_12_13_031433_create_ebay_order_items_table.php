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
        Schema::create('ebay_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ebay_order_id');
            $table->string('item_id');
            $table->string('sku')->nullable();
            $table->integer('quantity');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency')->default('USD');
            $table->json('raw_data')->nullable(); // Store full item data
            $table->timestamps();

            $table->foreign('ebay_order_id')->references('id')->on('ebay_orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_order_items');
    }
};

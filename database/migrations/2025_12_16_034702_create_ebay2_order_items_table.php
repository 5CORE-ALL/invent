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
        Schema::create('ebay2_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ebay2_order_id')->constrained('ebay2_orders')->onDelete('cascade');
            $table->string('item_id')->nullable();
            $table->string('sku')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2)->nullable();
            $table->string('title')->nullable();
            $table->longText('raw_data')->nullable();
            $table->timestamps();
            
            $table->index('sku');
            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay2_order_items');
    }
};

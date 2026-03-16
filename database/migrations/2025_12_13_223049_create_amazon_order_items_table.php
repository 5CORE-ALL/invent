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
        Schema::create('amazon_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('amazon_order_id')->constrained('amazon_orders')->onDelete('cascade');
            $table->string('asin')->nullable();
            $table->string('sku')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->string('title')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            
            $table->index('sku');
            $table->index('asin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_order_items');
    }
};

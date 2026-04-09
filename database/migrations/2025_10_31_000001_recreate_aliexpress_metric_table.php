<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('aliexpress_metric');
        
        Schema::create('aliexpress_metric', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->string('sku')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('l30')->default(0);
            $table->integer('l60')->default(0);
            $table->json('order_dates')->nullable();
            $table->timestamp('last_order_date')->nullable();
            $table->timestamps();
            
            $table->unique(['product_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aliexpress_metric');
    }
};
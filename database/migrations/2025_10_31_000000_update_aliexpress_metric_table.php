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
            $table->decimal('l30', 10, 2)->default(0);
            $table->decimal('l60', 10, 2)->default(0);
            $table->integer('product_count')->default(0);
            $table->timestamp('order_date')->nullable();
            $table->string('order_id')->nullable();
            $table->string('order_status')->nullable();
            $table->timestamps();
            
            $table->index('sku');
            $table->index('order_date');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aliexpress_metric');
    }
};
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
        Schema::create('shein_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique()->index();
            $table->string('product_name')->nullable();
            $table->string('spu_name')->nullable();
            
            // Inventory
            $table->integer('inventory')->default(0);
            
            // Pricing
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('retail_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            
            // Metrics
            $table->bigInteger('views')->default(0);
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('review_count')->default(0);
            
            // Additional Info
            $table->string('status')->nullable(); // active, inactive, out_of_stock
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('category')->nullable();
            
            // Tracking
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_data')->nullable(); // Store full API response
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shein_metrics');
    }
};

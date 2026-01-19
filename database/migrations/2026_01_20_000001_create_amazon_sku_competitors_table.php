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
        // Create amazon_sku_competitors table if it doesn't exist
        if (!Schema::hasTable('amazon_sku_competitors')) {
            Schema::create('amazon_sku_competitors', function (Blueprint $table) {
                $table->id();
                $table->string('sku', 100)->index();
                $table->string('asin', 50)->nullable()->index();
                $table->string('marketplace', 10)->default('US')->index();
                $table->text('product_link')->nullable();
                $table->text('product_title')->nullable();
                $table->decimal('price', 10, 2)->default(0)->index();
                $table->timestamps();
                
                // Composite index for faster lookups
                $table->index(['sku', 'marketplace']);
                $table->index(['sku', 'price']);
                
                // Unique constraint to prevent duplicate entries
                $table->unique(['sku', 'asin', 'marketplace'], 'unique_sku_asin_marketplace');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_sku_competitors');
    }
};

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
        Schema::create('walmart_pricing_sales', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique()->index();
            $table->string('item_id')->nullable()->index();
            $table->string('item_name', 500)->nullable();
            
            // Pricing fields
            $table->decimal('current_price', 12, 2)->nullable();
            $table->decimal('buy_box_base_price', 12, 2)->nullable();
            $table->decimal('buy_box_total_price', 12, 2)->nullable();
            $table->decimal('buy_box_win_rate', 8, 2)->nullable();
            $table->decimal('competitor_price', 12, 2)->nullable();
            $table->decimal('comparison_price', 12, 2)->nullable();
            $table->decimal('price_differential', 12, 2)->nullable();
            $table->decimal('price_competitive_score', 8, 2)->nullable();
            $table->boolean('price_competitive')->default(false);
            
            // Repricer fields
            $table->string('repricer_strategy_type', 50)->nullable();
            $table->string('repricer_strategy_name', 100)->nullable();
            $table->string('repricer_status', 50)->nullable();
            $table->decimal('repricer_min_price', 12, 2)->nullable();
            $table->decimal('repricer_max_price', 12, 2)->nullable();
            
            // Sales & Inventory
            $table->decimal('gmv30', 12, 2)->nullable(); // Gross Merchandise Value L30
            $table->integer('inventory_count')->nullable();
            $table->string('fulfillment', 50)->nullable();
            $table->integer('sales_rank')->nullable();
            
            // Order counts from orders API
            $table->integer('l30_orders')->default(0);
            $table->integer('l30_qty')->default(0);
            $table->decimal('l30_revenue', 12, 2)->default(0);
            $table->integer('l60_orders')->default(0);
            $table->integer('l60_qty')->default(0);
            $table->decimal('l60_revenue', 12, 2)->default(0);
            
            // Traffic & Views
            $table->string('traffic', 20)->nullable();
            $table->integer('views')->nullable();
            $table->boolean('in_demand')->default(false);
            
            // Promo & Status
            $table->string('promo_status', 50)->nullable();
            $table->json('promo_details')->nullable();
            $table->string('reduced_referral_status', 50)->nullable();
            $table->string('walmart_funded_status', 50)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('walmart_pricing_sales');
    }
};



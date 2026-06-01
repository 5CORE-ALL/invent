<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates a table to store pre-calculated channel master data
     * Updated daily by scheduled command for instant page loads
     */
    public function up(): void
    {
        Schema::create('channel_master_calculated_data', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->unique()->index();
            $table->string('sheet_link')->nullable();
            $table->string('channel_percentage')->nullable();
            $table->string('type')->nullable()->index(); // B2C, B2B, Dropship
            $table->string('base')->nullable();
            $table->decimal('target', 15, 2)->nullable();
            $table->string('missing_link')->nullable();
            $table->string('addition_sheet')->nullable();
            
            // Sales metrics
            $table->decimal('l60_sales', 15, 2)->default(0);
            $table->decimal('l30_sales', 15, 2)->default(0)->index(); // For sorting
            $table->decimal('yesterday_sales', 15, 2)->default(0);
            $table->decimal('l7_sales', 15, 2)->default(0);
            $table->decimal('growth', 10, 2)->default(0);
            $table->decimal('l7_vs_30_pace', 10, 2)->nullable();
            
            // Order metrics
            $table->integer('l60_orders')->default(0);
            $table->integer('l30_orders')->default(0);
            $table->integer('total_quantity')->default(0);
            
            // Profit metrics
            $table->decimal('gprofit_pct', 10, 2)->default(0);
            $table->decimal('gprofit_l60', 10, 2)->default(0);
            $table->decimal('g_roi', 10, 2)->default(0);
            $table->decimal('g_roi_l60', 10, 2)->default(0);
            $table->decimal('total_profit', 15, 2)->default(0);
            $table->decimal('n_pft', 10, 2)->default(0);
            $table->decimal('n_roi', 10, 2)->default(0);
            $table->decimal('tacos_percentage', 10, 2)->default(0);
            $table->decimal('cogs', 15, 2)->default(0);
            
            // Ad metrics
            $table->decimal('total_ad_spend', 15, 2)->default(0);
            $table->decimal('ads_percentage', 10, 2)->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('ad_sold')->default(0);
            $table->decimal('ad_sales', 15, 2)->default(0);
            $table->decimal('cvr', 10, 2)->default(0);
            $table->decimal('acos', 10, 2)->default(0);
            $table->integer('missing_ads')->default(0);
            
            // Ad breakdown - Clicks
            $table->integer('kw_clicks')->default(0);
            $table->integer('pt_clicks')->default(0);
            $table->integer('hl_clicks')->default(0);
            $table->integer('pmt_clicks')->default(0);
            $table->integer('shopping_clicks')->default(0);
            $table->integer('serp_clicks')->default(0);
            
            // Ad breakdown - Sales
            $table->decimal('kw_sales', 15, 2)->default(0);
            $table->decimal('pt_sales', 15, 2)->default(0);
            $table->decimal('hl_sales', 15, 2)->default(0);
            $table->decimal('pmt_sales', 15, 2)->default(0);
            $table->decimal('shopping_sales', 15, 2)->default(0);
            $table->decimal('serp_sales', 15, 2)->default(0);
            
            // Ad breakdown - Units Sold
            $table->integer('kw_sold')->default(0);
            $table->integer('pt_sold')->default(0);
            $table->integer('hl_sold')->default(0);
            $table->integer('pmt_sold')->default(0);
            $table->integer('shopping_sold')->default(0);
            $table->integer('serp_sold')->default(0);
            
            // Ad breakdown - ACOS
            $table->decimal('kw_acos', 10, 2)->default(0);
            $table->decimal('pt_acos', 10, 2)->default(0);
            $table->decimal('hl_acos', 10, 2)->default(0);
            $table->decimal('pmt_acos', 10, 2)->default(0);
            $table->decimal('shopping_acos', 10, 2)->default(0);
            $table->decimal('serp_acos', 10, 2)->default(0);
            
            // Ad breakdown - CVR
            $table->decimal('kw_cvr', 10, 2)->default(0);
            $table->decimal('pt_cvr', 10, 2)->default(0);
            $table->decimal('hl_cvr', 10, 2)->default(0);
            $table->decimal('pmt_cvr', 10, 2)->default(0);
            $table->decimal('shopping_cvr', 10, 2)->default(0);
            $table->decimal('serp_cvr', 10, 2)->default(0);
            
            // Inventory metrics
            $table->integer('listed_count')->default(0);
            $table->integer('w_ads')->default(0);
            $table->integer('map')->default(0);
            $table->integer('miss')->default(0);
            $table->integer('nmap')->default(0);
            $table->integer('total_views')->default(0);
            
            // Other metrics
            $table->integer('nr')->default(0);
            $table->integer('update_flag')->default(0);
            $table->decimal('red_margin', 10, 2)->default(0);
            
            // Account health & reviews (JSON for flexibility)
            $table->json('account_health')->nullable();
            $table->json('reviews_data')->nullable();
            
            // Metadata
            $table->timestamp('calculated_at')->nullable()->index(); // When calculation was run
            $table->timestamp('data_as_of')->nullable(); // Data is valid as of this date
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['type', 'l30_sales']); // For filtering by type and sorting
            $table->index('calculated_at'); // For checking latest calculation
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_master_calculated_data');
    }
};

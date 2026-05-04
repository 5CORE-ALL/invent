<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration documents the enhanced JungleScout data structure.
     * The junglescout_product_data.data column now includes:
     * 
     * COMPETITOR SALES DATA (NEW):
     * - approximate_30_day_revenue: Estimated monthly revenue
     * - approximate_30_day_units_sold: Estimated monthly units sold
     * - number_of_sellers: Total sellers offering this ASIN
     * - buy_box_owner: Current buy box winner
     * - buy_box_owner_seller_id: Buy box winner seller ID
     * - seller_type: Type of seller (FBA, FBM, etc.)
     * 
     * PRODUCT STRUCTURE (NEW):
     * - is_variant: Whether product is a variant
     * - is_parent: Whether product is a parent ASIN
     * - variants: Number/list of variants
     * - date_first_available: When product was first listed
     * 
     * EXISTING FIELDS:
     * - price, reviews, rating, category
     * - image_url, parent_asin, brand
     * - product_rank, listing_quality_score
     * - weight, dimensions
     * 
     * Note: The 'data' column is JSON, so no schema change is needed.
     * This migration exists for documentation and version tracking.
     */
    public function up(): void
    {
        // No schema changes needed - data is stored as JSON
        // The ProcessJungleScoutSheetData command now fetches additional fields
        
        // Log the enhancement
        if (Schema::hasTable('junglescout_product_data')) {
            \Log::info('JungleScout data structure enhanced with competitor sales data fields');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No schema changes to reverse
        // Data will still contain the fields if they exist
    }
};

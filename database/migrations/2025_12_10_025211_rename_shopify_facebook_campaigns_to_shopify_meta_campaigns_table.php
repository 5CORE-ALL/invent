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
        Schema::rename('shopify_facebook_campaigns', 'shopify_meta_campaigns');
        
        // Update the index name
        Schema::table('shopify_meta_campaigns', function (Blueprint $table) {
            $table->dropIndex('shopify_fb_camp_idx');
            $table->index(['campaign_id', 'date_range', 'start_date'], 'shopify_meta_camp_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopify_meta_campaigns', function (Blueprint $table) {
            $table->dropIndex('shopify_meta_camp_idx');
            $table->index(['campaign_id', 'date_range', 'start_date'], 'shopify_fb_camp_idx');
        });
        
        Schema::rename('shopify_meta_campaigns', 'shopify_facebook_campaigns');
    }
};

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
        Schema::create('google_ads_campaigns', function (Blueprint $table) {
            $table->id();
            
            // Campaign Information
            $table->string('campaign_id')->index();
            $table->string('campaign_name')->nullable();
            $table->string('campaign_status')->nullable();
            $table->string('campaign_primary_status')->nullable();
            $table->text('campaign_primary_status_reasons')->nullable();
            $table->string('campaign_serving_status')->nullable();
            $table->string('advertising_channel_type')->nullable();
            $table->string('experiment_type')->nullable();
            $table->string('bidding_strategy_type')->nullable();
            $table->string('payment_mode')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            
            // Network Settings
            $table->boolean('target_google_search')->default(false);
            $table->boolean('target_search_network')->default(false);
            $table->boolean('target_content_network')->default(false);
            $table->boolean('target_partner_search_network')->default(false);
            
            // Shopping Settings
            $table->string('shopping_merchant_id')->nullable();
            $table->string('shopping_feed_label')->nullable();
            $table->integer('shopping_campaign_priority')->nullable();
            
            // Geo Target Settings
            $table->string('positive_geo_target_type')->nullable();
            $table->string('negative_geo_target_type')->nullable();
            
            // Manual CPC Settings
            $table->boolean('manual_cpc_enhanced_enabled')->default(false);
            
            // Budget Information
            $table->string('budget_id')->nullable();
            $table->string('budget_name')->nullable();
            $table->string('budget_status')->nullable();
            $table->bigInteger('budget_amount_micros')->nullable();
            $table->bigInteger('budget_total_amount_micros')->nullable();
            $table->string('budget_delivery_method')->nullable();
            $table->string('budget_period')->nullable();
            $table->boolean('budget_explicitly_shared')->default(false);
            $table->boolean('budget_has_recommended_budget')->default(false);
            
            // Metrics
            $table->bigInteger('metrics_impressions')->default(0);
            $table->bigInteger('metrics_clicks')->default(0);
            $table->decimal('metrics_ctr', 10, 4)->default(0);
            $table->decimal('metrics_average_cpc', 15, 2)->default(0);
            $table->decimal('metrics_average_cpm', 15, 2)->default(0);
            $table->decimal('metrics_average_cpe', 15, 2)->default(0);
            $table->decimal('metrics_average_cpv', 15, 2)->default(0);
            $table->bigInteger('metrics_cost_micros')->default(0);
            $table->bigInteger('metrics_interactions')->default(0);
            $table->decimal('metrics_interaction_rate', 10, 4)->default(0);
            
            // Conversion Metrics
            $table->decimal('metrics_all_conversions', 15, 2)->default(0);
            $table->decimal('metrics_all_conversions_value', 15, 2)->default(0);
            $table->decimal('metrics_conversions', 15, 2)->default(0);
            $table->decimal('metrics_conversions_value', 15, 2)->default(0);
            $table->decimal('metrics_cost_per_conversion', 15, 2)->default(0);
            $table->decimal('metrics_cost_per_all_conversions', 15, 2)->default(0);
            $table->decimal('metrics_value_per_conversion', 15, 2)->default(0);
            $table->decimal('metrics_value_per_all_conversions', 15, 2)->default(0);
            
            // Search Metrics
            $table->decimal('metrics_search_absolute_top_impression_share', 10, 4)->default(0);
            $table->decimal('metrics_search_impression_share', 10, 4)->default(0);
            $table->decimal('metrics_search_rank_lost_impression_share', 10, 4)->default(0);
            $table->decimal('metrics_search_budget_lost_impression_share', 10, 4)->default(0);
            
            // Video Metrics
            $table->bigInteger('metrics_video_views')->default(0);
            $table->decimal('metrics_video_quartile_p25_rate', 10, 4)->default(0);
            $table->decimal('metrics_video_quartile_p50_rate', 10, 4)->default(0);
            $table->decimal('metrics_video_quartile_p75_rate', 10, 4)->default(0);
            $table->decimal('metrics_video_quartile_p100_rate', 10, 4)->default(0);
            $table->decimal('metrics_video_view_rate', 10, 4)->default(0);
            
            // Date tracking
            $table->date('date')->index();
            
            $table->timestamps();
            
            // Composite unique index to prevent duplicate entries
            $table->unique(['campaign_id', 'date'], 'campaign_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_ads_campaigns');
    }
};

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
        Schema::create('tiktok_campaign_reports', function (Blueprint $table) {
            $table->id();
            
            // Campaign Information
            $table->string('campaign_name')->nullable()->index();
            $table->string('campaign_id')->nullable()->index();
            $table->string('product_id')->nullable()->index();
            $table->string('report_range')->nullable()->index(); // L7 or L30
            $table->string('creative_type')->nullable();
            $table->text('video_title')->nullable();
            $table->string('video_id')->nullable()->index();
            $table->string('tiktok_account')->nullable();
            $table->datetime('time_posted')->nullable();
            $table->string('status')->nullable();
            $table->string('authorization_type')->nullable();
            
            // Financial Metrics
            $table->decimal('cost', 15, 2)->nullable();
            $table->integer('sku_orders')->nullable()->default(0);
            $table->decimal('cost_per_order', 15, 2)->nullable();
            $table->decimal('gross_revenue', 15, 2)->nullable();
            $table->decimal('roi', 10, 2)->nullable();
            $table->string('currency', 10)->nullable()->default('USD');
            
            // Ad Performance Metrics
            $table->bigInteger('product_ad_impressions')->nullable()->default(0);
            $table->bigInteger('product_ad_clicks')->nullable()->default(0);
            $table->decimal('product_ad_click_rate', 8, 4)->nullable(); // Percentage
            $table->decimal('ad_conversion_rate', 8, 4)->nullable(); // Percentage
            
            // Video View Rates (Percentages)
            $table->decimal('video_view_rate_2_second', 8, 4)->nullable();
            $table->decimal('video_view_rate_6_second', 8, 4)->nullable();
            $table->decimal('video_view_rate_25_percent', 8, 4)->nullable();
            $table->decimal('video_view_rate_50_percent', 8, 4)->nullable();
            $table->decimal('video_view_rate_75_percent', 8, 4)->nullable();
            $table->decimal('video_view_rate_100_percent', 8, 4)->nullable();
            
            $table->timestamps();
            
            // Indexes for faster queries
            $table->index(['campaign_id', 'report_range']);
            $table->index(['product_id', 'report_range']);
            $table->index('time_posted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_campaign_reports');
    }
};

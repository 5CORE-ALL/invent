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
        Schema::create('amazon_sb_campaign_reports', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id')->nullable();
            $table->string('profile_id')->nullable();
            $table->string('ad_type'); // SPONSORED_PRODUCTS, SPONSORED_BRANDS, SPONSORED_DISPLAY
            $table->string('report_date_range')->nullable();

            $table->integer('addToCart')->nullable();
            $table->integer('addToCartClicks')->nullable();
            $table->decimal('addToCartRate', 8, 4)->nullable();

            $table->integer('addToList')->nullable();
            $table->integer('addToListFromClicks')->nullable();

            $table->integer('qualifiedBorrows')->nullable();
            $table->integer('qualifiedBorrowsFromClicks')->nullable();
            $table->integer('royaltyQualifiedBorrows')->nullable();
            $table->integer('royaltyQualifiedBorrowsFromClicks')->nullable();

            $table->integer('brandedSearches')->nullable();
            $table->integer('brandedSearchesClicks')->nullable();

            $table->decimal('campaignBudgetAmount', 12, 4)->nullable();
            $table->string('campaignBudgetCurrencyCode')->nullable();
            $table->string('campaignBudgetType')->nullable();
            $table->string('campaignName')->nullable();
            $table->string('campaignStatus')->nullable();

            $table->bigInteger('clicks')->nullable();
            $table->decimal('cost', 12, 4)->nullable();
            $table->string('costType')->nullable();

            $table->date('date')->nullable();
            $table->integer('detailPageViews')->nullable();
            $table->integer('detailPageViewsClicks')->nullable();
            $table->decimal('eCPAddToCart', 12, 4)->nullable();
            $table->date('endDate')->nullable();
            $table->bigInteger('impressions')->nullable();

            $table->decimal('newToBrandDetailPageViewRate', 8, 4)->nullable();
            $table->integer('newToBrandDetailPageViews')->nullable();
            $table->integer('newToBrandDetailPageViewsClicks')->nullable();
            $table->decimal('newToBrandECPDetailPageView', 12, 4)->nullable();

            $table->integer('newToBrandPurchases')->nullable();
            $table->integer('newToBrandPurchasesClicks')->nullable();
            $table->decimal('newToBrandPurchasesPercentage', 8, 4)->nullable();
            $table->decimal('newToBrandPurchasesRate', 8, 4)->nullable();

            $table->decimal('newToBrandSales', 12, 4)->nullable();
            $table->decimal('newToBrandSalesClicks', 12, 4)->nullable();
            $table->decimal('newToBrandSalesPercentage', 8, 4)->nullable();

            $table->integer('newToBrandUnitsSold')->nullable();
            $table->integer('newToBrandUnitsSoldClicks')->nullable();
            $table->decimal('newToBrandUnitsSoldPercentage', 8, 4)->nullable();

            $table->integer('purchases')->nullable();
            $table->integer('purchasesClicks')->nullable();
            $table->decimal('purchasesPromoted', 12, 4)->nullable();

            $table->decimal('sales', 12, 4)->nullable();
            $table->decimal('salesClicks', 12, 4)->nullable();
            $table->decimal('salesPromoted', 12, 4)->nullable();

            $table->date('startDate')->nullable();
            $table->decimal('topOfSearchImpressionShare', 8, 4)->nullable();

            $table->integer('unitsSold')->nullable();
            $table->integer('unitsSoldClicks')->nullable();

            $table->decimal('video5SecondViewRate', 8, 4)->nullable();
            $table->integer('video5SecondViews')->nullable();
            $table->integer('videoCompleteViews')->nullable();
            $table->integer('videoFirstQuartileViews')->nullable();
            $table->integer('videoMidpointViews')->nullable();
            $table->integer('videoThirdQuartileViews')->nullable();
            $table->integer('videoUnmutes')->nullable();

            $table->decimal('viewabilityRate', 8, 4)->nullable();
            $table->bigInteger('viewableImpressions')->nullable();
            $table->decimal('viewClickThroughRate', 8, 4)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_sb_campaign_reports');
    }
};

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
        Schema::create('amazon_sp_campaign_reports', function (Blueprint $table) {
            $table->id();

            // Identifiers
            $table->string('profile_id');
            $table->string('campaign_id')->nullable();
            $table->string('campaignName')->nullable();
            $table->string('ad_type'); // SPONSORED_PRODUCTS, SPONSORED_BRANDS, SPONSORED_DISPLAY
            $table->string('report_date_range'); // L60, L30, etc.

            $table->bigInteger('impressions')->nullable();
            $table->bigInteger('clicks')->nullable();
            $table->decimal('cost', 12, 4)->nullable();
            $table->decimal('spend', 12, 4)->nullable();
            
            $table->decimal('sales1d', 12, 4)->nullable();
            $table->decimal('sales7d', 12, 4)->nullable();
            $table->decimal('sales14d', 12, 4)->nullable();
            $table->decimal('sales30d', 12, 4)->nullable();

            $table->integer('unitsSoldClicks1d')->nullable();
            $table->integer('unitsSoldClicks7d')->nullable();
            $table->integer('unitsSoldClicks14d')->nullable();
            $table->integer('unitsSoldClicks30d')->nullable();

            $table->decimal('attributedSalesSameSku1d', 12, 4)->nullable();
            $table->decimal('attributedSalesSameSku7d', 12, 4)->nullable();
            $table->decimal('attributedSalesSameSku14d', 12, 4)->nullable();
            $table->decimal('attributedSalesSameSku30d', 12, 4)->nullable();

            $table->integer('unitsSoldSameSku1d')->nullable();
            $table->integer('unitsSoldSameSku7d')->nullable();
            $table->integer('unitsSoldSameSku14d')->nullable();
            $table->integer('unitsSoldSameSku30d')->nullable();

            $table->decimal('clickThroughRate', 6, 4)->nullable();
            $table->decimal('costPerClick', 12, 4)->nullable();
            $table->integer('qualifiedBorrows')->nullable();

            $table->integer('purchases1d')->nullable();
            $table->integer('purchases7d')->nullable();
            $table->integer('purchases14d')->nullable();
            $table->integer('purchases30d')->nullable();

            $table->integer('addToList')->nullable(); 
            $table->decimal('campaignBudgetAmount', 12, 4)->nullable();
            $table->string('campaignBudgetCurrencyCode')->nullable();
            $table->date('date')->nullable();
            $table->integer('royaltyQualifiedBorrows')->nullable();
            
            $table->integer('purchasesSameSku1d')->nullable();
            $table->integer('purchasesSameSku7d')->nullable();
            $table->integer('purchasesSameSku14d')->nullable();
            $table->integer('purchasesSameSku30d')->nullable();

            $table->integer('kindleEditionNormalizedPagesRead14d')->nullable();
            $table->decimal('kindleEditionNormalizedPagesRoyalties14d', 12, 4)->nullable();

            $table->string('campaignBiddingStrategy')->nullable();
            $table->timestamps();
            $table->date('endDate')->nullable();
            $table->date('startDate')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_sp_campaign_reports');
    }
};

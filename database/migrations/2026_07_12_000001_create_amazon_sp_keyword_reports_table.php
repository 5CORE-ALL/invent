<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sponsored Products keyword / targeting performance (Reporting API v3 spTargeting report,
     * groupBy = targeting). One row per keyword (or targeting expression) per report_date_range.
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_sp_keyword_reports')) {
            return;
        }

        Schema::create('amazon_sp_keyword_reports', function (Blueprint $table) {
            $table->id();

            // Identifiers
            $table->string('profile_id');
            $table->string('ad_type')->default('SPONSORED_PRODUCTS');
            $table->string('report_date_range'); // L30 / L15 / L7 / L1 / YYYY-MM-DD
            $table->string('campaign_id')->nullable();
            $table->string('campaignName')->nullable();
            $table->string('ad_group_id')->nullable();
            $table->string('adGroupName')->nullable();
            $table->string('keyword_id')->nullable();
            $table->string('keyword', 512)->nullable();      // keyword text / targeting label
            $table->string('targeting', 512)->nullable();     // raw targeting expression
            $table->string('keywordType')->nullable();
            $table->string('matchType')->nullable();          // BROAD / PHRASE / EXACT / TARGETING_EXPRESSION
            $table->string('adKeywordStatus')->nullable();
            $table->string('campaignStatus')->nullable();

            // Metrics
            $table->bigInteger('impressions')->nullable();
            $table->bigInteger('clicks')->nullable();
            $table->decimal('cost', 12, 4)->nullable();
            $table->decimal('costPerClick', 12, 4)->nullable();
            $table->decimal('clickThroughRate', 12, 6)->nullable();

            $table->integer('purchases1d')->nullable();
            $table->integer('purchases7d')->nullable();
            $table->integer('purchases14d')->nullable();
            $table->integer('purchases30d')->nullable();

            $table->decimal('sales1d', 12, 4)->nullable();
            $table->decimal('sales7d', 12, 4)->nullable();
            $table->decimal('sales14d', 12, 4)->nullable();
            $table->decimal('sales30d', 12, 4)->nullable();

            $table->integer('unitsSoldClicks1d')->nullable();
            $table->integer('unitsSoldClicks7d')->nullable();
            $table->integer('unitsSoldClicks14d')->nullable();
            $table->integer('unitsSoldClicks30d')->nullable();

            $table->decimal('acosClicks14d', 12, 6)->nullable();
            $table->decimal('roasClicks14d', 12, 6)->nullable();

            $table->string('startDate')->nullable();
            $table->string('endDate')->nullable();

            $table->timestamps();

            $table->index(['profile_id', 'report_date_range'], 'amz_sp_kw_profile_range_idx');
            $table->index('campaign_id', 'amz_sp_kw_campaign_idx');
            $table->index('keyword_id', 'amz_sp_kw_keyword_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_sp_keyword_reports');
    }
};

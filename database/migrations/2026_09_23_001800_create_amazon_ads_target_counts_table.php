<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sponsored Brands / Display keyword and target counts.
     * SP counts stay on amazon_sp_keyword_reports. SB campaigns are not in that report,
     * so /amazon-ads/all was showing M even when the campaign had keywords.
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_ads_target_counts')) {
            return;
        }

        Schema::create('amazon_ads_target_counts', function (Blueprint $table) {
            $table->id();
            $table->string('ad_product', 8);
            $table->string('campaign_id', 64);
            $table->unsignedInteger('targets')->default(0);
            $table->unsignedInteger('n_targets')->nullable();
            $table->timestamps();

            $table->unique(['ad_product', 'campaign_id'], 'amz_ads_target_counts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_ads_target_counts');
    }
};

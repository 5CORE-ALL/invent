<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pulled SP product ads: one row per advertised SKU on a campaign.
     * Separate from amazon_sp_product_ads (variation-verify).
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_ads_campaign_skus')) {
            return;
        }

        Schema::create('amazon_ads_campaign_skus', function (Blueprint $table) {
            $table->id();
            $table->string('profile_id');
            $table->string('ad_id');
            $table->string('campaign_id')->nullable()->index();
            $table->string('campaign_name')->nullable()->index();
            $table->string('ad_group_id')->nullable();
            $table->string('sku', 255)->nullable()->index();
            $table->string('asin', 32)->nullable()->index();
            $table->string('state', 32)->nullable()->index();
            $table->timestamp('pulled_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['profile_id', 'ad_id'], 'amz_ads_campaign_skus_profile_ad_unique');
            $table->index(['campaign_id', 'sku'], 'amz_ads_campaign_skus_cid_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_ads_campaign_skus');
    }
};

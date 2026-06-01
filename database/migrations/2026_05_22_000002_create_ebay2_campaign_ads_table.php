<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors `ebay_campaign_ads` for eBay account 2 (EBAY2_* credentials).
 * Used by `ebay2:sync-campaign-listings` to store raw campaign ad data
 * pulled from eBay Marketing + Recommendation APIs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ebay2_campaign_ads');

        Schema::create('ebay2_campaign_ads', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('funding_strategy')->nullable();   // COST_PER_SALE | COST_PER_CLICK
            $table->string('campaign_status')->nullable();    // RUNNING | PAUSED | ENDED
            $table->string('ad_id')->nullable();
            $table->string('listing_id');                     // eBay item_id
            $table->string('sku')->nullable();
            $table->decimal('bid_percentage', 8, 2)->nullable();
            $table->double('suggested_bid')->nullable();
            $table->decimal('price', 15, 4)->nullable();
            $table->string('promote_with_ad')->nullable();    // RECOMMENDED | OPTIONAL | AD_ALREADY_CREATED | NOT_RECOMMENDED | UNDETERMINED
            $table->timestamps();

            $table->index('listing_id');
            $table->index('campaign_id');
            $table->index('funding_strategy');
            $table->index('sku');
            $table->index('promote_with_ad');
            $table->unique(['listing_id', 'campaign_id'], 'uniq_ebay2_listing_campaign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay2_campaign_ads');
    }
};

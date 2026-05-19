<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ebay_campaign_ads');

        Schema::create('ebay_campaign_ads', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id');
            $table->string('campaign_name')->nullable();
            $table->string('funding_strategy')->nullable();   // COST_PER_SALE | COST_PER_CLICK
            $table->string('campaign_status')->nullable();    // RUNNING | PAUSED | ENDED
            $table->string('ad_id')->nullable();
            $table->string('listing_id');                     // eBay item_id
            $table->string('sku')->nullable();
            $table->decimal('bid_percentage', 8, 2)->nullable();
            $table->double('suggested_bid')->nullable();
            $table->decimal('price', 15, 4)->nullable();
            $table->timestamps();

            $table->index('listing_id');
            $table->index('campaign_id');
            $table->index('funding_strategy');
            $table->index('sku');
            $table->unique(['listing_id', 'campaign_id'], 'uniq_listing_campaign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_campaign_ads');
    }
};

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
        if (Schema::hasTable('google_ads_negative_keywords')) {
            return;
        }

        Schema::create('google_ads_negative_keywords', function (Blueprint $table) {
            $table->id();

            // Where the negative lives: CAMPAIGN | AD_GROUP | SHARED_SET (negative keyword list)
            $table->string('level')->index();

            $table->string('customer_id')->nullable()->index();

            // Campaign context (present for CAMPAIGN and AD_GROUP levels)
            $table->string('campaign_id')->nullable()->index();
            $table->string('campaign_name')->nullable();

            // Ad group context (present for AD_GROUP level only)
            $table->string('ad_group_id')->nullable()->index();
            $table->string('ad_group_name')->nullable();

            // Shared set / negative keyword list context (present for SHARED_SET level only)
            $table->string('shared_set_id')->nullable()->index();
            $table->string('shared_set_name')->nullable();

            // The criterion itself
            $table->string('criterion_id')->nullable();
            // Fully-qualified Google Ads resource name — the natural upsert key
            $table->string('criterion_resource_name', 512);
            $table->string('keyword_text')->nullable();
            $table->string('match_type')->nullable();
            $table->string('criterion_type')->default('KEYWORD');
            $table->string('status')->nullable();

            $table->timestamps();

            // A single criterion resource is globally unique in Google Ads, so it is the
            // natural upsert key across all three levels.
            $table->unique('criterion_resource_name', 'gads_neg_kw_resource_unique');

            $table->index(['keyword_text', 'match_type'], 'gads_neg_kw_text_match_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_ads_negative_keywords');
    }
};

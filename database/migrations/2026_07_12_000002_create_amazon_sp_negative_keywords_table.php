<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sponsored Products negative keywords — campaign-level (sp/campaignNegativeKeywords)
     * and ad-group-level (sp/negativeKeywords) list APIs. One row per Amazon keywordId.
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_sp_negative_keywords')) {
            return;
        }

        Schema::create('amazon_sp_negative_keywords', function (Blueprint $table) {
            $table->id();

            $table->string('profile_id');
            $table->string('level'); // CAMPAIGN | AD_GROUP
            $table->string('keyword_id')->unique();
            $table->string('campaign_id')->nullable();
            $table->string('campaignName')->nullable();
            $table->string('ad_group_id')->nullable();
            $table->string('adGroupName')->nullable();
            $table->string('keywordText', 512)->nullable();
            $table->string('matchType')->nullable(); // NEGATIVE_EXACT | NEGATIVE_PHRASE
            $table->string('state')->nullable();      // ENABLED | PAUSED | ...

            $table->timestamps();

            $table->index(['profile_id', 'level'], 'amz_sp_neg_profile_level_idx');
            $table->index('campaign_id', 'amz_sp_neg_campaign_idx');
            $table->index(['keywordText', 'matchType'], 'amz_sp_neg_text_match_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_sp_negative_keywords');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Negative keywords kept in the app for the Keyword Targeting page (same shape as
     * amazon_kw_targeting_keywords). Import and KW-cousin sync only insert missing rows.
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_kw_targeting_negatives')) {
            return;
        }

        Schema::create('amazon_kw_targeting_negatives', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id', 64);
            $table->string('campaign_name', 512)->nullable();
            $table->string('ad_group_id', 64)->nullable();
            $table->string('keyword_id', 64)->nullable();
            $table->string('keyword', 512);
            $table->string('keyword_key', 255);
            $table->string('match_type', 32)->default(''); // NEGATIVE_EXACT | NEGATIVE_PHRASE
            $table->string('level', 16)->default('CAMPAIGN'); // CAMPAIGN | AD_GROUP
            $table->string('state', 32)->nullable();
            $table->string('source', 16)->default('amazon'); // amazon | cousin | manual
            $table->timestamps();

            $table->unique(['campaign_id', 'keyword_key', 'match_type'], 'amz_kw_neg_unique');
            $table->index('campaign_id', 'amz_kw_neg_campaign_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_kw_targeting_negatives');
    }
};

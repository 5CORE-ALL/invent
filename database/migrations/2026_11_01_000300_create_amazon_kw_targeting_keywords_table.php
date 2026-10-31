<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keywords kept in the app for the Keyword Targeting page.
     * "Pull keywords" inserts the ones that exist in Amazon KW campaigns but not here;
     * rows already in this table are never changed or duplicated.
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_kw_targeting_keywords')) {
            return;
        }

        Schema::create('amazon_kw_targeting_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id', 64);
            $table->string('campaign_name', 512)->nullable();
            $table->string('ad_group_id', 64)->nullable();
            $table->string('keyword_id', 64)->nullable();
            $table->string('keyword', 512);
            $table->string('keyword_key', 255); // lower-cased, whitespace-collapsed keyword used for uniqueness
            $table->string('match_type', 32)->default('');
            $table->string('state', 32)->nullable();
            $table->string('source', 16)->default('amazon'); // amazon | manual
            $table->timestamps();

            $table->unique(['campaign_id', 'keyword_key', 'match_type'], 'amz_kw_tgt_unique');
            $table->index('campaign_id', 'amz_kw_tgt_campaign_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_kw_targeting_keywords');
    }
};

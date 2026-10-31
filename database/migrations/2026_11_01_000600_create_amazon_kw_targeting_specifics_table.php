<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Specific KW on the Keyword Targeting page: keywords added by hand per campaign.
     * Never imported from Amazon and never synced between KW cousins.
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_kw_targeting_specifics')) {
            return;
        }

        Schema::create('amazon_kw_targeting_specifics', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id', 64);
            $table->string('campaign_name', 512)->nullable();
            $table->string('keyword', 512);
            $table->string('keyword_key', 255);
            $table->string('match_type', 32)->default('');
            $table->string('added_by')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'keyword_key', 'match_type'], 'amz_kw_specific_unique');
            $table->index('campaign_id', 'amz_kw_specific_campaign_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_kw_targeting_specifics');
    }
};

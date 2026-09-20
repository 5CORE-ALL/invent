<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SP + SB ad groups pulled from Amazon (one row per adGroupId).
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_ads_ad_groups')) {
            return;
        }

        Schema::create('amazon_ads_ad_groups', function (Blueprint $table) {
            $table->id();
            $table->string('profile_id');
            $table->string('ad_type', 32);
            $table->string('ad_group_id');
            $table->string('campaign_id')->nullable()->index();
            $table->string('campaignName')->nullable()->index();
            $table->string('adGroupName')->nullable()->index();
            $table->string('state', 32)->nullable()->index();
            $table->decimal('defaultBid', 10, 2)->nullable();
            $table->timestamp('pulled_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['profile_id', 'ad_type', 'ad_group_id'], 'amz_ads_ad_groups_profile_type_ag_unique');
            $table->index(['profile_id', 'ad_type'], 'amz_ads_ad_groups_profile_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_ads_ad_groups');
    }
};

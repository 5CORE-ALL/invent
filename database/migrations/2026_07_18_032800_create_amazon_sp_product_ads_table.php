<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sponsored Products product ads from Amazon Advertising API
     * POST /sp/productAds/list — used by Amz Variation Verify.
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_sp_product_ads')) {
            return;
        }

        Schema::create('amazon_sp_product_ads', function (Blueprint $table) {
            $table->id();
            $table->string('profile_id');
            $table->string('ad_id');
            $table->string('campaign_id')->nullable()->index();
            $table->string('ad_group_id')->nullable();
            $table->string('sku', 255)->nullable()->index();
            $table->string('asin', 32)->nullable()->index();
            $table->string('state', 32)->nullable()->index();
            $table->timestamp('pulled_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['profile_id', 'ad_id'], 'amz_sp_product_ads_profile_ad_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_sp_product_ads');
    }
};

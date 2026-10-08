<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily Lbid (live Amazon bid) per campaign, one row per campaign per calendar day.
     * Feeds the Lbid history dot on /amazon-ads/all. `last_sbid` on the report tables is
     * overwritten on every sync, so it cannot show how the live bid moved day to day.
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_ads_lbid_daily')) {
            return;
        }

        Schema::create('amazon_ads_lbid_daily', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 4); // sp | sb
            $table->string('campaign_id', 64);
            $table->date('report_date');
            $table->decimal('lbid', 10, 2);
            $table->timestamps();

            $table->unique(['channel', 'campaign_id', 'report_date'], 'amz_lbid_daily_unique');
            $table->index(['campaign_id', 'report_date'], 'amz_lbid_daily_cid_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_ads_lbid_daily');
    }
};

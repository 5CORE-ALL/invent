<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 2 of ebay:sync-campaign-listings stores "eligible but not yet enrolled"
 * listings with campaign_id = NULL. The original nullable migration
 * (2026_05_19_052341) was scaffolded empty, so campaign_id stayed NOT NULL and
 * every eligible insert failed silently — leaving 0 eligible rows. Make it
 * nullable for real via raw SQL (avoids needing doctrine/dbal).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ebay_campaign_ads', 'campaign_id')) {
            DB::statement('ALTER TABLE ebay_campaign_ads MODIFY campaign_id VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ebay_campaign_ads', 'campaign_id')) {
            DB::statement('ALTER TABLE ebay_campaign_ads MODIFY campaign_id VARCHAR(255) NOT NULL');
        }
    }
};

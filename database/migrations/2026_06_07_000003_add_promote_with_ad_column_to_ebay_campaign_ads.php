<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The earlier 2026_05_19_051200 migration was scaffolded but left empty, so the
 * promote_with_ad column was never actually created — even though both the
 * ebay:sync-campaign-listings command and the /ebay/campaign-ads grid filter
 * use it (causing "Unknown column 'ca.promote_with_ad'"). This adds it for real.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ebay_campaign_ads', 'promote_with_ad')) {
            Schema::table('ebay_campaign_ads', function (Blueprint $table) {
                // eBay recommendation flag (e.g. RECOMMENDED / null) written by
                // ebay:sync-campaign-listings and filtered on the grid.
                $table->string('promote_with_ad')->nullable()->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ebay_campaign_ads', 'promote_with_ad')) {
            Schema::table('ebay_campaign_ads', function (Blueprint $table) {
                $table->dropColumn('promote_with_ad');
            });
        }
    }
};

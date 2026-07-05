<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an `active` (active-campaign count) column to the Shopify Ads Master daily
 * snapshots so the ACTIVE column/badge gets a day-over-day trend dot + history chart,
 * matching the other metrics and the /advertisement-master page.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shopify_ads_master_metric_snapshots')
            && ! Schema::hasColumn('shopify_ads_master_metric_snapshots', 'active')) {
            Schema::table('shopify_ads_master_metric_snapshots', function (Blueprint $table) {
                $table->integer('active')->default(0)->after('sales');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shopify_ads_master_metric_snapshots')
            && Schema::hasColumn('shopify_ads_master_metric_snapshots', 'active')) {
            Schema::table('shopify_ads_master_metric_snapshots', function (Blueprint $table) {
                $table->dropColumn('active');
            });
        }
    }
};

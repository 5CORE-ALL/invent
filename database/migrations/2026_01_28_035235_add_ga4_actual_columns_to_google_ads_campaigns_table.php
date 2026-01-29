<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('google_ads_campaigns', function (Blueprint $table) {
            // Add columns for actual GA4 data (from GA4 API, not Google Ads API)
            // These will match GA4 exactly when GA4 API is configured
            $table->decimal('ga4_actual_sold_units', 15, 2)->default(0)->after('ga4_ad_sales')->comment('Actual GA4 purchases from GA4 API');
            $table->decimal('ga4_actual_revenue', 15, 2)->default(0)->after('ga4_actual_sold_units')->comment('Actual GA4 revenue from GA4 API');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_ads_campaigns', function (Blueprint $table) {
            $table->dropColumn(['ga4_actual_sold_units', 'ga4_actual_revenue']);
        });
    }
};

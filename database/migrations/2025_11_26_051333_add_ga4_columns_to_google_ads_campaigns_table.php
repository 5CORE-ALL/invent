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
            $table->decimal('ga4_sold_units', 15, 2)->default(0)->after('metrics_video_view_rate');
            $table->decimal('ga4_ad_sales', 15, 2)->default(0)->after('ga4_sold_units');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_ads_campaigns', function (Blueprint $table) {
            $table->dropColumn(['ga4_sold_units', 'ga4_ad_sales']);
        });
    }
};

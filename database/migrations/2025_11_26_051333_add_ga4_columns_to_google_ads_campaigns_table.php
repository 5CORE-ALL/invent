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
        if (! Schema::hasTable('google_ads_campaigns')) {
            return;
        }

        if (! Schema::hasColumn('google_ads_campaigns', 'ga4_sold_units')) {
            Schema::table('google_ads_campaigns', function (Blueprint $table) {
                $table->decimal('ga4_sold_units', 15, 2)->default(0)->after('metrics_video_view_rate');
            });
        }
        if (! Schema::hasColumn('google_ads_campaigns', 'ga4_ad_sales')) {
            Schema::table('google_ads_campaigns', function (Blueprint $table) {
                $table->decimal('ga4_ad_sales', 15, 2)->default(0)->after('ga4_sold_units');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('google_ads_campaigns')) {
            return;
        }

        $columns = ['ga4_sold_units', 'ga4_ad_sales'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('google_ads_campaigns', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('google_ads_campaigns', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};

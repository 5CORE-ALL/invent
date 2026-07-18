<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store each campaign's L30 (rolling 30-day) badge metrics alongside the daily SBGT
 * snapshot. The Google Shopping / SERP / YouTube toolbar charts read these so each
 * plotted point is "account L30 ACOS as of that day", not single-day ACOS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_ads_sbgt_snapshots')) {
            return;
        }

        Schema::table('google_ads_sbgt_snapshots', function (Blueprint $table) {
            if (! Schema::hasColumn('google_ads_sbgt_snapshots', 'spend_l30')) {
                $table->decimal('spend_l30', 14, 2)->nullable()->after('acos');
            }
            if (! Schema::hasColumn('google_ads_sbgt_snapshots', 'sales_l30')) {
                $table->decimal('sales_l30', 14, 2)->nullable()->after('spend_l30');
            }
            if (! Schema::hasColumn('google_ads_sbgt_snapshots', 'clicks_l30')) {
                $table->decimal('clicks_l30', 14, 2)->nullable()->after('sales_l30');
            }
            if (! Schema::hasColumn('google_ads_sbgt_snapshots', 'sold_l30')) {
                $table->decimal('sold_l30', 14, 2)->nullable()->after('clicks_l30');
            }
            if (! Schema::hasColumn('google_ads_sbgt_snapshots', 'bgt')) {
                $table->decimal('bgt', 12, 2)->nullable()->after('sold_l30');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('google_ads_sbgt_snapshots')) {
            return;
        }

        Schema::table('google_ads_sbgt_snapshots', function (Blueprint $table) {
            foreach (['spend_l30', 'sales_l30', 'clicks_l30', 'sold_l30', 'bgt'] as $col) {
                if (Schema::hasColumn('google_ads_sbgt_snapshots', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

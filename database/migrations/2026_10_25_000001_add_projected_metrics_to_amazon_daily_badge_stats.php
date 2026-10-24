<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Projected (L7 pace × 30) badge history for /amazon-tabulator-view. */
    private const COLUMNS = [
        'p_sales' => [14, 2],
        'p_qty' => [14, 2],
        'p_groi_pct' => [10, 2],
        'p_npft' => [10, 2],
        'p_nroi_pct' => [10, 2],
        'p_ads_pct' => [10, 2],
        'p_views' => [14, 2],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('amazon_daily_badge_stats')) {
            return;
        }

        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            foreach (self::COLUMNS as $name => [$total, $places]) {
                if (! Schema::hasColumn('amazon_daily_badge_stats', $name)) {
                    $table->decimal($name, $total, $places)->default(0);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('amazon_daily_badge_stats')) {
            return;
        }

        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            foreach (array_keys(self::COLUMNS) as $name) {
                if (Schema::hasColumn('amazon_daily_badge_stats', $name)) {
                    $table->dropColumn($name);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Yesterday badge history (trend dots + charts) for /amazon-tabulator-view. Nullable: null = not captured that day. */
    private const COLUMNS = [
        'y_spend',
        'y_acos_pct',
        'y_tacos_pct',
        'y_sales',
        'y_cvr_pct',
        'y_bgt',
        'y_utilized_pct',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('amazon_daily_badge_stats')) {
            return;
        }

        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            foreach (self::COLUMNS as $col) {
                if (! Schema::hasColumn('amazon_daily_badge_stats', $col)) {
                    $table->decimal($col, 14, 2)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('amazon_daily_badge_stats')) {
            return;
        }

        $existing = array_values(array_filter(
            self::COLUMNS,
            static fn (string $c): bool => Schema::hasColumn('amazon_daily_badge_stats', $c)
        ));
        if ($existing !== []) {
            Schema::table('amazon_daily_badge_stats', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};

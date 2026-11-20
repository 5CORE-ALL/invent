<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CVR Price Rule modal on /amazon-tabulator-view: SKU count per case (CVR Down / within target / CVR Up) for the
     * history dot + rolling chart. Nullable: null = not captured that day.
     */
    private const COLUMNS = [
        'cvr_down_count',
        'cvr_same_count',
        'cvr_up_count',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('amazon_daily_badge_stats')) {
            return;
        }

        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            foreach (self::COLUMNS as $col) {
                if (! Schema::hasColumn('amazon_daily_badge_stats', $col)) {
                    $table->integer($col)->nullable();
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

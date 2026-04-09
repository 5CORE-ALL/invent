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
        if (! Schema::hasTable('amazon_daily_badge_stats')) {
            return;
        }

        if (! Schema::hasColumn('amazon_daily_badge_stats', 'total_pft')) {
            Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
                $col = $table->decimal('total_pft', 12, 2)->default(0);
                if (Schema::hasColumn('amazon_daily_badge_stats', 'pt_spend')) {
                    $col->after('pt_spend');
                }
            });
        }
        if (! Schema::hasColumn('amazon_daily_badge_stats', 'total_sales')) {
            Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
                $col = $table->decimal('total_sales', 12, 2)->default(0);
                if (Schema::hasColumn('amazon_daily_badge_stats', 'total_pft')) {
                    $col->after('total_pft');
                } elseif (Schema::hasColumn('amazon_daily_badge_stats', 'pt_spend')) {
                    $col->after('pt_spend');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('amazon_daily_badge_stats')) {
            return;
        }

        $columns = ['total_pft', 'total_sales'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('amazon_daily_badge_stats', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};

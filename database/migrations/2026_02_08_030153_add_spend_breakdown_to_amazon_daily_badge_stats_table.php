<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('amazon_daily_badge_stats')) {
            return;
        }

        if (! Schema::hasColumn('amazon_daily_badge_stats', 'kw_spend')) {
            Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
                $col = $table->decimal('kw_spend', 12, 2)->default(0);
                if (Schema::hasColumn('amazon_daily_badge_stats', 'ub7_ub1_count')) {
                    $col->after('ub7_ub1_count');
                }
            });
        }
        if (! Schema::hasColumn('amazon_daily_badge_stats', 'hl_spend')) {
            Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
                $col = $table->decimal('hl_spend', 12, 2)->default(0);
                if (Schema::hasColumn('amazon_daily_badge_stats', 'kw_spend')) {
                    $col->after('kw_spend');
                }
            });
        }
        if (! Schema::hasColumn('amazon_daily_badge_stats', 'pt_spend')) {
            Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
                $col = $table->decimal('pt_spend', 12, 2)->default(0);
                if (Schema::hasColumn('amazon_daily_badge_stats', 'hl_spend')) {
                    $col->after('hl_spend');
                } elseif (Schema::hasColumn('amazon_daily_badge_stats', 'kw_spend')) {
                    $col->after('kw_spend');
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('amazon_daily_badge_stats')) {
            return;
        }

        $columns = ['kw_spend', 'hl_spend', 'pt_spend'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('amazon_daily_badge_stats', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};

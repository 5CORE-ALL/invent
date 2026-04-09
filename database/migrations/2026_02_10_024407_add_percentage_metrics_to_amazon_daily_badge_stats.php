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

        if (! Schema::hasColumn('amazon_daily_badge_stats', 'gpft_pct')) {
            Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
                $col = $table->decimal('gpft_pct', 8, 2)->default(0);
                if (Schema::hasColumn('amazon_daily_badge_stats', 'total_spend')) {
                    $col->after('total_spend');
                }
            });
        }
        if (! Schema::hasColumn('amazon_daily_badge_stats', 'npft_pct')) {
            Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
                $col = $table->decimal('npft_pct', 8, 2)->default(0);
                if (Schema::hasColumn('amazon_daily_badge_stats', 'gpft_pct')) {
                    $col->after('gpft_pct');
                } elseif (Schema::hasColumn('amazon_daily_badge_stats', 'total_spend')) {
                    $col->after('total_spend');
                }
            });
        }
        if (! Schema::hasColumn('amazon_daily_badge_stats', 'groi_pct')) {
            Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
                $col = $table->decimal('groi_pct', 8, 2)->default(0);
                if (Schema::hasColumn('amazon_daily_badge_stats', 'npft_pct')) {
                    $col->after('npft_pct');
                } elseif (Schema::hasColumn('amazon_daily_badge_stats', 'gpft_pct')) {
                    $col->after('gpft_pct');
                }
            });
        }
        if (! Schema::hasColumn('amazon_daily_badge_stats', 'nroi_pct')) {
            Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
                $col = $table->decimal('nroi_pct', 8, 2)->default(0);
                if (Schema::hasColumn('amazon_daily_badge_stats', 'groi_pct')) {
                    $col->after('groi_pct');
                }
            });
        }
        if (! Schema::hasColumn('amazon_daily_badge_stats', 'tcos_pct')) {
            Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
                $col = $table->decimal('tcos_pct', 8, 2)->default(0);
                if (Schema::hasColumn('amazon_daily_badge_stats', 'nroi_pct')) {
                    $col->after('nroi_pct');
                } elseif (Schema::hasColumn('amazon_daily_badge_stats', 'groi_pct')) {
                    $col->after('groi_pct');
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

        $columns = ['gpft_pct', 'npft_pct', 'groi_pct', 'nroi_pct', 'tcos_pct'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('amazon_daily_badge_stats', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};

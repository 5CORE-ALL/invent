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
        if (Schema::hasColumn('amazon_daily_badge_stats', 'total_l30_orders')) {
            return;
        }

        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            $col = $table->integer('total_l30_orders')->default(0);
            if (Schema::hasColumn('amazon_daily_badge_stats', 'tcos_pct')) {
                $col->after('tcos_pct');
            } elseif (Schema::hasColumn('amazon_daily_badge_stats', 'total_spend')) {
                $col->after('total_spend');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('amazon_daily_badge_stats')) {
            return;
        }
        if (! Schema::hasColumn('amazon_daily_badge_stats', 'total_l30_orders')) {
            return;
        }

        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            $table->dropColumn('total_l30_orders');
        });
    }
};

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
        if (Schema::hasColumn('amazon_daily_badge_stats', 'total_spend')) {
            return;
        }

        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            $col = $table->decimal('total_spend', 12, 2)->default(0);
            if (Schema::hasColumn('amazon_daily_badge_stats', 'total_sales')) {
                $col->after('total_sales');
            } elseif (Schema::hasColumn('amazon_daily_badge_stats', 'total_pft')) {
                $col->after('total_pft');
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
        if (! Schema::hasColumn('amazon_daily_badge_stats', 'total_spend')) {
            return;
        }

        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            $table->dropColumn('total_spend');
        });
    }
};

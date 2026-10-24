<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Projected ACOS% history for /amazon-tabulator-view. */
    public function up(): void
    {
        if (! Schema::hasTable('amazon_daily_badge_stats') || Schema::hasColumn('amazon_daily_badge_stats', 'p_acos_pct')) {
            return;
        }

        Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
            $table->decimal('p_acos_pct', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('amazon_daily_badge_stats') && Schema::hasColumn('amazon_daily_badge_stats', 'p_acos_pct')) {
            Schema::table('amazon_daily_badge_stats', function (Blueprint $table) {
                $table->dropColumn('p_acos_pct');
            });
        }
    }
};

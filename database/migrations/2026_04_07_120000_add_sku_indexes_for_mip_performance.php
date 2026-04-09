<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speeds MIP lookups: forecast join by SKU, mfrg_progress filters by SKU.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('forecast_analysis')) {
            Schema::table('forecast_analysis', function (Blueprint $table) {
                $table->index('sku', 'idx_forecast_analysis_sku');
            });
        }

        $mfrg = Schema::hasTable('mfrg_progress') ? 'mfrg_progress' : (Schema::hasTable('mfrg_progresses') ? 'mfrg_progresses' : null);
        if ($mfrg !== null) {
            Schema::table($mfrg, function (Blueprint $table) {
                $table->index('sku', 'idx_mfrg_progress_sku');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('forecast_analysis')) {
            Schema::table('forecast_analysis', function (Blueprint $table) {
                $table->dropIndex('idx_forecast_analysis_sku');
            });
        }

        $mfrg = Schema::hasTable('mfrg_progress') ? 'mfrg_progress' : (Schema::hasTable('mfrg_progresses') ? 'mfrg_progresses' : null);
        if ($mfrg !== null) {
            Schema::table($mfrg, function (Blueprint $table) {
                $table->dropIndex('idx_mfrg_progress_sku');
            });
        }
    }
};

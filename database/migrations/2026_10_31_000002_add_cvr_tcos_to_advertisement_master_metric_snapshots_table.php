<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save the CVR and TCOS the grid cells show, next to the ACOS column, so the
 * history graphs plot the same numbers as the cells.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('advertisement_master_metric_snapshots')) {
            return;
        }
        foreach (['cvr', 'tcos'] as $col) {
            if (! Schema::hasColumn('advertisement_master_metric_snapshots', $col)) {
                Schema::table('advertisement_master_metric_snapshots', function (Blueprint $table) use ($col) {
                    $table->decimal($col, 10, 2)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('advertisement_master_metric_snapshots')) {
            return;
        }
        foreach (['cvr', 'tcos'] as $col) {
            if (Schema::hasColumn('advertisement_master_metric_snapshots', $col)) {
                Schema::table('advertisement_master_metric_snapshots', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};

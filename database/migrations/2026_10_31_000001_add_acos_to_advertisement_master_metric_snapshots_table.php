<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the ACOS the grid cell shows (Active Channel ACOS, or Spend / T Sales on
 * total rows) so the history graph plots the same number as the cell instead of
 * recomputing Spend / Ads Sales from the saved columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('advertisement_master_metric_snapshots')
            && ! Schema::hasColumn('advertisement_master_metric_snapshots', 'acos')) {
            Schema::table('advertisement_master_metric_snapshots', function (Blueprint $table) {
                $table->decimal('acos', 10, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('advertisement_master_metric_snapshots')
            && Schema::hasColumn('advertisement_master_metric_snapshots', 'acos')) {
            Schema::table('advertisement_master_metric_snapshots', function (Blueprint $table) {
                $table->dropColumn('acos');
            });
        }
    }
};

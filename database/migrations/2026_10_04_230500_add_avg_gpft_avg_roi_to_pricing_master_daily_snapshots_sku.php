<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Store Avg GPFT% and Avg GROI% from /pricing-master-cvr next to Avg NPFT% / Avg NROI%.
     */
    public function up(): void
    {
        $tableName = 'pricing_master_daily_snapshots_sku';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'avg_gpft')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'avg_pft')) {
                    $table->decimal('avg_gpft', 10, 2)->nullable()->after('avg_pft');
                } else {
                    $table->decimal('avg_gpft', 10, 2)->nullable();
                }
            });
        }

        if (! Schema::hasColumn($tableName, 'avg_roi')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'avg_gpft')) {
                    $table->decimal('avg_roi', 10, 2)->nullable()->after('avg_gpft');
                } else {
                    $table->decimal('avg_roi', 10, 2)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $tableName = 'pricing_master_daily_snapshots_sku';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn($tableName, 'avg_gpft') ? 'avg_gpft' : null,
            Schema::hasColumn($tableName, 'avg_roi') ? 'avg_roi' : null,
        ]));

        if ($columns !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = 'pricing_master_daily_snapshots_sku';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'avg_pft')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'avg_cvr')) {
                    $table->decimal('avg_pft', 10, 2)->nullable()->after('avg_cvr');
                } else {
                    $table->decimal('avg_pft', 10, 2)->nullable();
                }
            });
        }

        if (! Schema::hasColumn($tableName, 'avg_nroi')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'avg_pft')) {
                    $table->decimal('avg_nroi', 10, 2)->nullable()->after('avg_pft');
                } else {
                    $table->decimal('avg_nroi', 10, 2)->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'pricing_master_daily_snapshots_sku';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn($tableName, 'avg_pft') ? 'avg_pft' : null,
            Schema::hasColumn($tableName, 'avg_nroi') ? 'avg_nroi' : null,
        ]));

        if ($columns !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};

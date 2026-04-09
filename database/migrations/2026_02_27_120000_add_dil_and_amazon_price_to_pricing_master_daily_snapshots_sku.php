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

        if (! Schema::hasColumn($tableName, 'dil_percent')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'avg_cvr')) {
                    $table->decimal('dil_percent', 10, 2)->nullable()->after('avg_cvr');
                } else {
                    $table->decimal('dil_percent', 10, 2)->nullable();
                }
            });
        }

        if (! Schema::hasColumn($tableName, 'amazon_price')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'dil_percent')) {
                    $table->decimal('amazon_price', 12, 2)->nullable()->after('dil_percent');
                } else {
                    $table->decimal('amazon_price', 12, 2)->nullable();
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
            Schema::hasColumn($tableName, 'dil_percent') ? 'dil_percent' : null,
            Schema::hasColumn($tableName, 'amazon_price') ? 'amazon_price' : null,
        ]));

        if ($columns !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};

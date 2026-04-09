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

        if (! Schema::hasColumn($tableName, 'rating')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'amazon_price')) {
                    $table->decimal('rating', 4, 2)->nullable()->after('amazon_price');
                } else {
                    $table->decimal('rating', 4, 2)->nullable();
                }
            });
        }

        if (! Schema::hasColumn($tableName, 'total_views')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'rating')) {
                    $table->unsignedBigInteger('total_views')->default(0)->after('rating');
                } else {
                    $table->unsignedBigInteger('total_views')->default(0);
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
            Schema::hasColumn($tableName, 'rating') ? 'rating' : null,
            Schema::hasColumn($tableName, 'total_views') ? 'total_views' : null,
        ]));

        if ($columns !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};

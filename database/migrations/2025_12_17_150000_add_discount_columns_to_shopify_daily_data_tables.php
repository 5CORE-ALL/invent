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
        foreach (['shopify_b2c_daily_data', 'shopify_b2b_daily_data'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (! Schema::hasColumn($tableName, 'original_price')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->decimal('original_price', 10, 2)->default(0)->after('price');
                });
            }
            if (! Schema::hasColumn($tableName, 'discount_amount')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->decimal('discount_amount', 10, 2)->default(0)->after('original_price');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['shopify_b2c_daily_data', 'shopify_b2b_daily_data'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columns = ['original_price', 'discount_amount'];
            $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn($tableName, $col)));
            if ($toDrop === []) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }
    }
};

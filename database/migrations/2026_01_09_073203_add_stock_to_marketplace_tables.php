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
        foreach (['macy_products', 'bestbuy_usa_products', 'tiendamia_products'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (Schema::hasColumn($tableName, 'stock')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->integer('stock')->nullable()->after('price');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['macy_products', 'bestbuy_usa_products', 'tiendamia_products'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (! Schema::hasColumn($tableName, 'stock')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('stock');
            });
        }
    }
};

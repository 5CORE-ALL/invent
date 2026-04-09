<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'aliexpress_pricing_prices';

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'ae_stock')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'price')) {
                $table->unsignedInteger('ae_stock')->default(0)->after('price');
            } else {
                $table->unsignedInteger('ae_stock')->default(0);
            }
        });
    }

    public function down(): void
    {
        $tableName = 'aliexpress_pricing_prices';

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'ae_stock')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('ae_stock');
        });
    }
};

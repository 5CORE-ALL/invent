<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'shein_pricing_prices';

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'original_price')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'price')) {
                $table->decimal('original_price', 12, 2)->default(0)->after('price');
            } else {
                $table->decimal('original_price', 12, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        $tableName = 'shein_pricing_prices';

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'original_price')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('original_price');
        });
    }
};

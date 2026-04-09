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
        $tableName = Schema::hasTable('ebay_2_metrics') ? 'ebay_2_metrics' : 'ebay2_metrics';
        if (! Schema::hasTable($tableName)) {
            return;
        }
        if (Schema::hasColumn($tableName, 'ebay_stock')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->integer('ebay_stock')->nullable()->after('ebay_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = Schema::hasTable('ebay_2_metrics') ? 'ebay_2_metrics' : 'ebay2_metrics';
        if (! Schema::hasTable($tableName)) {
            return;
        }
        if (! Schema::hasColumn($tableName, 'ebay_stock')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('ebay_stock');
        });
    }
};

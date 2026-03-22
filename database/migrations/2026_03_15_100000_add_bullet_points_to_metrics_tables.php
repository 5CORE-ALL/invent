<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds bullet_points (text) column to marketplace metrics tables.
     */
    public function up(): void
    {
        $tables = [
            'ebay_metrics',
            'ebay_2_metrics',
            'ebay_3_metrics',
            'walmart_metrics',
            'shein_metrics',
            'doba_metrics',
            'aliexpress_metric',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'bullet_points')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->text('bullet_points')->nullable();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'ebay_metrics',
            'ebay_2_metrics',
            'ebay_3_metrics',
            'walmart_metrics',
            'shein_metrics',
            'doba_metrics',
            'aliexpress_metric',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'bullet_points')) {
                Schema::table($tableName, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('bullet_points');
                });
            }
        }
    }
};

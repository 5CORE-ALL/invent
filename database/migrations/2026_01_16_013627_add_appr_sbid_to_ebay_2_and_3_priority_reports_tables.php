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
        foreach (['ebay_2_priority_reports', 'ebay_3_priority_reports'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (Schema::hasColumn($tableName, 'apprSbid')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('apprSbid')->nullable()->after('sbid_m');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['ebay_2_priority_reports', 'ebay_3_priority_reports'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (! Schema::hasColumn($tableName, 'apprSbid')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('apprSbid');
            });
        }
    }
};

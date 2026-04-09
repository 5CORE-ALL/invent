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
        foreach (['amazon_sb_campaign_reports', 'amazon_sd_campaign_reports', 'amazon_sp_campaign_reports'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (Schema::hasColumn($tableName, 'sbid_m')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('sbid_m')->nullable()->after('last_sbid');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['amazon_sb_campaign_reports', 'amazon_sd_campaign_reports', 'amazon_sp_campaign_reports'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (! Schema::hasColumn($tableName, 'sbid_m')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('sbid_m');
            });
        }
    }
};

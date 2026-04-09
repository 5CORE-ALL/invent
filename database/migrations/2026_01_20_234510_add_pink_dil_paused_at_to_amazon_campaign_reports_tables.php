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
        foreach (['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (Schema::hasColumn($tableName, 'pink_dil_paused_at')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('pink_dil_paused_at')->nullable()->after('campaignStatus');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['amazon_sp_campaign_reports', 'amazon_sb_campaign_reports'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (! Schema::hasColumn($tableName, 'pink_dil_paused_at')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('pink_dil_paused_at');
            });
        }
    }
};

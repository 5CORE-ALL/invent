<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['amazon_sb_campaign_reports', 'amazon_sd_campaign_reports', 'amazon_sp_campaign_reports'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'sbgt')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'sbid')) {
                    $table->string('sbgt')->nullable()->after('sbid');
                } else {
                    $table->string('sbgt')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['amazon_sb_campaign_reports', 'amazon_sd_campaign_reports', 'amazon_sp_campaign_reports'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'sbgt')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('sbgt');
            });
        }
    }
};

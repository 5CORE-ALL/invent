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
        if (! Schema::hasTable('amazon_sd_campaign_reports')) {
            return;
        }

        if (Schema::hasColumn('amazon_sd_campaign_reports', 'campaignStatus')) {
            return;
        }

        Schema::table('amazon_sd_campaign_reports', function (Blueprint $table) {
            $table->string('campaignStatus', 50)->nullable()->after('endDate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('amazon_sd_campaign_reports') || ! Schema::hasColumn('amazon_sd_campaign_reports', 'campaignStatus')) {
            return;
        }

        Schema::table('amazon_sd_campaign_reports', function (Blueprint $table) {
            $table->dropColumn('campaignStatus');
        });
    }
};

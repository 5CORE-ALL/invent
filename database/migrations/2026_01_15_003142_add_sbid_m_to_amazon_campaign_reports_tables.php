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
        // amazon_sb_campaign_reports
        Schema::table('amazon_sb_campaign_reports', function (Blueprint $table) {
            $table->string('sbid_m')->nullable()->after('last_sbid');
        });

        // amazon_sd_campaign_reports
        Schema::table('amazon_sd_campaign_reports', function (Blueprint $table) {
            $table->string('sbid_m')->nullable()->after('last_sbid');
        });

        // amazon_sp_campaign_reports
        Schema::table('amazon_sp_campaign_reports', function (Blueprint $table) {
            $table->string('sbid_m')->nullable()->after('last_sbid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amazon_sb_campaign_reports', function (Blueprint $table) {
            $table->dropColumn('sbid_m');
        });

        Schema::table('amazon_sd_campaign_reports', function (Blueprint $table) {
            $table->dropColumn('sbid_m');
        });

        Schema::table('amazon_sp_campaign_reports', function (Blueprint $table) {
            $table->dropColumn('sbid_m');
        });
    }
};

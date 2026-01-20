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
        Schema::table('amazon_sp_campaign_reports', function (Blueprint $table) {
            $table->timestamp('pink_dil_paused_at')->nullable()->after('campaignStatus');
        });

        Schema::table('amazon_sb_campaign_reports', function (Blueprint $table) {
            $table->timestamp('pink_dil_paused_at')->nullable()->after('campaignStatus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amazon_sp_campaign_reports', function (Blueprint $table) {
            $table->dropColumn('pink_dil_paused_at');
        });

        Schema::table('amazon_sb_campaign_reports', function (Blueprint $table) {
            $table->dropColumn('pink_dil_paused_at');
        });
    }
};

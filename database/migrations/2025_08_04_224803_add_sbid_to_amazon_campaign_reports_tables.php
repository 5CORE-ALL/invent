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
            $table->text('note')->nullable()->after('id');
            $table->string('sbid')->nullable()->after('note');
            $table->string('yes_sbid')->nullable()->after('sbid');
        });

        // amazon_sd_campaign_reports
        Schema::table('amazon_sd_campaign_reports', function (Blueprint $table) {
            $table->text('note')->nullable()->after('id');
            $table->string('sbid')->nullable()->after('note');
            $table->string('yes_sbid')->nullable()->after('sbid');
        });

        // amazon_sp_campaign_reports
        Schema::table('amazon_sp_campaign_reports', function (Blueprint $table) {
            $table->text('note')->nullable()->after('id');
            $table->string('sbid')->nullable()->after('note');
            $table->string('yes_sbid')->nullable()->after('sbid');
        });
    }

    public function down(): void
    {
        Schema::table('amazon_sb_campaign_reports', function (Blueprint $table) {
            $table->dropColumn(['note', 'sbid', 'yes_sbid']);
        });

        Schema::table('amazon_sd_campaign_reports', function (Blueprint $table) {
            $table->dropColumn(['note', 'sbid', 'yes_sbid']);
        });

        Schema::table('amazon_sp_campaign_reports', function (Blueprint $table) {
            $table->dropColumn(['note', 'sbid', 'yes_sbid']);
        });
    }
};

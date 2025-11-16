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
            $table->string('campaignStatus', 50)->nullable()->after('endDate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amazon_sp_campaign_reports', function (Blueprint $table) {
            //
        });
    }
};

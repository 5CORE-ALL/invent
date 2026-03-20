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
        Schema::table('ebay_3_priority_reports', function (Blueprint $table) {
            $table->timestamp('pink_dil_paused_at')->nullable()->after('campaignStatus')->comment('Timestamp when campaign was paused by pink DIL cron');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebay_3_priority_reports', function (Blueprint $table) {
            $table->dropColumn('pink_dil_paused_at');
        });
    }
};

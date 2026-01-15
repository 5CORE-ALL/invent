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
        // Add apprSbid column to ebay_2_priority_reports table
        Schema::table('ebay_2_priority_reports', function (Blueprint $table) {
            $table->string('apprSbid')->nullable()->after('sbid_m');
        });

        // Add apprSbid column to ebay_3_priority_reports table
        Schema::table('ebay_3_priority_reports', function (Blueprint $table) {
            $table->string('apprSbid')->nullable()->after('sbid_m');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebay_2_priority_reports', function (Blueprint $table) {
            $table->dropColumn('apprSbid');
        });

        Schema::table('ebay_3_priority_reports', function (Blueprint $table) {
            $table->dropColumn('apprSbid');
        });
    }
};

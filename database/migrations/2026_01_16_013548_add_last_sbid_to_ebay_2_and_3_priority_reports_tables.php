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
        // Add last_sbid column to ebay_2_priority_reports table
        Schema::table('ebay_2_priority_reports', function (Blueprint $table) {
            $table->string('last_sbid')->nullable()->after('cost_per_click');
        });

        // Add last_sbid column to ebay_3_priority_reports table
        Schema::table('ebay_3_priority_reports', function (Blueprint $table) {
            $table->string('last_sbid')->nullable()->after('cost_per_click');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebay_2_priority_reports', function (Blueprint $table) {
            $table->dropColumn('last_sbid');
        });

        Schema::table('ebay_3_priority_reports', function (Blueprint $table) {
            $table->dropColumn('last_sbid');
        });
    }
};

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
        Schema::table('ebay_2_priority_reports', function (Blueprint $table) {
            $table->decimal('sbid_m', 10, 2)->nullable()->after('campaignStatus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebay_2_priority_reports', function (Blueprint $table) {
            $table->dropColumn('sbid_m');
        });
    }
};

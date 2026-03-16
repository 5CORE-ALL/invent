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
        Schema::table('marketplace_daily_metrics', function (Blueprint $table) {
            $table->decimal('n_roi', 8, 2)->nullable()->after('n_pft'); // Net ROI = Net Profit / COGS * 100
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_daily_metrics', function (Blueprint $table) {
            $table->dropColumn('n_roi');
        });
    }
};

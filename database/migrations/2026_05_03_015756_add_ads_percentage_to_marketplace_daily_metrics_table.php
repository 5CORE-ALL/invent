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
            if (!Schema::hasColumn('marketplace_daily_metrics', 'ads_percentage')) {
                $table->decimal('ads_percentage', 10, 2)->nullable()->after('tacos_percentage');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_daily_metrics', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_daily_metrics', 'ads_percentage')) {
                $table->dropColumn('ads_percentage');
            }
        });
    }
};

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
            $table->decimal('ads_percentage', 8, 2)->nullable()->after('tacos_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_daily_metrics', function (Blueprint $table) {
            $table->dropColumn('ads_percentage');
        });
    }
};
